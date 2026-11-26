<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/format.php';

final class FormatTest extends TestCase
{
    public function testYen(): void
    {
        self::assertSame('¥2,480', yen(2480));
        self::assertSame('¥0', yen(0));
    }

    public function testPlayersLabel(): void
    {
        self::assertSame('1〜8人', players_label(['min_players' => 1, 'max_players' => 8]));
        self::assertSame('2人', players_label(['min_players' => '2', 'max_players' => '2']));
    }

    public function testOsLabel(): void
    {
        self::assertSame('Windows・Mac', os_label(['os_windows' => 1, 'os_mac' => 1]));
        self::assertSame('Windows', os_label(['os_windows' => 1, 'os_mac' => 0]));
        self::assertSame('Mac', os_label(['os_windows' => '0', 'os_mac' => '1']));
    }

    /** @return array<string, array{array<string, int>, string}> */
    public static function crossplayCases(): array
    {
        return [
            '両OS・可'   => [['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 1], '○ できる'],
            '両OS・不可' => [['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 0], '× できない'],
            // U02-003：片方の OS だけの商品は「対象外」
            'Windows のみ' => [['os_windows' => 1, 'os_mac' => 0, 'crossplay_flag' => 0], '対象外'],
            'Mac のみ'     => [['os_windows' => 0, 'os_mac' => 1, 'crossplay_flag' => 1], '対象外'],
        ];
    }

    #[DataProvider('crossplayCases')]
    public function testCrossplayLabel(array $row, string $expected): void
    {
        self::assertSame($expected, crossplay_label($row));
    }

    public function testSpecLine(): void
    {
        self::assertSame(
            '1〜8人 / 協力 / Windows・Mac / クロスプレイ ○ できる',
            spec_line(['min_players' => 1, 'max_players' => 8, 'play_style' => '協力', 'os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 1]),
        );
    }

    /** @return array<string, array{array<string, int>, string}> */
    public static function osTagCases(): array
    {
        return [
            '両方'         => [['os_windows' => 1, 'os_mac' => 1], 'Win / Mac'],
            'Windows のみ' => [['os_windows' => 1, 'os_mac' => 0], 'Windowsのみ'],
            'Mac のみ'     => [['os_windows' => 0, 'os_mac' => 1], 'Macのみ'],
        ];
    }

    #[DataProvider('osTagCases')]
    public function testOsTagLabel(array $row, string $expected): void
    {
        self::assertSame($expected, os_tag_label($row));
    }

    /** @return array<string, array{array<string, int>, string, bool}> */
    public static function crossplayTagCases(): array
    {
        return [
            'できる'   => [['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 1], 'クロスプレイ○', true],
            'できない' => [['os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 0], 'クロスプレイ×', false],
            '片方 OS'  => [['os_windows' => 1, 'os_mac' => 0, 'crossplay_flag' => 1], 'クロスプレイ対象外', false],
        ];
    }

    #[DataProvider('crossplayTagCases')]
    public function testCrossplayTag(array $row, string $label, bool $available): void
    {
        self::assertSame(['label' => $label, 'available' => $available], crossplay_tag($row));
    }

    public function testDisplayDatetime(): void
    {
        self::assertSame('2026/11/17 09:05', display_datetime('2026-11-17 09:05:59'));
        self::assertSame('2025/05/16', display_date('2025-05-16'));
    }

    public function testChecked(): void
    {
        self::assertSame(' checked', checked(true));
        self::assertSame('', checked(false));
    }

    /** @return array<string, array{mixed, ?int}> */
    public static function positiveIntCases(): array
    {
        return [
            '正の整数'   => ['5', 5],
            '先頭ゼロ'   => ['05', 5],
            '0'          => ['0', null],
            '負'         => ['-1', null],
            '小数'       => ['1.5', null],
            '文字'       => ['abc', null],
            '空'         => ['', null],
            '桁が多すぎる' => ['1234567890', null],
            '配列'       => [['5'], null],
            'null'       => [null, null],
        ];
    }

    #[DataProvider('positiveIntCases')]
    public function testParsePositiveInt(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, parse_positive_int($value));
    }
}
