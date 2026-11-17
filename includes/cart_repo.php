<?php

declare(strict_types=1);

// carts の DB 操作（会員ごとのカート。PK (member_id, product_id) で同じ商品は 1 行）
require_once __DIR__ . '/db.php';

const MYSQL_DUPLICATE_KEY = 1062;

// U07・U08 の表示に使う列＋購入可否の判定に使う列（public_flag・age_rating・purchased）
const CART_ROW_SQL = 'SELECT p.product_id, p.name, p.image, p.price, p.min_players, p.max_players,'
    . ' p.play_style, p.os_windows, p.os_mac, p.crossplay_flag, p.age_rating, p.public_flag,'
    . ' EXISTS (SELECT 1 FROM order_details d WHERE d.member_id = c.member_id AND d.product_id = c.product_id) AS purchased'
    . ' FROM carts c JOIN products p ON p.product_id = c.product_id'
    . ' WHERE c.member_id = ? ORDER BY c.product_id';

/**
 * $lock = true はトランザクションの中で使う（ロック読み：最新のコミットを読み、同じ会員のカートの
 * 追加・削除・二重の確定をコミットまで待たせる。Plan §2.6）
 */
function cart_rows(PDO $pdo, int $memberId, bool $lock = false): array
{
    $stmt = $pdo->prepare(CART_ROW_SQL . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

function cart_count(PDO $pdo, int $memberId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM carts WHERE member_id = ?');
    $stmt->execute([$memberId]);
    return (int) $stmt->fetchColumn();
}

// 追加済みなら何もしない（重複キーだけを許す。他のエラーは投げ直す）
function insert_cart_item(PDO $pdo, int $memberId, int $productId): void
{
    try {
        $pdo->prepare('INSERT INTO carts (member_id, product_id) VALUES (?, ?)')->execute([$memberId, $productId]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) !== MYSQL_DUPLICATE_KEY) {
            throw $e;
        }
    }
}

/** @param list<int> $productIds */
function delete_cart_items(PDO $pdo, int $memberId, array $productIds): int
{
    if ($productIds === []) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM carts WHERE member_id = ? AND product_id IN (' . implode(', ', array_fill(0, count($productIds), '?')) . ')',
    );
    $stmt->execute([$memberId, ...$productIds]);
    return $stmt->rowCount();
}

function is_purchased(PDO $pdo, int $memberId, int $productId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM order_details WHERE member_id = ? AND product_id = ?');
    $stmt->execute([$memberId, $productId]);
    return $stmt->fetchColumn() !== false;
}
