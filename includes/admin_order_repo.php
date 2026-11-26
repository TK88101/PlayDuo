<?php

declare(strict_types=1);

// A10 注文一覧の検索・A11 注文詳細（参照のみ）。Plan 2026-11-26-php-admin §2.0 A10 検索・付録 A
require_once __DIR__ . '/search.php';      // like_escape・trim_all
require_once __DIR__ . '/validation.php';  // is_real_date

const ORDER_SEARCH_TEXT_MAX = 50;
const MEMBER_CODE_PATTERN = '/\A[Mm]?([0-9]{1,9})\z/';

/**
 * GET の条件を読む。空欄は条件なし（全件：A10-009）
 * @return array{criteria: array{date_from: ?string, date_to: ?string, member_id: ?int, member_name: ?string, product: ?string}, errors: array<string, string>}
 */
function parse_order_criteria(array $get): array
{
    $errors = [];
    [$from, $to] = [order_date_value($get['date_from'] ?? null), order_date_value($get['date_to'] ?? null)];
    if ($from === false || $to === false) {
        $errors['date'] = '期間は正しい日付で入力してください';
    } elseif ($from !== null && $to !== null && $from > $to) {
        $errors['date'] = '期間の開始日は終了日以前にしてください';
    }

    $member = order_text_value($get['member'] ?? null);
    $product = order_text_value($get['product'] ?? null);
    foreach (['member' => [$member, '会員'], 'product' => [$product, '商品']] as $field => [$value, $label]) {
        if ($value !== null && mb_strlen($value) > ORDER_SEARCH_TEXT_MAX) {
            $errors[$field] = "{$label}は" . ORDER_SEARCH_TEXT_MAX . '文字以内で入力してください';
        }
    }
    $memberId = $member !== null && preg_match(MEMBER_CODE_PATTERN, $member, $m) === 1 ? (int) $m[1] : null;

    return ['criteria' => [
        'date_from' => $from ?: null, 'date_to' => $to ?: null,
        'member_id' => $memberId, 'member_name' => $memberId === null ? $member : null, 'product' => $product,
    ], 'errors' => $errors];
}

// 空＝null・不正＝false
function order_date_value(mixed $value): string|false|null
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        return false;
    }
    $value = trim_all($value);
    if ($value === '') {
        return null;
    }
    return is_real_date($value) ? $value : false;
}

function order_text_value(mixed $value): ?string
{
    $value = is_string($value) ? trim_all($value) : '';
    return $value === '' ? null : $value;
}

/** 両端を含む日付 → 半開区間 [from 00:00:00, to の翌日 00:00:00)（その日の 23:59:59 も入る：A10-004） */
function order_date_bounds(?string $from, ?string $to): array
{
    return [
        $from === null ? null : "{$from} 00:00:00",
        $to === null ? null : (new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
    ];
}

/** A10：AND 条件・購入日時の新しい順。商品は EXISTS、合計は注文ごとの集計から取る（重複・二重計上しない） */
function search_orders(PDO $pdo, array $criteria): array
{
    [$start, $end] = order_date_bounds($criteria['date_from'], $criteria['date_to']);
    $clauses = [];
    $params = [];
    if ($start !== null) {
        $clauses[] = 'o.order_date >= ?';
        $params[] = $start;
    }
    if ($end !== null) {
        $clauses[] = 'o.order_date < ?';
        $params[] = $end;
    }
    if ($criteria['member_id'] !== null) {
        $clauses[] = 'o.member_id = ?';
        $params[] = $criteria['member_id'];
    } elseif ($criteria['member_name'] !== null) {
        $clauses[] = 'm.name LIKE ?';
        $params[] = '%' . like_escape($criteria['member_name']) . '%';
    }
    if ($criteria['product'] !== null) {
        $clauses[] = 'EXISTS (SELECT 1 FROM order_details x WHERE x.order_id = o.order_id AND x.product_name LIKE ?)';
        $params[] = '%' . like_escape($criteria['product']) . '%';
    }
    $where = $clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses);
    $stmt = $pdo->prepare(
        "SELECT o.order_id, o.order_date, o.member_id, m.name AS member_name, t.item_count, t.total
         FROM orders o
         JOIN members m ON m.member_id = o.member_id
         JOIN (SELECT order_id, COUNT(*) AS item_count, SUM(price) AS total FROM order_details GROUP BY order_id) t
           ON t.order_id = o.order_id
         {$where}
         ORDER BY o.order_date DESC, o.order_id DESC",
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** A11：注文 1 件と明細（購入時の名称・価格）。商品コードのために今の file_path も読む */
function find_order_detail(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT o.order_id, o.order_date, o.member_id, m.name AS member_name
         FROM orders o JOIN members m ON m.member_id = o.member_id WHERE o.order_id = ?',
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if ($order === false) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT d.product_id, d.product_name, d.price, p.file_path
         FROM order_details d JOIN products p ON p.product_id = d.product_id
         WHERE d.order_id = ? ORDER BY d.product_id',
    );
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    return [
        'order' => $order,
        'items' => $items,
        'total' => array_sum(array_map(static fn (array $i): int => (int) $i['price'], $items)),
    ];
}
