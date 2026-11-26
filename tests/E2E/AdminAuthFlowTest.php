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

/**
 * A01 管理者ログイン・A02 管理メニュー・ログアウトの HTTP シナリオ。
 * 期待結果はテスト仕様書（管理画面）A01・A02 と Plan 2026-11-26-php-admin §2.0（管理者と会員のセッション）。
 */
final class AdminAuthFlowTest extends TestCase
{
    private const SID = 'PLAYDUO_SID';

    private PDO $pdo;
    private HttpClient $client;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAdmin($this->pdo, 'admin', 'admin1234');
        ShopFixtures::insertMember($this->pdo, 'b@example.com');
        $this->client = new HttpClient(AppServer::baseUrl());
    }

    private static function assertRedirect(HttpResponse $res, string $location): void
    {
        self::assertSame(303, $res->status, $res->body);
        self::assertSame($location, $res->header('location'));
    }

    private function adminLogin(string $loginId = 'admin', string $password = 'admin1234'): HttpResponse
    {
        $token = $this->client->get('admin/login.php')->csrfToken();
        return $this->client->post('admin/login.php', ['csrf_token' => $token, 'login_id' => $loginId, 'password' => $password]);
    }

    private function memberLogin(): HttpResponse
    {
        $token = $this->client->get('login.php')->csrfToken();
        return $this->client->post('login.php', ['csrf_token' => $token, 'email' => 'b@example.com', 'password' => 'abc12345']);
    }

    private function adminLogout(): HttpResponse
    {
        $token = $this->client->get('admin/index.php')->csrfToken();
        return $this->client->post('admin/logout.php', ['csrf_token' => $token]);
    }

    public function testAdminPagesRedirectToLoginWhenNotLoggedIn(): void
    {
        foreach (['admin/index.php', 'admin/products.php', 'admin/members.php', 'admin/orders.php'] as $page) {
            if (!is_file(__DIR__ . '/../../' . $page)) {
                continue;   // T2 以降で作る画面は、作った時点で対象になる
            }
            self::assertRedirect($this->client->get($page), 'login.php');
        }
    }

    public function testLoginShowsMenuWithThreeCards(): void
    {
        $page = $this->client->get('admin/login.php');
        self::assertSame(200, $page->status);
        self::assertStringContainsString('name="login_id"', $page->body);
        self::assertStringContainsString('no-store', (string) $page->header('cache-control'));

        self::assertRedirect($this->adminLogin(), 'index.php');
        $menu = $this->client->get('admin/index.php');
        self::assertSame(200, $menu->status);
        foreach (['products.php', 'members.php', 'orders.php'] as $href) {
            self::assertStringContainsString("href=\"{$href}\"", $menu->body);
        }
        self::assertStringContainsString('action="logout.php"', $menu->body);
    }

    public function testSessionIdChangesOnLogin(): void
    {
        $this->client->get('admin/login.php');
        $before = $this->client->cookies()[self::SID] ?? null;
        self::assertNotNull($before);

        $this->adminLogin();
        self::assertNotSame($before, $this->client->cookies()[self::SID] ?? null);
    }

    public function testWrongPasswordAndUnknownIdShowTheSameError(): void
    {
        $wrong = $this->adminLogin('admin', 'nope');
        $unknown = $this->adminLogin('nobody', 'admin1234');

        self::assertSame(200, $wrong->status);
        self::assertStringContainsString('管理者IDまたはパスワードが違います', $wrong->body);
        self::assertSame(
            str_replace('value="admin"', '', preg_replace('/name="csrf_token" value="[^"]*"/', '', $wrong->body)),
            str_replace('value="nobody"', '', preg_replace('/name="csrf_token" value="[^"]*"/', '', $unknown->body)),
            '入力した ID 以外は同じ画面',
        );
        self::assertStringNotContainsString('nope', $wrong->body, 'パスワードは入れ直し');
        self::assertRedirect($this->client->get('admin/index.php'), 'login.php');
    }

    public function testLoginWithoutCsrfIsRejected(): void
    {
        $this->client->get('admin/login.php');
        self::assertSame(400, $this->client->post('admin/login.php', ['login_id' => 'admin', 'password' => 'admin1234'])->status);
    }

    public function testLoggedInAdminOpeningLoginGoesToMenu(): void
    {
        $this->adminLogin();
        self::assertRedirect($this->client->get('admin/login.php'), 'index.php');
    }

    public function testAdminSessionIsNotMemberLogin(): void
    {
        $this->adminLogin();
        self::assertRedirect($this->client->get('mypage.php'), 'login.php');
    }

    public function testAdminLoginLogsOutMemberAndViceVersa(): void
    {
        self::assertRedirect($this->memberLogin(), 'index.php');
        self::assertSame(200, $this->client->get('mypage.php')->status);

        $this->adminLogin();
        self::assertRedirect($this->client->get('mypage.php'), 'login.php');
        self::assertSame(200, $this->client->get('admin/index.php')->status);

        $this->memberLogin();
        self::assertRedirect($this->client->get('admin/index.php'), 'login.php');
        self::assertSame(200, $this->client->get('mypage.php')->status);
    }

    public function testLogoutAcceptsOnlyPostWithCsrf(): void
    {
        $this->adminLogin();
        self::assertSame(405, $this->client->get('admin/logout.php')->status);
        self::assertSame(400, $this->client->post('admin/logout.php', [])->status);
        self::assertSame(200, $this->client->get('admin/index.php')->status, 'まだログイン中');

        $before = $this->client->cookies()[self::SID] ?? null;
        self::assertRedirect($this->adminLogout(), 'login.php');
        self::assertNotSame($before, $this->client->cookies()[self::SID] ?? null);
        self::assertRedirect($this->client->get('admin/index.php'), 'login.php');
    }
}
