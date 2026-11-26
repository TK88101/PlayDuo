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
 * A03 商品一覧・A04 非公開・A05 再公開の HTTP シナリオ。
 * 期待結果はテスト仕様書（管理画面）A03〜A05 と Plan 2026-11-26-php-admin §2.0。
 */
final class AdminProductFlowTest extends TestCase
{
    private PDO $pdo;
    private HttpClient $admin;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->pdo->exec("UPDATE products SET file_path = 'downloads/g05.zip' WHERE product_id = 5");
        ShopFixtures::insertAdmin($this->pdo);
        $this->admin = new HttpClient(AppServer::baseUrl());
        $token = $this->admin->get('admin/login.php')->csrfToken();
        $this->admin->post('admin/login.php', ['csrf_token' => $token, 'login_id' => 'admin', 'password' => 'admin1234']);
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

    private function publicFlag(int $id): int
    {
        return (int) $this->pdo->query("SELECT public_flag FROM products WHERE product_id = {$id}")->fetchColumn();
    }

    private function postVisibility(int $id, string $action): HttpResponse
    {
        $token = $this->admin->get("admin/product_visibility.php?id={$id}")->csrfToken();
        return $this->admin->post("admin/product_visibility.php?id={$id}", [
            'csrf_token' => $token, 'product_id' => (string) $id, 'action' => $action,
        ]);
    }

    /** @return list<string> 一覧の行（<tr>…</tr>）を上から */
    private static function rows(string $html): array
    {
        preg_match_all('#<tr class="product-row"[^>]*>.*?</tr>#s', $html, $m);
        return $m[0];
    }

    public function testListShowsAllProductsInIdOrderWithColumns(): void
    {
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 6');
        $page = $this->admin->get('admin/products.php');

        self::assertSame(200, $page->status);
        $text = self::text($page->body);
        foreach (['ID', 'タイトル', 'ジャンル', 'プレイ人数', '協力・対戦', '対応OS', 'クロスプレイ', '価格', '年齢区分', '状態', '操作'] as $col) {
            self::assertStringContainsString("<th>{$col}</th>", $page->body, $col);
        }
        self::assertStringContainsString('href="product_edit.php"', $page->body);
        self::assertStringContainsString('＋商品を登録', $text);

        $rows = self::rows($page->body);
        self::assertCount(8, $rows, '非公開も含めて全件');
        self::assertStringContainsString('data-product-id="1"', $rows[0]);
        self::assertStringContainsString('data-product-id="8"', $rows[7]);

        $g05 = self::text($rows[4]);
        foreach (['g05', 'ブロックフィールド開拓記', 'クラフト', '1〜8人', '協力', 'Win・Mac', '○', '¥2,480', 'A', '公開中', '編集', '非公開'] as $value) {
            self::assertStringContainsString($value, $g05, $value);
        }
        self::assertStringContainsString('href="product_edit.php?id=5"', $rows[4]);
        self::assertStringContainsString('href="product_visibility.php?id=5"', $rows[4]);

        $hidden = self::text($rows[5]);
        self::assertStringContainsString('非公開', $hidden);
        self::assertStringContainsString('公開', $hidden);
        self::assertStringNotContainsString('公開中', $hidden);
        self::assertStringNotContainsString('○', self::text($rows[0]), '単一 OS のクロスプレイ欄は空');
    }

    public function testHideConfirmationShowsTargetAndExplanation(): void
    {
        $page = $this->admin->get('admin/product_visibility.php?id=5');
        $text = self::text($page->body);

        self::assertSame(200, $page->status);
        self::assertStringContainsString('この商品を非公開にしますか？', $text);
        self::assertStringContainsString('g05 ブロックフィールド開拓記', $text);
        self::assertStringContainsString('購入済みの会員は引き続きダウンロード', $text);
        self::assertStringContainsString('name="action" value="hide"', $page->body);
        self::assertStringContainsString('非公開にする', $text);
        self::assertStringContainsString('href="products.php"', $page->body, 'キャンセル');
        self::assertSame(1, $this->publicFlag(5), '表示しただけでは変わらない');
    }

