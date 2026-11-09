<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/session.php';

final class SessionTest extends TestCase
{
    public function testCsrfTokenIsCreatedOnceAndReused(): void
    {
        // Arrange
        $session = [];

        // Act
        $first = csrf_token($session);
        $second = csrf_token($session);

        // Assert
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $first);
        self::assertSame($first, $second);
    }

    public function testCsrfIsValidForMatchingToken(): void
    {
        $session = [];
        $token = csrf_token($session);

        self::assertTrue(csrf_is_valid($session, $token));
    }

    /** @return array<string, array{mixed}> */
    public static function invalidTokens(): array
    {
        return [
            'wrong'  => [str_repeat('0', 64)],
            'empty'  => [''],
            'null'   => [null],
            'array'  => [['x']],
        ];
    }

    #[DataProvider('invalidTokens')]
    public function testCsrfIsInvalidForWrongOrMissingToken(mixed $token): void
    {
        $session = [];
        csrf_token($session);

        self::assertFalse(csrf_is_valid($session, $token));
    }

    public function testCsrfIsInvalidWhenSessionHasNoToken(): void
    {
        self::assertFalse(csrf_is_valid([], str_repeat('a', 64)));
    }

    public function testCurrentMemberIdReadsIntegerOrNull(): void
    {
        self::assertSame(12, current_member_id(['member_id' => 12]));
        self::assertNull(current_member_id([]));
        self::assertNull(current_member_id(['member_id' => '12']));
    }

    public function testClearAuthFlowsRemovesOnlyFlowState(): void
    {
        // Arrange
        $session = [
            'csrf_token'     => 'keep',
            'member_id'      => 3,
            'register'       => ['input' => []],
            'register_done'  => ['recovery_codes' => []],
            'password_reset' => ['member_id' => 3],
        ];

        // Act
        clear_auth_flows($session);

        // Assert
        self::assertSame(['csrf_token' => 'keep', 'member_id' => 3], $session);
    }
}
