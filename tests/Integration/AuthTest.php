<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/auth.php';

final class AuthTest extends TestCase
{
    private const NOW = 1_794_000_000;
    private const PASSWORD = 'abc12345';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
    }

    /** @return array{member_id: int, secret: string, codes: list<string>} */
    private function registerMember(string $email = 'a@example.com', int $now = self::NOW): array
    {
        $secret = totp_generate_secret();
        $result = register_member($this->pdo, [
            'input' => [
                'name' => '山田 花子', 'email' => $email, 'birth_date' => '2000-01-01', 'nickname' => null, 'phone' => null,
            ],
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'totp_secret'   => $secret,
        ], totp_code($secret, totp_step($now)), $now);
        self::assertSame('ok', $result['status']);

        return ['member_id' => $result['member_id'], 'secret' => $secret, 'codes' => $result['recovery_codes']];
    }

    /** @return array<string, mixed> */
    private function member(int $id): array
    {
        return $this->pdo->query("SELECT * FROM members WHERE member_id = {$id}")->fetch();
    }

    // ---- 会員登録（U17） ----

    public function testRegisterMemberStoresTenHashedCodesAndConsumedStep(): void
    {
        $m = $this->registerMember();

        self::assertCount(10, $m['codes']);
        self::assertSame(totp_step(self::NOW), (int) $this->member($m['member_id'])['totp_last_step']);
        $hashes = $this->pdo->query('SELECT code_hash FROM recovery_codes')->fetchAll(PDO::FETCH_COLUMN);
        self::assertNotContains($m['codes'][0], $hashes, '平文で保存しない');
        self::assertTrue(password_verify(normalize_recovery_code($m['codes'][0]), $hashes[0]));
    }

    public function testRegisterMemberRejectsWrongCodeWithoutInsert(): void
    {
        $secret = totp_generate_secret();
        $wrong = totp_code($secret, totp_step(self::NOW) + 5);

        $result = register_member($this->pdo, [
            'input' => ['name' => 'x', 'email' => 'a@example.com', 'birth_date' => '2000-01-01', 'nickname' => null, 'phone' => null],
            'password_hash' => 'h', 'totp_secret' => $secret,
        ], $wrong, self::NOW);

        self::assertSame('invalid_code', $result['status']);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM members')->fetchColumn());
    }

    public function testRegisterMemberReportsDuplicateEmail(): void
    {
        $this->registerMember();
        $secret = totp_generate_secret();

        $result = register_member($this->pdo, [
            'input' => ['name' => 'x', 'email' => 'a@example.com', 'birth_date' => '2000-01-01', 'nickname' => null, 'phone' => null],
            'password_hash' => 'h', 'totp_secret' => $secret,
        ], totp_code($secret, totp_step(self::NOW)), self::NOW);

        self::assertSame('duplicate', $result['status']);
    }

    // ---- ログイン（U06） ----

    public function testLoginSucceedsAndResetsFailureCount(): void
    {
        $m = $this->registerMember();
        attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);

        $result = attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW);

        self::assertSame(['status' => 'ok', 'member_id' => $m['member_id']], $result);
        self::assertSame(0, (int) $this->member($m['member_id'])['login_failure_count']);
    }

    public function testFifthFailureLocksAndCorrectPasswordIsRefusedWhileLocked(): void
    {
        $this->registerMember();

        for ($i = 1; $i <= 4; $i++) {
            self::assertSame('invalid', attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW)['status']);
        }
        self::assertSame('locked', attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW)['status']);

        self::assertSame('locked', attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW + 599)['status']);
        self::assertSame('ok', attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW + 600)['status']);
    }

    public function testFailureAfterLockExpiryStartsCountingAgain(): void
    {
        $m = $this->registerMember();
        for ($i = 1; $i <= 5; $i++) {
            attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);
        }

        $result = attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW + 600);

        self::assertSame('invalid', $result['status']);
        $member = $this->member($m['member_id']);
        self::assertSame(1, (int) $member['login_failure_count']);
        self::assertNull($member['locked_at']);
    }

    public function testUnknownAndWithdrawnEmailsGetSameInvalidResult(): void
    {
        $m = $this->registerMember();
        $this->pdo->exec("UPDATE members SET withdrawal_flag = 1 WHERE member_id = {$m['member_id']}");

        self::assertSame(['status' => 'invalid'], attempt_login($this->pdo, 'nobody@example.com', self::PASSWORD, self::NOW));
        self::assertSame(['status' => 'invalid'], attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW));
    }

    public function testLoginRehashesOutdatedHash(): void
    {
        $m = $this->registerMember();
        $weak = password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]);
        $stmt = $this->pdo->prepare('UPDATE members SET password = ? WHERE member_id = ?');
        $stmt->execute([$weak, $m['member_id']]);

        attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW);

        $stored = $this->member($m['member_id'])['password'];
        self::assertNotSame($weak, $stored);
        self::assertFalse(password_needs_rehash($stored, PASSWORD_DEFAULT));
    }

    // ---- パスワード再設定 本人確認（U14） ----

    public function testIdentityByTotpSucceedsOnceThenSameCodeIsRejected(): void
    {
        $m = $this->registerMember();
        $later = self::NOW + 90;
        $code = totp_code($m['secret'], totp_step($later));

        $first = verify_reset_identity($this->pdo, 'a@example.com', $code, $later);
        $second = verify_reset_identity($this->pdo, 'a@example.com', $code, $later);

        self::assertSame('ok', $first['status']);
        self::assertSame($m['member_id'], $first['member_id']);
        self::assertSame(hash('sha256', $this->member($m['member_id'])['password']), $first['password_fp']);
        self::assertSame('invalid', $second['status']);
    }

    public function testIdentityRejectsRegistrationCodeReuse(): void
    {
        $m = $this->registerMember();
        $registrationCode = totp_code($m['secret'], totp_step(self::NOW));

        self::assertSame('invalid', verify_reset_identity($this->pdo, 'a@example.com', $registrationCode, self::NOW)['status']);
    }

    public function testIdentityByRecoveryCodeWorksOnceAndAcceptsLooseFormat(): void
    {
        $m = $this->registerMember();
        $loose = strtolower(str_replace('-', '', $m['codes'][3]));

        self::assertSame('ok', verify_reset_identity($this->pdo, 'a@example.com', $loose, self::NOW)['status']);
        self::assertSame('invalid', verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][3], self::NOW)['status']);
        self::assertSame(9, (int) $this->pdo->query('SELECT COUNT(*) FROM recovery_codes WHERE used_at IS NULL')->fetchColumn());
    }

    public function testIdentitySuccessDoesNotResetFailureCounter(): void
    {
        $m = $this->registerMember();
        attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);

        verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], self::NOW);

        self::assertSame(1, (int) $this->member($m['member_id'])['login_failure_count']);
    }

    public function testLoginAndResetShareOneFailureCounter(): void
    {
        $m = $this->registerMember();
        for ($i = 1; $i <= 3; $i++) {
            attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);
        }
        self::assertSame('invalid', verify_reset_identity($this->pdo, 'a@example.com', '000000', self::NOW)['status']);

        self::assertSame('locked', verify_reset_identity($this->pdo, 'a@example.com', 'garbage', self::NOW)['status']);
        self::assertSame('locked', verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], self::NOW)['status']);
        self::assertSame('locked', attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW)['status']);
    }

    // ロック中は正しい 6 桁・復旧コードでも照合しない（総当たりの抜け道を作らない）。通らなかったコードは消費もしない
    public function testLockedIdentityRefusesCorrectCodesWithoutConsumingThem(): void
    {
        $m = $this->registerMember();
        for ($i = 1; $i <= 5; $i++) {
            attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);
        }
        $later = self::NOW + 90;
        $totp = totp_code($m['secret'], totp_step($later));

        self::assertSame('locked', verify_reset_identity($this->pdo, 'a@example.com', $totp, $later)['status']);
        self::assertSame('locked', verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], $later)['status']);

        self::assertSame(totp_step(self::NOW), (int) $this->member($m['member_id'])['totp_last_step']);
        self::assertSame(10, (int) $this->pdo->query('SELECT COUNT(*) FROM recovery_codes WHERE used_at IS NULL')->fetchColumn());
    }

    public function testIdentityLockEndsExactlyAfterTenMinutes(): void
    {
        $m = $this->registerMember();
        for ($i = 1; $i <= 5; $i++) {
            attempt_login($this->pdo, 'a@example.com', 'wrong1234', self::NOW);
        }

        self::assertSame('locked', verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], self::NOW + 599)['status']);
        self::assertSame('ok', verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], self::NOW + 600)['status']);
    }

    public function testIdentityForUnknownEmailIsInvalid(): void
    {
        self::assertSame(['status' => 'invalid'], verify_reset_identity($this->pdo, 'x@example.com', '123456', self::NOW));
    }

    // ---- 新パスワード（U15） ----

    /** @return array{member_id: int, issued_at: int, password_fp: string} */
    private function resetAuth(array $m, int $issuedAt = self::NOW): array
    {
        $r = verify_reset_identity($this->pdo, 'a@example.com', $m['codes'][0], $issuedAt);
        return ['member_id' => $r['member_id'], 'issued_at' => $issuedAt, 'password_fp' => $r['password_fp']];
    }

    public function testCompleteResetUpdatesPasswordWithinTtl(): void
    {
        $m = $this->registerMember();
        $auth = $this->resetAuth($m);

        $status = complete_password_reset($this->pdo, $auth, 'newpass99', self::NOW + 599);

        self::assertSame('ok', $status);
        self::assertSame('ok', attempt_login($this->pdo, 'a@example.com', 'newpass99', self::NOW + 600)['status']);
    }

    public function testCompleteResetExpiresAtTtl(): void
    {
        $m = $this->registerMember();

        self::assertSame('expired', complete_password_reset($this->pdo, $this->resetAuth($m), 'newpass99', self::NOW + 600));
        self::assertSame('expired', complete_password_reset($this->pdo, null, 'newpass99', self::NOW));
    }

    public function testCompleteResetIsStaleWhenPasswordChangedAfterIdentity(): void
    {
        $m = $this->registerMember();
        $auth = $this->resetAuth($m);
        $stmt = $this->pdo->prepare('UPDATE members SET password = ? WHERE member_id = ?');
        $stmt->execute([password_hash('other999', PASSWORD_DEFAULT), $m['member_id']]);

        self::assertSame('stale', complete_password_reset($this->pdo, $auth, 'newpass99', self::NOW + 10));
    }

    // U14 を通ったあとでロックされても、有効時間内なら U15 は完了できる。ただしロックは解除しない（設計書 U15 手順 8）
    public function testResetIssuedBeforeLockCompletesButLeavesLockInPlace(): void
    {
        $m = $this->registerMember();
        $auth = $this->resetAuth($m);
        $lockedAt = self::NOW + 10;
        for ($i = 1; $i <= 5; $i++) {
            attempt_login($this->pdo, 'a@example.com', 'wrong1234', $lockedAt);
        }

        self::assertSame('ok', complete_password_reset($this->pdo, $auth, 'newpass99', self::NOW + 20));

        $member = $this->member($m['member_id']);
        self::assertSame(5, (int) $member['login_failure_count']);
        self::assertNotNull($member['locked_at']);
        self::assertSame('locked', attempt_login($this->pdo, 'a@example.com', 'newpass99', $lockedAt + 599)['status']);
        self::assertSame('ok', attempt_login($this->pdo, 'a@example.com', 'newpass99', $lockedAt + 600)['status']);
    }

    public function testCompleteResetIsStaleForWithdrawnMember(): void
    {
        $m = $this->registerMember();
        $auth = $this->resetAuth($m);
        $this->pdo->exec("UPDATE members SET withdrawal_flag = 1 WHERE member_id = {$m['member_id']}");

        self::assertSame('stale', complete_password_reset($this->pdo, $auth, 'newpass99', self::NOW + 10));
    }

    public function testAuthAttemptRollsBackAndRethrowsOnUnexpectedError(): void
    {
        $this->registerMember();

        try {
            run_auth_attempt($this->pdo, 'a@example.com', self::NOW, static function (): bool {
                throw new \RuntimeException('boom');
            }, true);
            self::fail('例外が伝わらなかった');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertFalse($this->pdo->inTransaction());
    }

    public function testResetAuthValidity(): void
    {
        $auth = ['member_id' => 1, 'issued_at' => self::NOW, 'password_fp' => 'x'];

        self::assertTrue(reset_auth_is_valid($auth, self::NOW + 599));
        self::assertFalse(reset_auth_is_valid($auth, self::NOW + 600));
        self::assertFalse(reset_auth_is_valid(['member_id' => '1', 'issued_at' => self::NOW, 'password_fp' => 'x'], self::NOW));
        self::assertFalse(reset_auth_is_valid(null, self::NOW));
    }
}
