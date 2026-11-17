<?php

declare(strict_types=1);

// カート追加・購入可否・購入確定の業務ロジック（U03・U07〜U10）。Plan 2026-11-17 §2.3〜2.6
require_once __DIR__ . '/age.php';
require_once __DIR__ . '/product_repo.php';
require_once __DIR__ . '/cart_repo.php';
require_once __DIR__ . '/order_repo.php';

// 購入できない理由（優先順：販売終了 → 購入済み → 年齢制限）
const REASON_PRIVATE = '販売終了';
const REASON_PURCHASED = '購入済み';
const REASON_AGE = '年齢制限';

const PURCHASE_TTL_SECONDS = 1800;  // U08 を表示してから確定できる時間
const PURCHASE_DONE_KEEP = 5;       // 確定済みトークンを覚えておく件数（再送で同じ完了画面へ）

// ---- 購入可否 ----

function cart_item_reason(array $row, string $birthDate, int $now): ?string
{
    if ((int) $row['public_flag'] !== 1) {
        return REASON_PRIVATE;
    }
    if ((int) $row['purchased'] === 1) {
        return REASON_PURCHASED;
    }
    if (!is_age_allowed($birthDate, (string) $row['age_rating'], $now)) {
        return REASON_AGE;
    }
    return null;
}

/**
 * U07〜U09 の表示用。点数・合計は購入できる商品だけ（U07-005）。
 * @return array{items: list<array>, count: int, total: int, ng_items: list<array{name: string, reason: string}>}
 */
function cart_view(array $rows, string $birthDate, int $now): array
{
    $items = [];
    $ngItems = [];
    $total = 0;
    foreach ($rows as $row) {
        $reason = cart_item_reason($row, $birthDate, $now);
        $items[] = $row + ['can_buy' => $reason === null, 'reason' => $reason];
        if ($reason === null) {
            $total += (int) $row['price'];
        } else {
            $ngItems[] = ['name' => $row['name'], 'reason' => $reason];
        }
    }
    return ['items' => $items, 'count' => count($items) - count($ngItems), 'total' => $total, 'ng_items' => $ngItems];
}

function age_restriction_message(string $rating): string
{
    return sprintf('年齢区分（CERO %s）の対象年齢に達していないため購入できません', $rating);
}

// U07：購入できない行に出す案内（U07-005 の文言）
function cart_reason_message(array $item): ?string
{
    return match ($item['reason']) {
        null             => null,
        REASON_PRIVATE   => 'この商品は販売を終了しています。カートから削除してください。',
        REASON_PURCHASED => '購入済みの商品です。カートから削除してください。',
        default          => age_restriction_message((string) $item['age_rating']) . '。カートから削除してください。',
    };
}

// U03：画面を開いた時点で分かる「追加できない理由」（ボタンを押せない表示にする。要確認 Q07）。
// 判定はカートと同じ cart_item_reason（公開中の商品なので販売終了にはならない）
function add_block_reason(array $product, bool $purchased, string $birthDate, int $now): ?string
{
    $row = ['purchased' => (int) $purchased] + $product + ['public_flag' => 1];
    return match (cart_item_reason($row, $birthDate, $now)) {
        REASON_PURCHASED => '購入済みです',
        REASON_AGE       => age_restriction_message((string) $product['age_rating']),
        default          => null,
    };
}

const ADD_TO_CART_STATUS = [REASON_PRIVATE => 'not_found', REASON_PURCHASED => 'purchased', REASON_AGE => 'age'];

/** @return 'ok'|'not_found'|'purchased'|'age' 状態が途中で変わった場合もここで拒否する */
function try_add_to_cart(PDO $pdo, int $memberId, string $birthDate, int $productId, int $now): string
{
    $product = find_public_product($pdo, $productId);
    if ($product === null) {
        return 'not_found';
    }
    $reason = cart_item_reason(
        ['purchased' => (int) is_purchased($pdo, $memberId, $productId)] + $product,
        $birthDate,
        $now,
    );
    if ($reason !== null) {
        return ADD_TO_CART_STATUS[$reason];
    }
    insert_cart_item($pdo, $memberId, $productId);
    return 'ok';
}

