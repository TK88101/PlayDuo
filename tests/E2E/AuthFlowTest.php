<?php

declare(strict_types=1);

namespace PlayDuo\Tests\E2E;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\AppServer;
use PlayDuo\Tests\Support\HttpClient;
use PlayDuo\Tests\Support\HttpResponse;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/totp.php';

/**
 * ブラウザなしの HTTP シナリオテスト（php -S ＋ playduo_test）。
 * 対応表どおりの name で POST し、status・Location・Set-Cookie・DB の最終状態まで確かめる。
 */
final class AuthFlowTest extends TestCase
{
    private const EMAIL = 'hanako@example.com';
    private const PASSWORD = 'abc12345';

    private PDO $pdo;
    private HttpClient $client;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        $this->client = new HttpClient(AppServer::baseUrl());
    }

    private function memberCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM members')->fetchColumn();
    }

    private static function assertRedirect(HttpResponse $res, string $location): void
    {
        self::assertSame(303, $res->status, $res->body);
        self::assertSame($location, $res->header('location'));
    }

    /** U04 → U05 → U17 を通し、U17 の POST 応答を返す */
    private function registerUntilTotpPost(HttpClient $client, string $email = self::EMAIL): HttpResponse
    {
        $token = $client->get('register.php')->csrfToken();
        $res = $client->post('register.php', [
            'csrf_token' => $token, 'name' => '山田 花子', 'email' => $email,
            'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'birth_year' => '2000', 'birth_month' => '1', 'birth_day' => '15', 'nickname' => '', 'phone' => '090-1234-5678',
        ]);
        self::assertRedirect($res, 'register_confirm.php');

        $confirm = $client->get('register_confirm.php');
        self::assertStringContainsString('山田 花子', $confirm->body);
        self::assertRedirect(
            $client->post('register_confirm.php', ['csrf_token' => $token, 'action' => 'register']),
            'register_totp.php',
        );

        $totpPage = $client->get('register_totp.php');
        self::assertSame(1, preg_match('/id="totp_secret">([A-Z2-7]{32})</', $totpPage->body, $m));
        self::assertStringContainsString('data-uri="otpauth://totp/', $totpPage->body);

        return $client->post('register_totp.php', [
            'csrf_token' => $token, 'totp_code' => totp_code($m[1], totp_step(time())),
        ]);
    }

    /** @return list<string> 復旧コード */
    private function registerMember(): array
    {
        self::assertRedirect($this->registerUntilTotpPost($this->client), 'register_recovery.php');
        $page = $this->client->get('register_recovery.php');
        preg_match_all('/class="recovery-code">([A-Z2-9]{5}-[A-Z2-9]{5})</', $page->body, $m);
        return $m[1];
    }

    private function login(HttpClient $client, string $password): HttpResponse
    {
        $token = $client->get('login.php')->csrfToken();
        return $client->post('login.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'password' => $password]);
    }

    // ---- 会員登録 ----

    public function testRegistrationShowsTenRecoveryCodesOnlyOnce(): void
    {
        self::assertRedirect($this->registerUntilTotpPost($this->client), 'register_recovery.php');

        $page = $this->client->get('register_recovery.php');
        self::assertSame(200, $page->status);
        self::assertSame(10, preg_match_all('/class="recovery-code">/', $page->body));
        self::assertStringContainsString('no-store', (string) $page->header('cache-control'));
        self::assertStringContainsString('name="csrf_token"', $page->body, 'U18 の「次へ」は POST form');

        self::assertRedirect($this->client->get('register_recovery.php'), 'login.php');
        self::assertSame(1, $this->memberCount());
    }

    public function testValidationErrorKeepsInputExceptPasswords(): void
    {
        $token = $this->client->get('register.php')->csrfToken();
        $res = $this->client->post('register.php', [
            'csrf_token' => $token, 'name' => '山田', 'email' => 'bad', 'password' => 'abc12345', 'password_confirm' => 'x',
            'birth_year' => '2000', 'birth_month' => '1', 'birth_day' => '1', 'nickname' => 'デュオ', 'phone' => '',
        ]);

        self::assertSame(200, $res->status);
        self::assertStringContainsString('メールアドレスの形式が正しくありません', $res->body);
        self::assertStringContainsString('value="デュオ"', $res->body);
        self::assertStringNotContainsString('value="abc12345"', $res->body);
    }

    public function testDuplicateEmailIsRejectedOnU04(): void
    {
        $this->registerMember();
        $other = new HttpClient(AppServer::baseUrl());
        $token = $other->get('register.php')->csrfToken();

        $res = $other->post('register.php', [
            'csrf_token' => $token, 'name' => '別人', 'email' => self::EMAIL, 'password' => 'xyz98765', 'password_confirm' => 'xyz98765',
            'birth_year' => '1999', 'birth_month' => '3', 'birth_day' => '3', 'nickname' => '', 'phone' => '',
        ]);

        self::assertSame(200, $res->status);
        self::assertStringContainsString('このメールアドレスは登録済みです', $res->body);
    }

    public function testDoubleSubmitOnU17DoesNotCreateSecondMember(): void
    {
        self::assertRedirect($this->registerUntilTotpPost($this->client), 'register_recovery.php');
        $token = $this->client->get('login.php')->csrfToken();

        $again = $this->client->post('register_totp.php', ['csrf_token' => $token, 'totp_code' => '000000']);

        self::assertRedirect($again, 'register.php');
        self::assertSame(1, $this->memberCount());
    }

    public function testBackFromConfirmRestoresInput(): void
    {
        $token = $this->client->get('register.php')->csrfToken();
        $this->client->post('register.php', [
            'csrf_token' => $token, 'name' => '山田 花子', 'email' => self::EMAIL,
            'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD,
            'birth_year' => '2000', 'birth_month' => '1', 'birth_day' => '15', 'nickname' => '', 'phone' => '',
        ]);

        self::assertRedirect($this->client->post('register_confirm.php', ['csrf_token' => $token, 'action' => 'back']), 'register.php');
        $form = $this->client->get('register.php');
        self::assertStringContainsString('value="hanako@example.com"', $form->body);
    }

    // ---- ログイン・ログアウト ----

    public function testLoginRegeneratesSessionAndSetsSecureCookieFlags(): void
    {
        $this->registerMember();
        $before = $this->client->cookies()['PHPSESSID'] ?? '';

        $res = $this->login($this->client, self::PASSWORD);

        self::assertRedirect($res, 'index.php');
        $after = $this->client->cookies()['PHPSESSID'] ?? '';
        self::assertNotSame($before, $after);
        $setCookie = implode("\n", $res->headers('set-cookie'));
        self::assertStringContainsStringIgnoringCase('HttpOnly', $setCookie);
        self::assertStringContainsStringIgnoringCase('SameSite=Lax', $setCookie);
    }

    public function testFiveFailuresShowLockAndCorrectPasswordStaysLocked(): void
    {
        $this->registerMember();

        for ($i = 1; $i <= 4; $i++) {
            $res = $this->login($this->client, 'wrong1234');
            self::assertStringContainsString('メールアドレスまたはパスワードが違います', $res->body);
            self::assertStringNotContainsString('class="locked"', $res->body);
        }
        self::assertStringContainsString('class="locked"', $this->login($this->client, 'wrong1234')->body);

        $res = $this->login($this->client, self::PASSWORD);
        self::assertSame(200, $res->status);
        self::assertStringContainsString('class="locked"', $res->body);
    }

    public function testLogoutRequiresPostAndEndsSession(): void
    {
        $this->registerMember();
        $this->login($this->client, self::PASSWORD);
        $oldCookies = $this->client->cookies();
        $loggedIn = $this->client->get('login.php');
        self::assertStringContainsString('action="logout.php"', $loggedIn->body);

        self::assertSame(405, $this->client->get('logout.php')->status);
        self::assertRedirect($this->client->post('logout.php', ['csrf_token' => $loggedIn->csrfToken()]), 'login.php');

        $replay = new HttpClient(AppServer::baseUrl());
        $replay->setCookies($oldCookies);
        self::assertStringNotContainsString('action="logout.php"', $replay->get('login.php')->body);
    }

    // ---- CSRF ----

    public function testPostWithoutOrWithWrongCsrfIsRejected(): void
    {
        $this->client->get('login.php');

        self::assertSame(400, $this->client->post('login.php', ['email' => self::EMAIL, 'password' => 'x'])->status);
        self::assertSame(400, $this->client->post('login.php', [
            'csrf_token' => str_repeat('0', 64), 'email' => self::EMAIL, 'password' => 'x',
        ])->status);
        self::assertSame(400, $this->client->post('register.php', ['name' => 'x'])->status);
    }

    // ---- パスワード再設定 ----

    public function testResetWithRecoveryCodeThenLoginWithNewPassword(): void
    {
        $codes = $this->registerMember();
        $reset = new HttpClient(AppServer::baseUrl());
        $token = $reset->get('password_reset.php')->csrfToken();

        $res = $reset->post('password_reset.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'code' => $codes[0]]);
        self::assertRedirect($res, 'password_reset_new.php');

        $form = $reset->get('password_reset_new.php');
        self::assertSame(200, $form->status);
        $done = $reset->post('password_reset_new.php', [
            'csrf_token' => $form->csrfToken(), 'new_password' => 'newpass99', 'new_password_confirm' => 'newpass99',
        ]);
        self::assertRedirect($done, 'login.php');
        self::assertStringContainsString('パスワードを再設定しました', $reset->get('login.php')->body);

        self::assertRedirect($this->login(new HttpClient(AppServer::baseUrl()), 'newpass99'), 'index.php');
    }

    public function testRecoveryCodeCannotBeUsedTwice(): void
    {
        $codes = $this->registerMember();
        $first = new HttpClient(AppServer::baseUrl());
        $token = $first->get('password_reset.php')->csrfToken();
        $first->post('password_reset.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'code' => $codes[1]]);

        $second = new HttpClient(AppServer::baseUrl());
        $token = $second->get('password_reset.php')->csrfToken();
        $res = $second->post('password_reset.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'code' => $codes[1]]);

        self::assertSame(200, $res->status);
        self::assertStringContainsString('メールアドレスまたはコードが違います', $res->body);
    }

    public function testNewPasswordPageWithoutIdentityRedirectsToU14(): void
    {
        self::assertRedirect($this->client->get('password_reset_new.php'), 'password_reset.php');
    }

    public function testNewPasswordValidationErrorKeepsAuthorizationAndDoubleSubmitIsRejected(): void
    {
        $codes = $this->registerMember();
        $token = $this->client->get('password_reset.php')->csrfToken();
        $this->client->post('password_reset.php', ['csrf_token' => $token, 'email' => self::EMAIL, 'code' => $codes[2]]);
        $token = $this->client->get('password_reset_new.php')->csrfToken();

        $bad = $this->client->post('password_reset_new.php', [
            'csrf_token' => $token, 'new_password' => 'short', 'new_password_confirm' => 'short',
        ]);
        self::assertSame(200, $bad->status);
        self::assertStringContainsString('半角英字・数字をそれぞれ含む', $bad->body);

        $fields = ['csrf_token' => $token, 'new_password' => 'newpass99', 'new_password_confirm' => 'newpass99'];
        self::assertRedirect($this->client->post('password_reset_new.php', $fields), 'login.php');
        self::assertRedirect($this->client->post('password_reset_new.php', $fields), 'password_reset.php');
    }
}
