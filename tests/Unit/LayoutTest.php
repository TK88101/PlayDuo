<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/functions.php';

final class LayoutTest extends TestCase
{
    /** @param array<string, mixed> $vars */
    private static function renderHeader(array $vars): string
    {
        extract($vars);
        ob_start();
        include __DIR__ . '/../../includes/header.php';
        return (string) ob_get_clean();
    }

    public function testHeaderEscapesTitleAndMessage(): void
    {
        $html = self::renderHeader([
            'page_title' => '<会員登録>', 'login_member' => null, 'message' => '<b>完了</b>', 'csrf_token' => 't',
        ]);

        self::assertStringContainsString('<title>&lt;会員登録&gt; | Play Duo</title>', $html);
        self::assertStringContainsString('&lt;b&gt;完了&lt;/b&gt;', $html);
    }

    public function testHeaderShowsLoginAndRegisterLinksForGuest(): void
    {
        $html = self::renderHeader(['page_title' => 'x', 'login_member' => null, 'message' => null, 'csrf_token' => 't']);

        self::assertStringContainsString('href="login.php"', $html);
        self::assertStringContainsString('href="register.php"', $html);
        self::assertStringNotContainsString('logout.php', $html);
    }

    public function testHeaderShowsMypageAndLogoutFormForMember(): void
    {
        $html = self::renderHeader([
            'page_title' => 'x', 'login_member' => ['member_id' => 1, 'nickname' => 'デュオ', 'name' => '山田'],
            'message' => null, 'csrf_token' => 'tok"en',
        ]);

        self::assertStringContainsString('href="mypage.php"', $html);
        self::assertStringContainsString('action="logout.php"', $html);
        self::assertStringContainsString('name="csrf_token" value="tok&quot;en"', $html);
        self::assertStringNotContainsString('href="register.php"', $html);
    }

    public function testFooterClosesDocument(): void
    {
        ob_start();
        include __DIR__ . '/../../includes/footer.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('</body>', $html);
        self::assertStringContainsString('</html>', $html);
    }
}
