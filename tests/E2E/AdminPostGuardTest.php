<?php

declare(strict_types=1);

namespace PlayDuo\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\AppServer;
use PlayDuo\Tests\Support\HttpClient;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

/**
 * 管理画面の POST 全部に共通の守り（Plan 2026-11-26-php-admin T8・付録 A）：
 * 未ログイン＝A01 へ／CSRF なし・違う＝400。状態は変わらない
 */
final class AdminPostGuardTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, string>}> */
    public static function postEndpoints(): iterable
    {
        yield 'A04/A05' => ['admin/product_visibility.php?id=5', ['product_id' => '5', 'action' => 'hide']];
        yield 'A06' => ['admin/product_edit.php', ['name' => 'x']];
        yield 'A06-a' => ['admin/product_edit.php?id=5', ['name' => 'x']];
        yield 'A07' => ['admin/product_confirm.php', ['action' => 'save']];
        yield 'logout' => ['admin/logout.php', []];
    }

    protected function setUp(): void
    {
        $pdo = TestDb::pdo();
        TestDb::truncateAll($pdo);
        ShopFixtures::insertAllProducts($pdo);
        ShopFixtures::insertAdmin($pdo);
    }

    #[DataProvider('postEndpoints')]
    public function testGuestIsSentToLogin(string $path, array $fields): void
    {
        $guest = new HttpClient(AppServer::baseUrl());
        $token = $guest->get('admin/login.php')->csrfToken();
        $res = $guest->post($path, ['csrf_token' => $token] + $fields);

        if ($path === 'admin/logout.php') {
            self::assertSame(303, $res->status, 'ログアウトは未ログインでも A01 へ戻すだけ');
        } else {
            self::assertSame(303, $res->status, $res->body);
            self::assertSame('login.php', $res->header('location'));
        }
        self::assertSame(1, (int) TestDb::pdo()->query('SELECT public_flag FROM products WHERE product_id = 5')->fetchColumn());
    }

    #[DataProvider('postEndpoints')]
    public function testMissingOrWrongCsrfIs400(string $path, array $fields): void
    {
        $admin = new HttpClient(AppServer::baseUrl());
        $token = $admin->get('admin/login.php')->csrfToken();
        $admin->post('admin/login.php', ['csrf_token' => $token, 'login_id' => 'admin', 'password' => 'admin1234']);
        if ($path === 'admin/product_confirm.php') {
            // 確認待ちが無いときは CSRF を見る前に A03 へ戻す（何も変えない）。
            // 確認待ちがあるときの CSRF なし＝400 は AdminProductEditFlowTest::testBadRequests で確かめている
            self::assertSame(303, $admin->post($path, $fields)->status);
            return;
        }

        self::assertSame(400, $admin->post($path, $fields)->status, 'CSRF なし');
        self::assertSame(400, $admin->post($path, ['csrf_token' => 'wrong'] + $fields)->status, 'CSRF 違い');
        self::assertSame(200, $admin->get('admin/index.php')->status, 'ログアウトされていない');
        self::assertSame(1, (int) TestDb::pdo()->query('SELECT public_flag FROM products WHERE product_id = 5')->fetchColumn());
    }
}
