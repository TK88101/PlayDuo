<?php

declare(strict_types=1);

// products の参照（利用者画面用：公開中の商品だけ）。SQL はここに集める
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/search.php';

const RECOMMEND_COUNT = 4;   // U01 おすすめのゲーム

// U01 のカード（対応表 U01 の列＋遊び方・クロスプレイ）
const PRODUCT_CARD_COLUMNS = 'p.product_id, p.name, p.image, p.price, p.min_players, p.max_players, p.play_style,'
    . ' p.os_windows, p.os_mac, p.crossplay_flag';
// U02 の一覧（カード＋ジャンル・容量・プレイ時間）
const PRODUCT_LIST_COLUMNS = PRODUCT_CARD_COLUMNS . ', p.genre, p.capacity, p.play_time';

function find_recommended_products(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT ' . PRODUCT_CARD_COLUMNS . ' FROM products p WHERE p.public_flag = 1'
        . ' ORDER BY ' . search_order_by('recommend') . ' LIMIT ' . RECOMMEND_COUNT,
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

// $criteria は parse_search_criteria() の結果（ホワイトリスト済み）
function search_products(PDO $pdo, array $criteria): array
{
    $where = search_where($criteria);
    $stmt = $pdo->prepare(
        'SELECT ' . PRODUCT_LIST_COLUMNS . ' FROM products p WHERE ' . $where['sql']
        . ' ORDER BY ' . search_order_by($criteria['sort']),
    );
    $stmt->execute($where['params']);
    return $stmt->fetchAll();
}

// U03：公開中の商品 1 件（全列）。非公開・存在しない ID は null（要確認 Q25）
function find_public_product(PDO $pdo, int $productId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ? AND public_flag = 1');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
