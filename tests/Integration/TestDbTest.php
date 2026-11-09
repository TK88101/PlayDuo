<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\TestDb;

final class TestDbTest extends TestCase
{
    public function testSchemaIsLoadedIntoTestDatabaseOnly(): void
    {
        // Arrange
        $pdo = TestDb::pdo();

        // Act
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        // Assert
        sort($tables);
        self::assertSame(
            ['admins', 'carts', 'members', 'order_details', 'orders', 'products', 'recovery_codes'],
            $tables,
        );
        self::assertSame('playduo_test', $pdo->query('SELECT DATABASE()')->fetchColumn());
    }
}
