<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

use PDO;
use RuntimeException;

/**
 * テスト専用 DB（playduo_test）の初期化。
 * DB 自体は作り直さず（README の手順で用意済み）、テーブルを消して sql/schema.sql の CREATE TABLE 以降を流す。
 * schema.sql は書き換えない。
 */
final class TestDb
{
    public const NAME = 'playduo_test';

    public static function createSchema(): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../../sql/schema.sql');
        $start = strpos($sql, 'CREATE TABLE');
        if ($start === false) {
            throw new RuntimeException('schema.sql に CREATE TABLE が見つかりません');
        }
        $body = substr($sql, $start);
        if (preg_match('/\b(DROP|CREATE)\s+DATABASE\b|^\s*USE\s/mi', $body) === 1) {
            throw new RuntimeException('schema.sql の CREATE TABLE 以降に DB を切り替える文があります');
        }

        $pdo = self::connect(emulatePrepares: true);
        self::dropAllTables($pdo);
        $pdo->exec($body);
    }

    public static function pdo(): PDO
    {
        return self::connect(emulatePrepares: false);
    }

    public static function truncateAll(PDO $pdo): void
    {
        self::withoutForeignKeyChecks($pdo, static function (PDO $pdo, string $table): void {
            $pdo->exec("TRUNCATE TABLE `{$table}`");
        });
    }

    private static function dropAllTables(PDO $pdo): void
    {
        self::withoutForeignKeyChecks($pdo, static function (PDO $pdo, string $table): void {
            $pdo->exec("DROP TABLE `{$table}`");
        });
    }

    /** @param callable(PDO, string): void $perTable */
    private static function withoutForeignKeyChecks(PDO $pdo, callable $perTable): void
    {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($tables as $table) {
                $perTable($pdo, (string) $table);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function connect(bool $emulatePrepares): PDO
    {
        if (getenv('PLAYDUO_DB_NAME') !== self::NAME) {
            throw new RuntimeException('PLAYDUO_DB_NAME が ' . self::NAME . ' ではありません。phpunit.xml 経由で実行してください');
        }
        $pdo = new PDO(
            'mysql:host=' . (getenv('PLAYDUO_DB_HOST') ?: '127.0.0.1') . ';dbname=' . self::NAME . ';charset=utf8mb4',
            (string) getenv('PLAYDUO_DB_USER'),
            (string) getenv('PLAYDUO_DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => $emulatePrepares,
            ],
        );
        $current = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($current !== self::NAME) {
            throw new RuntimeException("テスト接続先が {$current} です（" . self::NAME . ' 以外には接続しない）');
        }
        return $pdo;
    }
}
