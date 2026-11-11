<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/validation.php';

final class ValidationTest extends TestCase
{
    private const NOW = 1_794_000_000; // 2026-11 上旬

    /** @return array<string, string> */
    private static function validPost(): array
    {
        return [
            'name' => ' 山田 花子 ', 'email' => 'hanako@example.com',
            'password' => 'abc12345', 'password_confirm' => 'abc12345',
            'birth_year' => '2000', 'birth_month' => '2', 'birth_day' => '29',
            'nickname' => '', 'phone' => '',
        ];
    }

    public function testValidInputIsNormalized(): void
    {
        $result = validate_register_input(self::validPost(), self::NOW);

        self::assertSame([], $result['errors']);
        self::assertSame([
            'name' => '山田 花子', 'email' => 'hanako@example.com', 'birth_date' => '2000-02-29',
            'nickname' => null, 'phone' => null,
        ], $result['input']);
    }

    public function testRequiredFieldsAreReported(): void
    {
        $result = validate_register_input([], self::NOW);

        foreach (['name', 'email', 'password', 'password_confirm', 'birth_date'] as $field) {
            self::assertArrayHasKey($field, $result['errors'], $field);
        }
        self::assertArrayNotHasKey('nickname', $result['errors']);
        self::assertArrayNotHasKey('phone', $result['errors']);
    }

    public function testNonStringValuesAreTreatedAsEmpty(): void
    {
        $post = self::validPost();
        $post['name'] = ['x'];

        self::assertArrayHasKey('name', validate_register_input($post, self::NOW)['errors']);
    }

