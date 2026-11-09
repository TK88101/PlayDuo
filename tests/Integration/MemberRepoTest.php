<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/member_repo.php';

final class MemberRepoTest extends TestCase
{
    private const NOW = 1_794_000_000;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
    }

    /** @return array<string, mixed> */
    private static function memberData(string $email = 'a@example.com'): array
    {
        return [
            // ここではハッシュの中身を照合しないので bcrypt を計算しない（テストを速くする）
            'name' => '山田 花子', 'email' => $email, 'password_hash' => 'hash-old',
            'birth_date' => '2000-01-01', 'nickname' => null, 'phone' => null,
            'totp_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'totp_last_step' => 100,
        ];
    }

    /** @return list<string> */
    private static function codeHashes(int $count = 10): array
    {
        return array_map(static fn (int $i): string => "code-hash-{$i}", range(1, $count));
    }

    public function testCreateMemberWithCodesInsertsMemberAndTenCodes(): void
    {
        // Act
        $memberId = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(), self::NOW);

        // Assert
        $member = find_member_by_id($this->pdo, $memberId);
        self::assertSame('a@example.com', $member['email']);
        self::assertSame(100, (int) $member['totp_last_step']);
        self::assertCount(10, unused_recovery_codes($this->pdo, $memberId));
    }

    public function testDuplicateEmailThrowsAndLeavesNoOrphanCodes(): void
    {
        create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(), self::NOW);

        try {
            create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(), self::NOW);
            self::fail('重複メールで例外にならなかった');
        } catch (PDOException $e) {
            self::assertSame('23000', $e->getCode());
        }

        self::assertSame(10, (int) $this->pdo->query('SELECT COUNT(*) FROM recovery_codes')->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testEmailExists(): void
    {
        create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);

        self::assertTrue(email_exists($this->pdo, 'a@example.com'));
        self::assertFalse(email_exists($this->pdo, 'b@example.com'));
    }

    public function testConsumeTotpStepAllowsOnlyNewerSteps(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);

        self::assertFalse(consume_totp_step($this->pdo, $id, 100), '登録時に使った step は再利用できない');
        self::assertFalse(consume_totp_step($this->pdo, $id, 99));
        self::assertTrue(consume_totp_step($this->pdo, $id, 101));
        self::assertFalse(consume_totp_step($this->pdo, $id, 101));
    }

    public function testConsumeTotpStepFromSecondConnectionFailsAfterFirstCommitted(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);
        $other = TestDb::pdo();

        self::assertTrue(consume_totp_step($this->pdo, $id, 101));
        self::assertFalse(consume_totp_step($other, $id, 101));
    }

    public function testConsumeRecoveryCodeOnlyOnceEvenFromAnotherConnection(): void
    {
        // Arrange
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(2), self::NOW);
        $codeId = (int) unused_recovery_codes($this->pdo, $id)[0]['recovery_code_id'];
        $other = TestDb::pdo();

        // Act / Assert
        self::assertTrue(consume_recovery_code($this->pdo, $codeId, self::NOW));
        self::assertFalse(consume_recovery_code($this->pdo, $codeId, self::NOW));
        self::assertFalse(consume_recovery_code($other, $codeId, self::NOW));
        self::assertCount(1, unused_recovery_codes($this->pdo, $id));
    }

    public function testFindActiveMemberIgnoresWithdrawnMembers(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);
        $this->pdo->exec("UPDATE members SET withdrawal_flag = 1 WHERE member_id = {$id}");

        $this->pdo->beginTransaction();
        $found = find_active_member_by_email_for_update($this->pdo, 'a@example.com');
        $this->pdo->commit();

        self::assertNull($found);
        self::assertNull(find_member_by_id($this->pdo, $id));
    }

    public function testFindLoginMemberReturnsOnlyDisplayColumns(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);

        self::assertSame(['member_id', 'name', 'nickname'], array_keys(find_login_member($this->pdo, $id)));
        self::assertNull(find_login_member($this->pdo, $id + 1));
    }

    public function testRehashOnlyReplacesTheHashThatWasVerified(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);

        self::assertTrue(rehash_password_if_unchanged($this->pdo, $id, 'hash-old', 'hash-rehashed'));
        // 照合後に別の処理（パスワード再設定）で変わっていたら上書きしない
        update_password_hash($this->pdo, $id, 'hash-from-reset');
        self::assertFalse(rehash_password_if_unchanged($this->pdo, $id, 'hash-rehashed', 'hash-stale'));
        self::assertSame('hash-from-reset', find_member_by_id($this->pdo, $id)['password']);
    }

    public function testRecordFailureLocksOnFifthAndResetClears(): void
    {
        $id = create_member_with_codes($this->pdo, self::memberData(), self::codeHashes(1), self::NOW);

        for ($i = 1; $i <= 5; $i++) {
            $locked = record_failure($this->pdo, find_member_by_id($this->pdo, $id), self::NOW);
            self::assertSame($i === 5, $locked, "{$i} 回目");
        }
        $member = find_member_by_id($this->pdo, $id);
        self::assertSame(5, (int) $member['login_failure_count']);
        self::assertNotNull($member['locked_at']);

        reset_failures($this->pdo, $id);
        $member = find_member_by_id($this->pdo, $id);
        self::assertSame(0, (int) $member['login_failure_count']);
        self::assertNull($member['locked_at']);
    }
}
