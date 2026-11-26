<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;
use RuntimeException;

require_once __DIR__ . '/../../includes/admin_product_save.php';

/** A07 の保存（Plan 2026-11-26-php-admin §2.0・付録 A P0。テスト仕様書 A07-003・A07-010・A06a-005〜007） */
final class AdminProductSaveTest extends TestCase
{
    private const NOW = 1_794_000_000;

    private PDO $pdo;
    private string $root;
    private string $tmp;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $base = sys_get_temp_dir() . '/playduo_save_test_' . bin2hex(random_bytes(4));
        $this->root = "{$base}/root";
        $this->tmp = "{$base}/tmp";
        mkdir("{$this->root}/assets/games/uploads", 0700, true);
        mkdir("{$this->root}/downloads", 0700, true);
        mkdir($this->tmp, 0700);
    }

    protected function tearDown(): void
    {
        $base = dirname($this->root);
        foreach ([...(glob("{$this->root}/assets/games/uploads/*") ?: []), ...(glob("{$this->root}/assets/games/*.png") ?: []),
            ...(glob("{$this->root}/downloads/*") ?: []), ...(glob("{$this->tmp}/*") ?: [])] as $file) {
            unlink($file);
        }
        foreach (["{$this->root}/assets/games/uploads", "{$this->root}/assets/games", "{$this->root}/assets", "{$this->root}/downloads", $this->root, $this->tmp, $base] as $dir) {
            rmdir($dir);
        }
    }

    private function stash(string $bytes, string $ext): array
    {
        $name = bin2hex(random_bytes(16));
        file_put_contents("{$this->tmp}/{$name}", $bytes);
        return ['name' => $name, 'ext' => $ext];
    }

    /** @return array<string, array{name: string, ext: string}> */
    private function allFiles(): array
    {
        return [
            'image' => $this->stash('cover', 'png'), 'screenshot1' => $this->stash('s1', 'jpg'),
            'screenshot2' => $this->stash('s2', 'png'), 'screenshot3' => $this->stash('s3', 'png'),
            'game_file' => $this->stash('PK-zip', 'zip'),
        ];
    }

    private static function input(array $overrides = []): array
    {
        return $overrides + [
            'name' => '新作<script>', 'title_en' => null, 'genre' => 'レース', 'price' => 1980, 'capacity' => '12.5',
            'release_date' => '2027-04-01', 'developer' => 'Dev', 'age_rating' => 'B', 'description' => "説明\n2行目",
            'min_players' => 2, 'max_players' => 2, 'play_style' => '対戦', 'play_time' => '約10分',
            'os_windows' => 1, 'os_mac' => 0, 'crossplay_flag' => 0,
        ];
    }

    private function row(int $id): array
    {
        return $this->pdo->query("SELECT * FROM products WHERE product_id = {$id}")->fetch();
    }

    public function testNewProductIsSavedPublicWithFilesInPlace(): void
    {
        $files = $this->allFiles();
        $id = save_product($this->pdo, null, self::input(), $files, $this->tmp, $this->root, self::NOW);

        $row = $this->row($id);
        self::assertSame(9, $id);
        self::assertSame('新作<script>', $row['name']);
        self::assertNull($row['title_en']);
        self::assertSame(1, (int) $row['public_flag'], 'A07-003');
        self::assertSame(9, (int) $row['recommend_order'], '既存の最大＋1');
        self::assertSame(db_datetime(self::NOW), $row['created_at']);
        self::assertSame('downloads/p9.zip', $row['file_path']);
        self::assertMatchesRegularExpression('#\Auploads/p9_cover_[0-9a-f]{16}\.png\z#', $row['image']);
        self::assertMatchesRegularExpression('#\Auploads/p9_ss1_[0-9a-f]{16}\.jpg\z#', $row['screenshot1']);
        self::assertSame('cover', file_get_contents("{$this->root}/assets/games/{$row['image']}"));
        self::assertSame('PK-zip', file_get_contents("{$this->root}/downloads/p9.zip"));
        self::assertSame([], glob("{$this->tmp}/*"), '一時ファイルは移動済み');
    }

    public function testFailureWhilePlacingLeavesNoRowAndNoFiles(): void
    {
        $files = $this->allFiles();
        unlink("{$this->tmp}/{$files['screenshot3']['name']}");   // 3 枚目の移動で失敗させる

        try {
            save_product($this->pdo, null, self::input(), $files, $this->tmp, $this->root, self::NOW);
            self::fail('例外になるはず');
        } catch (RuntimeException) {
        }

        self::assertSame(8, ShopFixtures::count($this->pdo, 'products'), 'A07-010：登録されない');
        self::assertSame([], glob("{$this->root}/assets/games/uploads/*"), '置いた画像は消す');
        self::assertSame([], glob("{$this->root}/downloads/*"));
    }

    public function testEditWithoutFilesKeepsExistingPaths(): void
    {
        $before = $this->row(5);
        save_product($this->pdo, 5, self::input(['name' => '改名', 'price' => 2980]), [], $this->tmp, $this->root, self::NOW);

        $after = $this->row(5);
        self::assertSame('改名', $after['name']);
        self::assertSame(2980, (int) $after['price']);
        foreach (['image', 'screenshot1', 'screenshot2', 'screenshot3', 'file_path', 'public_flag', 'recommend_order', 'created_at'] as $column) {
            self::assertSame($before[$column], $after[$column], $column);
        }
    }

    public function testEditReplacesFilesWithNewNamesAndRemovesOnlyOwnOldFiles(): void
    {
        // seed 形式の既存画像（アップロード以外）：差し替えても消さない
        file_put_contents("{$this->root}/assets/games/seed_cover.png", 'seed');
        $this->pdo->exec("UPDATE products SET image = 'seed_cover.png' WHERE product_id = 5");

        save_product($this->pdo, 5, self::input(), ['image' => $this->stash('v1', 'png'), 'game_file' => $this->stash('zip1', 'zip')], $this->tmp, $this->root, self::NOW);
        $v1 = $this->row(5);
        self::assertFileExists("{$this->root}/assets/games/seed_cover.png");
        self::assertMatchesRegularExpression('#\Adownloads/p5_[0-9a-f]{16}\.zip\z#', $v1['file_path']);

        save_product($this->pdo, 5, self::input(), ['image' => $this->stash('v2', 'jpg'), 'game_file' => $this->stash('zip2', 'zip')], $this->tmp, $this->root, self::NOW);
        $v2 = $this->row(5);
        self::assertNotSame($v1['image'], $v2['image']);
        self::assertSame('v2', file_get_contents("{$this->root}/assets/games/{$v2['image']}"));
        self::assertFileDoesNotExist("{$this->root}/assets/games/{$v1['image']}", '自分で作った古いファイルは消す');
        self::assertFileDoesNotExist("{$this->root}/{$v1['file_path']}");
        self::assertSame('zip2', file_get_contents("{$this->root}/{$v2['file_path']}"));
    }

    public function testEditOfMissingProductFailsAndLeavesNoFiles(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            save_product($this->pdo, 999, self::input(), ['image' => $this->stash('x', 'png')], $this->tmp, $this->root, self::NOW);
        } finally {
            self::assertSame([], glob("{$this->root}/assets/games/uploads/*"));
        }
    }
}