    /** @return array<string, array{string, string}> */
    public static function invalidFields(): array
    {
        return [
            'name too long'      => ['name', str_repeat('あ', 51)],
            'email format'       => ['email', 'not-an-email'],
            'email too long'     => ['email', str_repeat('a', 250) . '@example.com'],
            'nickname too short' => ['nickname', 'あ'],
            'nickname too long'  => ['nickname', str_repeat('あ', 21)],
            'phone short'        => ['phone', '090-1234-567'],
            'phone landline'     => ['phone', '03-1234-5678'],
            'phone letters'      => ['phone', '090-abcd-5678'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function testInvalidFieldIsReported(string $field, string $value): void
    {
        $post = self::validPost();
        $post[$field] = $value;

        self::assertArrayHasKey($field, validate_register_input($post, self::NOW)['errors']);
    }

    /** @return array<string, array{string}> */
    public static function validPhones(): array
    {
        return ['hyphen' => ['090-1234-5678'], 'no hyphen' => ['09012345678']];
    }

    #[DataProvider('validPhones')]
    public function testMobilePhoneWithOrWithoutHyphensIsAccepted(string $phone): void
    {
        $post = self::validPost();
        $post['phone'] = $phone;

        $result = validate_register_input($post, self::NOW);

        self::assertSame([], $result['errors']);
        self::assertSame($phone, $result['input']['phone']);
    }

    public function testNicknameBoundariesAreAccepted(): void
    {
        foreach (['あい', str_repeat('あ', 20)] as $nickname) {
            $post = self::validPost();
            $post['nickname'] = $nickname;
            self::assertSame([], validate_register_input($post, self::NOW)['errors'], $nickname);
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidBirthDates(): array
    {
        return [
            'not exist'   => ['2001', '2', '29'],
            'future'      => ['2030', '1', '1'],
            'before 1900' => ['1899', '12', '31'],
            'not number'  => ['20x0', '1', '1'],
        ];
    }

    #[DataProvider('invalidBirthDates')]
    public function testInvalidBirthDateIsReported(string $year, string $month, string $day): void
    {
        $post = array_merge(self::validPost(), ['birth_year' => $year, 'birth_month' => $month, 'birth_day' => $day]);

        self::assertArrayHasKey('birth_date', validate_register_input($post, self::NOW)['errors']);
    }

    /** @return array<string, array{string}> */
    public static function invalidPasswords(): array
    {
        return [
            'too short'       => ['abc1234'],
            'letters only'    => ['abcdefgh'],
            'digits only'     => ['12345678'],
            'full-width'      => ['ａｂｃ１２３４５'],
            'space inside'    => ['abc 12345'],
            'over 72'         => [str_repeat('a1', 37)],
            'exactly 73'      => [str_repeat('a1', 36) . 'a'],
        ];
    }

    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordIsReported(string $password): void
    {
        $errors = validate_new_password($password, $password, 'new_password', 'new_password_confirm');

        self::assertArrayHasKey('new_password', $errors);
    }

    public function testPasswordBoundariesAreAccepted(): void
    {
        self::assertSame([], validate_new_password('abcd1234', 'abcd1234', 'new_password', 'new_password_confirm'));
        $max = str_repeat('a1', 36);
        self::assertSame([], validate_new_password($max, $max, 'new_password', 'new_password_confirm'));
    }

    // 画面設計書の旧記載（20 文字・半角英数のみ）より長い・記号入りのパスワードも受け付ける
    public function testPasswordOverTwentyCharactersWithSymbolsIsAccepted(): void
    {
        $password = 'Abc123!@#$%^&*()_+-=x';

        self::assertSame(21, strlen($password));
        self::assertSame([], validate_new_password($password, $password, 'new_password', 'new_password_confirm'));
    }

    // 形式として正しいメールアドレスを指定の長さで作る（ローカル部 64 文字、ドメインの各ラベルは 63 文字以内）
    private static function emailOfLength(int $length): string
    {
        $suffix = '.example.com';
        $remaining = $length - 64 - 1 - strlen($suffix);
        $labels = [];
        while ($remaining > 0) {
            $size = min(63, $remaining);
            if ($remaining - $size === 1) {
                $size--;
            }
            $labels[] = str_repeat('b', $size);
            $remaining -= $size + 1;
        }

        return str_repeat('a', 64) . '@' . implode('.', $labels) . $suffix;
    }

    /** @return array<string, array{int, bool}> */
    public static function emailLengths(): array
    {
        return [
            'over old design limit (101)' => [101, true],
            'longest accepted (254)'      => [254, true],
            'one over (255)'              => [255, false],
            'over column size (256)'      => [256, false],
        ];
    }

    // 上限は 254 文字。EMAIL_MAX_LENGTH は 255 だが、FILTER_VALIDATE_EMAIL が 255 文字以上を通さない
    #[DataProvider('emailLengths')]
    public function testEmailLengthBoundary(int $length, bool $accepted): void
    {
        $post = self::validPost();
        $post['email'] = self::emailOfLength($length);

        self::assertSame($length, strlen($post['email']));
        self::assertSame($accepted, !isset(validate_register_input($post, self::NOW)['errors']['email']));
    }

    public function testRegisterOldValuesKeepsInputButNeverPasswords(): void
    {
        $old = register_old_values(self::validPost() + ['extra' => 'x']);

        self::assertSame(['name', 'email', 'birth_year', 'birth_month', 'birth_day', 'nickname', 'phone'], array_keys($old));
        self::assertSame(' 山田 花子 ', $old['name'], '入力したままの値を戻す');
    }

    public function testRegisterOldFromInputSplitsBirthDateForSelects(): void
    {
        $old = register_old_from_input([
            'name' => '山田', 'email' => 'a@example.com', 'birth_date' => '2000-02-09', 'nickname' => null, 'phone' => '09012345678',
        ]);

        self::assertSame(['2000', '2', '9'], [$old['birth_year'], $old['birth_month'], $old['birth_day']]);
        self::assertSame('', $old['nickname']);
        self::assertSame('09012345678', $old['phone']);
    }

    public function testPasswordConfirmMismatchIsReportedOnConfirmField(): void
    {
        $errors = validate_new_password('abcd1234', 'abcd1235', 'new_password', 'new_password_confirm');

        self::assertSame(['new_password_confirm'], array_keys($errors));
    }
}
