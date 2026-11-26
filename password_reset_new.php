<?php
// U15 パスワード再設定（新パスワード）。U14 の本人確認から 10 分以内・1 回限り
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

$page_title = 'パスワード再設定';
$now = time();
$auth = $_SESSION['password_reset'] ?? null;

if (!reset_auth_is_valid($auth, $now)) {
    unset($_SESSION['password_reset']);
    flash_set($_SESSION, '本人確認からやり直してください');
    redirect('password_reset.php');
}

if (is_post()) {
    verify_csrf_or_fail();
    $newPassword = post_string($_POST, 'new_password');
    $errors = validate_new_password($newPassword, post_string($_POST, 'new_password_confirm'), 'new_password', 'new_password_confirm');
    if ($errors === []) {
        $status = complete_password_reset(db(), $auth, $newPassword, $now);
        unset($_SESSION['password_reset']);
        if ($status === 'ok') {
            renew_session_for_auth();
            flash_set($_SESSION, 'パスワードを再設定しました。新しいパスワードでログインしてください');
            redirect('login.php');
        }
        flash_set($_SESSION, '本人確認からやり直してください');
        redirect('password_reset.php');
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="auth-card">
    <h1>パスワード再設定</h1>
    <form method="post" action="" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <label>新しいパスワード <input type="password" name="new_password" autocomplete="new-password"></label>
        <label>新しいパスワード（確認用） <input type="password" name="new_password_confirm" autocomplete="new-password"></label>
        <p class="field-hint">半角英字・数字をそれぞれ含む8文字以上</p>
        <?php include __DIR__ . '/includes/form_errors.php'; ?>
        <button type="submit">パスワードを再設定する</button>
    </form>
    <p class="auth-switch"><a href="login.php">ログイン画面に戻る</a></p>
</div>
<p class="auth-note">※再設定後はログイン画面から新しいパスワードでログインしてください。<br>※パスワードを再設定しても、ログインのロックは解除されません（ロック中は10分後に再度お試しください）。</p>
<?php include __DIR__ . '/includes/footer.php'; ?>
