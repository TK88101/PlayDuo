<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/functions.php';

final class FunctionsTest extends TestCase
{
    public function testHEscapesHtmlSpecialCharactersIncludingQuotes(): void
    {
        self::assertSame('&lt;a href=&quot;x&quot;&gt;&#039;&amp;&lt;/a&gt;', h('<a href="x">\'&</a>'));
    }

    public function testHReturnsEmptyStringForNull(): void
    {
        self::assertSame('', h(null));
    }

    public function testHAcceptsNumbers(): void
    {
        self::assertSame('4180', h(4180));
    }

    public function testErrorHtmlEscapesMessageAndIsEmptyWithoutError(): void
    {
        self::assertSame('<p class="error">&lt;x&gt;</p>', error_html(['email' => '<x>'], 'email'));
        self::assertSame('', error_html([], 'email'));
    }

    public function testIsPostReadsRequestMethod(): void
    {
        $saved = $_SERVER['REQUEST_METHOD'] ?? null;
        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            self::assertTrue(is_post());
            $_SERVER['REQUEST_METHOD'] = 'GET';
            self::assertFalse(is_post());
        } finally {
            $_SERVER['REQUEST_METHOD'] = $saved;
        }
    }

    public function testDbDatetimeRoundTripsInJapanTime(): void
    {
        self::assertSame('2026-11-09 09:00:00', db_datetime(1_794_182_400));
        self::assertSame(1_794_182_400, db_timestamp('2026-11-09 09:00:00'));
    }

    public function testFlashIsReturnedOnlyOnce(): void
    {
        // Arrange
        $session = [];
        flash_set($session, '登録が完了しました');

        // Act
        $first = flash_take($session);
        $second = flash_take($session);

        // Assert
        self::assertSame('登録が完了しました', $first);
        self::assertNull($second);
        self::assertArrayNotHasKey('flash', $session);
    }
}
