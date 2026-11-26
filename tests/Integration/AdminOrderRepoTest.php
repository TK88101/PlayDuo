<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/admin_order_repo.php';

/** A10 注文一覧の検索・A11 注文詳細（テスト仕様書（管理画面）A10・A11） */
final class AdminOrderRepoTest extends TestCase
{
    private PDO $pdo;
    /** @var array<string, int> */
    private array $orders = [];
    private int $hanako;
    private int $taro;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->hanako = ShopFixtures::insertMember($this->pdo, 'hanako@example.com');
        $this->taro = ShopFixtures::insertMember($this->pdo, 'taro@example.com');
        $this->pdo->exec("UPDATE members SET name = '山田 花子' WHERE member_id = {$this->hanako}");
        $this->pdo->exec("UPDATE members SET name = '佐藤 太郎', withdrawal_flag = 1 WHERE member_id = {$this->taro}");
        $this->order('early', $this->taro, '2026-09-28 00:00:00', ShopFixtures::CLOUDPATH);
        $this->order('noon', $this->hanako, '2026-09-28 12:10:00', 1, 6);                       // 鉄骨前線＋アリーナ・ゼロ
        $this->order('night', $this->taro, '2026-09-28 23:59:59', 7);
        $this->order('next', $this->hanako, '2026-10-02 21:15:00', ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);
    }

    private function order(string $key, int $memberId, string $date, int ...$productIds): void
    {
        $id = ShopFixtures::purchase($this->pdo, $memberId, ...$productIds);
        $this->pdo->exec("UPDATE orders SET order_date = '{$date}' WHERE order_id = {$id}");
        $this->orders[$key] = $id;
    }

    /** @return list<int> */
    private function search(array $get): array
    {
        $parsed = parse_order_criteria($get);
        self::assertSame([], $parsed['errors']);
        return array_map(static fn (array $r): int => (int) $r['order_id'], search_orders($this->pdo, $parsed['criteria']));
    }

    public function testAllNewestFirstWithCountAndTotal(): void
    {
        $o = $this->orders;
        self::assertSame([$o['next'], $o['night'], $o['noon'], $o['early']], $this->search([]), 'A10-011');

        $rows = search_orders($this->pdo, parse_order_criteria([])['criteria']);
        $noon = $rows[2];
        self::assertSame([2, 7260], [(int) $noon['item_count'], (int) $noon['total']], 'A10-010');
        self::assertSame('山田 花子', $noon['member_name']);
        self::assertSame($this->hanako, (int) $noon['member_id']);
    }

    public function testDateBoundariesIncludeTheWholeDay(): void
    {
        $o = $this->orders;
        self::assertSame([$o['night'], $o['noon'], $o['early']], $this->search(['date_from' => '2026-09-28', 'date_to' => '2026-09-28']), 'A10-004');
        self::assertSame([$o['next']], $this->search(['date_from' => '2026-09-29']));
        self::assertSame([$o['night'], $o['noon'], $o['early']], $this->search(['date_to' => '2026-09-28']));
    }

    public function testMemberAndProductConditionsAndCombination(): void
    {
        $o = $this->orders;
        self::assertSame([$o['next'], $o['noon']], $this->search(['member' => 'M' . sprintf('%04d', $this->hanako)]), 'A10-006');
        self::assertSame([$o['next'], $o['noon']], $this->search(['member' => '花子']));
        self::assertSame([$o['night'], $o['early']], $this->search(['member' => '太郎']), 'A10-017 退会済み');

        self::assertSame([$o['next']], $this->search(['product' => 'ネオン']), 'A10-007');
        self::assertSame([$o['noon']], $this->search(['product' => 'ゼロ']), '一部一致');
        self::assertSame([$o['noon']], $this->search(['product' => '・']), 'A10-008 前提');
        self::assertSame([$o['noon']], $this->search(['member' => '花子', 'product' => '・', 'date_from' => '2026-09-28', 'date_to' => '2026-09-28']), 'A10-008');
        self::assertSame([], $this->search(['member' => '花子', 'product' => '双月']), 'A10-009');
    }

    public function testProductMatchingSeveralItemsDoesNotDuplicateOrDoubleTotal(): void
    {
        $this->pdo->exec("UPDATE order_details SET product_name = CONCAT('セット', product_name) WHERE order_id = {$this->orders['next']}");
        $rows = search_orders($this->pdo, parse_order_criteria(['product' => 'セット'])['criteria']);   // 明細 2 行とも一致
        $next = array_values(array_filter($rows, fn (array $r): bool => (int) $r['order_id'] === $this->orders['next']));
        self::assertCount(1, $next);
        self::assertCount(1, $rows);
        self::assertSame([2, 5780], [(int) $next[0]['item_count'], (int) $next[0]['total']]);
    }

    public function testProductUsesNameAtPurchaseAndWildcardsAreLiteral(): void
    {
        $this->pdo->exec("UPDATE products SET name = '新しい名前' WHERE product_id = 2");
        self::assertSame([$this->orders['next']], $this->search(['product' => 'ネオンサイト']), 'A10-016：購入時の名称');
        self::assertSame([], $this->search(['product' => '新しい名前']));
        self::assertSame([], $this->search(['product' => '%']));
        self::assertSame([], $this->search(['member' => '_']));
    }

    public function testOrderDetail(): void
    {
        $this->pdo->exec('UPDATE products SET public_flag = 0, price = 1 WHERE product_id = 5');
        $detail = find_order_detail($this->pdo, $this->orders['next']);

        self::assertSame('山田 花子', $detail['order']['member_name']);
        self::assertSame('2026-10-02 21:15:00', $detail['order']['order_date']);
        self::assertSame(5780, $detail['total'], 'A11-002');
        self::assertSame([[2, 'ネオンサイト', 3300], [5, 'ブロックフィールド開拓記', 2480]], array_map(
            static fn (array $i): array => [(int) $i['product_id'], $i['product_name'], (int) $i['price']],
            $detail['items'],
        ), 'A11-003・A11-005');
        self::assertNull(find_order_detail($this->pdo, 999), 'A11-007');
    }
}
