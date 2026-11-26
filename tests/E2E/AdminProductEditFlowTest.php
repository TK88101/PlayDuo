<?php

declare(strict_types=1);

namespace PlayDuo\Tests\E2E;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\AppServer;
use PlayDuo\Tests\Support\HttpClient;
use PlayDuo\Tests\Support\HttpResponse;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;
use ZipArchive;

/**
 * A06 商品登録・A06-a 編集・A07 確認と保存の HTTP シナリオ（ファイルは multipart で送る）。
 * 期待結果はテスト仕様書（管理画面）A06・A06a・A07 と Plan 2026-11-26-php-admin §2.0。
 * php -S は本物のフォルダに書くので、新規商品（p9）のファイルは前後で消す。
 */
final class AdminProductEditFlowTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private PDO $pdo;
    private HttpClient $admin;

    protected function setUp(): void
    {
        self::removeUploadedFiles();
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        ShopFixtures::insertAdmin($this->pdo);
        $this->admin = new HttpClient(AppServer::baseUrl());
        $token = $this->admin->get('admin/login.php')->csrfToken();
        $this->admin->post('admin/login.php', ['csrf_token' => $token, 'login_id' => 'admin', 'password' => 'admin1234']);
    }

    protected function tearDown(): void
    {
        self::removeUploadedFiles();
    }

    private static function removeUploadedFiles(): void
    {
        foreach ([...(glob(self::ROOT . '/assets/games/uploads/p*') ?: []), ...(glob(self::ROOT . '/downloads/p*.zip') ?: [])] as $file) {
            unlink($file);
        }
    }

    private static function assertRedirect(HttpResponse $res, string $location): void
    {
        self::assertSame(303, $res->status, $res->body);
        self::assertSame($location, $res->header('location'));
    }

    private static function text(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', strip_tags($html));
    }

    private static function png(): array
    {
        ob_start();
        imagepng(imagecreatetruecolor(8, 6));
        return ['a.png', (string) ob_get_clean(), 'image/png'];
    }

    private static function zip(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pdzip');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('README.txt', 'game');
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);
        return ['game.zip', $bytes, 'application/zip'];
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    private static function allFiles(): array
    {
        return ['image' => self::png(), 'screenshot1' => self::png(), 'screenshot2' => self::png(), 'screenshot3' => self::png(), 'game_file' => self::zip()];
    }

    /** @return array<string, string> */
    private static function fields(array $overrides = []): array
    {
        return $overrides + [
            'name' => '新作<script>alert(1)</script>', 'title_en' => 'NEW GAME', 'genre' => 'レース', 'price' => '1980',
            'capacity' => '12.5', 'release_date' => '2027-04-01', 'developer' => 'Dev', 'age_rating' => 'B',
            'description' => "1行目\n2行目", 'min_players' => '2', 'max_players' => '2', 'play_style' => '対戦',
            'play_time' => '約10分', 'os_windows' => '1', 'os_mac' => '1', 'crossplay_flag' => '1',
        ];
    }

    private function postEdit(string $path, array $fields, array $files = []): HttpResponse
    {
        $token = $this->admin->get('admin/products.php')->csrfToken();
        return $this->admin->postMultipart($path, ['csrf_token' => $token] + $fields, $files);
    }

    private function confirm(string $action): HttpResponse
    {
        $token = $this->admin->get('admin/products.php')->csrfToken();
        return $this->admin->post('admin/product_confirm.php', ['csrf_token' => $token, 'action' => $action]);
    }

    public function testNewFormIsEmptyWithEveryField(): void
    {
        $page = $this->admin->get('admin/product_edit.php');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('enctype="multipart/form-data"', $page->body);
        foreach (['name', 'title_en', 'genre', 'price', 'capacity', 'release_date', 'developer', 'age_rating', 'description',
            'game_file', 'min_players', 'max_players', 'play_style', 'play_time', 'os_windows', 'os_mac', 'crossplay_flag',
            'image', 'screenshot1', 'screenshot2', 'screenshot3'] as $name) {
            self::assertStringContainsString("name=\"{$name}\"", $page->body, $name);
        }
        self::assertStringContainsString('name="name" value=""', $page->body);
        self::assertStringContainsString('「非公開」に切り替えて', self::text($page->body), 'A06-026');
    }

    public function testRegisterNewProductThroughConfirmation(): void
    {
        self::assertRedirect($this->postEdit('admin/product_edit.php', self::fields(), self::allFiles()), 'product_confirm.php');

        $confirm = $this->admin->get('admin/product_confirm.php');
        self::assertSame(200, $confirm->status);
        self::assertStringContainsString('新作&lt;script&gt;', $confirm->body, 'A06-023');
        self::assertStringNotContainsString('<script>alert', $confirm->body);
        self::assertSame(8, ShopFixtures::count($this->pdo, 'products'), '確認画面ではまだ保存しない');

        self::assertRedirect($this->confirm('save'), 'products.php');
        self::assertSame(9, ShopFixtures::count($this->pdo, 'products'));
        $list = $this->admin->get('admin/products.php');
        self::assertStringContainsString('を登録しました', self::text($list->body));
        self::assertStringContainsString('data-product-id="9"', $list->body);
        self::assertStringContainsString('p9', self::text($list->body));

        $row = $this->pdo->query('SELECT * FROM products WHERE product_id = 9')->fetch();
        self::assertSame(1, (int) $row['public_flag']);
        self::assertSame('downloads/p9.zip', $row['file_path']);
        self::assertFileExists(self::ROOT . '/downloads/p9.zip');
        self::assertFileExists(self::ROOT . '/assets/games/' . $row['image']);
        self::assertSame(200, (new HttpClient(AppServer::baseUrl()))->get('product.php?id=9')->status, '会員サイトに出る');

        // 二重送信・直接アクセス（A07-007・A07-008）
        self::assertRedirect($this->confirm('save'), 'products.php');
        self::assertRedirect($this->admin->get('admin/product_confirm.php'), 'products.php');
        self::assertSame(9, ShopFixtures::count($this->pdo, 'products'));
    }

    public function testErrorsKeepInputAndAcceptedFiles(): void
    {
        $res = $this->postEdit('admin/product_edit.php', self::fields(['price' => '-1']), self::allFiles());
        self::assertSame(200, $res->status);
        self::assertStringContainsString('価格は0〜1000000の整数で入力してください', $res->body);
        self::assertStringContainsString('value="新作&lt;script&gt;alert(1)&lt;/script&gt;"', $res->body, 'A06-022');
        self::assertSame(5, substr_count($res->body, '選択済み'), '通ったファイルは選び直さなくてよい');

        self::assertRedirect($this->postEdit('admin/product_edit.php', self::fields()), 'product_confirm.php');
        self::assertRedirect($this->confirm('save'), 'products.php');
        self::assertSame(9, ShopFixtures::count($this->pdo, 'products'));
    }

    public function testAllEmptyAndFakeImageAreRejected(): void
    {
        $empty = $this->postEdit('admin/product_edit.php', []);
        self::assertSame(200, $empty->status);
        foreach (['タイトルを入力してください', 'カバー画像を選択してください', '配布ファイルを選択してください', '対応OSを1つ以上選択してください'] as $message) {
            self::assertStringContainsString($message, $empty->body, $message);
        }

        $files = self::allFiles();
        $files['image'] = ['fake.png', '<?php echo 1;', 'image/png'];
        $files['game_file'] = ['game.zip', 'not a zip', 'application/zip'];
        $fake = $this->postEdit('admin/product_edit.php', self::fields(), $files);
        self::assertStringContainsString('画像はPNGまたはJPEGのファイルを選択してください', $fake->body, 'A06-016');
        self::assertStringContainsString('配布ファイルはZIP形式のファイルを選択してください', $fake->body, 'A06-019');
        self::assertRedirect($this->admin->get('admin/product_confirm.php'), 'products.php');
    }

    public function testBackFromConfirmationKeepsInputAndReopeningDiscardsIt(): void
    {
        $this->postEdit('admin/product_edit.php', self::fields(['developer' => '戻る前']), self::allFiles());
        self::assertRedirect($this->confirm('back'), 'product_edit.php?resume=1');
        $back = $this->admin->get('admin/product_edit.php?resume=1');
        self::assertStringContainsString('value="戻る前"', $back->body, 'A07-006');
        self::assertRedirect($this->admin->get('admin/product_confirm.php'), 'products.php', '戻ったら確認画面は使えない');

        $fresh = $this->admin->get('admin/product_edit.php');
        self::assertStringNotContainsString('戻る前', $fresh->body, 'A06-025');
        self::assertStringNotContainsString('選択済み', $fresh->body);
    }

    public function testEditPriceKeepsFilesStateAndPastOrders(): void
    {
        $memberId = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        $orderId = ShopFixtures::purchase($this->pdo, $memberId, ShopFixtures::BLOCKFIELD);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 5');
        $before = $this->pdo->query('SELECT * FROM products WHERE product_id = 5')->fetch();

        $form = $this->admin->get('admin/product_edit.php?id=5');
        self::assertStringContainsString('商品編集', $form->body);
        self::assertStringContainsString('value="ブロックフィールド開拓記"', $form->body);
        self::assertStringContainsString('更新する', $form->body);
        self::assertStringContainsString('非公開', self::text($form->body), 'A06a-002');

        $fields = self::fields(['name' => 'ブロックフィールド開拓記', 'price' => '2980', 'crossplay_flag' => '1']);
        self::assertRedirect($this->postEdit('admin/product_edit.php?id=5', $fields), 'product_confirm.php');
        self::assertRedirect($this->confirm('save'), 'products.php');

        $after = $this->pdo->query('SELECT * FROM products WHERE product_id = 5')->fetch();
        self::assertSame(2980, (int) $after['price']);
        foreach (['image', 'file_path', 'public_flag'] as $column) {
            self::assertSame($before[$column], $after[$column], "{$column}（A06a-005・A06a-012）");
        }
        $paid = $this->pdo->query("SELECT price FROM order_details WHERE order_id = {$orderId}")->fetchColumn();
        self::assertSame(2480, (int) $paid, 'A06a-003：購入時の価格のまま');
        self::assertStringContainsString('¥2,980', $this->admin->get('admin/products.php')->body);
    }

    public function testDraftDoesNotSurviveAnotherLogin(): void
    {
        ShopFixtures::insertMember($this->pdo, 'b@example.com');
        self::assertRedirect($this->postEdit('admin/product_edit.php', self::fields(), self::allFiles()), 'product_confirm.php');

        $token = $this->admin->get('login.php')->csrfToken();
        $this->admin->post('login.php', ['csrf_token' => $token, 'email' => 'b@example.com', 'password' => 'abc12345']);
        $token = $this->admin->get('admin/login.php')->csrfToken();
        $this->admin->post('admin/login.php', ['csrf_token' => $token, 'login_id' => 'admin', 'password' => 'admin1234']);

        self::assertRedirect($this->admin->get('admin/product_confirm.php'), 'products.php', '前の管理者の確認待ちは残らない');
        self::assertSame(8, ShopFixtures::count($this->pdo, 'products'));
    }

    public function testBadRequests(): void
    {
        self::assertSame(404, $this->admin->get('admin/product_edit.php?id=999')->status);
        self::assertSame(404, $this->admin->get('admin/product_edit.php?id=abc')->status);
        self::assertSame(400, $this->admin->postMultipart('admin/product_edit.php', self::fields(), [])->status, 'CSRF なし');

        $this->postEdit('admin/product_edit.php', self::fields(), self::allFiles());
        self::assertSame(400, $this->confirm('delete')->status);
        self::assertSame(400, $this->admin->post('admin/product_confirm.php', ['action' => 'save'])->status);
        self::assertSame(8, ShopFixtures::count($this->pdo, 'products'));

        $guest = new HttpClient(AppServer::baseUrl());
        self::assertRedirect($guest->get('admin/product_edit.php'), 'login.php');
        self::assertRedirect($guest->get('admin/product_confirm.php'), 'login.php');
    }
}
