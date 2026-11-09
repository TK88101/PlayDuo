<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

use PDO;
use RuntimeException;

/**
 * テスト専用 DB（playduo_test）の作成と初期化。
 * sql/schema.sql は編集せず、先頭の DB 作り直し 3 文だけを playduo_test に置き換えて流す。
 */
final class TestDb
{
    public const NAME = 'playduo_test';

    private const SCHEMA_HEADER_PATTERNS = [
        '/DROP DATABASE IF EXISTS playduo;/'   => 'DROP DATABASE IF EXISTS ' . self::NAME . ';',
        '/CREATE DATABASE playduo /'           => 'CREATE DATABASE ' . self::NAME . ' ',
        '/USE playduo;/'                       => 'USE ' . self::NAME . ';',
    ];

    private const TABLES_CHILD_FIRST = [
        'order_details', 'orders', 'carts', 'recovery_codes', 'products', 'members', 'admins',
    ];

    public static function createSchema(): void
    {
        self::assertTargetName();
        $sql = (string) file_get_contents(__DIR__ . '/../../sql/schema.sql');
        foreach (self::SCHEMA_HEADER_PATTERNS as $pattern => $replacement) {
            $sql = (string) preg_replace($pattern, $replacement, $sql, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("schema.sql の先頭が想定と違います: {$pattern}");
            }
        }
        if (preg_match('/\bplayduo\b(?!_test)/', self::stripComments($sql)) === 1) {
            throw new RuntimeException('置換後の SQL に開発 DB 名 playduo が残っています');
        }

        $pdo = new PDO('mysql:host=' . self::host() . ';charset=utf8mb4', self::user(), self::password(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        $pdo->exec($sql);
    }

    public static function pdo(): PDO
    {
        self::assertTargetName();
        $pdo = new PDO('mysql:host=' . self::host() . ';dbname=' . self::NAME . ';charset=utf8mb4', self::user(), self::password(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $current = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($current !== self::NAME) {
            throw new RuntimeException("テスト接続先が {$current} です（" . self::NAME . ' 以外には接続しない）');
        }
        return $pdo;
    }

    public static function truncateAll(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::TABLES_CHILD_FIRST as $table) {
                $pdo->exec("TRUNCATE TABLE {$table}");
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private static function assertTargetName(): void
    {
        if (getenv('PLAYDUO_DB_NAME') !== self::NAME) {
            throw new RuntimeException('PLAYDUO_DB_NAME が ' . self::NAME . ' ではありません。phpunit.xml 経由で実行してください');
        }
    }

    private static function stripComments(string $sql): string
    {
        return (string) preg_replace('/--[^\n]*/', '', $sql);
    }

    private static function host(): string
    {
        // brew PHP の既定ソケット（/tmp/mysql.sock）は XAMPP に無いので TCP で接続する
        return (string) (getenv('PLAYDUO_DB_HOST') ?: '127.0.0.1');
    }

    private static function user(): string
    {
        return (string) getenv('PLAYDUO_DB_USER');
    }

    private static function password(): string
    {
        return (string) getenv('PLAYDUO_DB_PASSWORD');
    }
}
