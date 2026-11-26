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
