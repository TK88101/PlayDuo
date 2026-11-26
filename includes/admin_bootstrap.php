<?php

declare(strict_types=1);

// 管理画面（admin/*.php）の先頭で require する。利用者側の bootstrap に管理者の状態を足す
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin_auth.php';

// 管理画面は個人情報・注文を出すので、すべてブラウザに残さない
send_no_store_headers();

$admin_id = current_admin_id($_SESSION);
if ($admin_id !== null && !admin_exists(db(), $admin_id)) {
    // 管理者の行が消えた：その session は使わせない
    $_SESSION = [];
    session_regenerate_id(true);
    $admin_id = null;
    $csrf_token = csrf_token($_SESSION);
}
