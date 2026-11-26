<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/admin_order_repo.php';

/** A10 の検索条件の解釈（テスト仕様書（管理画面）A10-005・A10-006、Plan §2.0 A10 検索） */
final class AdminOrderCriteriaTest extends TestCase
{
    public function testEmptyMeansNoCondition(): void
    {
        self::assertSame(
            ['criteria' => ['date_from' => null, 'date_to' => null, 'member_id' => null, 'member_name' => null, 'product' => null], 'errors' => []],
            parse_order_criteria([]),
        );
        self::assertSame([], parse_order_criteria(['date_from' => ' ', 'member' => '　', 'product' => ''])['errors']);
    }

    public function testDates(): void
    {
        $one = parse_order_criteria(['date_from' => '2026-09-28', 'date_to' => '']);
        self::assertSame('2026-09-28', $one['criteria']['date_from']);
        self::assertNull($one['criteria']['date_to']);
        self::assertSame([], parse_order_criteria(['date_from' => '2026-09-28', 'date_to' => '2026-09-28'])['errors'], '同日');

        self::assertArrayHasKey('date', parse_order_criteria(['date_from' => '2026-10-01', 'date_to' => '2026-09-30'])['errors']);
        self::assertArrayHasKey('date', parse_order_criteria(['date_from' => '2026-02-30'])['errors']);
        self::assertArrayHasKey('date', parse_order_criteria(['date_to' => 'abc'])['errors']);
        self::assertArrayHasKey('date', parse_order_criteria(['date_to' => ['2026-01-01']])['errors']);
    }

    public function testMemberIsIdOrName(): void
    {
        self::assertSame(1, parse_order_criteria(['member' => 'M0001'])['criteria']['member_id']);
        self::assertSame(1, parse_order_criteria(['member' => 'm0001'])['criteria']['member_id']);
        self::assertSame(12, parse_order_criteria(['member' => ' 12 '])['criteria']['member_id']);
        $name = parse_order_criteria(['member' => '山田'])['criteria'];
        self::assertNull($name['member_id']);
        self::assertSame('山田', $name['member_name']);
    }

    public function testTooLongTextIsAnError(): void
    {
        self::assertArrayHasKey('member', parse_order_criteria(['member' => str_repeat('あ', 51)])['errors']);
        self::assertArrayHasKey('product', parse_order_criteria(['product' => str_repeat('あ', 51)])['errors']);
    }

    public function testDateRangeSqlIsHalfOpen(): void
    {
        self::assertSame(['2026-09-28 00:00:00', '2026-09-29 00:00:00'], order_date_bounds('2026-09-28', '2026-09-28'));
        self::assertSame(['2026-12-31 00:00:00', '2027-01-01 00:00:00'], order_date_bounds('2026-12-31', '2026-12-31'));
        self::assertSame([null, null], order_date_bounds(null, null));
    }
}
