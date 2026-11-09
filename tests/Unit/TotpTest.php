<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/totp.php';

final class TotpTest extends TestCase
{
    // RFC 6238 付録 B の SHA1 用シークレット "12345678901234567890" を Base32 にしたもの
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** @return array<string, array{int, string}> */
    public static function rfcVectors(): array
    {
        // 8 桁の公式値の下 6 桁（XAMPP PHP で実算一致を確認済み）
        return [
            't=59'          => [59, '287082'],
            't=1111111109'  => [1111111109, '081804'],
            't=1234567890'  => [1234567890, '005924'],
            't=2000000000'  => [2000000000, '279037'],
            't=20000000000' => [20000000000, '353130'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function testCodeMatchesRfc6238Vectors(int $time, string $expected): void
    {
        self::assertSame($expected, totp_code(self::RFC_SECRET, totp_step($time)));
    }

    public function testBase32RoundTrip(): void
    {
        $binary = random_bytes(20);

        self::assertSame($binary, base32_decode(base32_encode($binary)));
        self::assertSame('12345678901234567890', base32_decode(self::RFC_SECRET));
    }

    public function testBase32DecodeAcceptsLowercase(): void
    {
        self::assertSame('12345678901234567890', base32_decode(strtolower(self::RFC_SECRET)));
    }

    /** @return array<string, array{string}> */
    public static function invalidBase32(): array
    {
        return ['empty' => [''], 'digit 1' => ['GEZD1NBV'], 'symbol' => ['GEZD-NBV']];
    }

    #[DataProvider('invalidBase32')]
    public function testBase32DecodeRejectsInvalidInput(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        base32_decode($input);
    }

    public function testGeneratedSecretIs160BitBase32(): void
    {
        $secret = totp_generate_secret();

        self::assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $secret);
        self::assertSame(20, strlen(base32_decode($secret)));
        self::assertNotSame($secret, totp_generate_secret());
    }

    public function testMatchStepAcceptsPreviousCurrentAndNextWindow(): void
    {
        // Arrange
        $now = 1_700_000_015;
        $step = totp_step($now);

        // Act / Assert
        foreach ([-1, 0, 1] as $offset) {
            $code = totp_code(self::RFC_SECRET, $step + $offset);
            self::assertSame($step + $offset, totp_match_step(self::RFC_SECRET, $code, $now));
        }
    }

    public function testMatchStepRejectsCodesTwoWindowsAway(): void
    {
        $now = 1_700_000_015;
        $step = totp_step($now);

        self::assertNull(totp_match_step(self::RFC_SECRET, totp_code(self::RFC_SECRET, $step - 2), $now));
        self::assertNull(totp_match_step(self::RFC_SECRET, totp_code(self::RFC_SECRET, $step + 2), $now));
    }

    /** @return array<string, array{string}> */
    public static function malformedCodes(): array
    {
        return ['5 digits' => ['12345'], '7 digits' => ['1234567'], 'letters' => ['12a456'], 'spaces' => [' 12345']];
    }

    #[DataProvider('malformedCodes')]
    public function testMatchStepRejectsMalformedCode(string $code): void
    {
        self::assertNull(totp_match_step(self::RFC_SECRET, $code, 1_700_000_015));
    }

    public function testUriContainsIssuerSecretAndEncodedEmail(): void
    {
        $uri = totp_uri(self::RFC_SECRET, 'a+b@example.com');

        self::assertSame(
            'otpauth://totp/PlayDuo:a%2Bb%40example.com?secret=' . self::RFC_SECRET
            . '&issuer=PlayDuo&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }
}
