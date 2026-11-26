<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Support;

use PDO;

/**
 * 商品・会員・注文のテストデータ（playduo_test 専用。値はすべて架空）。
 * 商品は seed.sql と同じ 8 件の名称・価格・人数を使う（テスト仕様書の例と照合しやすくするため）。
 */
final class ShopFixtures
{
    public const BLOCKFIELD = 5;   // ブロックフィールド開拓記 ¥2,480 CERO A 1〜8 人 協力 両OS
    public const NEONSIGHT = 2;    // ネオンサイト ¥3,300 CERO C 2〜8 人 対戦 両OS
    public const CLOUDPATH = 4;    // 雲路の旅団 ¥2,860 CERO A Mac のみ

    /** @var array<int, array<string, mixed>> product_id => 列 */
    private const PRODUCTS = [
        1 => ['鉄骨前線', 'IRONLINE', 4180, 'FPS', 'C', 2, 10, '対戦', 0, 1, 0, '2026-03-12', 5],
        2 => ['ネオンサイト', 'NEONSIGHT', 3300, 'FPS', 'C', 2, 8, '対戦', 1, 1, 1, '2025-11-20', 2],
        3 => ['千年樹オンライン', 'THOUSAND TREE ONLINE', 2200, 'MMO', 'B', 2, 40, '協力', 1, 1, 1, '2025-07-01', 3],
        4 => ['雲路の旅団', 'CLOUDPATH BRIGADE', 2860, 'MMO', 'A', 2, 20, '協力', 0, 0, 1, '2026-01-28', 6],
        5 => ['ブロックフィールド開拓記', 'BLOCKFIELD FRONTIER', 2480, 'クラフト', 'A', 1, 8, '協力', 1, 1, 1, '2025-05-16', 1],
        6 => ['アリーナ・ゼロ', 'ARENA ZERO', 3080, '対戦(PVP)', 'B', 2, 2, '対戦', 0, 1, 0, '2025-09-09', 7],
        7 => ['双月決闘', 'TWIN MOON DUEL', 2640, '対戦(PVP)', 'B', 2, 2, '対戦', 1, 1, 1, '2026-04-24', 4],
        8 => ['湾岸シグナル', 'BAYLINE SIGNAL', 3960, 'レース', 'A', 2, 8, '対戦', 0, 1, 0, '2026-02-19', 8],
    ];

    public static function insertAllProducts(PDO $pdo): void
    {
        foreach (array_keys(self::PRODUCTS) as $id) {
            self::insertProduct($pdo, $id);
        }
    }

    /** @param array<string, mixed> $overrides */
    public static function insertProduct(PDO $pdo, int $id, array $overrides = []): void
    {
        [$name, $en, $price, $genre, $cero, $min, $max, $style, $cross, $win, $mac, $release, $order] = self::PRODUCTS[$id]
            ?? ["テスト商品{$id}", null, 1000, 'FPS', 'A', 2, 4, '協力', 0, 1, 0, '2026-01-01', 100 + $id];
        $row = $overrides + [
            'product_id' => $id, 'name' => $name, 'title_en' => $en, 'price' => $price,
            'description' => "{$name}の説明", 'genre' => $genre, 'capacity' => 10.5, 'age_rating' => $cero,
            'developer' => 'Test Studio', 'release_date' => $release,
            'image' => "test/g{$id}_cover.png", 'screenshot1' => "test/g{$id}_ss1.png",
            'screenshot2' => "test/g{$id}_ss2.png", 'screenshot3' => "test/g{$id}_ss3.png",
            'min_players' => $min, 'max_players' => $max, 'play_style' => $style, 'crossplay_flag' => $cross,
            'os_windows' => $win, 'os_mac' => $mac, 'play_time' => '約30分', 'recommend_order' => $order,
            'file_path' => "downloads/g{$id}.zip", 'public_flag' => 1, 'created_at' => '2026-11-01 00:00:00',
        ];
        $columns = array_keys($row);
        $pdo->prepare(
            'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
        )->execute(array_values($row));
    }

    public static function insertMember(PDO $pdo, string $email, string $birthDate = '2000-01-01', string $password = 'abc12345'): int
    {
        $pdo->prepare(
            'INSERT INTO members (name, email, password, birth_date, totp_secret, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        )->execute([
            'テスト会員', $email, password_hash($password, PASSWORD_DEFAULT), $birthDate,
            'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '2026-11-01 00:00:00',
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function insertAdmin(PDO $pdo, string $loginId = 'admin', string $password = 'admin1234'): int
    {
        $pdo->prepare('INSERT INTO admins (login_id, password) VALUES (?, ?)')
            ->execute([$loginId, password_hash($password, PASSWORD_DEFAULT)]);
        return (int) $pdo->lastInsertId();
    }

    public static function addToCart(PDO $pdo, int $memberId, int $productId): void
    {
        $pdo->prepare('INSERT INTO carts (member_id, product_id) VALUES (?, ?)')->execute([$memberId, $productId]);
    }

    // 購入済みにする（注文 1 件に指定の商品をまとめる）
    public static function purchase(PDO $pdo, int $memberId, int ...$productIds): int
    {
        $pdo->prepare('INSERT INTO orders (member_id, order_date) VALUES (?, ?)')->execute([$memberId, '2026-11-10 10:00:00']);
        $orderId = (int) $pdo->lastInsertId();
        foreach ($productIds as $productId) {
            $pdo->prepare(
                'INSERT INTO order_details (order_id, product_id, member_id, product_name, price)
                 SELECT ?, product_id, ?, name, price FROM products WHERE product_id = ?',
            )->execute([$orderId, $memberId, $productId]);
        }
        return $orderId;
    }

    public static function count(PDO $pdo, string $table): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
}
