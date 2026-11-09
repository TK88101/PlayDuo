<?php

declare(strict_types=1);

const CSRF_TOKEN_BYTES = 32;

// 登録・再設定の途中状態（ログイン／ログアウトのたびに消す）
const AUTH_FLOW_KEYS = ['register', 'register_done', 'password_reset'];

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(array &$session): string
{
    if (!isset($session['csrf_token']) || !is_string($session['csrf_token'])) {
        $session['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_BYTES));
    }
    return $session['csrf_token'];
}

function csrf_is_valid(array $session, mixed $token): bool
{
    $expected = $session['csrf_token'] ?? null;
    if (!is_string($expected) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

// POST の先頭で呼ぶ。トークンが合わなければ 400 で終了する
function verify_csrf_or_fail(): void
{
    if (csrf_is_valid($_SESSION, $_POST['csrf_token'] ?? null)) {
        return;
    }
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '不正なリクエストです。画面を開き直してからもう一度お試しください。';
    exit;
}

function current_member_id(array $session): ?int
{
    $id = $session['member_id'] ?? null;
    return is_int($id) ? $id : null;
}

function clear_auth_flows(array &$session): void
{
    foreach (AUTH_FLOW_KEYS as $key) {
        unset($session[$key]);
    }
}

// 認証まわりの画面はブラウザ・履歴に残さない
function send_no_store_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// ログアウト：session の中身・cookie・サーバ側データをすべて消す
function destroy_session(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
    session_destroy();
}
