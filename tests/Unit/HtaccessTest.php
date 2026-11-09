<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * .htaccess の遮断ルールが、開発用フォルダだけを塞いで画面用の資産（assets/js/vendor など）を塞がないこと。
 * php -S は .htaccess を読まないので HTTP シナリオテストでは確かめられない（11/9 に QR が出ない事故があった）。
 */
final class HtaccessTest extends TestCase
{
    private static function blockPattern(): string
    {
        $htaccess = (string) file_get_contents(__DIR__ . '/../../.htaccess');
        self::assertSame(1, preg_match('/^RewriteRule\s+(\S+)\s+-\s+\[R=404/m', $htaccess, $m), 'RewriteRule が見つからない');
        return '#' . $m[1] . '#';
    }

    /** @return array<string, array{string, bool}> */
    public static function paths(): array
    {
        return [
            'composer vendor'   => ['vendor/autoload.php', true],
            'tests'             => ['tests/bootstrap.php', true],
            'sql seed'          => ['sql/seed.sql', true],
            'docs'              => ['docs/plans/x.md', true],
            'git'               => ['.git/config', true],
            'qrcode library'    => ['assets/js/vendor/qrcode.min.js', false],
            'game image'        => ['assets/games/both/fps/g02_cover.png', false],
            'page'              => ['register.php', false],
            'similar file name' => ['vendors.php', false],
        ];
    }

    #[DataProvider('paths')]
    public function testOnlyDevelopmentFoldersAreBlocked(string $path, bool $blocked): void
    {
        self::assertSame($blocked, preg_match(self::blockPattern(), $path) === 1, $path);
    }
}
