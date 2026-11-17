<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/search.php';

final class SearchTest extends TestCase
{
    public function testEmptyQueryGivesNoConditionsAndRecommendSort(): void
    {
        $result = parse_search_criteria([]);

        self::assertSame([], $result['errors']);
        self::assertSame([
            'keyword' => '', 'terms' => [], 'players' => [], 'os' => [], 'play_style' => [],
            'crossplay' => false, 'genre' => [], 'price_min' => null, 'price_max' => null, 'sort' => 'recommend',
        ], $result['criteria']);
    }

    public function testOnlyWhitelistedValuesAreKept(): void
    {
        $result = parse_search_criteria([
            'players' => ['5plus', '2', 'evil', '2'], 'os' => ['mac', 'linux'], 'play_style' => ['対戦', 'ソロ'],
            'genre' => ['レース', 'FPS', "FPS' OR 1=1"], 'crossplay' => '1', 'sort' => 'price_desc',
        ]);

        $c = $result['criteria'];
        self::assertSame(['2', '5plus'], $c['players'], '対応表の順に並べ直し、重複と未知の値を捨てる');
        self::assertSame(['mac'], $c['os']);
        self::assertSame(['対戦'], $c['play_style']);
        self::assertSame(['FPS', 'レース'], $c['genre']);
        self::assertTrue($c['crossplay']);
        self::assertSame('price_desc', $c['sort']);
    }

    public function testNonArrayOrBrokenParametersAreIgnored(): void
    {
        $result = parse_search_criteria([
            'players' => '2', 'os' => [['windows']], 'crossplay' => 'yes', 'sort' => ['new'], 'keyword' => ['x'],
        ]);

        $c = $result['criteria'];
        self::assertSame([], $c['players']);
        self::assertSame([], $c['os']);
        self::assertFalse($c['crossplay']);
        self::assertSame('recommend', $c['sort']);
        self::assertSame('', $c['keyword']);
    }

    /** @return array<string, array{array<string, string>, ?int, ?int, ?string}> */
    public static function priceCases(): array
    {
        return [
            '両方空'           => [['price_min' => '', 'price_max' => ''], null, null, null],
            '正常'             => [['price_min' => '0', 'price_max' => '5000'], 0, 5000, null],
            '全角数字も受ける' => [['price_min' => '１０００'], 1000, null, null],
            '負の数'           => [['price_min' => '-1'], null, null, '価格は0〜1000000の整数で入力してください'],
            '小数'             => [['price_max' => '10.5'], null, null, '価格は0〜1000000の整数で入力してください'],
            '上限超え'         => [['price_max' => '1000001'], null, null, '価格は0〜1000000の整数で入力してください'],
            '下限が上限より大きい' => [['price_min' => '5000', 'price_max' => '100'], null, null, '価格の下限は上限以下にしてください'],
        ];
    }

    #[DataProvider('priceCases')]
    public function testPriceRange(array $get, ?int $min, ?int $max, ?string $error): void
    {
        $result = parse_search_criteria($get);

        self::assertSame($min, $result['criteria']['price_min']);
        self::assertSame($max, $result['criteria']['price_max']);
        self::assertSame($error, $result['errors']['price'] ?? null);
    }

    public function testKeywordIsNormalizedAndSplitIntoTerms(): void
    {
        $result = parse_search_criteria(['keyword' => '　ｎｅｏｎ　ｻｲﾄ  ']);

        self::assertSame('neon サイト', $result['criteria']['keyword']);
        self::assertSame(['neon', 'サイト'], $result['criteria']['terms']);
    }

    public function testKeywordOverFiftyCharactersIsAnErrorAndNotUsed(): void
    {
        $ok = parse_search_criteria(['keyword' => str_repeat('あ', 50)]);
        $ng = parse_search_criteria(['keyword' => str_repeat('あ', 51)]);

        self::assertArrayNotHasKey('keyword', $ok['errors']);
        self::assertSame([str_repeat('あ', 50)], $ok['criteria']['terms']);
        self::assertSame('キーワードは50文字以内で入力してください', $ng['errors']['keyword']);
        self::assertSame([], $ng['criteria']['terms']);
    }

    public function testLikeEscapeEscapesWildcards(): void
    {
        self::assertSame('100\%\_a\\\\b', like_escape('100%_a\b'));
    }

    public function testSearchWhereWithoutConditionsOnlyRestrictsToPublic(): void
    {
        $where = search_where(parse_search_criteria([])['criteria']);

        self::assertSame('p.public_flag = 1', $where['sql']);
        self::assertSame([], $where['params']);
    }

    public function testSearchWhereCombinesKindsWithAndAndValuesWithOr(): void
    {
        $criteria = parse_search_criteria([
            'keyword' => 'ネオン サイト', 'players' => ['2', '3-4', '5plus'], 'os' => ['windows', 'mac'],
            'play_style' => ['協力'], 'crossplay' => '1', 'genre' => ['FPS', 'MMO'],
            'price_min' => '100', 'price_max' => '5000',
        ])['criteria'];

        $where = search_where($criteria);

        self::assertSame(
            'p.public_flag = 1'
            . " AND (p.name LIKE ? ESCAPE '\\\\' OR p.title_en LIKE ? ESCAPE '\\\\')"
            . " AND (p.name LIKE ? ESCAPE '\\\\' OR p.title_en LIKE ? ESCAPE '\\\\')"
            . ' AND ((p.min_players <= 2 AND p.max_players >= 2)'
            . ' OR (p.min_players <= 3 AND p.max_players >= 4)'
            . ' OR (p.max_players >= 5))'
            . ' AND (p.os_windows = 1 OR p.os_mac = 1)'
            . ' AND p.play_style IN (?)'
            . ' AND p.genre IN (?, ?)'
            . ' AND p.crossplay_flag = 1'
            . ' AND p.price >= ?'
            . ' AND p.price <= ?',
            $where['sql'],
        );
        self::assertSame(['%ネオン%', '%ネオン%', '%サイト%', '%サイト%', '協力', 'FPS', 'MMO', 100, 5000], $where['params']);
    }

    /** @return array<string, array{string, string}> */
    public static function sortCases(): array
    {
        return [
            'recommend'  => ['recommend', 'p.recommend_order ASC, p.product_id ASC'],
            'new'        => ['new', 'p.release_date DESC, p.product_id ASC'],
            'price_asc'  => ['price_asc', 'p.price ASC, p.product_id ASC'],
            'price_desc' => ['price_desc', 'p.price DESC, p.product_id ASC'],
            '不正値'     => ['DROP TABLE', 'p.recommend_order ASC, p.product_id ASC'],
        ];
    }

    #[DataProvider('sortCases')]
    public function testSearchOrderBy(string $sort, string $expected): void
    {
        $criteria = parse_search_criteria(['sort' => $sort])['criteria'];

        self::assertSame($expected, search_order_by($criteria['sort']));
    }
}
