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
