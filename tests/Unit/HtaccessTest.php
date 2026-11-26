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
        self::assertSame(1, preg_match('/^RewriteRule\s+(\S+)\s+-\s+\[([^\]]*R=404[^\]]*)\]/m', $htaccess, $m), 'RewriteRule が見つからない');
        // macOS・Windows のファイルシステムは大文字小文字を区別しないので、NC（区別しない）が無いと DOWNLOADS/ で抜けられる
        return '#' . $m[1] . '#' . (preg_match('/(^|,)NC(,|$)/', $m[2]) === 1 ? 'i' : '');
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
            'downloads'         => ['downloads/g01.zip', true],
            'downloads 大文字'  => ['DOWNLOADS/g01.zip', true],   // セキュリティ評審 HIGH-1（大文字で .htaccess を抜けられた）
            'sql 大文字'        => ['SQL/schema.sql', true],
            'git 大文字'        => ['.GIT/HEAD', true],   // 配布ファイルは download.php だけが送る（Plan 2026-11-26 §2.3）
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
