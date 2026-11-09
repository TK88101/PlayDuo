<?php

declare(strict_types=1);

// 全画面の先頭で require する。共通部品を読み込み、どの画面でも使う変数を用意する
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/auth.php';

start_app_session();

$csrf_token = csrf_token($_SESSION);
$member_id = current_member_id($_SESSION);
$login_member = $member_id === null ? null : find_member_by_id(db(), $member_id);
$errors = [];
$old = [];
$message = flash_take($_SESSION);
