<?php

declare(strict_types=1);

// U01・U02 の検索条件（GET）を読み、SQL の WHERE と ORDER BY を組み立てる。
// 入力値は SQL 文字列に入れない：列名・並び順・人数条件は下の固定表から選び、値はプレースホルダで渡す
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/format.php';

const KEYWORD_MAX_LENGTH = 50;
const PRICE_MAX = 1_000_000;

// 対応表 U01・U02 の値（この順で並べ直す）
const SEARCH_PLAYERS = ['2', '3-4', '5plus'];
const SEARCH_OS = ['windows', 'mac'];
const SEARCH_PLAY_STYLES = ['協力', '対戦'];
const SEARCH_GENRES = PRODUCT_GENRES;

// 画面に出す名前（U01・U02 の絞り込みパネルと、U02 の「条件：」の行で共用）
const SEARCH_FILTER_LABELS = [
    'players'    => ['プレイ人数', ['2' => '2人', '3-4' => '3〜4人', '5plus' => '5人以上']],
    'os'         => ['対応OS', ['windows' => 'Windows', 'mac' => 'Mac']],
    'play_style' => ['遊び方', ['協力' => '協力', '対戦' => '対戦']],
];

// 要確認 Q02：選んだ人数の全員で遊べる商品だけ
const PLAYERS_SQL = [
    '2'     => '(p.min_players <= 2 AND p.max_players >= 2)',
    '3-4'   => '(p.min_players <= 3 AND p.max_players >= 4)',
    '5plus' => '(p.max_players >= 5)',
];
const OS_SQL = ['windows' => 'p.os_windows = 1', 'mac' => 'p.os_mac = 1'];

// 同順位は product_id で固定（ページを開くたびに順番が変わらないように）
const SEARCH_ORDER_BY = [
    'recommend'  => 'p.recommend_order ASC, p.product_id ASC',
    'new'        => 'p.release_date DESC, p.product_id ASC',
    'price_asc'  => 'p.price ASC, p.product_id ASC',
    'price_desc' => 'p.price DESC, p.product_id ASC',
];
const SEARCH_DEFAULT_SORT = 'recommend';

const PRICE_ERROR = '価格は0〜1000000の整数で入力してください';
const PRICE_ORDER_ERROR = '価格の下限は上限以下にしてください';
const KEYWORD_ERROR = 'キーワードは50文字以内で入力してください';

/**
 * @return array{criteria: array{keyword: string, terms: list<string>, players: list<string>, os: list<string>,
 *   play_style: list<string>, crossplay: bool, genre: list<string>, price_min: ?int, price_max: ?int, sort: string},
 *   errors: array<string, string>}
 */
function parse_search_criteria(array $get): array
{
    $errors = [];

    $keyword = normalize_keyword(is_string($get['keyword'] ?? null) ? $get['keyword'] : '');
    $terms = $keyword === '' ? [] : preg_split('/ /u', $keyword);
    if (mb_strlen($keyword) > KEYWORD_MAX_LENGTH) {
        $errors['keyword'] = KEYWORD_ERROR;
        $terms = [];
    }

    $price = parse_price_range($get);
    if ($price['error'] !== null) {
        $errors['price'] = $price['error'];
    }

    $sort = $get['sort'] ?? null;
    return [
        'criteria' => [
            'keyword'    => $keyword,
            'terms'      => $terms,
            'players'    => pick_whitelisted($get['players'] ?? null, SEARCH_PLAYERS),
            'os'         => pick_whitelisted($get['os'] ?? null, SEARCH_OS),
            'play_style' => pick_whitelisted($get['play_style'] ?? null, SEARCH_PLAY_STYLES),
            'crossplay'  => ($get['crossplay'] ?? null) === '1',
            'genre'      => pick_whitelisted($get['genre'] ?? null, SEARCH_GENRES),
            'price_min'  => $price['min'],
            'price_max'  => $price['max'],
            'sort'       => is_string($sort) && isset(SEARCH_ORDER_BY[$sort]) ? $sort : SEARCH_DEFAULT_SORT,
        ],
        'errors' => $errors,
    ];
}

// 英数・空白を半角、半角カナを全角にそろえ、空白の連続を 1 つにする
function normalize_keyword(string $keyword): string
{
    $converted = mb_convert_kana($keyword, 'asKV', 'UTF-8');
    return (string) preg_replace('/\s+/u', ' ', trim_all($converted));
}

