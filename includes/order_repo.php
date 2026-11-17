<?php

declare(strict_types=1);

// orders / order_details の DB 操作（合計金額は持たず、order_details.price の合計で出す）
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * 呼び出し側のトランザクションの中で使う。商品名・価格は今の値を注文詳細に保存する。
 * @param list<array{product_id: int|string, name: string, price: int|string}> $rows
 */
function create_order(PDO $pdo, int $memberId, array $rows, int $now): int
{
    $pdo->prepare('INSERT INTO orders (member_id, order_date) VALUES (?, ?)')->execute([$memberId, db_datetime($now)]);
    $orderId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO order_details (order_id, product_id, member_id, product_name, price) VALUES (?, ?, ?, ?, ?)',
    );
    foreach ($rows as $row) {
        $stmt->execute([$orderId, (int) $row['product_id'], $memberId, $row['name'], (int) $row['price']]);
    }
    return $orderId;
}

/**
 * U10：自分の注文だけ（他人の注文・存在しない ID は null）。
 * @return array{order: array{order_id: int, order_date: string}, items: list<array>, total: int}|null
 */
function find_member_order(PDO $pdo, int $memberId, int $orderId): ?array
{
    $stmt = $pdo->prepare('SELECT order_id, order_date FROM orders WHERE order_id = ? AND member_id = ?');
    $stmt->execute([$orderId, $memberId]);
    $order = $stmt->fetch();
    if ($order === false) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT d.product_id, d.product_name, d.price, p.image, p.os_windows, p.os_mac'
        . ' FROM order_details d JOIN products p ON p.product_id = d.product_id'
        . ' WHERE d.order_id = ? ORDER BY d.product_id',
    );
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();

    return [
        'order' => $order,
        'items' => $items,
        'total' => array_sum(array_map(static fn (array $i): int => (int) $i['price'], $items)),
    ];
}

// 表示用の注文番号：R＋注文日 yyMMdd＋通し番号の order_id（3 桁ゼロ埋め。要確認 Q13・趙 11/17 決定）
function format_order_number(int $orderId, string $orderDate): string
{
    $yymmdd = substr($orderDate, 2, 2) . substr($orderDate, 5, 2) . substr($orderDate, 8, 2);
    return sprintf('R%s%03d', $yymmdd, $orderId);
}
