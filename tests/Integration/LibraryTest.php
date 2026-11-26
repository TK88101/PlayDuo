<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/library_repo.php';

/** U11 購入履歴（ライブラリ）の読み出し。期待結果はテスト仕様書 U11-004・006・007・011、Plan 2026-11-26 §2.0 Q22 */
final class LibraryTest extends TestCase
{
    private PDO $pdo;
    private int $memberId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->memberId = ShopFixtures::insertMember($this->pdo, 'a@example.com');
    }

    private function purchaseAt(string $orderDate, int ...$productIds): int
    {
        $orderId = ShopFixtures::purchase($this->pdo, $this->memberId, ...$productIds);
        $this->pdo->prepare('UPDATE orders SET order_date = ? WHERE order_id = ?')->execute([$orderDate, $orderId]);
        return $orderId;
    }

    public function testEmptyLibraryIsEmptyList(): void
    {
        self::assertSame([], member_library($this->pdo, $this->memberId));
    }

    public function testNewestOrderFirstAndProductIdWithinOrder(): void
    {
        $old = $this->purchaseAt('2026-09-28 10:00:00', ShopFixtures::CLOUDPATH);
        $new = $this->purchaseAt('2026-10-02 09:00:00', ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);

        $rows = member_library($this->pdo, $this->memberId);

        self::assertSame(
            [[$new, ShopFixtures::NEONSIGHT], [$new, ShopFixtures::BLOCKFIELD], [$old, ShopFixtures::CLOUDPATH]],
            array_map(static fn (array $r): array => [(int) $r['order_id'], (int) $r['product_id']], $rows),
        );
        self::assertSame('R261002' . sprintf('%03d', $new), $rows[0]['order_number']);
    }

    public function testShowsPurchaseTimeNameAndPriceEvenAfterProductChanges(): void
    {
        $this->purchaseAt('2026-10-02 09:00:00', ShopFixtures::BLOCKFIELD);
        $this->pdo->exec("UPDATE products SET name = '改名後', price = 9999, public_flag = 0 WHERE product_id = 5");

        $rows = member_library($this->pdo, $this->memberId);

        self::assertCount(1, $rows, '販売終了でも一覧に出る（U11-006）');
        self::assertSame('ブロックフィールド開拓記', $rows[0]['name']);
        self::assertSame(2480, (int) $rows[0]['price'], '購入時の価格（U11-007）');
        self::assertSame('downloads/g5.zip', $rows[0]['file_path']);
    }

    public function testOtherMembersPurchasesAreNotIncluded(): void
    {
        $other = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        ShopFixtures::purchase($this->pdo, $other, ShopFixtures::BLOCKFIELD);

        self::assertSame([], member_library($this->pdo, $this->memberId));
        self::assertNull(find_library_item($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD));
    }

    public function testFindLibraryItemReturnsOwnPurchaseOnly(): void
    {
        $this->purchaseAt('2026-10-02 09:00:00', ShopFixtures::BLOCKFIELD);

        $item = find_library_item($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);

        self::assertNotNull($item);
        self::assertSame('downloads/g5.zip', $item['file_path']);
        self::assertNull(find_library_item($this->pdo, $this->memberId, ShopFixtures::NEONSIGHT), '未購入');
    }
}
