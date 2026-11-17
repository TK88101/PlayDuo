<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/age.php';

final class AgeTest extends TestCase
{
    // 日本時間の正午にそろえる（日付の境目の誤差を避ける）
    private static function noonJst(string $date): int
    {
        return (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('Asia/Tokyo')))->getTimestamp();
    }

    /** @return array<string, array{string, string, int}> */
    public static function ageCases(): array
    {
        return [
            '誕生日の前日はまだ加算しない' => ['2011-11-18', '2026-11-17', 14],
            '誕生日当日から加算する'       => ['2011-11-17', '2026-11-17', 15],
            '誕生日の翌日'                 => ['2011-11-16', '2026-11-17', 15],
            '2/29 生まれは平年の 2/28 では未到達' => ['2008-02-29', '2026-02-28', 17],
            '2/29 生まれは平年の 3/1 で到達'      => ['2008-02-29', '2026-03-01', 18],
        ];
    }

    #[DataProvider('ageCases')]
    public function testAgeOnCountsFromTheBirthdayItself(string $birthDate, string $today, int $expected): void
    {
        self::assertSame($expected, age_on($birthDate, self::noonJst($today)));
    }

    public function testAgeUsesJapanTimeNotUtc(): void
    {
        // 日本時間 11/17 00:30 ＝ UTC では 11/16。日本時間で誕生日当日として数える
        $now = (new \DateTimeImmutable('2026-11-17 00:30:00', new \DateTimeZone('Asia/Tokyo')))->getTimestamp();

        self::assertSame(15, age_on('2011-11-17', $now));
    }

    /** @return array<string, array{string, int}> */
    public static function ceroCases(): array
    {
        return [
            'A' => ['A', 0], 'B' => ['B', 12], 'C' => ['C', 15], 'D' => ['D', 17], 'Z' => ['Z', 18],
            '未知の値は安全側の 18' => ['X', 18],
            '空文字も 18' => ['', 18],
        ];
    }

    #[DataProvider('ceroCases')]
    public function testCeroMinAge(string $rating, int $expected): void
    {
        self::assertSame($expected, cero_min_age($rating));
    }

    public function testIsAgeAllowedOnBoundary(): void
    {
        $today = self::noonJst('2026-11-17');

        self::assertFalse(is_age_allowed('2011-11-18', 'C', $today), '15 歳の前日は C を買えない');
        self::assertTrue(is_age_allowed('2011-11-17', 'C', $today), '15 歳の誕生日当日から C を買える');
        self::assertTrue(is_age_allowed('2026-01-01', 'A', $today), 'A は全年齢');
    }
}
