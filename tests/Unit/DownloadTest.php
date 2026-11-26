<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/download.php';

/** 配布ファイルの場所の解決（Plan 2026-11-26 §2.3：downloads/ の外は出さない） */
final class DownloadTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/playduo_dl_' . bin2hex(random_bytes(4));
        mkdir($this->base . '/downloads', 0777, true);
        mkdir($this->base . '/downloads-evil', 0777, true);
        file_put_contents($this->base . '/downloads/g01.zip', 'zip');
        file_put_contents($this->base . '/downloads-evil/g01.zip', 'evil');
        file_put_contents($this->base . '/secret.zip', 'secret');
        mkdir($this->base . '/downloads/dir.zip');
    }

    protected function tearDown(): void
    {
        foreach (['downloads/g01.zip', 'downloads-evil/g01.zip', 'secret.zip'] as $f) {
            @unlink($this->base . '/' . $f);
        }
        @rmdir($this->base . '/downloads/dir.zip');
        @rmdir($this->base . '/downloads');
        @rmdir($this->base . '/downloads-evil');
        @rmdir($this->base);
    }

    public function testResolvesFileInsideDownloads(): void
    {
        self::assertSame(realpath($this->base . '/downloads/g01.zip'), download_file_path('downloads/g01.zip', $this->base));
    }

    /** @return array<string, array{string}> */
    public static function rejectedPaths(): array
    {
        return [
            '親フォルダ'       => ['downloads/../secret.zip'],
            '似た名前のフォルダ' => ['downloads-evil/g01.zip'],
            '絶対パス'         => ['/etc/passwd'],
            '別フォルダ'       => ['secret.zip'],
            '存在しない'       => ['downloads/g99.zip'],
            'フォルダ'         => ['downloads/dir.zip'],
            'zip 以外'         => ['downloads/g01.php'],
            '空'               => [''],
        ];
    }

    /** @dataProvider rejectedPaths */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedPaths')]
    public function testRejectsPathsOutsideOrInvalid(string $path): void
    {
        self::assertNull(download_file_path($path, $this->base));
    }
}
