<?php

declare(strict_types=1);

// A01 管理者ログイン・ログアウトと、管理画面の入口（Plan 2026-11-26-php-admin §2.0）
// 管理者と会員は同じ session の別キー（admin_id ／ member_id）で、同時には持たない
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_product_draft.php';

// A01-008：ロックしない・回数も数えない。存在しない ID でもダミーで照合して、かかる時間をそろえる
function admin_login(PDO $pdo, string $loginId, #[\SensitiveParameter] string $password): ?int
{
    $loginId = trim($loginId);
    $row = null;
    if ($loginId !== '') {
        $stmt = $pdo->prepare('SELECT admin_id, login_id, password FROM admins WHERE login_id = ?');
        $stmt->execute([$loginId]);
        $row = $stmt->fetch() ?: null;
    }
    $verified = password_verify($password, $row['password'] ?? DUMMY_PASSWORD_HASH);
    // DB の照合順序は大文字小文字を区別しないので、ID の完全一致はここで確かめる（A01-011）
    if (!$verified || $row === null || $row['login_id'] !== $loginId) {
        return null;
    }
    return (int) $row['admin_id'];
}

function admin_exists(PDO $pdo, int $adminId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM admins WHERE admin_id = ?');
    $stmt->execute([$adminId]);
    return $stmt->fetchColumn() !== false;
}

function current_admin_id(array $session): ?int
{
    $id = $session['admin_id'] ?? null;
    return is_int($id) ? $id : null;
}

// 未ログインなら A01 へ（admin/ の中から呼ぶので相対パスでよい）
function require_admin(?int $adminId): int
{
    if ($adminId === null) {
        redirect('login.php');
    }
    return $adminId;
}

// ログイン成功：session ID と CSRF を作り直し、会員としてのログイン状態は消す
function admin_sign_in(int $adminId): void
{
    forget_product_draft($_SESSION);
    renew_session_for_auth();
    unset($_SESSION['member_id'], $_SESSION['login_return']);
    $_SESSION['admin_id'] = $adminId;
}

// ログアウト：管理者の状態だけ消して session ID と CSRF を作り直す（会員の状態はログイン時に消えている）
function admin_sign_out(): void
{
    forget_product_draft($_SESSION);
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}
