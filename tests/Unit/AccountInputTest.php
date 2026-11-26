<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/account.php';

/** U12 の入力チェックと、現在パスワード確認のセッション内スロットル（Plan 2026-11-26 §2.0 Q18 d） */
final class AccountInputTest extends TestCase
{
    private const NOW = 1_795_600_000;

    public function testValidInputIsTrimmedAndBlankOptionalsAreNull(): void
    {
        $result = validate_account_input(['email' => ' a@example.com ', 'nickname' => '', 'phone' => '', 'name' => '無視', 'birth_date' => '1990-01-01']);

        self::assertSame([], $result['errors']);
        self::assertSame(['email' => 'a@example.com', 'nickname' => null, 'phone' => null], $result['input'], '氏名・生年月日は受け取らない');
    }

    public function testFieldErrorsAreTheSameRulesAsRegistration(): void
    {
        $errors = validate_account_input(['email' => 'abc@', 'nickname' => 'あ', 'phone' => 'abc-defg-hijk'])['errors'];

        self::assertSame('メールアドレスの形式が正しくありません', $errors['email']);
        self::assertSame('ニックネームは2〜20文字で入力してください', $errors['nickname']);
        self::assertSame('電話番号は 090-1234-5678 の形で入力してください', $errors['phone']);
        self::assertSame('メールアドレスを入力してください', validate_account_input([])['errors']['email']);
    }

    public function testFifthFailureThrottlesForTenMinutes(): void
    {
        $session = [];
        for ($i = 0; $i < 4; $i++) {
            record_password_check_failure($session, self::NOW);
            self::assertFalse(password_check_throttled($session, self::NOW));
        }
        record_password_check_failure($session, self::NOW);

        self::assertTrue(password_check_throttled($session, self::NOW));
        self::assertTrue(password_check_throttled($session, self::NOW + 599));
        self::assertFalse(password_check_throttled($session, self::NOW + 600));
    }

    public function testSuccessClearsTheCount(): void
    {
        $session = [];
        for ($i = 0; $i < 4; $i++) {
            record_password_check_failure($session, self::NOW);
        }
        clear_password_check_failures($session);
        record_password_check_failure($session, self::NOW);

        self::assertFalse(password_check_throttled($session, self::NOW));
    }
}