/** @return list<string> チェックボックスの値のうち、許可された値だけ（許可表の順） */
function pick_whitelisted(mixed $values, array $allowed): array
{
    if (!is_array($values)) {
        return [];
    }
    $given = array_filter($values, 'is_string');
    return array_values(array_filter($allowed, static fn (string $v): bool => in_array($v, $given, true)));
}

/** @return array{min: ?int, max: ?int, error: ?string} */
function parse_price_range(array $get): array
{
    $min = parse_price($get['price_min'] ?? null);
    $max = parse_price($get['price_max'] ?? null);
    if ($min === false || $max === false) {
        return ['min' => null, 'max' => null, 'error' => PRICE_ERROR];
    }
    if ($min !== null && $max !== null && $min > $max) {
        return ['min' => null, 'max' => null, 'error' => PRICE_ORDER_ERROR];
    }
    return ['min' => $min, 'max' => $max, 'error' => null];
}

// 空＝条件なし（null）、不正＝false
function parse_price(mixed $value): int|false|null
{
    if (!is_string($value)) {
        return $value === null ? null : false;
    }
    $digits = trim_all(mb_convert_kana($value, 'n', 'UTF-8'));
    if ($digits === '') {
        return null;
    }
    if (!ctype_digit($digits) || strlen($digits) > strlen((string) PRICE_MAX) || (int) $digits > PRICE_MAX) {
        return false;
    }
    return (int) $digits;
}

// LIKE の特殊文字（% _ \）をそのままの文字として探すためにエスケープする
function like_escape(string $value): string
{
    return strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
}

/** @return array{sql: string, params: list<string|int>} 種類どうしは AND、同じ種類の複数選択は OR */
function search_where(array $criteria): array
{
    $clauses = ['p.public_flag = 1'];
    $params = [];

    foreach ($criteria['terms'] as $term) {
        $clauses[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.title_en LIKE ? ESCAPE '\\\\')";
        $like = '%' . like_escape($term) . '%';
        array_push($params, $like, $like);
    }
    if ($criteria['players'] !== []) {
        $clauses[] = '(' . implode(' OR ', array_map(static fn (string $p): string => PLAYERS_SQL[$p], $criteria['players'])) . ')';
    }
    if ($criteria['os'] !== []) {
        $clauses[] = '(' . implode(' OR ', array_map(static fn (string $o): string => OS_SQL[$o], $criteria['os'])) . ')';
    }
    foreach (['play_style' => 'p.play_style', 'genre' => 'p.genre'] as $key => $column) {
        if ($criteria[$key] !== []) {
            $clauses[] = $column . ' IN (' . implode(', ', array_fill(0, count($criteria[$key]), '?')) . ')';
            array_push($params, ...$criteria[$key]);
        }
    }
    if ($criteria['crossplay']) {
        $clauses[] = 'p.crossplay_flag = 1';
    }
    if ($criteria['price_min'] !== null) {
        $clauses[] = 'p.price >= ?';
        $params[] = $criteria['price_min'];
    }
    if ($criteria['price_max'] !== null) {
        $clauses[] = 'p.price <= ?';
        $params[] = $criteria['price_max'];
    }

    return ['sql' => implode(' AND ', $clauses), 'params' => $params];
}

function search_order_by(string $sort): string
{
    return SEARCH_ORDER_BY[$sort] ?? SEARCH_ORDER_BY[SEARCH_DEFAULT_SORT];
}

// U02 の「条件：」の行。パネルと同じ順に「種類内は・、種類間は／」でつなぐ（値はホワイトリスト済み）
function criteria_summary(array $criteria): string
{
    $parts = [];
    if ($criteria['keyword'] !== '') {
        $parts[] = '「' . $criteria['keyword'] . '」';
    }
    foreach (SEARCH_FILTER_LABELS as $name => [, $options]) {
        $selected = array_values(array_filter(array_keys($options), static fn ($v): bool => in_array((string) $v, $criteria[$name], true)));
        if ($selected !== []) {
            $parts[] = implode('・', array_map(static fn ($v): string => $options[$v], $selected));
        }
    }
    if ($criteria['crossplay']) {
        $parts[] = 'クロスプレイできる';
    }
    if ($criteria['genre'] !== []) {
        $parts[] = implode('・', $criteria['genre']);
    }
    if ($criteria['price_min'] !== null || $criteria['price_max'] !== null) {
        $min = $criteria['price_min'] === null ? '' : '¥' . number_format($criteria['price_min']);
        $max = $criteria['price_max'] === null ? '' : '¥' . number_format($criteria['price_max']);
        $parts[] = $min . '〜' . $max;
    }
    return $parts === [] ? '指定なし' : implode('／', $parts);
}
