<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipArchive;

require_once __DIR__ . '/../../includes/admin_upload.php';

/** A06 のファイル（テスト仕様書（管理画面）A06-016〜A06-020・Plan §2.0 A06 ファイル・付録 A） */
final class AdminUploadTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/playduo_upload_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function file(string $name, string $bytes): string
    {
        $path = "{$this->dir}/{$name}";
        file_put_contents($path, $bytes);
        return $path;
    }

    private function png(int $width = 8, int $height = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        return $this->file('src.png', (string) ob_get_clean());
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(8, 6);
        ob_start();
        imagejpeg($image);
        return $this->file('src.jpg', (string) ob_get_clean());
    }

    private function zip(): string
    {
        $path = "{$this->dir}/game.zip";
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('README.txt', 'dummy');
        $zip->close();
        return $path;
    }

    // ---- $_FILES の 1 項目 ----

    public function testUploadedFileStates(): void
    {
        $yes = static fn (string $p): bool => true;
        $path = $this->png();

        self::assertSame(['status' => 'none'], uploaded_file([], 'image', $yes));
        self::assertSame(['status' => 'none'], uploaded_file(['image' => ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0]], 'image', $yes));
        self::assertSame(
            ['status' => 'ok', 'tmp' => $path],
            uploaded_file(['image' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => 10]], 'image', $yes),
        );
    }

    public function testUploadedFileRejectsOddShapes(): void
    {
        $yes = static fn (string $p): bool => true;
        $no = static fn (string $p): bool => false;
        $path = $this->png();

        self::assertSame('error', uploaded_file(['image' => ['error' => [0], 'tmp_name' => [$path], 'size' => [1]]], 'image', $yes)['status'], '配列');
        self::assertSame('error', uploaded_file(['image' => 'x'], 'image', $yes)['status']);
        self::assertSame('error', uploaded_file(['image' => ['error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => $path, 'size' => 1]], 'image', $yes)['status']);
        self::assertSame('too_large', uploaded_file(['image' => ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0]], 'image', $yes)['status']);
        self::assertSame('error', uploaded_file(['image' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/etc/hosts', 'size' => 1]], 'image', $no)['status'], 'アップロードされたファイルではない');
    }

    // ---- 中身の判定 ----

    public function testPngAndJpegAreAcceptedWithExtensionFromContent(): void
    {
        self::assertSame(['ext' => 'png'], check_image($this->png()));
        self::assertSame(['ext' => 'jpg'], check_image($this->jpeg()));
    }

    public function testDisguisedOrOtherFormatsAreRejected(): void
    {
        self::assertArrayHasKey('error', check_image($this->file('fake.png', '<?php echo 1; ?>')), 'A06-016 偽装');
        $gif = imagecreatetruecolor(4, 4);
        ob_start();
        imagegif($gif);
        self::assertArrayHasKey('error', check_image($this->file('a.gif', (string) ob_get_clean())));
        self::assertArrayHasKey('error', check_image($this->file('empty.png', '')));
    }

    public function testImageSizeAndPixelLimits(): void
    {
        $big = $this->file('big.png', file_get_contents($this->png()) . str_repeat("\0", UPLOAD_IMAGE_MAX_BYTES));
        self::assertStringContainsString('3MB', check_image($big)['error'] ?? '');
        self::assertArrayHasKey('error', check_image($this->png(UPLOAD_IMAGE_MAX_SIDE + 1, 1)));
        self::assertSame(['ext' => 'png'], check_image($this->png(UPLOAD_IMAGE_MAX_SIDE, 1)));
    }

    public function testZipCheck(): void
    {
        self::assertNull(check_zip($this->zip()));
        self::assertNotNull(check_zip($this->file('fake.zip', 'not a zip')), 'A06-019');
        self::assertNotNull(check_zip($this->png()));
        $big = $this->file('big.zip', str_repeat('a', UPLOAD_ZIP_MAX_BYTES + 1));
        self::assertStringContainsString('25MB', (string) check_zip($big));
    }

    // ---- 一時保存 ----

    public function testStashMovesToRandomNameAndDiscardRemoves(): void
    {
        $src = $this->png();
        $move = static fn (string $from, string $to): bool => rename($from, $to);

        $name = stash_upload($src, $this->dir, $move);

        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $name);
        self::assertFileExists("{$this->dir}/{$name}");
        self::assertFileDoesNotExist($src);

        discard_stashed($this->dir, [$name, '../etc', 'not-ours']);
        self::assertFileDoesNotExist("{$this->dir}/{$name}");
    }

    public function testStashFailureThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        stash_upload($this->png(), $this->dir, static fn (string $f, string $t): bool => false);
    }

    public function testPurgeRemovesOnlyOldStashedFiles(): void
    {
        $now = 2_000_000_000;
        $old = $this->file(str_repeat('a', 32), 'x');
        touch($old, $now - UPLOAD_STASH_TTL - 1);
        $fresh = $this->file(str_repeat('b', 32), 'x');
        touch($fresh, $now - 10);
        $other = $this->file('keep.txt', 'x');
        touch($other, $now - UPLOAD_STASH_TTL - 1);

        purge_stale_uploads($this->dir, $now);

        self::assertFileDoesNotExist($old);
        self::assertFileExists($fresh);
        self::assertFileExists($other, '自分の形式の名前だけ消す');
    }

    // ---- post_max_size 超過 ----

    public function testPostTooLargeDetection(): void
    {
        self::assertTrue(post_too_large(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '999999999'], [], [], '40M'));
        self::assertFalse(post_too_large(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '100'], [], [], '40M'));
        self::assertFalse(post_too_large(['REQUEST_METHOD' => 'POST', 'CONTENT_LENGTH' => '999999999'], ['a' => '1'], [], '40M'));
        self::assertFalse(post_too_large(['REQUEST_METHOD' => 'GET'], [], [], '40M'));
        self::assertSame(40 * 1024 * 1024, ini_bytes('40M'));
        self::assertSame(512 * 1024, ini_bytes('512K'));
        self::assertSame(2 * 1024 ** 3, ini_bytes('2G'));
        self::assertSame(1000, ini_bytes('1000'));
    }
}
