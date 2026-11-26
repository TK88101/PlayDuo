<?php

declare(strict_types=1);

// 画面表示用の小さな整形関数（HTML に出すときは結果を h() に通す）
const ID_MAX_DIGITS = 9;   // INT の範囲に収める

function yen(int|string $price): string
{
    return '¥' . number_format((int) $price);
}

function players_label(array $row): string
{
    $min = (int) $row['min_players'];
    $max = (int) $row['max_players'];
    return $min === $max ? "{$min}人" : "{$min}〜{$max}人";
}

function os_label(array $row): string
{
    $names = [];
    if ((int) $row['os_windows'] === 1) {
        $names[] = 'Windows';
    }
    if ((int) $row['os_mac'] === 1) {
        $names[] = 'Mac';
    }
    return implode('・', $names);
}

// U02-003：両 OS 対応の商品だけ「できる／できない」、片方だけの商品は「対象外」
function crossplay_label(array $row): string
{
    if ((int) $row['os_windows'] !== 1 || (int) $row['os_mac'] !== 1) {
        return '対象外';
    }
    return (int) $row['crossplay_flag'] === 1 ? '○ できる' : '× できない';
}

// U01 カードのタグ（Figma U01）：両対応は「Win / Mac」、片方だけは「Windowsのみ」「Macのみ」
function os_tag_label(array $row): string
{
    $windows = (int) $row['os_windows'] === 1;
    $mac = (int) $row['os_mac'] === 1;
    if ($windows && $mac) {
        return 'Win / Mac';
    }
    return $windows ? 'Windowsのみ' : 'Macのみ';
}

/**
 * U01 カードのクロスプレイタグ。available が true のときだけ緑で表示する
 * @return array{label: string, available: bool}
 */
function crossplay_tag(array $row): array
{
    $label = crossplay_label($row);
    return match ($label) {
        '○ できる' => ['label' => 'クロスプレイ○', 'available' => true],
        '× できない' => ['label' => 'クロスプレイ×', 'available' => false],
        default => ['label' => 'クロスプレイ' . $label, 'available' => false],
    };
}

// U03「みんなで遊べるか確認」のクロスプレイ欄（Figma U03）。できるときだけ何ができるかを書く
function crossplay_detail_label(array $row): string
{
    $label = crossplay_label($row);
    return $label === '○ できる' ? '○ Windows と Mac で一緒に遊べる' : $label;
}

// ---- U03「みんなで遊べるか確認」の表記（Figma U03） ----
// CERO の対象年齢（表記は CERO の区分名に合わせる。購入可否の判定は age.php の CERO_MIN_AGES）
const CERO_TARGET_LABELS = ['A' => '全年齢', 'B' => '12才以上', 'C' => '15才以上', 'D' => '17才以上', 'Z' => '18才以上のみ'];

function players_detail_label(array $row): string
{
    return players_label($row) . '（パーティー：最大' . (int) $row['max_players'] . '人）';
}

function play_style_detail_label(array $row): string
{
    return $row['play_style'] . 'プレイ';
}

function os_detail_label(array $row): string
{
    return str_replace('・', ' ／ ', os_label($row));
}

// タグ用「CERO A（全年齢）」。未知の区分は記号だけ出す
function cero_tag_label(string $rating): string
{
    $target = CERO_TARGET_LABELS[$rating] ?? null;
    return $target === null ? "CERO {$rating}" : "CERO {$rating}（{$target}）";
}

// 仕様欄用「CERO A（全年齢対象）」
function cero_detail_label(string $rating): string
{
    $target = CERO_TARGET_LABELS[$rating] ?? null;
    return $target === null ? "CERO {$rating}" : "CERO {$rating}（{$target}対象）";
}

// 一覧・カートで使う「人数 / 遊び方 / 対応OS / クロスプレイ」の 1 行
function spec_line(array $row): string
{
    return players_label($row) . ' / ' . $row['play_style'] . ' / ' . os_label($row) . ' / クロスプレイ ' . crossplay_label($row);
}

// DB の DATETIME（Y-m-d H:i:s）→ 画面の yyyy/MM/dd HH:mm（U10-003）
function display_datetime(string $datetime): string
{
    return str_replace('-', '/', substr($datetime, 0, 16));
}

function display_date(string $date): string
{
    return str_replace('-', '/', substr($date, 0, 10));
}

function checked(bool $on): string
{
    return $on ? ' checked' : '';
}

// URL の id（商品・注文）：正の整数だけ。それ以外は null
function parse_positive_int(mixed $value): ?int
{
    if (!is_string($value) || !ctype_digit($value) || strlen($value) > ID_MAX_DIGITS) {
        return null;
    }
    $int = (int) $value;
    return $int > 0 ? $int : null;
}
