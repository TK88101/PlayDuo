<?php

declare(strict_types=1);

// A08 会員一覧・A09 会員詳細（参照のみ。パスワード・認証アプリの秘密鍵は SELECT しない：A08-012・A09-007）
require_once __DIR__ . '/search.php';   // like_escape・trim_all

const MEMBER_PUBLIC_COLUMNS = 'm.member_id, m.name, m.email, m.birth_date, m.created_at, m.withdrawal_flag';

/** A08：氏名・メールの部分一致。空なら全件。退会済みも出す。会員 ID 順（A08-004〜A08-008・A08-013） */
function search_members(PDO $pdo, string $keyword): array
{
    $keyword = trim_all($keyword);
    $where = '';
    $params = [];
    if ($keyword !== '') {
        $where = 'WHERE m.name LIKE ? OR m.email LIKE ?';
        $like = '%' . like_escape($keyword) . '%';
        $params = [$like, $like];
    }
    $stmt = $pdo->prepare(
        'SELECT ' . MEMBER_PUBLIC_COLUMNS . ',
            (SELECT COUNT(*) FROM order_details d WHERE d.member_id = m.member_id) AS purchase_count
         FROM members m ' . $where . ' ORDER BY m.member_id',
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_member_detail(PDO $pdo, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT ' . MEMBER_PUBLIC_COLUMNS . ' FROM members m WHERE m.member_id = ?');
    $stmt->execute([$memberId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** A09 の購入履歴：購入日の新しい順・同じ注文は商品 ID 順。名称・価格は購入時（A09-002・A09-006） */
function member_purchases(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(
        'SELECT o.order_id, o.order_date, d.product_id, d.product_name, d.price
         FROM orders o JOIN order_details d ON d.order_id = o.order_id
         WHERE o.member_id = ?
         ORDER BY o.order_date DESC, o.order_id DESC, d.product_id',
    );
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

function member_code(int $memberId): string
{
    return sprintf('M%04d', $memberId);
}

function member_status_label(array $row): string
{
    return (int) $row['withdrawal_flag'] === 1 ? '退会済み' : '利用中';
}
