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
 * マイページ・ダウンロード・会員情報変更・パスワード変更・退会の HTTP シナリオ（php -S ＋ playduo_test）。
 * 期待結果はテスト仕様書（利用者）第2版 U11〜U16・COM と Plan 2026-11-26 §2.0。
 */
final class MypageFlowTest extends TestCase
{
    private const PASSWORD = 'abc12345';
    private const EMAIL = 'b@example.com';

    private PDO $pdo;
    private HttpClient $client;
    private int $memberId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->memberId = ShopFixtures::insertMember($this->pdo, self::EMAIL);
        $this->client = new HttpClient(AppServer::baseUrl());
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

    private function login(string $email = self::EMAIL, string $password = self::PASSWORD, ?HttpClient $client = null): HttpResponse
    {
        $client ??= $this->client;
        $token = $client->get('login.php')->csrfToken();
        return $client->post('login.php', ['csrf_token' => $token, 'email' => $email, 'password' => $password]);
    }

    /** @param array<string, string> $fields */
    private function postForm(string $path, array $fields): HttpResponse
    {
        $token = $this->client->get($path)->csrfToken();
        return $this->client->post($path, ['csrf_token' => $token] + $fields);
    }

    // ---- U11 ----

    public function testLibraryListsPurchasesNewestFirstAndSelectsFirst(): void
    {
        $old = ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::CLOUDPATH);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-09-28 10:00:00' WHERE order_id = {$old}");
        $new = ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-10-02 21:15:00' WHERE order_id = {$new}");
        $this->login();

