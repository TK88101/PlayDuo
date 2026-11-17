<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/shop.php';

final class ShopTest extends TestCase
{
    private const NOW = 1_794_900_000;   // 2026-11-17 頃
    private const ADULT = '2000-01-01';
    private const CHILD = '2015-01-01';  // 11 歳：CERO C（ネオンサイト）は買えない

    private PDO $pdo;
    private int $memberId;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
        $this->memberId = ShopFixtures::insertMember($this->pdo, 'a@example.com');
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DROP TRIGGER IF EXISTS test_fail_order_details');
    }

    /** @return list<int> */
    private function cartIds(?int $memberId = null): array
    {
        return array_map('intval', array_column(cart_rows($this->pdo, $memberId ?? $this->memberId), 'product_id'));
    }

    // ---- カート追加（U03） ----

    public function testAddToCartAndDuplicateStaysOneRow(): void
    {
        self::assertSame('ok', try_add_to_cart($this->pdo, $this->memberId, self::ADULT, ShopFixtures::BLOCKFIELD, self::NOW));
        self::assertSame('ok', try_add_to_cart($this->pdo, $this->memberId, self::ADULT, ShopFixtures::BLOCKFIELD, self::NOW), 'Q08：再追加は成功扱い');

        self::assertSame([ShopFixtures::BLOCKFIELD], $this->cartIds());
        self::assertSame(1, cart_count($this->pdo, $this->memberId));
    }

    public function testAddToCartRejectsPrivateMissingPurchasedAndAgeRestricted(): void
    {
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = ' . ShopFixtures::CLOUDPATH);
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        $child = ShopFixtures::insertMember($this->pdo, 'child@example.com', self::CHILD);

        self::assertSame('not_found', try_add_to_cart($this->pdo, $this->memberId, self::ADULT, ShopFixtures::CLOUDPATH, self::NOW));
        self::assertSame('not_found', try_add_to_cart($this->pdo, $this->memberId, self::ADULT, 99999, self::NOW));
        self::assertSame('purchased', try_add_to_cart($this->pdo, $this->memberId, self::ADULT, ShopFixtures::BLOCKFIELD, self::NOW));
        self::assertSame('age', try_add_to_cart($this->pdo, $child, self::CHILD, ShopFixtures::NEONSIGHT, self::NOW));

        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'));
    }

    public function testIsPurchased(): void
    {
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);

        self::assertTrue(is_purchased($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD));
        self::assertFalse(is_purchased($this->pdo, $this->memberId, ShopFixtures::NEONSIGHT));
    }

    // ---- カート（U07） ----

    public function testCartRowsIncludePrivateAndPurchasedFlags(): void
    {
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::CLOUDPATH);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = ' . ShopFixtures::CLOUDPATH);
        ShopFixtures::purchase($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);

        $rows = cart_rows($this->pdo, $this->memberId);

        self::assertSame([ShopFixtures::CLOUDPATH, ShopFixtures::BLOCKFIELD], array_map('intval', array_column($rows, 'product_id')));
        self::assertSame([0, 1], array_map('intval', array_column($rows, 'public_flag')));
        self::assertSame([0, 1], array_map('intval', array_column($rows, 'purchased')));
    }

    public function testDeleteCartItemsOnlyTouchesOwnRows(): void
    {
        $other = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::BLOCKFIELD);
        ShopFixtures::addToCart($this->pdo, $other, ShopFixtures::BLOCKFIELD);

        self::assertSame(1, delete_cart_items($this->pdo, $this->memberId, [ShopFixtures::BLOCKFIELD]));
        self::assertSame(0, delete_cart_items($this->pdo, $this->memberId, []));

        self::assertSame([], $this->cartIds());
        self::assertSame([ShopFixtures::BLOCKFIELD], $this->cartIds($other));
    }

    // ---- U09：購入できない商品の削除 ----

    public function testRemoveUnbuyableItemsKeepsBuyableOnes(): void
    {
        $child = ShopFixtures::insertMember($this->pdo, 'child@example.com', self::CHILD);
        foreach ([ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT, 3] as $id) {
            ShopFixtures::addToCart($this->pdo, $child, $id);
        }
        ShopFixtures::purchase($this->pdo, $child, 3);

        $removed = remove_unbuyable_items($this->pdo, $child, self::CHILD, self::NOW);

        self::assertSame(2, $removed, '購入済み 1・年齢制限 1');
        self::assertSame([ShopFixtures::BLOCKFIELD], $this->cartIds($child));
        self::assertSame(0, remove_unbuyable_items($this->pdo, $child, self::CHILD, self::NOW));
    }

    // ---- 購入確定（U08） ----

    /** @return array<int, int> 確認画面で表示した内容（product_id => price） */
    private function fillCart(int ...$ids): array
    {
        foreach ($ids as $id) {
            ShopFixtures::addToCart($this->pdo, $this->memberId, $id);
        }
        return purchase_snapshot_of(cart_rows($this->pdo, $this->memberId));
    }

    public function testPlaceOrderWritesOrderDetailsAtCurrentPriceAndClearsCart(): void
    {
        $snapshot = $this->fillCart(ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);

        $result = place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW);

        self::assertSame('ok', $result['status']);
        $details = $this->pdo->query('SELECT product_id, member_id, product_name, price FROM order_details ORDER BY product_id')->fetchAll();
        self::assertSame([
            ['product_id' => 2, 'member_id' => $this->memberId, 'product_name' => 'ネオンサイト', 'price' => 3300],
            ['product_id' => 5, 'member_id' => $this->memberId, 'product_name' => 'ブロックフィールド開拓記', 'price' => 2480],
        ], $details);
        self::assertSame(1, ShopFixtures::count($this->pdo, 'orders'));
        self::assertSame(0, ShopFixtures::count($this->pdo, 'carts'));

        $order = find_member_order($this->pdo, $this->memberId, $result['order_id']);
        self::assertSame(db_datetime(self::NOW), $order['order']['order_date']);
        self::assertSame(5780, $order['total']);
        self::assertSame(['ネオンサイト', 'ブロックフィールド開拓記'], array_column($order['items'], 'product_name'));
    }

    public function testSecondPlaceOrderSeesEmptyCart(): void
    {
        $snapshot = $this->fillCart(ShopFixtures::BLOCKFIELD);
        place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW);

        $again = place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW);

        self::assertSame(['status' => 'empty'], $again);
        self::assertSame(1, ShopFixtures::count($this->pdo, 'orders'));
    }

    public function testPlaceOrderRejectsUnbuyableWithoutWriting(): void
    {
        $snapshot = $this->fillCart(ShopFixtures::BLOCKFIELD, ShopFixtures::CLOUDPATH);
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = ' . ShopFixtures::CLOUDPATH);

        $result = place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW);

        self::assertSame(['status' => 'ng'], $result);
        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
        self::assertSame(2, ShopFixtures::count($this->pdo, 'carts'));
    }

    public function testPlaceOrderDetectsPriceOrCartChangeAfterConfirmation(): void
    {
        $snapshot = $this->fillCart(ShopFixtures::BLOCKFIELD);

        $this->pdo->exec('UPDATE products SET price = 1980 WHERE product_id = ' . ShopFixtures::BLOCKFIELD);
        self::assertSame(['status' => 'changed'], place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW));

        $this->pdo->exec('UPDATE products SET price = 2480 WHERE product_id = ' . ShopFixtures::BLOCKFIELD);
        ShopFixtures::addToCart($this->pdo, $this->memberId, ShopFixtures::NEONSIGHT);
        self::assertSame(['status' => 'changed'], place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW), '別タブで追加された');

        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
    }

    public function testFailureInTheMiddleRollsBackEverything(): void
    {
        $snapshot = $this->fillCart(ShopFixtures::BLOCKFIELD, ShopFixtures::NEONSIGHT);
        $this->pdo->exec(
            "CREATE TRIGGER test_fail_order_details BEFORE INSERT ON order_details FOR EACH ROW
             BEGIN IF NEW.product_id = 5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'; END IF; END",
        );

        try {
            place_order($this->pdo, $this->memberId, self::ADULT, $snapshot, self::NOW);
            self::fail('例外が投げ直されること');
        } catch (PDOException $e) {
            self::assertStringContainsString('test failure', $e->getMessage());
        }

        // U08-011：全件登録または全件未登録
        self::assertSame(0, ShopFixtures::count($this->pdo, 'orders'));
        self::assertSame(0, ShopFixtures::count($this->pdo, 'order_details'));
        self::assertSame(2, ShopFixtures::count($this->pdo, 'carts'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testLockingCartReadBlocksConcurrentAddForSameMember(): void
    {
        // 排他の前提（Plan §2.6）：ロック読み中は、同じ会員のカートへの追加がコミットまで待たされる
        $holder = TestDb::pdo();
        $holder->beginTransaction();
        cart_rows($holder, $this->memberId, lock: true);

        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            try_add_to_cart($this->pdo, $this->memberId, self::ADULT, ShopFixtures::BLOCKFIELD, self::NOW);
            self::fail('ロック待ちでタイムアウトすること');
        } catch (PDOException $e) {
            self::assertSame(1205, $e->errorInfo[1] ?? null, 'Lock wait timeout');
        } finally {
            $holder->rollBack();
        }
    }

    // ---- U10 ----

    public function testFindMemberOrderOnlyReturnsOwnOrder(): void
    {
        $other = ShopFixtures::insertMember($this->pdo, 'b@example.com');
        $orderId = ShopFixtures::purchase($this->pdo, $other, ShopFixtures::BLOCKFIELD);

        self::assertNull(find_member_order($this->pdo, $this->memberId, $orderId));
        self::assertNull(find_member_order($this->pdo, $this->memberId, 99999));
        self::assertNotNull(find_member_order($this->pdo, $other, $orderId));
    }

    public function testUnexpectedNonDuplicateErrorOnAddIsRethrown(): void
    {
        // 存在しない会員（外部キー違反 1452）は「追加済み」に読み替えない
        $this->expectException(PDOException::class);
        try_add_to_cart($this->pdo, 99999, self::ADULT, ShopFixtures::BLOCKFIELD, self::NOW);
    }
}
