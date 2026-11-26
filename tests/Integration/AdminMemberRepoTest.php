<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/admin_member_repo.php';

/** A08 会員一覧の検索・A09 会員詳細（テスト仕様書（管理画面）A08・A09、Plan §2.0） */
final class AdminMemberRepoTest extends TestCase
{
    private PDO $pdo;
    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        foreach (['hanako@example.com' => '山田 花子', 'taro@example.com' => '佐藤 太郎', 'per%cent_x@test.jp' => '100%_会員'] as $email => $name) {
            $id = ShopFixtures::insertMember($this->pdo, $email);
            $this->pdo->prepare('UPDATE members SET name = ? WHERE member_id = ?')->execute([$name, $id]);
            $this->ids[$email] = $id;
        }
        $this->pdo->exec("UPDATE members SET withdrawal_flag = 1 WHERE member_id = {$this->ids['taro@example.com']}");
    }

    /** @return list<int> */
    private static function idsOf(array $rows): array
    {
        return array_map(static fn (array $r): int => (int) $r['member_id'], $rows);
    }

    public function testEmptyKeywordListsEveryoneIncludingWithdrawnInIdOrder(): void
    {
        $rows = search_members($this->pdo, '');
        self::assertSame(array_values($this->ids), self::idsOf($rows));
        self::assertSame(array_values($this->ids), self::idsOf(search_members($this->pdo, '　 ')));
        self::assertArrayNotHasKey('password', $rows[0], 'A08-012');
        self::assertArrayNotHasKey('totp_secret', $rows[0]);
    }

    public function testSearchByNameOrEmailPartialMatch(): void
    {
        $hanako = $this->ids['hanako@example.com'];
        self::assertSame([$hanako], self::idsOf(search_members($this->pdo, '花子')), 'A08-004');
        self::assertSame([$hanako], self::idsOf(search_members($this->pdo, ' hanako@ ')), 'A08-005');
        self::assertCount(2, search_members($this->pdo, '@example.com'));
        self::assertSame([$this->ids['taro@example.com']], self::idsOf(search_members($this->pdo, '太郎')), 'A08-007 退会済みも出る');
        self::assertSame([], search_members($this->pdo, 'いない'), 'A08-008');
    }

    public function testLikeWildcardsAreLiteral(): void
    {
        self::assertSame([$this->ids['per%cent_x@test.jp']], self::idsOf(search_members($this->pdo, '%')));
        self::assertSame([$this->ids['per%cent_x@test.jp']], self::idsOf(search_members($this->pdo, '_')));
        self::assertSame([], search_members($this->pdo, "'; DROP TABLE members; --"), 'A08-009');
    }

    public function testPurchaseCountIsNumberOfGames(): void
    {
        $hanako = $this->ids['hanako@example.com'];
        ShopFixtures::purchase($this->pdo, $hanako, ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);
        ShopFixtures::purchase($this->pdo, $hanako, ShopFixtures::CLOUDPATH);

        $rows = search_members($this->pdo, '');
        self::assertSame([3, 0, 0], array_map(static fn (array $r): int => (int) $r['purchase_count'], $rows), 'A08-003：ゲーム数');
    }

    public function testDetailAndPurchasesNewestFirstWithNamesAtPurchase(): void
    {
        $hanako = $this->ids['hanako@example.com'];
        $old = ShopFixtures::purchase($this->pdo, $hanako, ShopFixtures::CLOUDPATH);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-09-28 12:10:00' WHERE order_id = {$old}");
        $new = ShopFixtures::purchase($this->pdo, $hanako, ShopFixtures::NEONSIGHT, ShopFixtures::BLOCKFIELD);
        $this->pdo->exec("UPDATE orders SET order_date = '2026-10-02 21:15:00' WHERE order_id = {$new}");
        $this->pdo->exec("UPDATE products SET name = '改名後', price = 9999, public_flag = 0 WHERE product_id = 2");

        $member = find_member_detail($this->pdo, $hanako);
        self::assertSame('山田 花子', $member['name']);
        self::assertArrayNotHasKey('password', $member, 'A09-007');

        $history = member_purchases($this->pdo, $hanako);
        self::assertSame([[$new, 2], [$new, 5], [$old, 4]], array_map(
            static fn (array $r): array => [(int) $r['order_id'], (int) $r['product_id']],
            $history,
        ), 'A09-002：新しい順・同じ注文は複数行');
        self::assertSame('ネオンサイト', $history[0]['product_name'], 'A09-006：購入時の名称');
        self::assertSame(3300, (int) $history[0]['price']);

        self::assertNull(find_member_detail($this->pdo, 999));
        self::assertSame([], member_purchases($this->pdo, $this->ids['taro@example.com']), 'A09-004');
    }

    public function testMemberCode(): void
    {
        self::assertSame('M0001', member_code(1));
        self::assertSame('M12345', member_code(12345));
    }
}
