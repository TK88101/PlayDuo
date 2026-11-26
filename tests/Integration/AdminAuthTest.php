<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/admin_auth.php';

/** A01 管理者ログインの照合（テスト仕様書（管理画面）A01・Plan 2026-11-26-php-admin §2.0） */
final class AdminAuthTest extends TestCase
{
    private PDO $pdo;
    private int $adminId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        $this->adminId = ShopFixtures::insertAdmin($this->pdo, 'admin', 'admin1234');
    }

    public function testCorrectIdAndPasswordReturnsAdminId(): void
    {
        self::assertSame($this->adminId, admin_login($this->pdo, 'admin', 'admin1234'));
    }

    public function testWrongPasswordAndUnknownIdBothReturnNull(): void
    {
        self::assertNull(admin_login($this->pdo, 'admin', 'wrong-pass'));
        self::assertNull(admin_login($this->pdo, 'nobody', 'admin1234'));
        self::assertNull(admin_login($this->pdo, '', ''));
    }

    public function testSurroundingSpacesInIdAreIgnoredButCaseIsNot(): void
    {
        self::assertSame($this->adminId, admin_login($this->pdo, "  admin\t", 'admin1234'));
        // DB の照合順序は大文字小文字を区別しないので、PHP 側で完全一致を確かめる（A01-011）
        self::assertNull(admin_login($this->pdo, 'ADMIN', 'admin1234'));
        self::assertNull(admin_login($this->pdo, 'admin', ' admin1234'), 'パスワードは空白を除かない');
    }

    public function testMemberCredentialsCannotLogInAsAdmin(): void
    {
        ShopFixtures::insertMember($this->pdo, 'admin@example.com', '2000-01-01', 'admin1234');
        self::assertNull(admin_login($this->pdo, 'admin@example.com', 'admin1234'));
    }

    public function testNoLockAfterTenFailures(): void
    {
        for ($i = 0; $i < 10; $i++) {
            self::assertNull(admin_login($this->pdo, 'admin', "wrong{$i}"));
        }
        self::assertSame($this->adminId, admin_login($this->pdo, 'admin', 'admin1234'), 'A01-008：ロックしない');
    }

    public function testAdminExistsChecksTheRow(): void
    {
        self::assertTrue(admin_exists($this->pdo, $this->adminId));
        self::assertFalse(admin_exists($this->pdo, $this->adminId + 1));
    }

    public function testCurrentAdminIdAcceptsOnlyInt(): void
    {
        self::assertSame(3, current_admin_id(['admin_id' => 3]));
        self::assertNull(current_admin_id(['admin_id' => '3']));
        self::assertNull(current_admin_id([]));
    }
}
