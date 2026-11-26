<?php
// A01 管理者ログイン（ロックなし：A01-008）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';

if ($admin_id !== null) {
    redirect('index.php');   // A01-012
}

$page_title = '管理者ログイン';
if (is_post()) {
    verify_csrf_or_fail();
    $loginId = trim(post_string($_POST, 'login_id'));
    $signedIn = admin_login(db(), $loginId, post_string($_POST, 'password'));
    if ($signedIn !== null) {
        admin_sign_in($signedIn);
        redirect('index.php');
    }
    // ID が無い・パスワードが違う・未入力は同じ文言（どれに当たったかは出さない）
    $errors['login'] = '管理者IDまたはパスワードが違います';
    $old['login_id'] = $loginId;
}
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="admin-login">
    <h1>管理者ログイン</h1>
    <?php if (isset($errors['login'])): ?>
        <p class="form-alert"><?= h($errors['login']) ?></p>
    <?php endif; ?>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <label>管理者ID <input type="text" name="login_id" value="<?= h($old['login_id'] ?? '') ?>" autocomplete="username"></label>
        <label>パスワード <input type="password" name="password" autocomplete="current-password"></label>
        <button type="submit">ログイン</button>
    </form>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