// ---- 購入確定 ----

/** @return array<int, int> product_id => price（確認画面で見せた内容） */
function purchase_snapshot_of(array $rows): array
{
    $snapshot = [];
    foreach ($rows as $row) {
        $snapshot[(int) $row['product_id']] = (int) $row['price'];
    }
    ksort($snapshot);
    return $snapshot;
}

/**
 * U08「購入を確定する」：カートと商品をロック読みし、その結果だけで判定して
 * orders → order_details → carts（購入分）を 1 トランザクションで書く。
 * @param array<int, int> $snapshot
 * @return array{status: 'ok', order_id: int}|array{status: 'empty'|'ng'|'changed'}
 */
function place_order(PDO $pdo, int $memberId, string $birthDate, array $snapshot, int $now): array
{
    ksort($snapshot);
    return db_transaction($pdo, static function () use ($pdo, $memberId, $birthDate, $snapshot, $now): array {
        $rows = cart_rows($pdo, $memberId, lock: true);
        if ($rows === []) {
            return ['status' => 'empty'];
        }
        if (cart_view($rows, $birthDate, $now)['ng_items'] !== []) {
            return ['status' => 'ng'];
        }
        if (purchase_snapshot_of($rows) !== $snapshot) {
            return ['status' => 'changed'];
        }
        $orderId = create_order($pdo, $memberId, $rows, $now);
        delete_cart_items($pdo, $memberId, array_map('intval', array_column($rows, 'product_id')));
        return ['status' => 'ok', 'order_id' => $orderId];
    });
}

// U09「対象商品を削除してカートへ」：その時点で購入できない行だけを消す
function remove_unbuyable_items(PDO $pdo, int $memberId, string $birthDate, int $now): int
{
    return db_transaction($pdo, static function () use ($pdo, $memberId, $birthDate, $now): int {
        $view = cart_view(cart_rows($pdo, $memberId, lock: true), $birthDate, $now);
        $ngIds = [];
        foreach ($view['items'] as $item) {
            if (!$item['can_buy']) {
                $ngIds[] = (int) $item['product_id'];
            }
        }
        return delete_cart_items($pdo, $memberId, $ngIds);
    });
}

// ---- 確認トークン（二重送信防止。セッションに 1 つだけ持つ＝別タブで開き直すと古い確認は無効） ----

function start_purchase(array &$session, array $snapshot, int $now): string
{
    $token = bin2hex(random_bytes(16));
    $session['purchase'] = ['token' => $token, 'snapshot' => $snapshot, 'created_at' => $now];
    return $token;
}

/** @return array<int, int>|null 有効なら確認時のスナップショット */
function purchase_snapshot(array $session, mixed $token, int $now): ?array
{
    $purchase = $session['purchase'] ?? null;
    if (!is_string($token) || !is_array($purchase) || !is_string($purchase['token'] ?? null)) {
        return null;
    }
    if (!hash_equals($purchase['token'], $token) || $now >= (int) $purchase['created_at'] + PURCHASE_TTL_SECONDS) {
        return null;
    }
    return $purchase['snapshot'];
}

function finish_purchase(array &$session, string $token, int $orderId): void
{
    unset($session['purchase']);
    $done = is_array($session['purchase_done'] ?? null) ? $session['purchase_done'] : [];
    $done[$token] = $orderId;
    $session['purchase_done'] = array_slice($done, -PURCHASE_DONE_KEEP, null, true);
}

function finished_order_id(array $session, mixed $token): ?int
{
    if (!is_string($token)) {
        return null;
    }
    $orderId = $session['purchase_done'][$token] ?? null;
    return is_int($orderId) ? $orderId : null;
}

function clear_purchase(array &$session): void
{
    unset($session['purchase']);
}
