<?php

declare(strict_types=1);

// 管理画面の商品まわりの表示値（A03〜A05）。入力の検証は T3 でここに足す
require_once __DIR__ . '/format.php';

// 「g01」のような商品コード：file_path が決まった形のときだけ取り出す（Plan §2.0・付録 A）
const PRODUCT_CODE_PATTERN = '#\Adownloads/([a-z][0-9]+)(?:_[0-9a-f]{16})?\.zip\z#';

function product_code(array $row): string
{
    if (is_string($row['file_path'] ?? null) && preg_match(PRODUCT_CODE_PATTERN, $row['file_path'], $m) === 1) {
        return $m[1];
    }
    return '#' . (int) $row['product_id'];
}

// A03 の対応OS列「Win・Mac」「Win」「Mac」（A03-002）
function admin_os_label(array $row): string
{
    $names = [];
    if ((int) $row['os_windows'] === 1) {
        $names[] = 'Win';
    }
    if ((int) $row['os_mac'] === 1) {
        $names[] = 'Mac';
    }
    return implode('・', $names);
}

// A03 のクロスプレイ列：片方の OS だけの商品は空欄（A03-004）
function admin_crossplay_mark(array $row): string
{
    if ((int) $row['os_windows'] !== 1 || (int) $row['os_mac'] !== 1) {
        return '';
    }
    return (int) $row['crossplay_flag'] === 1 ? '○' : '×';
}

function public_status_label(array $row): string
{
    return (int) $row['public_flag'] === 1 ? '公開中' : '非公開';
}

// ---- A06 入力チェック（Plan §2.0 A06 入力規則） ----
require_once __DIR__ . '/validation.php';

const PRODUCT_PLAY_STYLES = ['協力', '対戦'];
const PRODUCT_PRICE_MAX = 1_000_000;
const PRODUCT_PLAYERS_MAX = 99;
// [最大文字数, 必須か, 項目名]
const PRODUCT_TEXT_FIELDS = [
    'name'        => [100, true, 'タイトル'],
    'title_en'    => [100, false, '英語名'],
    'developer'   => [100, true, '開発元'],
    'description' => [2000, true, '説明文'],
    'play_time'   => [20, true, '1回のプレイ時間'],
];

/**
 * @return array{errors: array<string, string>, input: array<string, mixed>}
 */
function validate_product_input(array $post): array
{
    $errors = [];
    $input = [];
    foreach (PRODUCT_TEXT_FIELDS as $field => [$max, $required, $label]) {
        $value = trim_all(post_string($post, $field));
        $error = product_text_error($value, $max, $required, $label);
        if ($error !== null) {
            $errors[$field] = $error;
        }
        $input[$field] = $value === '' ? null : $value;
    }
    $input += product_choices($post, $errors) + product_numbers($post, $errors) + product_os($post, $errors);

    // 画面の項目順にそろえる（確認画面・メッセージ表示欄の並び）
    return ['errors' => in_form_order($errors), 'input' => in_form_order($input)];
}

// A06 の項目順（エラーの 'os' は対応OS欄）
const PRODUCT_FORM_ORDER = ['name', 'title_en', 'genre', 'price', 'capacity', 'release_date', 'developer', 'age_rating',
    'description', 'min_players', 'max_players', 'play_style', 'play_time', 'os', 'os_windows', 'os_mac', 'crossplay_flag'];

function in_form_order(array $values): array
{
    return array_replace(array_intersect_key(array_flip(PRODUCT_FORM_ORDER), $values), $values);
}

function product_text_error(string $value, int $max, bool $required, string $label): ?string
{
    if ($value === '') {
        return $required ? "{$label}を入力してください" : null;
    }
    return mb_strlen($value) > $max ? "{$label}は{$max}文字以内で入力してください" : null;
}

/** ジャンル・年齢区分・遊び方（決まった選択肢だけ） */
function product_choices(array $post, array &$errors): array
{
    $choices = [
        'genre'      => [PRODUCT_GENRES, 'ジャンルを選択してください'],
        'age_rating' => [array_keys(CERO_TARGET_LABELS), '年齢区分を選択してください'],
        'play_style' => [PRODUCT_PLAY_STYLES, '遊び方を選択してください'],
    ];
    $input = [];
    foreach ($choices as $field => [$allowed, $message]) {
        $value = post_string($post, $field);
        if (!in_array($value, $allowed, true)) {
            $errors[$field] = $message;
        }
        $input[$field] = $value;
    }
    return $input;
}

/** 価格・容量・発売日・人数（半角の数字だけ。全角・カンマ・符号は受けない：A06-007） */
function product_numbers(array $post, array &$errors): array
{
    $price = trim_all(post_string($post, 'price'));
    if ($price === '') {
        $errors['price'] = '価格を入力してください';
    } elseif (preg_match('/\A[0-9]{1,7}\z/', $price) !== 1 || (int) $price > PRODUCT_PRICE_MAX) {
        $errors['price'] = '価格は0〜1000000の整数で入力してください';
    }

    $capacity = trim_all(post_string($post, 'capacity'));
    if ($capacity === '') {
        $errors['capacity'] = '容量を入力してください';
    } elseif (preg_match('/\A[0-9]{1,4}(\.[0-9])?\z/', $capacity) !== 1 || (float) $capacity <= 0) {
        $errors['capacity'] = '容量は0.1〜9999.9の数値（小数は1桁まで）で入力してください';
    }

    $date = trim_all(post_string($post, 'release_date'));
    if ($date === '') {
        $errors['release_date'] = '発売日を入力してください';
    } elseif (!is_real_date($date)) {
        $errors['release_date'] = '発売日は実在する日付で入力してください';
    }

    [$min, $max] = [product_players(post_string($post, 'min_players')), product_players(post_string($post, 'max_players'))];
    if ($min === null || $max === null || $min > $max) {
        $errors['min_players'] = 'プレイ人数は1〜99の整数で、最小が最大以下になるように入力してください';
    }

    return [
        'price' => (int) $price, 'capacity' => $capacity, 'release_date' => $date,
        'min_players' => $min ?? 0, 'max_players' => $max ?? 0,
    ];
}

function product_players(string $value): ?int
{
    $value = trim_all($value);
    return preg_match('/\A[1-9][0-9]?\z/', $value) === 1 ? (int) $value : null;
}

/** 対応OS（1つ以上）とクロスプレイ（両 OS のときだけ：A06-012・A06-013） */
function product_os(array $post, array &$errors): array
{
    $windows = post_string($post, 'os_windows') === '1' ? 1 : 0;
    $mac = post_string($post, 'os_mac') === '1' ? 1 : 0;
    $crossplay = post_string($post, 'crossplay_flag') === '1' ? 1 : 0;
    if ($windows + $mac === 0) {
        $errors['os'] = '対応OSを1つ以上選択してください';
    } elseif ($crossplay === 1 && $windows + $mac !== 2) {
        $errors['crossplay_flag'] = 'クロスプレイはWindowsとMacの両方に対応する場合だけ選べます';
    }
    return ['os_windows' => $windows, 'os_mac' => $mac, 'crossplay_flag' => $crossplay];
}
