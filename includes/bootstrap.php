<?php

declare(strict_types=1);

// 全画面の先頭で require する。共通部品を読み込み、どの画面でも使う変数を用意する
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';

// 想定外の例外：ログには種類と場所だけ残し（引数は残さない）、画面には詳細を出さない
set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf('[playduo] %s: %s at %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }
    echo 'エラーが発生しました。時間をおいてからもう一度お試しください。';
});

start_app_session();
send_frame_deny_headers();

$csrf_token = csrf_token($_SESSION);
$login_member = null;
$member_id = current_member_id($_SESSION);
if ($member_id !== null) {
    $login_member = find_login_member(db(), $member_id);
    if ($login_member === null) {
        // 退会済みなど：ログイン状態を残さない
        unset($_SESSION['member_id']);
    }
}
$errors = [];
$old = [];
$message = flash_take($_SESSION);
