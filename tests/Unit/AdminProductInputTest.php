<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/admin_product.php';
require_once __DIR__ . '/../../includes/search.php';

/** A06 商品登録・編集の入力チェック（テスト仕様書（管理画面）A06-003〜A06-015・Plan §2.0 A06 入力規則） */
final class AdminProductInputTest extends TestCase
{
    /** @return array<string, string> */
    private static function valid(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'ブロックフィールド開拓記', 'title_en' => 'BLOCKFIELD FRONTIER', 'genre' => 'クラフト',
            'price' => '2480', 'capacity' => '3.2', 'release_date' => '2025-05-16', 'developer' => 'Cubeworks',
            'age_rating' => 'A', 'description' => "1行目\n2行目", 'min_players' => '1', 'max_players' => '8',
            'play_style' => '協力', 'play_time' => '約30分', 'os_windows' => '1', 'os_mac' => '1', 'crossplay_flag' => '1',
        ];
    }

    public function testValidInputIsNormalized(): void
    {
        $result = validate_product_input(self::valid(['name' => '  ブロックフィールド開拓記　']));

        self::assertSame([], $result['errors']);
        self::assertSame([
            'name' => 'ブロックフィールド開拓記', 'title_en' => 'BLOCKFIELD FRONTIER', 'genre' => 'クラフト',
            'price' => 2480, 'capacity' => '3.2', 'release_date' => '2025-05-16', 'developer' => 'Cubeworks',
            'age_rating' => 'A', 'description' => "1行目\n2行目", 'min_players' => 1, 'max_players' => 8,
            'play_style' => '協力', 'play_time' => '約30分', 'os_windows' => 1, 'os_mac' => 1, 'crossplay_flag' => 1,
        ], $result['input']);
    }

    public function testEnglishNameIsOptional(): void
    {
        $result = validate_product_input(self::valid(['title_en' => '  ']));
        self::assertSame([], $result['errors']);
        self::assertNull($result['input']['title_en']);
    }

    public function testAllEmptyGivesAnErrorForEveryRequiredField(): void
    {
        $errors = validate_product_input([])['errors'];
        foreach (['name', 'genre', 'price', 'capacity', 'release_date', 'developer', 'age_rating', 'description',
            'min_players', 'play_style', 'play_time', 'os'] as $field) {
            self::assertArrayHasKey($field, $errors, $field);
        }
        self::assertArrayNotHasKey('title_en', $errors);
    }

    public function testSpacesOnlyIsEmpty(): void
    {
        $errors = validate_product_input(self::valid(['name' => "　 \t", 'description' => "\n\n", 'developer' => ' ', 'play_time' => '　']))['errors'];
        self::assertSame(['name', 'developer', 'description', 'play_time'], array_keys($errors));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function fieldCases(): iterable
    {
        yield 'name 100' => ['name', str_repeat('あ', 100), true];
        yield 'name 101' => ['name', str_repeat('あ', 101), false];
        yield 'title_en 101' => ['title_en', str_repeat('a', 101), false];
        yield 'price 0' => ['price', '0', true];
        yield 'price max' => ['price', '1000000', true];
        yield 'price over' => ['price', '1000001', false];
        yield 'price negative' => ['price', '-1', false];
        yield 'price decimal' => ['price', '100.5', false];
        yield 'price zenkaku' => ['price', '２４８０', false];
        yield 'price text' => ['price', 'abc', false];
        yield 'price comma' => ['price', '2,480', false];
        yield 'capacity small' => ['capacity', '0.1', true];
        yield 'capacity int' => ['capacity', '62', true];
        yield 'capacity max' => ['capacity', '999.9', true];
        yield 'capacity over db' => ['capacity', '1000', false];
        yield 'capacity zero' => ['capacity', '0', false];
        yield 'capacity 2 decimals' => ['capacity', '3.25', false];
        yield 'capacity over' => ['capacity', '10000', false];
        yield 'capacity negative' => ['capacity', '-3', false];
        yield 'capacity text' => ['capacity', '3GB', false];
        yield 'date future' => ['release_date', '2030-01-01', true];
        yield 'date leap' => ['release_date', '2028-02-29', true];
        yield 'date not real' => ['release_date', '2027-02-29', false];
        yield 'date format' => ['release_date', '2025/05/16', false];
        yield 'developer 101' => ['developer', str_repeat('a', 101), false];
        yield 'description 2000' => ['description', str_repeat('あ', 2000), true];
        yield 'description 2001' => ['description', str_repeat('あ', 2001), false];
        yield 'play_time 20' => ['play_time', str_repeat('分', 20), true];
        yield 'play_time 21' => ['play_time', str_repeat('分', 21), false];
        yield 'genre outside' => ['genre', 'RPG', false];
        yield 'genre pvp' => ['genre', '対戦(PVP)', true];
        yield 'cero Z' => ['age_rating', 'Z', true];
        yield 'cero outside' => ['age_rating', 'E', false];
        yield 'style outside' => ['play_style', 'ソロ', false];
    }

    #[DataProvider('fieldCases')]
    public function testEachFieldBoundary(string $field, string $value, bool $ok): void
    {
        $errors = validate_product_input(self::valid([$field => $value]))['errors'];
        if ($ok) {
            self::assertSame([], $errors);
        } else {
            self::assertArrayHasKey($field, $errors);
        }
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function playerCases(): iterable
    {
        yield 'same' => ['2', '2', true];
        yield 'max 99' => ['1', '99', true];
        yield 'min > max' => ['5', '4', false];
        yield 'zero' => ['0', '4', false];
        yield 'negative' => ['-1', '4', false];
        yield 'decimal' => ['1.5', '4', false];
        yield 'text' => ['two', '4', false];
        yield 'max 100' => ['1', '100', false];
        yield 'max empty' => ['1', '', false];
    }

    #[DataProvider('playerCases')]
    public function testPlayers(string $min, string $max, bool $ok): void
    {
        $errors = validate_product_input(self::valid(['min_players' => $min, 'max_players' => $max]))['errors'];
        self::assertSame($ok, !isset($errors['min_players']), json_encode($errors, JSON_UNESCAPED_UNICODE));
    }

    public function testOsAndCrossplay(): void
    {
        $noOs = self::valid(['crossplay_flag' => '']);
        unset($noOs['os_windows'], $noOs['os_mac']);
        self::assertArrayHasKey('os', validate_product_input($noOs)['errors'], 'A06-012');

        $winOnly = self::valid(['os_mac' => '']);
        self::assertArrayHasKey('crossplay_flag', validate_product_input($winOnly)['errors'], 'A06-013');

        $winOnlyNoCross = validate_product_input(self::valid(['os_mac' => '', 'crossplay_flag' => '']));
        self::assertSame([], $winOnlyNoCross['errors']);
        self::assertSame([1, 0, 0], [
            $winOnlyNoCross['input']['os_windows'], $winOnlyNoCross['input']['os_mac'], $winOnlyNoCross['input']['crossplay_flag'],
        ]);
    }

    public function testArraysAndOddValuesAreRejectedNotCrashing(): void
    {
        $result = validate_product_input(self::valid(['name' => ['x'], 'price' => ['1'], 'os_windows' => ['1'], 'os_mac' => '2']));
        self::assertArrayHasKey('name', $result['errors']);
        self::assertArrayHasKey('price', $result['errors']);
        self::assertArrayHasKey('os', $result['errors']);
    }

    public function testGenresAreTheSameAsTheSearchFilter(): void
    {
        self::assertSame(PRODUCT_GENRES, SEARCH_GENRES, 'A06-014：U02 の絞り込みと一致');
        self::assertSame(['A', 'B', 'C', 'D', 'Z'], array_keys(CERO_TARGET_LABELS));
    }
}
