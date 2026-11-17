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
 * 商品検索〜カート〜購入の HTTP シナリオ（php -S ＋ playduo_test）。
 * 期待結果はテスト仕様書（利用者）第2版と Plan 2026-11-17 §2.0（趙 11/17 決定）による。
 */
final class ShopFlowTest extends TestCase
{
    private const PASSWORD = 'abc12345';
    private const ADULT_EMAIL = 'adult@example.com';
    private const CHILD_EMAIL = 'child@example.com';   // 2015 年生まれ：CERO C（ネオンサイト）は買えない

    private PDO $pdo;
    private HttpClient $client;
    private int $adultId;
    private int $childId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->adultId = ShopFixtures::insertMember($this->pdo, self::ADULT_EMAIL, '2000-01-01', self::PASSWORD);
        $this->childId = ShopFixtures::insertMember($this->pdo, self::CHILD_EMAIL, '2015-01-01', self::PASSWORD);
        $this->client = new HttpClient(AppServer::baseUrl());
    }

    private static function assertRedirect(HttpResponse $res, string $location): void
    {
        self::assertSame(303, $res->status, $res->body);
        self::assertSame($location, $res->header('location'));
    }

    private function login(string $email = self::ADULT_EMAIL): HttpResponse
    {
        $token = $this->client->get('login.php')->csrfToken();
        return $this->client->post('login.php', ['csrf_token' => $token, 'email' => $email, 'password' => self::PASSWORD]);
    }

    private function addToCart(int $productId): HttpResponse
    {
        $token = $this->client->get("product.php?id={$productId}")->csrfToken();
        return $this->client->post("product.php?id={$productId}", ['csrf_token' => $token, 'product_id' => (string) $productId]);
    }

    /** @return string U08 の confirm_token */
    private function openConfirm(): string
    {
        $page = $this->client->get('purchase_confirm.php');
        self::assertSame(200, $page->status, $page->body);
        self::assertSame(1, preg_match('/name="confirm_token" value="([0-9a-f]{32})"/', $page->body, $m));
        return $m[1];
    }

    private function confirm(string $confirmToken): HttpResponse
    {
        $csrf = $this->client->get('cart.php')->csrfToken();
        return $this->client->post('purchase_confirm.php', ['csrf_token' => $csrf, 'confirm_token' => $confirmToken]);
    }

    // ---- U01・U02 ----

    public function testTopShowsFourRecommendedGamesAndHeader(): void
    {
        $page = $this->client->get('index.php');

        self::assertSame(200, $page->status);
        self::assertSame(4, preg_match_all('/class="product-card"/', $page->body));
        self::assertStringContainsString('ブロックフィールド開拓記', $page->body);
        self::assertStringContainsString('¥2,480', $page->body);
        self::assertStringContainsString('href="product.php?id=5"', $page->body);
        self::assertStringContainsString('action="search.php"', $page->body, 'ヘッダーの検索ボックス');
        self::assertStringContainsString('name="players[]"', $page->body, '条件から探す');
        self::assertStringNotContainsString('class="cart-count"', $page->body, '未ログインではカート数を出さない');
    }

    public function testSearchFiltersAndCountsResults(): void
    {
        $page = $this->client->get('search.php?players%5B%5D=3-4&play_style%5B%5D=%E5%8D%94%E5%8A%9B&crossplay=1');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('検索結果 2件', $page->body);
        self::assertSame(2, preg_match_all('/class="product-row"/', $page->body));
        self::assertStringContainsString('value="3-4" checked', $page->body, '選んだ条件が絞り込みパネルに残る');
    }

    public function testSearchWithNoHitsAndScriptKeywordIsSafe(): void
    {
        $page = $this->client->get('search.php?keyword=' . rawurlencode('<script>alert(1)</script>'));

        self::assertSame(200, $page->status);
        self::assertStringContainsString('検索結果 0件', $page->body);
        self::assertStringContainsString('条件に合うゲームが見つかりませんでした', $page->body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $page->body);
        self::assertStringContainsString('&lt;script&gt;', $page->body);
    }

    public function testSearchShowsPriceError(): void
    {
        $page = $this->client->get('search.php?price_min=abc');

        self::assertStringContainsString('価格は0〜1000000の整数で入力してください', $page->body);
        self::assertStringContainsString('検索結果 8件', $page->body, '不正な条件は使わずに検索する');
    }

    // ---- U03 ----

    public function testProductDetailAndNotFound(): void
    {
        $page = $this->client->get('product.php?id=5');
        self::assertSame(200, $page->status);
        self::assertStringContainsString('<h1>ブロックフィールド開拓記</h1>', $page->body);
        self::assertStringContainsString('BLOCKFIELD FRONTIER', $page->body);
        self::assertStringContainsString('1〜8人', $page->body);

        self::assertSame(404, $this->client->get('product.php?id=99999')->status);
        self::assertSame(404, $this->client->get('product.php?id=abc')->status);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 4');
        self::assertSame(404, $this->client->get('product.php?id=4')->status, 'Q25：非公開は 404');
    }

    public function testGuestAddToCartGoesToLoginAndReturnsToTheProduct(): void
    {
        self::assertRedirect($this->addToCart(5), 'login.php');

        self::assertRedirect($this->login(), 'product.php?id=5');
        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'), 'Q06：自動では追加しない');
    }

    public function testLoginReturnIsUsedOnlyOnce(): void
    {
        $this->addToCart(5);
        $this->login();
        $this->client->post('logout.php', ['csrf_token' => $this->client->get('index.php')->csrfToken()]);

        self::assertRedirect($this->login(), 'index.php');
    }

    public function testAddToCartShowsPopupAndCount(): void
    {
        $this->login();

        self::assertRedirect($this->addToCart(5), 'product.php?id=5');
        $page = $this->client->get('product.php?id=5');
        self::assertStringContainsString('id="cart-added"', $page->body);
        self::assertStringContainsString('カート内の商品：1点', $page->body);
        self::assertStringContainsString('小計 ¥2,480', $page->body);
        self::assertStringContainsString('<span class="cart-count">1</span>', $page->body);

        self::assertStringNotContainsString('id="cart-added"', $this->client->get('product.php?id=5')->body, 'ポップアップは 1 回だけ');

        $this->addToCart(5);
        self::assertSame(1, ShopFixtures::count($this->pdo, 'carts'), 'Q08：重複しない');
    }

    public function testPurchasedOrAgeRestrictedProductCannotBeAdded(): void
    {
        ShopFixtures::purchase($this->pdo, $this->childId, 5);
        $this->login(self::CHILD_EMAIL);

        $purchased = $this->client->get('product.php?id=5');
        self::assertStringContainsString('購入済みです', $purchased->body);
        self::assertMatchesRegularExpression('/<button type="submit"[^>]*disabled/', $purchased->body);

        $restricted = $this->client->get('product.php?id=2');
        self::assertStringContainsString('年齢区分（CERO C）の対象年齢に達していないため購入できません', $restricted->body);

        // 押せない表示でも POST はサーバで拒否する
        $res = $this->addToCart(2);
        self::assertSame(200, $res->status);
        self::assertStringContainsString('年齢区分（CERO C）の対象年齢に達していないため購入できません', $res->body);
        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'));
    }

    // ---- U07 ----

    public function testGuestCartGoesToLoginAndBack(): void
    {
        self::assertRedirect($this->client->get('cart.php'), 'login.php');
        self::assertRedirect($this->login(), 'cart.php');
    }

    public function testCartShowsTotalsAndDeletes(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->adultId, 5);
        ShopFixtures::addToCart($this->pdo, $this->adultId, 2);
        ShopFixtures::addToCart($this->pdo, $this->adultId, 4);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 4');
        $this->login();

        $cart = $this->client->get('cart.php');
        self::assertSame(3, preg_match_all('/class="cart-item"/', $cart->body));
        self::assertStringContainsString('この商品は販売を終了しています。カートから削除してください。', $cart->body);
        self::assertStringContainsString('商品数 2点', $cart->body, 'U07-005');
        self::assertStringContainsString('合計 ¥5,780', $cart->body);

        $res = $this->client->post('cart.php', ['csrf_token' => $cart->csrfToken(), 'action' => 'delete', 'product_id' => '2']);
        self::assertRedirect($res, 'cart.php');
        self::assertStringContainsString('合計 ¥2,480', $this->client->get('cart.php')->body);

        foreach (['5', '4'] as $id) {
            $this->client->post('cart.php', ['csrf_token' => $cart->csrfToken(), 'action' => 'delete', 'product_id' => $id]);
        }
        $empty = $this->client->get('cart.php');
        self::assertStringContainsString('カートは空です', $empty->body);
        self::assertStringContainsString('カートが空のため、購入手続きには進めません', $empty->body);
        self::assertStringNotContainsString('href="purchase_confirm.php"', $empty->body);
    }

    public function testCartWithOnlyUnbuyableItemsCannotProceed(): void
    {
        // U07-007：販売終了だけのカートは 0 点・¥0 で購入手続きに進めない
        ShopFixtures::addToCart($this->pdo, $this->adultId, 4);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 4');
        $this->login();

        $cart = $this->client->get('cart.php');

        self::assertStringContainsString('商品数 0点', $cart->body);
        self::assertStringContainsString('合計 ¥0', $cart->body);
        self::assertStringContainsString('購入できる商品がないため、購入手続きには進めません', $cart->body);
        self::assertStringNotContainsString('href="purchase_confirm.php"', $cart->body);
    }

    // ---- U08〜U10 ----

    public function testPurchaseFlowRegistersOrderOnce(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->adultId, 5);
        ShopFixtures::addToCart($this->pdo, $this->adultId, 2);
        $this->login();

        $page = $this->client->get('purchase_confirm.php');
        self::assertStringContainsString('合計 ¥5,780', $page->body);
        self::assertStringContainsString('「購入を確定する」を押すと注文が登録され、ダウンロードできるようになります。', $page->body);

        $token = $this->openConfirm();
        $res = $this->confirm($token);
        $orderId = (int) $this->pdo->query('SELECT MAX(order_id) FROM orders')->fetchColumn();
        self::assertRedirect($res, "purchase_complete.php?id={$orderId}");

        // 受け入れ基準 3
        self::assertSame(1, ShopFixtures::count($this->pdo, 'orders'));
        self::assertSame(2, ShopFixtures::count($this->pdo, 'order_details'));
        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'));

        $done = $this->client->get("purchase_complete.php?id={$orderId}");
        self::assertStringContainsString('購入が完了しました', $done->body);
        self::assertStringContainsString('no-store', (string) $done->header('cache-control'), '注文内容をブラウザに残さない');
        self::assertMatchesRegularExpression('/class="order-number">R\d{6}' . sprintf('%03d', $orderId) . '</', $done->body);
        self::assertStringContainsString('href="mypage.php"', $done->body);

        // 受け入れ基準 4：同じ確認の再送でも注文は 1 件（同じ完了画面へ）
        self::assertRedirect($this->confirm($token), "purchase_complete.php?id={$orderId}");
        self::assertSame(1, ShopFixtures::count($this->pdo, 'orders'));
        self::assertSame(200, $this->client->get('mypage.php')->status, '暫定マイページ（準備中）');
    }

    public function testStaleConfirmTokenGoesBackToConfirm(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->adultId, 5);
        $this->login();
        $old = $this->openConfirm();
        $this->openConfirm();   // 別タブで開き直した

        self::assertRedirect($this->confirm($old), 'purchase_confirm.php');
        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
    }

    public function testCartChangedAfterConfirmIsNotPurchased(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->adultId, 5);
        $this->login();
        $token = $this->openConfirm();
        ShopFixtures::addToCart($this->pdo, $this->adultId, 2);   // 別端末で追加

        self::assertRedirect($this->confirm($token), 'purchase_confirm.php');
        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
        self::assertStringContainsString('カートの内容が変わりました', $this->client->get('purchase_confirm.php')->body);
    }

    public function testEmptyCartCannotOpenConfirm(): void
    {
        $this->login();

        self::assertRedirect($this->client->get('purchase_confirm.php'), 'cart.php');
        self::assertRedirect($this->client->get('purchase_error.php'), 'cart.php');   // U09-011
    }

    public function testUnbuyableItemsGoToU09AndCanBeRemoved(): void
    {
        // SC-002：購入済み・年齢制限・購入可能が混在
        ShopFixtures::purchase($this->pdo, $this->childId, 3);
        foreach ([5, 2, 3] as $id) {
            ShopFixtures::addToCart($this->pdo, $this->childId, $id);
        }
        $this->login(self::CHILD_EMAIL);

        self::assertRedirect($this->client->get('purchase_confirm.php'), 'purchase_error.php');
        $u09 = $this->client->get('purchase_error.php');
        self::assertStringContainsString('カート内に購入できない商品が2点あります', $u09->body);
        self::assertStringContainsString('注文は登録されていません', $u09->body);
        self::assertStringContainsString('購入済み', $u09->body);
        self::assertStringContainsString('年齢制限', $u09->body);
        self::assertSame(1, ShopFixtures::count($this->pdo, 'orders'), 'U09-003：注文は増えない');

        self::assertRedirect($this->client->post('purchase_error.php', ['csrf_token' => $u09->csrfToken()]), 'cart.php');
        self::assertSame([5], array_map('intval', $this->pdo->query('SELECT product_id FROM carts')->fetchAll(PDO::FETCH_COLUMN)));

        $token = $this->openConfirm();
        $this->confirm($token);
        self::assertSame(2, ShopFixtures::count($this->pdo, 'orders'), 'U09-007：削除後は購入できる');
    }

    public function testOtherMembersOrderIsNotFound(): void
    {
        $orderId = ShopFixtures::purchase($this->pdo, $this->childId, 5);
        $this->login();

        self::assertSame(404, $this->client->get("purchase_complete.php?id={$orderId}")->status);
        self::assertSame(404, $this->client->get('purchase_complete.php?id=abc')->status);
    }

    public function testPostWithoutCsrfIsRejected(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->adultId, 5);
        $this->login();
        $token = $this->openConfirm();

        $res = $this->client->post('purchase_confirm.php', ['csrf_token' => 'bad', 'confirm_token' => $token]);

        self::assertSame(400, $res->status);
        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
    }
}
