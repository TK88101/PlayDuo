<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;
use ZipArchive;

require_once __DIR__ . '/../../includes/admin_product_draft.php';
require_once __DIR__ . '/../../includes/admin_product_repo.php';

/**
 * A06 → A07 の確認待ち（Plan 2026-11-26-php-admin §2.0・付録 A）と商品の読み書き。
 * E2E は php -S の別プロセスで動くため、ここで関数を直接呼んで分岐を確かめる。
 */
final class AdminProductDraftTest extends TestCase
{
    private const NOW = 1_794_000_000;

    private PDO $pdo;
    private string $base;
    private string $tmp;
    private string $root;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->base = sys_get_temp_dir() . '/playduo_draft_test_' . bin2hex(random_bytes(4));
        $this->tmp = "{$this->base}/tmp";
        $this->root = "{$this->base}/root";
        mkdir($this->tmp, 0700, true);
        mkdir("{$this->root}/assets/games/uploads", 0700, true);
        mkdir("{$this->root}/downloads", 0700, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->base);
    }

    private function pngFile(string $name = 'a.png'): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(8, 6));
        $path = "{$this->base}/{$name}";
        file_put_contents($path, (string) ob_get_clean());
        return $path;
    }

    private function zipFile(): string
    {
        $path = "{$this->base}/game.zip";
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('README.txt', 'game');
        $zip->close();
        return $path;
    }

    /** @return array{name: string, ext: string} */
    private function stash(string $source, string $ext): array
    {
        $name = bin2hex(random_bytes(16));
        copy($source, "{$this->tmp}/{$name}");
        return ['name' => $name, 'ext' => $ext];
    }

    private static function upload(string $path): array
    {
        return ['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'size' => filesize($path)];
    }

    /** @return array<string, string> */
    private static function validPost(): array
    {
        return [
            'name' => '新作', 'title_en' => '', 'genre' => 'レース', 'price' => '1980', 'capacity' => '12.5',
            'release_date' => '2027-04-01', 'developer' => 'Dev', 'age_rating' => 'B', 'description' => '説明',
            'min_players' => '2', 'max_players' => '2', 'play_style' => '対戦', 'play_time' => '約10分',
            'os_windows' => '1', 'os_mac' => '', 'crossplay_flag' => '',
        ];
    }

    // ---- 確認待ちの取り出し・破棄 ----

    public function testProductDraftMatchesOnlyTheSameProduct(): void
    {
        $session = [PRODUCT_DRAFT_KEY => ['product_id' => 5, 'input' => [], 'files' => [], 'ready' => true]];

        self::assertNotNull(product_draft($session, 5));
        self::assertNull(product_draft($session, null), '新規の画面では編集中の確認待ちを使わない');
        self::assertNull(product_draft($session, 6));
        self::assertNull(product_draft([PRODUCT_DRAFT_KEY => 'x'], 5));
        self::assertNull(product_draft([], null));
    }

    public function testTakeConsumesOnlyReadyDraft(): void
    {
        $session = [PRODUCT_DRAFT_KEY => ['product_id' => null, 'ready' => true]];
        self::assertSame(['product_id' => null, 'ready' => true], take_product_draft($session));
        self::assertArrayNotHasKey(PRODUCT_DRAFT_KEY, $session, '取り出したら消す（二重送信対策）');
        self::assertNull(take_product_draft($session));

        $notReady = [PRODUCT_DRAFT_KEY => ['product_id' => null, 'ready' => false]];
        self::assertNull(take_product_draft($notReady));
        self::assertArrayNotHasKey(PRODUCT_DRAFT_KEY, $notReady);
    }

    public function testDiscardRemovesDraftAndItsFiles(): void
    {
        $file = $this->stash($this->pngFile(), 'png');
        $session = [PRODUCT_DRAFT_KEY => ['product_id' => null, 'files' => ['image' => $file]]];

        discard_product_draft($session, $this->tmp);

        self::assertArrayNotHasKey(PRODUCT_DRAFT_KEY, $session);
        self::assertFileDoesNotExist("{$this->tmp}/{$file['name']}");
        $empty = [];
        discard_product_draft($empty, $this->tmp);
        self::assertSame([], $empty);
    }

    // ---- A06 のファイル受け取り ----

    public function testCollectStashesValidFilesAndRequiresAllForNew(): void
    {
        $yes = static fn (string $p): bool => true;
        $copy = static fn (string $from, string $to): bool => copy($from, $to);
        $files = ['image' => self::upload($this->pngFile()), 'game_file' => self::upload($this->zipFile())];

        $result = collect_product_files($files, [], true, $this->tmp, $yes, $copy);

        self::assertSame(['image', 'game_file'], array_keys($result['files']));
        self::assertSame('png', $result['files']['image']['ext']);
        self::assertSame('zip', $result['files']['game_file']['ext']);
        self::assertFileExists("{$this->tmp}/{$result['files']['image']['name']}");
        self::assertSame(
            ['screenshot1' => 'スクリーンショット1を選択してください', 'screenshot2' => 'スクリーンショット2を選択してください',
                'screenshot3' => 'スクリーンショット3を選択してください'],
            $result['errors'],
        );
    }

    public function testCollectKeepsPreviousFilesAndReplacesOnlyNewOnes(): void
    {
        $yes = static fn (string $p): bool => true;
        $copy = static fn (string $from, string $to): bool => copy($from, $to);
        $oldCover = $this->stash($this->pngFile(), 'png');
        $oldZip = $this->stash($this->zipFile(), 'zip');

        $result = collect_product_files(
            ['image' => self::upload($this->pngFile('b.png'))],
            ['image' => $oldCover, 'game_file' => $oldZip],
            false,
            $this->tmp,
            $yes,
            $copy,
        );

        self::assertSame([], $result['errors'], '編集は未選択でもエラーにしない');
        self::assertNotSame($oldCover['name'], $result['files']['image']['name']);
        self::assertFileDoesNotExist("{$this->tmp}/{$oldCover['name']}", '選び直した前のファイルは消す');
        self::assertSame($oldZip, $result['files']['game_file'], '選ばなかった項目は前のまま');
    }

    public function testCollectReportsBadFilesButKeepsPreviousOnes(): void
    {
        $yes = static fn (string $p): bool => true;
        $fake = "{$this->base}/fake.png";
        file_put_contents($fake, 'not an image');
        $oldCover = $this->stash($this->pngFile(), 'png');

        $result = collect_product_files(
            [
                'image'       => self::upload($fake),
                'screenshot1' => ['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0],
                'screenshot2' => ['error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => '', 'size' => 0],
                'game_file'   => self::upload($fake),
            ],
            ['image' => $oldCover],
            false,
            $this->tmp,
            $yes,
        );

        self::assertSame('画像はPNGまたはJPEGのファイルを選択してください', $result['errors']['image']);
        self::assertSame('スクリーンショット1が大きすぎます', $result['errors']['screenshot1']);
        self::assertSame('スクリーンショット2をアップロードできませんでした', $result['errors']['screenshot2']);
        self::assertSame('配布ファイルはZIP形式のファイルを選択してください', $result['errors']['game_file']);
        self::assertSame($oldCover, $result['files']['image'], '失敗した選び直しでは前のファイルを捨てない');
    }

    public function testFormValuesAreStrings(): void
    {
        $values = product_form_values(['name' => 'x', 'price' => 100, 'title_en' => null, 'os_windows' => 1, 'genre' => ['a']]);

        self::assertSame('x', $values['name']);
        self::assertSame('100', $values['price']);
        self::assertSame('', $values['title_en']);
        self::assertSame('1', $values['os_windows']);
        self::assertSame('', $values['genre'], '配列は空にする');
        self::assertSame('', $values['developer'], '無い項目は空');
    }

    // ---- A07 の保存 ----

    public function testSaveDraftCreatesProductWithAllFiles(): void
    {
        $files = ['image' => $this->stash($this->pngFile(), 'png')];
        foreach (['screenshot1', 'screenshot2', 'screenshot3'] as $field) {
            $files[$field] = $this->stash($this->pngFile(), 'png');
        }
        $files['game_file'] = $this->stash($this->zipFile(), 'zip');
        $draft = ['product_id' => null, 'input' => validate_product_input(self::validPost())['input'], 'files' => $files, 'ready' => true];

        $message = save_product_draft($this->pdo, $draft, $this->tmp, self::NOW, $this->root);

        self::assertSame('「新作」を登録しました', $message);
        self::assertSame(9, ShopFixtures::count($this->pdo, 'products'));
        self::assertFileExists("{$this->root}/downloads/p9.zip");
    }

    public function testSaveDraftUpdatesExistingProduct(): void
    {
        $draft = ['product_id' => 5, 'input' => validate_product_input(['name' => '改名'] + self::validPost())['input'], 'files' => [], 'ready' => true];

        self::assertSame('「改名」を更新しました', save_product_draft($this->pdo, $draft, $this->tmp, self::NOW, $this->root));
        self::assertSame('改名', find_product_any($this->pdo, 5)['name']);
    }

    public function testSaveDraftRejectsBrokenDrafts(): void
    {
        self::assertSame('', save_product_draft($this->pdo, null, $this->tmp, self::NOW, $this->root));

        $input = validate_product_input(self::validPost())['input'];
        $missingFiles = ['product_id' => null, 'input' => $input, 'files' => ['image' => $this->stash($this->pngFile(), 'png')]];
        $badInput = ['product_id' => 5, 'input' => ['price' => -1] + $input, 'files' => []];
        $tampered = ['product_id' => 5, 'input' => $input, 'files' => ['image' => ['name' => '../../etc/passwd', 'ext' => 'png']]];
        $wrongExt = ['product_id' => 5, 'input' => $input, 'files' => ['image' => $this->stash($this->pngFile(), 'jpg')]];

        foreach ([$missingFiles, $badInput, $tampered, $wrongExt] as $draft) {
            self::assertSame(
                '入力内容を確認できなかったため保存していません。もう一度入力してください',
                save_product_draft($this->pdo, $draft, $this->tmp, self::NOW, $this->root),
            );
        }
        self::assertSame(8, ShopFixtures::count($this->pdo, 'products'));
        self::assertSame([], glob("{$this->tmp}/*"), '使わなかった一時ファイルは消す');
    }

    public function testSaveDraftReportsFailureWhenTheProductIsGone(): void
    {
        $draft = ['product_id' => 999, 'input' => validate_product_input(self::validPost())['input'], 'files' => [], 'ready' => true];

        $log = ini_set('error_log', '/dev/null');   // 失敗の記録は想定どおりなのでテストの出力に混ぜない
        try {
            $message = save_product_draft($this->pdo, $draft, $this->tmp, self::NOW, $this->root);
        } finally {
            ini_set('error_log', (string) $log);
        }
        self::assertSame('保存できませんでした。もう一度お試しください', $message);
    }

    // ---- 商品の読み書き（A03〜A05） ----

    public function testRepoListsAllIncludingHiddenAndTogglesFlag(): void
    {
        set_public_flag($this->pdo, 6, false);
        set_public_flag($this->pdo, 6, false);

        $all = all_products_for_admin($this->pdo);
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8], array_map(static fn (array $r): int => (int) $r['product_id'], $all));
        self::assertSame(0, (int) find_product_any($this->pdo, 6)['public_flag']);
        self::assertNull(find_product_any($this->pdo, 999));

        set_public_flag($this->pdo, 6, true);
        self::assertSame(1, (int) find_product_any($this->pdo, 6)['public_flag']);
    }
}