    public function testHideUpdatesStateShowsMessageOnceAndKeepsPurchases(): void
    {
        $memberId = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        ShopFixtures::purchase($this->pdo, $memberId, ShopFixtures::BLOCKFIELD);
        $otherId = ShopFixtures::insertMember($this->pdo, 'c@example.com');
        ShopFixtures::addToCart($this->pdo, $otherId, ShopFixtures::BLOCKFIELD);

        self::assertRedirect($this->postVisibility(5, 'hide'), 'products.php');
        self::assertSame(0, $this->publicFlag(5));

        $list = $this->admin->get('admin/products.php');
        self::assertStringContainsString('非公開にしました', self::text($list->body));
        self::assertStringNotContainsString('非公開にしました', self::text($this->admin->get('admin/products.php')->body), 'A03-005');

        $guest = new HttpClient(AppServer::baseUrl());
        self::assertStringNotContainsString('ブロックフィールド開拓記', $guest->get('search.php')->body, 'A04-005');

        $buyer = new HttpClient(AppServer::baseUrl());
        $token = $buyer->get('login.php')->csrfToken();
        $buyer->post('login.php', ['csrf_token' => $token, 'email' => 'b@example.com', 'password' => 'abc12345']);
        self::assertSame(200, $buyer->get('download.php?id=5')->status, 'A04-006');
        self::assertStringContainsString('ブロックフィールド開拓記', $buyer->get('mypage.php')->body, 'A04-007');

        $other = new HttpClient(AppServer::baseUrl());
        $token = $other->get('login.php')->csrfToken();
        $other->post('login.php', ['csrf_token' => $token, 'email' => 'c@example.com', 'password' => 'abc12345']);
        self::assertStringContainsString('現在購入できません', $other->get('cart.php')->body, 'A04-005');
    }

    public function testDoubleHideAndShowEndInTheRightState(): void
    {
        self::assertRedirect($this->postVisibility(5, 'hide'), 'products.php');
        self::assertRedirect($this->postVisibility(5, 'hide'), 'products.php');
        self::assertSame(0, $this->publicFlag(5), 'A04-008');

        $page = $this->admin->get('admin/product_visibility.php?id=5');
        self::assertStringContainsString('この商品を公開しますか？', self::text($page->body));
        self::assertStringContainsString('name="action" value="show"', $page->body);

        self::assertRedirect($this->postVisibility(5, 'show'), 'products.php');
        self::assertRedirect($this->postVisibility(5, 'show'), 'products.php');
        self::assertSame(1, $this->publicFlag(5), 'A05-006');
        self::assertStringContainsString('ブロックフィールド開拓記', (new HttpClient(AppServer::baseUrl()))->get('search.php')->body, 'A05-004');
    }

    public function testBadRequestsAreRejected(): void
    {
        $this->admin->get('admin/product_visibility.php?id=5');
        self::assertSame(400, $this->admin->post('admin/product_visibility.php?id=5', ['product_id' => '5', 'action' => 'hide'])->status);
        self::assertSame(400, $this->postVisibility(5, 'delete')->status);
        self::assertSame(1, $this->publicFlag(5));

        foreach (['999', 'abc', '0'] as $id) {
            self::assertSame(404, $this->admin->get("admin/product_visibility.php?id={$id}")->status, $id);
        }
        self::assertSame(404, $this->admin->get('admin/product_visibility.php')->status);
        $token = $this->admin->get('admin/products.php')->csrfToken();
        self::assertSame(404, $this->admin->post('admin/product_visibility.php?id=999', [
            'csrf_token' => $token, 'product_id' => '999', 'action' => 'hide',
        ])->status);
    }

    public function testNotLoggedInCannotChangeVisibility(): void
    {
        $guest = new HttpClient(AppServer::baseUrl());
        self::assertRedirect($guest->get('admin/products.php'), 'login.php');
        self::assertRedirect($guest->get('admin/product_visibility.php?id=5'), 'login.php');
        $token = $guest->get('admin/login.php')->csrfToken();
        self::assertRedirect($guest->post('admin/product_visibility.php?id=5', [
            'csrf_token' => $token, 'product_id' => '5', 'action' => 'hide',
        ]), 'login.php');
        self::assertSame(1, $this->publicFlag(5));
    }
}
