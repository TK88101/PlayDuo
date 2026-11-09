<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/recovery.php';

final class RecoveryTest extends TestCase
{
    public function testGeneratesTenUniqueCodesInDisplayFormat(): void
    {
        $codes = generate_recovery_codes();

        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/\A[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}\z/', $code);
        }
    }

    public function testNormalizeUppercasesAndStripsHyphensAndSpaces(): void
    {
        self::assertSame('ABCDE23456', normalize_recovery_code(' abcde-23456 '));
        self::assertSame('ABCDE23456', normalize_recovery_code("ABCDE 23456"));
    }

    public function testFormatCheckAcceptsOnlyNormalizedTenChars(): void
    {
        self::assertTrue(is_recovery_code_format('ABCDE23456'));
        self::assertFalse(is_recovery_code_format('ABCDE2345'));
        self::assertFalse(is_recovery_code_format('ABCDE2345O'), 'O は紛らわしいので使わない');
        self::assertFalse(is_recovery_code_format('123456'));
    }
}
