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

/** A08 会員一覧・A09 会員詳細の HTTP シナリオ（テスト仕様書（管理画面）A08・A09） */
final class AdminMemberFlowTest extends TestCase
{
    private PDO $pdo;
    private HttpClient $admin;
    private int $memberId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        ShopFixtures::insertAdmin($this->pdo);
        $this->memberId = ShopFixtures::insertMember($this->pdo, 'hanako@example.com', '2005-04-12');
        $this->pdo->exec("UPDATE members SET name = '山田 花子', created_at = '2026-09-01 10:00:00' WHERE member_id = {$this->memberId}");
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

    public function testListShowsColumnsAndValues(): void
    {
        $withdrawn = ShopFixtures::insertMember($this->pdo, 'gone@example.com');
        $this->pdo->exec("UPDATE members SET withdrawal_flag = 1 WHERE member_id = {$withdrawn}");
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);

        $page = $this->admin->get('admin/members.php');
        self::assertSame(200, $page->status);
        self::assertStringContainsString('name="keyword"', $page->body);
        foreach (['会員ID', '氏名', 'メールアドレス', '生年月日', '登録日', '購入数', '状態', '操作'] as $col) {
            self::assertStringContainsString("<th>{$col}</th>", $page->body, $col);
        }
        $text = self::text($page->body);
        foreach (['M0001', '山田 花子', 'hanako@example.com', '2005/04/12', '2026/09/01', '利用中', 'M0002', '退会済み'] as $value) {
            self::assertStringContainsString($value, $text, $value);
        }
        self::assertMatchesRegularExpression('#M0001.*?2026/09/01\s*2\s#su', $text, '購入数 2');
        self::assertStringContainsString('href="member.php?id=1"', $page->body);
        self::assertStringNotContainsString('$2y$', $page->body, 'A08-012');
        self::assertSame(1, substr_count($page->body, '<form method="post"'), 'A08-013：POST はヘッダーのログアウトだけ（編集・削除なし）');
    }

    public function testSearchKeepsKeywordAndEscapes(): void
    {
        ShopFixtures::insertMember($this->pdo, 'taro@example.com');

        $page = $this->admin->get('admin/members.php?keyword=' . rawurlencode('花子'));
        self::assertStringContainsString('hanako@example.com', $page->body);
        self::assertStringNotContainsString('taro@example.com', $page->body);
        self::assertStringContainsString('value="花子"', $page->body);

        $xss = $this->admin->get('admin/members.php?keyword=' . rawurlencode('<script>x</script>'));
        self::assertSame(200, $xss->status);
        self::assertStringNotContainsString('<script>x', $xss->body, 'A08-009');
        self::assertStringContainsString('該当する会員はいません', self::text($xss->body));

        self::assertSame(200, $this->admin->get('admin/members.php?keyword[]=x')->status);
    }

    public function testDetailShowsAgeHistoryAndNoPassword(): void
    {
        $order = ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-10-02 21:15:00' WHERE order_id = {$order}");

        $page = $this->admin->get("admin/member.php?id={$this->memberId}");
        self::assertSame(200, $page->status);
        $text = self::text($page->body);
        $age = (int) date('Y') - 2005 - (date('m-d') < '04-12' ? 1 : 0);
        foreach (['M0001', '山田 花子', 'hanako@example.com', "2005/04/12（{$age}歳）", '2026/09/01', '利用中',
            '※パスワードは表示しない', 'R261002001', 'ブロックフィールド開拓記', '¥2,480', 'ネオンサイト', '¥3,300', '2026/10/02'] as $value) {
            self::assertStringContainsString($value, $text, $value);
        }
        self::assertSame(2, substr_count($page->body, 'class="purchase-row"'));
        self::assertStringContainsString("href=\"order.php?id={$order}\"", $page->body, 'A09-010');
        self::assertStringContainsString('href="members.php"', $page->body, 'A09-009');
        self::assertStringNotContainsString('$2y$', $page->body, 'A09-007');
        self::assertStringNotContainsString('GEZDGNBV', $page->body, '認証アプリの秘密鍵も出さない');
    }

    public function testEmptyHistoryAndMissingMember(): void
    {
        $page = $this->admin->get("admin/member.php?id={$this->memberId}");
        self::assertStringContainsString('購入履歴はありません', self::text($page->body), 'A09-004');

        foreach (['999', 'abc', '0', ''] as $id) {
            self::assertSame(404, $this->admin->get("admin/member.php?id={$id}")->status, "A09-011 {$id}");
        }
        $guest = new HttpClient(AppServer::baseUrl());
        self::assertRedirect($guest->get('admin/members.php'), 'login.php');
        self::assertRedirect($guest->get('admin/member.php?id=1'), 'login.php');
    }
}
