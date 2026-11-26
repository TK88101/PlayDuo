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

/** A10 注文一覧・A11 注文詳細の HTTP シナリオ（テスト仕様書（管理画面）A10・A11） */
final class AdminOrderFlowTest extends TestCase
{
    private PDO $pdo;
    private HttpClient $admin;
    private int $orderId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->pdo->exec("UPDATE products SET file_path = CONCAT('downloads/g0', product_id, '.zip')");
        ShopFixtures::insertAdmin($this->pdo);
        $memberId = ShopFixtures::insertMember($this->pdo, 'hanako@example.com');
        $this->pdo->exec("UPDATE members SET name = '山田 花子' WHERE member_id = {$memberId}");
        $this->orderId = ShopFixtures::purchase($this->pdo, $memberId, ShopFixtures::NEONSIGHT, ShopFixtures::BLOCKFIELD);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-10-02 21:15:00' WHERE order_id = {$this->orderId}");
        $this->admin = new HttpClient(AppServer::baseUrl());
        $token = $this->admin->get('admin/login.php')->csrfToken();
        $this->admin->post('admin/login.php', ['csrf_token' => $token, 'login_id' => 'admin', 'password' => 'admin1234']);
    }

    private static function text(string $html): string
    {
        return (string) preg_replace('/\s+/u', ' ', strip_tags($html));
    }

    private static function assertRedirect(HttpResponse $res, string $location): void
    {
        self::assertSame(303, $res->status, $res->body);
        self::assertSame($location, $res->header('location'));
    }

    public function testListShowsColumnsAndRow(): void
    {
        $page = $this->admin->get('admin/orders.php');
        self::assertSame(200, $page->status);
        foreach (['date_from', 'date_to', 'member', 'product'] as $name) {
            self::assertStringContainsString("name=\"{$name}\"", $page->body);
        }
        foreach (['注文番号', '会員ID', '氏名', '購入日時', '商品数', '合計金額', '操作'] as $col) {
            self::assertStringContainsString("<th>{$col}</th>", $page->body, $col);
        }
        $text = self::text($page->body);
        foreach (['R261002001', 'M0001', '山田 花子', '2026/10/02 21:15', '2点', '¥5,780'] as $value) {
            self::assertStringContainsString($value, $text, $value);
        }
        self::assertStringContainsString("href=\"order.php?id={$this->orderId}\"", $page->body, 'A10-013');
    }

    public function testSearchConditionsErrorsAndEscaping(): void
    {
        self::assertStringContainsString('R261002001', $this->admin->get('admin/orders.php?date_from=2026-10-02&date_to=2026-10-02&member=M0001&product=' . rawurlencode('ネオン'))->body);
        self::assertStringContainsString('該当する注文はありません', self::text($this->admin->get('admin/orders.php?date_from=2026-10-03')->body));

        $bad = $this->admin->get('admin/orders.php?date_from=2026-10-05&date_to=2026-10-01');
        self::assertSame(200, $bad->status);
        self::assertStringContainsString('期間の開始日は終了日以前にしてください', $bad->body, 'A10-005');
        self::assertStringContainsString('value="2026-10-05"', $bad->body, '入力は残す');

        $xss = $this->admin->get('admin/orders.php?product=' . rawurlencode('<script>x</script>'));
        self::assertSame(200, $xss->status, 'A10-012');
        self::assertStringNotContainsString('<script>x', $xss->body);
        self::assertSame(200, $this->admin->get('admin/orders.php?member[]=1')->status);
    }

    public function testDetailShowsItemsAtPurchaseTime(): void
    {
        $this->pdo->exec("UPDATE products SET name = '改名', price = 1, public_flag = 0 WHERE product_id = 2");
        $page = $this->admin->get("admin/order.php?id={$this->orderId}");

        self::assertSame(200, $page->status);
        $text = self::text($page->body);
        foreach (['R261002001', '2026/10/02 21:15', 'M0001', '山田 花子', '2点', '¥5,780',
            '購入したゲーム（ダウンロード権利あり）', 'g02', 'ネオンサイト', '¥3,300', 'g05', 'ブロックフィールド開拓記', '¥2,480'] as $value) {
            self::assertStringContainsString($value, $text, $value);
        }
        self::assertStringNotContainsString('改名', $text, 'A11-003');
        self::assertSame(2, substr_count($page->body, 'class="order-item-row"'));
        self::assertStringContainsString('href="orders.php"', $page->body, 'A11-006');
        self::assertSame(1, substr_count($page->body, '<form method="post"'), 'A11-008：ログアウト以外の操作なし');
    }

    public function testMissingOrderAndGuest(): void
    {
        foreach (['999', 'abc', ''] as $id) {
            self::assertSame(404, $this->admin->get("admin/order.php?id={$id}")->status, $id);
        }
        $guest = new HttpClient(AppServer::baseUrl());
        self::assertRedirect($guest->get('admin/orders.php'), 'login.php');
        self::assertRedirect($guest->get('admin/order.php?id=1'), 'login.php');
    }
}
