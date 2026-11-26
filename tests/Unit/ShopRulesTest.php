<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/shop.php';
require_once __DIR__ . '/../../includes/auth_guard.php';

final class ShopRulesTest extends TestCase
{
    private const NOW = 1_794_900_000; // 2026-11-17 頃（日本時間）

    /** @param array<string, mixed> $overrides */
    private static function row(int $id, int $price, array $overrides = []): array
    {
        return $overrides + [
            'product_id' => $id, 'name' => "商品{$id}", 'price' => $price,
            'age_rating' => 'A', 'public_flag' => 1, 'purchased' => 0,
        ];
    }

    public function testCartViewSumsOnlyBuyableItems(): void
    {
        $rows = [
            self::row(1, 2480),
            self::row(2, 3300),
            self::row(3, 2860, ['public_flag' => 0]),
        ];

        $view = cart_view($rows, '2000-01-01', self::NOW);

        self::assertSame(2, $view['count'], 'U07-005：販売終了は点数に含めない');
        self::assertSame(5780, $view['total']);
        self::assertSame([true, true, false], array_column($view['items'], 'can_buy'));
        self::assertSame([null, null, '販売終了'], array_column($view['items'], 'reason'));
        self::assertSame([['name' => '商品3', 'reason' => '販売終了']], $view['ng_items']);
    }

    public function testReasonPriorityIsPrivateThenPurchasedThenAge(): void
    {
        $rows = [
            self::row(1, 100, ['public_flag' => 0, 'purchased' => 1, 'age_rating' => 'Z']),
            self::row(2, 100, ['purchased' => 1, 'age_rating' => 'Z']),
            self::row(3, 100, ['age_rating' => 'Z']),
        ];

        $view = cart_view($rows, '2015-01-01', self::NOW);

        self::assertSame(['販売終了', '購入済み', '年齢制限'], array_column($view['items'], 'reason'));
        self::assertSame(0, $view['count']);
        self::assertSame(0, $view['total']);
    }

    public function testEmptyCartView(): void
    {
        self::assertSame(['items' => [], 'count' => 0, 'total' => 0, 'ng_items' => []], cart_view([], '2000-01-01', self::NOW));
    }

    public function testCartReasonMessage(): void
    {
        self::assertNull(cart_reason_message(['can_buy' => true, 'reason' => null, 'age_rating' => 'A']));
        self::assertSame(
            'この商品は販売を終了しています。カートから削除してください。',
            cart_reason_message(['can_buy' => false, 'reason' => '販売終了', 'age_rating' => 'A']),
        );
        self::assertSame(
            '購入済みの商品です。カートから削除してください。',
            cart_reason_message(['can_buy' => false, 'reason' => '購入済み', 'age_rating' => 'A']),
        );
        self::assertSame(
            '年齢区分（CERO C）の対象年齢に達していないため購入できません。カートから削除してください。',
            cart_reason_message(['can_buy' => false, 'reason' => '年齢制限', 'age_rating' => 'C']),
        );
    }

    public function testAddBlockReasonForDetailPage(): void
    {
        self::assertNull(add_block_reason(['age_rating' => 'C'], false, '2000-01-01', self::NOW));
        self::assertSame('購入済みです', add_block_reason(['age_rating' => 'A'], true, '2000-01-01', self::NOW));
        self::assertSame(
            '年齢区分（CERO C）の対象年齢に達していないため購入できません',
            add_block_reason(['age_rating' => 'C'], false, '2015-01-01', self::NOW),
        );
    }

    /** @return array<string, array{int, string, string}> */
    public static function orderNumberCases(): array
    {
        return [
            '3 桁ゼロ埋め'   => [1, '2026-11-17 10:15:00', 'R261117001'],
            '通し番号のまま' => [42, '2027-01-05 23:59:59', 'R270105042'],
            '1000 以降は桁が伸びる' => [1234, '2026-12-01 00:00:00', 'R2612011234'],
        ];
    }

    #[DataProvider('orderNumberCases')]
    public function testFormatOrderNumber(int $orderId, string $orderDate, string $expected): void
    {
        self::assertSame($expected, format_order_number($orderId, $orderDate));
    }

