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

    public function testTransactionCommitsResultAndRollsBackOnError(): void
    {
        $pdo = db();
        \PlayDuo\Tests\Support\TestDb::truncateAll($pdo);
        $insert = static fn (string $login): bool => $pdo
            ->prepare('INSERT INTO admins (login_id, password) VALUES (?, ?)')
            ->execute([$login, 'x']);

        self::assertTrue(db_transaction($pdo, static fn (): bool => $insert('kept')));
        try {
            db_transaction($pdo, static function () use ($insert): void {
                $insert('rolled-back');
                throw new \RuntimeException('boom');
            });
            self::fail('例外が伝わらなかった');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertFalse($pdo->inTransaction());
        self::assertSame(['kept'], $pdo->query('SELECT login_id FROM admins')->fetchAll(\PDO::FETCH_COLUMN));
    }
}
