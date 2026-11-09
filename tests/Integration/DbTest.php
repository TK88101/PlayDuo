<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/db.php';

final class DbTest extends TestCase
{
    public function testDbUsesEnvironmentOverrideAndIsShared(): void
    {
        // phpunit.xml が PLAYDUO_DB_* を playduo_test に向けている
        $pdo = db();

        self::assertSame('playduo_test', $pdo->query('SELECT DATABASE()')->fetchColumn());
        self::assertSame($pdo, db(), '同じ接続を使い回す');
        self::assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }
}
