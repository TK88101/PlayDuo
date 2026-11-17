<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PlayDuo\Tests\Support\ShopFixtures;
use PlayDuo\Tests\Support\TestDb;

require_once __DIR__ . '/../../includes/product_repo.php';

final class ProductRepoTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();
        TestDb::truncateAll($this->pdo);
        ShopFixtures::insertAllProducts($this->pdo);
    }

    /** @return list<string> */
    private function names(array $get): array
    {
        $rows = search_products($this->pdo, parse_search_criteria($get)['criteria']);
        return array_column($rows, 'name');
    }

    public function testRecommendedProductsAreTheFirstFourByRecommendOrder(): void
    {
        $rows = find_recommended_products($this->pdo);

        // テスト仕様書 U01-002
        self::assertSame(['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '双月決闘'], array_column($rows, 'name'));
        self::assertSame(['product_id', 'name', 'image', 'price', 'min_players', 'max_players', 'play_style',
            'os_windows', 'os_mac', 'crossplay_flag'], array_keys($rows[0]));
    }

    public function testRecommendedSkipsPrivateProducts(): void
    {
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = ' . ShopFixtures::BLOCKFIELD);

        self::assertNotContains('ブロックフィールド開拓記', array_column(find_recommended_products($this->pdo), 'name'));
    }

    public function testNoConditionsReturnsAllPublicProductsInRecommendOrder(): void
    {
        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = 8');

        $names = $this->names([]);

        self::assertCount(7, $names);
        self::assertSame('ブロックフィールド開拓記', $names[0]);
        self::assertNotContains('湾岸シグナル', $names);
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function filterCases(): array
    {
        return [
            // Q02：選んだ人数の全員で遊べる商品だけ
            '2人'     => [['players' => ['2']], ['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '双月決闘', '鉄骨前線', '雲路の旅団', 'アリーナ・ゼロ', '湾岸シグナル']],
            '3〜4人'  => [['players' => ['3-4']], ['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '鉄骨前線', '雲路の旅団', '湾岸シグナル']],
            '5人以上は鉄骨前線(2〜10)を含み 2人専用を除く' => [['players' => ['5plus']], ['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '鉄骨前線', '雲路の旅団', '湾岸シグナル']],
            'Macのみ'  => [['os' => ['mac']], ['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '双月決闘', '雲路の旅団']],
            '協力'     => [['play_style' => ['協力']], ['ブロックフィールド開拓記', '千年樹オンライン', '雲路の旅団']],
            'クロスプレイ' => [['crossplay' => '1'], ['ブロックフィールド開拓記', 'ネオンサイト', '千年樹オンライン', '双月決闘']],
            'ジャンル複数は OR' => [['genre' => ['FPS', 'MMO']], ['ネオンサイト', '千年樹オンライン', '鉄骨前線', '雲路の旅団']],
            // U02-009：上限ちょうどを含む
            '価格 上限ちょうど' => [['price_max' => '2480'], ['ブロックフィールド開拓記', '千年樹オンライン']],
            '価格 下限ちょうど' => [['price_min' => '3960'], ['鉄骨前線', '湾岸シグナル']],
            // U01-005：種類どうしは AND
            '複数条件 AND' => [['players' => ['3-4'], 'os' => ['windows', 'mac'], 'play_style' => ['協力'], 'crossplay' => '1'], ['ブロックフィールド開拓記', '千年樹オンライン']],
            '0件'      => [['play_style' => ['協力'], 'genre' => ['レース']], []],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('filterCases')]
    public function testFilters(array $get, array $expected): void
    {
        self::assertSame($expected, $this->names($get));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function keywordCases(): array
    {
        return [
            '名称の部分一致'      => ['ブロック', ['ブロックフィールド開拓記']],
            '英語名・大小文字無視' => ['blockfield', ['ブロックフィールド開拓記']],
            '空白区切りは各語 AND' => ['ネオン サイト', ['ネオンサイト']],
            '全角英字'            => ['ｎｅｏｎ', ['ネオンサイト']],
            '半角カナ'            => ['ﾈｵﾝ', ['ネオンサイト']],
            '該当なし'            => ['zzzzzzzz', []],
            '% は文字として扱う'  => ['%', []],
            '_ は文字として扱う'  => ['_', []],
            'SQL 文字列'          => ["' OR '1'='1", []],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('keywordCases')]
    public function testKeywordSearch(string $keyword, array $expected): void
    {
        self::assertSame($expected, $this->names(['keyword' => $keyword]));
    }

    public function testSortOrders(): void
    {
        self::assertSame('ネオンサイト', $this->names(['sort' => 'new', 'genre' => ['FPS']])[1], '発売日の新しい順（鉄骨前線 2026-03 → ネオンサイト 2025-11）');
        self::assertSame(['千年樹オンライン', 'ブロックフィールド開拓記'], array_slice($this->names(['sort' => 'price_asc']), 0, 2));
        self::assertSame('鉄骨前線', $this->names(['sort' => 'price_desc'])[0]);
    }

    public function testFindPublicProductReturnsAllColumnsOrNull(): void
    {
        $product = find_public_product($this->pdo, ShopFixtures::BLOCKFIELD);
        self::assertSame('ブロックフィールド開拓記', $product['name']);
        self::assertSame('downloads/g5.zip', $product['file_path']);

        $this->pdo->exec('UPDATE products SET public_flag = 0 WHERE product_id = ' . ShopFixtures::BLOCKFIELD);
        self::assertNull(find_public_product($this->pdo, ShopFixtures::BLOCKFIELD), '非公開は見せない（Q25）');
        self::assertNull(find_public_product($this->pdo, 99999));
    }
}
