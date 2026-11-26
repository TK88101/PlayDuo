<?php

declare(strict_types=1);

// 管理画面の商品の読み書き（非公開の商品も対象にする）

/** A03：全商品を ID 順（A03-006） */
function all_products_for_admin(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM products ORDER BY product_id')->fetchAll();
}

function find_product_any(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

// A04/A05：すでにその状態でも同じ結果（二重クリック A04-008・A05-006）
function set_public_flag(PDO $pdo, int $productId, bool $public): void
{
    $pdo->prepare('UPDATE products SET public_flag = ? WHERE product_id = ?')->execute([$public ? 1 : 0, $productId]);
}
