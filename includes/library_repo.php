<?php

declare(strict_types=1);

// U11 購入履歴（ライブラリ）とダウンロードの権限確認（Plan 2026-11-26 §2.2・§2.3）
// 名称・価格は購入時の値（order_details）、画像・OS・配布ファイルは今の商品（products）。販売終了（非公開）も含む
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order_repo.php';

const LIBRARY_SELECT = 'SELECT d.product_id, d.product_name AS name, d.price, o.order_id, o.order_date,'
    . ' p.image, p.os_windows, p.os_mac, p.file_path'
    . ' FROM order_details d'
    . ' JOIN orders o ON o.order_id = d.order_id'
    . ' JOIN products p ON p.product_id = d.product_id'
    . ' WHERE d.member_id = ?';

/**
 * 購入日時の新しい順。同じ注文の中は product_id 順（要確認 Q22：Plan §2.0）。
 * @return list<array<string, mixed>> 各行に order_number（表示用の注文番号）を足す
 */
function member_library(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(LIBRARY_SELECT . ' ORDER BY o.order_date DESC, o.order_id DESC, d.product_id ASC');
    $stmt->execute([$memberId]);
    return array_map(
        static fn (array $row): array => $row + ['order_number' => format_order_number((int) $row['order_id'], $row['order_date'])],
        $stmt->fetchAll(),
    );
}

// 本人が購入した商品 1 件（他会員の購入・未購入は null。どちらかは区別しない）
function find_library_item(PDO $pdo, int $memberId, int $productId): ?array
{
    $stmt = $pdo->prepare(LIBRARY_SELECT . ' AND d.product_id = ?');
    $stmt->execute([$memberId, $productId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
