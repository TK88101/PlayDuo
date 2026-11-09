<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/db.php';

final class DbSettingsTest extends TestCase
{
    private const FILE_CONFIG = [
        'host' => 'localhost', 'name' => 'playduo', 'user' => 'root', 'password' => '', 'charset' => 'utf8mb4',
    ];

    public function testUsesConfigFileValuesWhenNoEnvironmentOverride(): void
    {
        self::assertSame(self::FILE_CONFIG, db_settings(self::FILE_CONFIG, []));
    }

    public function testEnvironmentOverridesHostNameUserAndPassword(): void
    {
        // Arrange
        $env = [
            'PLAYDUO_DB_HOST' => '127.0.0.1', 'PLAYDUO_DB_NAME' => 'playduo_test',
            'PLAYDUO_DB_USER' => 'playduo_test', 'PLAYDUO_DB_PASSWORD' => 'secret',
        ];

        // Act
        $settings = db_settings(self::FILE_CONFIG, $env);

        // Assert
        self::assertSame('127.0.0.1', $settings['host']);
        self::assertSame('playduo_test', $settings['name']);
        self::assertSame('playduo_test', $settings['user']);
        self::assertSame('secret', $settings['password']);
        self::assertSame('utf8mb4', $settings['charset']);
    }

    public function testEmptyEnvironmentValueDoesNotOverride(): void
    {
        self::assertSame('playduo', db_settings(self::FILE_CONFIG, ['PLAYDUO_DB_NAME' => ''])['name']);
    }
}