    /** @return array<string, array{mixed, ?string}> */
    public static function returnCases(): array
    {
        return [
            'カート'         => [['page' => 'cart'], 'cart.php'],
            '購入内容確認'   => [['page' => 'purchase_confirm'], 'purchase_confirm.php'],
            '商品詳細'       => [['page' => 'product', 'id' => 5], 'product.php?id=5'],
            '商品詳細 ID なし' => [['page' => 'product'], null],
            '商品詳細 ID が文字列' => [['page' => 'product', 'id' => '5'], null],
            '商品詳細 ID が 0'     => [['page' => 'product', 'id' => 0], null],
            '未知のページ'   => [['page' => 'https://evil.example/'], null],
            'URL 文字列そのもの' => ['//evil.example/', null],
            '未設定'         => [null, null],
        ];
    }

    #[DataProvider('returnCases')]
    public function testLoginReturnPathOnlyBuildsKnownPages(mixed $saved, ?string $expected): void
    {
        self::assertSame($expected, login_return_path($saved));
    }

    public function testTakeLoginReturnIsSingleUse(): void
    {
        $session = ['login_return' => ['page' => 'product', 'id' => 5]];

        self::assertSame('product.php?id=5', take_login_return($session));
        self::assertArrayNotHasKey('login_return', $session);
        self::assertNull(take_login_return($session));
    }

    public function testTakeLoginReturnDropsBrokenValue(): void
    {
        $session = ['login_return' => ['page' => 'https://evil.example/']];

        self::assertNull(take_login_return($session));
        self::assertArrayNotHasKey('login_return', $session);
    }

    public function testRequireLoginReturnsTheMemberWhenLoggedIn(): void
    {
        $member = ['member_id' => 1, 'birth_date' => '2000-01-01'];

        self::assertSame($member, require_login($member, ['page' => 'cart']));
    }

    public function testPurchaseTokenIsSingleSlotAndExpires(): void
    {
        $session = [];
        $first = start_purchase($session, [5 => 2480], self::NOW);
        $second = start_purchase($session, [5 => 2480, 2 => 3300], self::NOW);

        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $second);
        self::assertNull(purchase_snapshot($session, $first, self::NOW), '別タブで開き直した古い確認は無効');
        self::assertSame([5 => 2480, 2 => 3300], purchase_snapshot($session, $second, self::NOW));
        self::assertNull(purchase_snapshot($session, $second, self::NOW + PURCHASE_TTL_SECONDS), '30 分で失効');
        self::assertNull(purchase_snapshot($session, ['x'], self::NOW));
    }

    public function testFinishedPurchaseIsRememberedForResubmit(): void
    {
        $session = [];
        $token = start_purchase($session, [5 => 2480], self::NOW);

        finish_purchase($session, $token, 10);

        self::assertArrayNotHasKey('purchase', $session);
        self::assertSame(10, finished_order_id($session, $token));
        self::assertNull(finished_order_id($session, 'other'));
        self::assertNull(finished_order_id($session, null));
    }

    public function testFinishedPurchasesKeepOnlyTheLatestFew(): void
    {
        $session = [];
        for ($i = 1; $i <= PURCHASE_DONE_KEEP + 2; $i++) {
            finish_purchase($session, "t{$i}", $i);
        }

        self::assertCount(PURCHASE_DONE_KEEP, $session['purchase_done']);
        self::assertNull(finished_order_id($session, 't1'));
        self::assertSame(PURCHASE_DONE_KEEP + 2, finished_order_id($session, 't' . (PURCHASE_DONE_KEEP + 2)));
    }

    public function testUnbuyableDetailMessageFollowsFigmaU09(): void
    {
        self::assertSame('すでに購入済みのため、再度購入することはできません。', unbuyable_detail_message(['reason' => '購入済み', 'age_rating' => 'A']));
        self::assertSame('この商品は販売を終了しています。', unbuyable_detail_message(['reason' => '販売終了', 'age_rating' => 'A']));
        self::assertSame('年齢区分（CERO C）の対象年齢に達していないため購入できません。', unbuyable_detail_message(['reason' => '年齢制限', 'age_rating' => 'C']));
    }
}
