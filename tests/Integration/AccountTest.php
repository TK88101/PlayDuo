<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/account.php';
require_once __DIR__ . '/../../includes/auth.php';

/** U12 会員情報の変更・U13 パスワードの変更・U16 退会（Plan 2026-11-26 §2.4〜§2.6） */
final class AccountTest extends TestCase
{
    private const PASSWORD = 'abc12345';
    private const NOW = 1_795_600_000;   // 2026-11-26 頃

    private PDO $pdo;
    private int $memberId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->memberId = ShopFixtures::insertMember($this->pdo, 'a@example.com');
    }

    /** @return array<string, mixed> */
    private function member(int $id = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM members WHERE member_id = ?');
        $stmt->execute([$id ?: $this->memberId]);
        return $stmt->fetch();
    }

    /** @param array<string, string|null> $input */
    private function update(array $input, string $password = self::PASSWORD): string
    {
        return update_account($this->pdo, $this->memberId, $input + ['email' => 'a@example.com', 'nickname' => null, 'phone' => null], $password);
    }

    // ---- U12 ----

    public function testUpdateAccountSavesNicknameEmailPhone(): void
    {
        self::assertSame('ok', $this->update(['email' => 'new@example.com', 'nickname' => '新ニック', 'phone' => '090-9999-0000']));

        $m = $this->member();
        self::assertSame(['new@example.com', '新ニック', '090-9999-0000'], [$m['email'], $m['nickname'], $m['phone']]);
        self::assertSame('テスト会員', $m['name'], '氏名は変わらない');
        self::assertSame('2000-01-01', $m['birth_date'], '生年月日は変わらない（U12-014）');
    }

    public function testBlankOptionalFieldsBecomeNullAndNoChangeIsOk(): void
    {
        $this->update(['nickname' => 'ニック', 'phone' => '09012345678']);

        self::assertSame('ok', $this->update(['nickname' => null, 'phone' => null]));
        self::assertNull($this->member()['nickname']);
        self::assertNull($this->member()['phone']);
        self::assertSame('ok', $this->update([]), '変更なしでも保存できる（Q18 b）');
    }

    public function testWrongPasswordDoesNotSave(): void
    {
        self::assertSame('wrong_password', $this->update(['nickname' => '変わらない'], 'Wrong1234'));
        self::assertNull($this->member()['nickname']);
    }

    public function testEmailUsedByOtherMemberIncludingWithdrawnIsTaken(): void
    {
        $other = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        self::assertSame('email_taken', $this->update(['email' => 'b@example.com']));

        $this->pdo->prepare('UPDATE members SET withdrawal_flag = 1 WHERE member_id = ?')->execute([$other]);
        self::assertSame('email_taken', $this->update(['email' => 'b@example.com']), '退会済みのメールも使えない（UNIQUE）');
        self::assertSame('a@example.com', $this->member()['email']);
    }

    public function testUniqueViolationRaceIsReportedAsTaken(): void
    {
        // 確認の後で別の会員が同じメールに変えた場合：UNIQUE 違反を email_taken に読み替える
        $this->pdo->exec("CREATE TRIGGER test_steal_email BEFORE UPDATE ON members FOR EACH ROW
            BEGIN IF NEW.email = 'race@example.com' THEN
              SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT = 'Duplicate entry', MYSQL_ERRNO = 1062; END IF; END");
        try {
            self::assertSame('email_taken', $this->update(['email' => 'race@example.com']));
        } finally {
            $this->pdo->exec('DROP TRIGGER IF EXISTS test_steal_email');
        }
    }

    public function testWithdrawnMemberGetsInactive(): void
    {
        $this->pdo->prepare('UPDATE members SET withdrawal_flag = 1 WHERE member_id = ?')->execute([$this->memberId]);

        self::assertSame('inactive', $this->update(['nickname' => 'x']));
        self::assertSame('inactive', change_password($this->pdo, $this->memberId, self::PASSWORD, 'NewPassw0rd2'));
    }

    // ---- U13 ----

    public function testChangePassword(): void
    {
        self::assertSame('wrong_password', change_password($this->pdo, $this->memberId, 'Wrong1234', 'NewPassw0rd2'));
        self::assertTrue(password_verify(self::PASSWORD, $this->member()['password']));

        self::assertSame('ok', change_password($this->pdo, $this->memberId, self::PASSWORD, 'NewPassw0rd2'));
        self::assertTrue(password_verify('NewPassw0rd2', $this->member()['password']));
        self::assertFalse(password_verify(self::PASSWORD, $this->member()['password']));
        self::assertSame('ok', attempt_login($this->pdo, 'a@example.com', 'NewPassw0rd2', self::NOW)['status']);
    }

    public function testSamePasswordIsAllowedWithNewHash(): void
    {
        $before = $this->member()['password'];

        self::assertSame('ok', change_password($this->pdo, $this->memberId, self::PASSWORD, self::PASSWORD), 'Q19：許可');
        self::assertNotSame($before, $this->member()['password']);
    }

    // ---- U16 ----

    public function testWithdrawMarksFlagDeletesCartAndBlocksLogin(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        $other = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        ShopFixtures::addToCart($this->pdo, $other, ShopFixtures::BLOCKFIELD);

        self::assertSame('wrong_password', withdraw_member($this->pdo, $this->memberId, 'Wrong1234'));
        self::assertSame(0, (int) $this->member()['withdrawal_flag']);

        self::assertSame('ok', withdraw_member($this->pdo, $this->memberId, self::PASSWORD));
        self::assertSame(1, (int) $this->member()['withdrawal_flag']);
        self::assertSame(1, ShopFixtures::count($this->pdo, 'carts'), '本人のカートだけ消える');
        self::assertSame('invalid', attempt_login($this->pdo, 'a@example.com', self::PASSWORD, self::NOW)['status']);
        self::assertSame('already', withdraw_member($this->pdo, $this->memberId, self::PASSWORD), '2 回目は何もしない');
    }

    public function testWithdrawWaitsWhileMemberRowIsLocked(): void
    {
        // 排他の前提（Plan §2.6）：別の処理が会員行をロック中は、退会もパスワード変更も待たされる
        $holder = TestDb::pdo();
        $holder->beginTransaction();
        $holder->prepare('SELECT * FROM members WHERE member_id = ? FOR UPDATE')->execute([$this->memberId]);

        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            foreach ([
                static fn (PDO $pdo, int $id) => withdraw_member($pdo, $id, self::PASSWORD),
                static fn (PDO $pdo, int $id) => change_password($pdo, $id, self::PASSWORD, 'NewPassw0rd2'),
            ] as $call) {
                try {
                    $call($this->pdo, $this->memberId);
                    self::fail('ロック待ちでタイムアウトすること');
                } catch (PDOException $e) {
                    self::assertSame(1205, $e->errorInfo[1] ?? null, 'Lock wait timeout');
                }
            }
        } finally {
            $holder->rollBack();
        }
        self::assertFalse($this->pdo->inTransaction(), '失敗時はロールバック済み');
    }
}
