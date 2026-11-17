<?php

declare(strict_types=1);

// ログインが必要な画面の入口と、ログイン後の戻り先（要確認 Q05・Q06・Q26）。
// 戻り先は URL 文字列を受け取らない：session には「ページの種類＋ID」だけを置き、URL は下の固定表から組み立てる
require_once __DIR__ . '/functions.php';

const LOGIN_RETURN_PAGES = [
    'cart'             => 'cart.php',
    'purchase_confirm' => 'purchase_confirm.php',
    'purchase_error'   => 'purchase_error.php',
    'mypage'           => 'mypage.php',
];
const LOGIN_RETURN_ID_PAGES = [
    'product'           => 'product.php?id=%d',
    'purchase_complete' => 'purchase_complete.php?id=%d',
];

// 知らないページ・不正な ID は null（保存するときと使うときの両方でこれを通す）
function login_return_path(mixed $saved): ?string
{
    if (!is_array($saved) || !is_string($saved['page'] ?? null)) {
        return null;
    }
    $page = $saved['page'];
    if (isset(LOGIN_RETURN_PAGES[$page])) {
        return LOGIN_RETURN_PAGES[$page];
    }
    $id = $saved['id'] ?? null;
    if (isset(LOGIN_RETURN_ID_PAGES[$page]) && is_int($id) && $id > 0) {
        return sprintf(LOGIN_RETURN_ID_PAGES[$page], $id);
    }
    return null;
}

/**
 * 未ログインなら戻り先を覚えてログイン画面へ（ここで処理を終える）。
 * @param array{page: string, id?: int} $returnTo
 */
function require_login(?array $loginMember, array $returnTo): array
{
    if ($loginMember !== null) {
        return $loginMember;
    }
    if (login_return_path($returnTo) !== null) {
        $_SESSION['login_return'] = $returnTo;
    }
    redirect('login.php');
}

// ログイン成功時に 1 回だけ取り出す（取り出したら消す）
function take_login_return(array &$session): ?string
{
    $path = login_return_path($session['login_return'] ?? null);
    unset($session['login_return']);
    return $path;
}
