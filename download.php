<?php
// ダウンロード（画面なし）。本人が購入した商品の配布ファイルだけを送る。Plan 2026-11-26 §2.3
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/library_repo.php';
require_once __DIR__ . '/includes/download.php';
send_no_store_headers();

// 未ログイン：ログイン後はマイページへ戻す（ファイルを勝手に送らない：要確認 Q05）
$login = require_login($login_member, ['page' => 'mypage']);
$product_id = parse_positive_int($_GET['id'] ?? null);
$item = $product_id === null ? null : find_library_item(db(), (int) $login['member_id'], $product_id);
$file = $item === null ? null : download_file_path($item['file_path']);
$size = $file === null ? false : filesize($file);
if ($size === false) {
    if ($item !== null) {
        error_log(sprintf('[playduo] download file missing: product_id=%d', $product_id));
    }
    $not_found_message = 'この商品は見つかりません';   // 未購入・他会員・不正な ID を区別しない
    require __DIR__ . '/includes/not_found.php';
}

// 認可と場所の確認が済んでからヘッダーを送る。途中の出力は捨てる（Codex P1-4）
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