        $page = $this->client->get('mypage.php');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('no-store', (string) $page->header('cache-control'));
        $text = self::text($page->body);
        self::assertStringContainsString('購入したゲーム（2件）', $text);
        self::assertLessThan(strpos($text, '雲路の旅団'), strpos($text, 'ブロックフィールド開拓記'), '新しい順（U11-004）');
        self::assertStringContainsString('R261002' . sprintf('%03d', $new), $text, '先頭が選択状態（U11-001）');
        self::assertStringContainsString('href="download.php?id=5"', $page->body);
        self::assertStringContainsString('href="account_edit.php"', $page->body, '左メニュー（U11-013）');
        self::assertStringContainsString('href="withdraw.php"', $page->body);
    }

    public function testSelectingAnotherGameAndIgnoringOthersOrInvalidIds(): void
    {
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);
        $other = ShopFixtures::insertMember($this->pdo, 'a@example.com');
        ShopFixtures::purchase($this->pdo, $other, ShopFixtures::CLOUDPATH);
        $this->login();

        self::assertStringContainsString('href="download.php?id=5" class="button-primary"', $this->client->get('mypage.php?id=5')->body);
        foreach (['mypage.php?id=4', 'mypage.php?id[]=5', 'mypage.php?id=-1', 'mypage.php?id=abc'] as $path) {
            $page = $this->client->get($path);
            self::assertSame(200, $page->status, $path);
            self::assertStringNotContainsString('雲路の旅団', $page->body, $path . '：他会員の購入は出ない（U11-011）');
        }
    }

    public function testEmptyLibraryAndRequiresLogin(): void
    {
        self::assertRedirect($this->client->get('mypage.php'), 'login.php');
        self::assertRedirect($this->login(), 'mypage.php', 'ログイン後はマイページへ戻る');

        $page = $this->client->get('mypage.php');
        self::assertStringContainsString('購入したゲームはまだありません', $page->body);
        self::assertStringNotContainsString('download.php', $page->body);
    }

    // ---- ダウンロード ----

    public function testDownloadSendsZipOnlyForOwnPurchaseIncludingDiscontinued(): void
    {
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        $this->pdo->exec("UPDATE products SET file_path = 'downloads/g05.zip', public_flag = 0 WHERE product_id = 5");
        $this->pdo->exec("UPDATE products SET file_path = 'downloads/g02.zip' WHERE product_id = 2");
        $this->login();

        $res = $this->client->get('download.php?id=5');

        self::assertSame(200, $res->status, '販売終了でもダウンロードできる（U11-006）');
        self::assertSame('application/zip', $res->header('content-type'));
        self::assertStringContainsString('attachment; filename="g05.zip"', (string) $res->header('content-disposition'));
        self::assertStringStartsWith("PK\x03\x04", $res->body);
        self::assertStringContainsString('no-store', (string) $res->header('cache-control'));

        foreach (['download.php?id=2', 'download.php?id=999', 'download.php?id=abc', 'download.php'] as $path) {
            self::assertSame(404, $this->client->get($path)->status, $path . '：未購入・不正な ID');
        }
    }

    public function testDownloadRequiresLoginAndReturnsToMypage(): void
    {
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);

        self::assertRedirect($this->client->get('download.php?id=5'), 'login.php');
        self::assertRedirect($this->login(), 'mypage.php', 'ログイン直後にファイルを送らない（Q05）');
    }

    // ---- U12 ----

    public function testAccountEditShowsCurrentValuesAndSaves(): void
    {
        $this->login();
        $form = $this->client->get('account_edit.php');
        self::assertStringContainsString('value="b@example.com"', $form->body, '登録値を初期表示（Q18 a）');
        self::assertStringNotContainsString('name="birth_year"', $form->body);

        $res = $this->postForm('account_edit.php', ['email' => 'new@example.com', 'nickname' => '新ニック', 'phone' => '', 'current_password' => self::PASSWORD]);

        self::assertRedirect($res, 'mypage.php');
        self::assertStringContainsString('会員情報を変更しました', $this->client->get('mypage.php')->body, 'U11 のメッセージ欄（U12-002）');
        self::assertStringNotContainsString('会員情報を変更しました', $this->client->get('mypage.php')->body, '1 回だけ');
        self::assertSame('新ニック', $this->pdo->query("SELECT nickname FROM members WHERE member_id = {$this->memberId}")->fetchColumn());
    }

    public function testAccountEditErrorsKeepInputAndDoNotSave(): void
    {
        ShopFixtures::insertMember($this->pdo, 'a@example.com');
        $this->login();

        $wrong = $this->postForm('account_edit.php', ['email' => 'x@example.com', 'nickname' => '', 'phone' => '', 'current_password' => 'Wrong1234']);
        self::assertSame(200, $wrong->status);
        self::assertStringContainsString('現在のパスワードが正しくありません', $wrong->body);
        self::assertStringContainsString('value="x@example.com"', $wrong->body, '入力値を戻す');

        $taken = $this->postForm('account_edit.php', ['email' => 'a@example.com', 'nickname' => '<script>alert(1)</script>', 'phone' => '', 'current_password' => self::PASSWORD]);
        self::assertStringContainsString('ニックネームは2〜20文字で入力してください', $taken->body);
        self::assertStringNotContainsString('<script>alert(1)</script>', $taken->body, 'XSS（U12-013）');

        $dup = $this->postForm('account_edit.php', ['email' => 'a@example.com', 'nickname' => '', 'phone' => '', 'current_password' => self::PASSWORD]);
        self::assertStringContainsString('このメールアドレスは登録済みです', $dup->body);

        $empty = $this->postForm('account_edit.php', ['email' => 'x@example.com', 'nickname' => '', 'phone' => '', 'current_password' => '']);
        self::assertStringContainsString('現在のパスワードを入力してください', $empty->body);
        self::assertSame(self::EMAIL, $this->pdo->query("SELECT email FROM members WHERE member_id = {$this->memberId}")->fetchColumn());
    }

    public function testFifthWrongCurrentPasswordThrottlesButDoesNotLockLogin(): void
    {
        $this->login();
        for ($i = 0; $i < 5; $i++) {
            $res = $this->postForm('password_change.php', ['current_password' => 'Wrong1234', 'new_password' => 'NewPassw0rd2', 'new_password_confirm' => 'NewPassw0rd2']);
        }
        self::assertStringContainsString('しばらくしてからもう一度お試しください', $res->body);

        $blocked = $this->postForm('password_change.php', ['current_password' => self::PASSWORD, 'new_password' => 'NewPassw0rd2', 'new_password_confirm' => 'NewPassw0rd2']);
        self::assertStringContainsString('しばらくしてからもう一度お試しください', $blocked->body, '正しくても照合しない');
        self::assertSame(0, (int) $this->pdo->query("SELECT login_failure_count FROM members WHERE member_id = {$this->memberId}")->fetchColumn(), 'ログインの失敗回数には数えない');
        self::assertRedirect($this->login(client: new HttpClient(AppServer::baseUrl())), 'index.php', '別のブラウザではログインできる');
    }

    // ---- U13 ----

    public function testPasswordChange(): void
    {
        $this->login();
        $form = $this->client->get('password_change.php');
        self::assertStringContainsString('ほかのサービスとは異なるパスワード', $form->body);

        $mismatch = $this->postForm('password_change.php', ['current_password' => self::PASSWORD, 'new_password' => 'NewPassw0rd2', 'new_password_confirm' => 'NewPassw0rd3']);
        self::assertStringContainsString('パスワードが一致しません', $mismatch->body);

        $res = $this->postForm('password_change.php', ['current_password' => self::PASSWORD, 'new_password' => 'NewPassw0rd2', 'new_password_confirm' => 'NewPassw0rd2']);
        self::assertRedirect($res, 'mypage.php');
        self::assertStringContainsString('パスワードを変更しました', $this->client->get('mypage.php')->body);

        $fresh = new HttpClient(AppServer::baseUrl());
        self::assertSame(200, $this->login(self::EMAIL, self::PASSWORD, $fresh)->status, '旧パスワードは不可');
        self::assertRedirect($this->login(self::EMAIL, 'NewPassw0rd2', $fresh), 'index.php');
    }

    // ---- U16 ----

    public function testWithdrawRequiresAgreementThenLogsOutAndBlocksEverything(): void
    {
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::NEONSIGHT);
        $this->login();
        $other = new HttpClient(AppServer::baseUrl());
        $this->login(client: $other);

        $form = $this->client->get('withdraw.php');
        self::assertStringContainsString('name="agree" value="1"', $form->body);
        self::assertStringNotContainsString('checked', $form->body, '同意は初期未チェック（Q21）');

        $noAgree = $this->postForm('withdraw.php', ['current_password' => self::PASSWORD]);
        self::assertStringContainsString('注意事項に同意してください', $noAgree->body);

        $res = $this->postForm('withdraw.php', ['current_password' => self::PASSWORD, 'agree' => '1']);
        self::assertRedirect($res, 'index.php');
        self::assertStringContainsString('退会が完了しました', $this->client->get('index.php')->body);
        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'));

        self::assertRedirect($this->client->get('mypage.php'), 'login.php', '退会後は未ログイン（U16-010）');
        self::assertRedirect($other->get('download.php?id=5'), 'login.php', '他端末のセッションも無効（U16-005・013）');
        self::assertSame(200, $this->login()->status, '退会後はログインできない（U16-003）');
    }

    public function testChangesWithoutCsrfAreRejected(): void
    {
        $this->login();
        foreach (['account_edit.php', 'password_change.php', 'withdraw.php'] as $path) {
            self::assertSame(400, $this->client->post($path, ['current_password' => self::PASSWORD, 'agree' => '1'])->status, $path);
        }
        self::assertSame(0, (int) $this->pdo->query("SELECT withdrawal_flag FROM members WHERE member_id = {$this->memberId}")->fetchColumn());
    }
}
