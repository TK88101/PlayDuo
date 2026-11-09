<?php
// U15 パスワード再設定（新パスワード）。U14 の本人確認から 10 分以内・1 回限り
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_auth_page_headers();

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
<h1>新しいパスワードの設定</h1>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <label>新しいパスワード <input type="password" name="new_password" placeholder="半角英字・数字をそれぞれ含む8文字以上"></label>
    <?= error_html($errors, 'new_password') ?>
    <label>新しいパスワード（確認用） <input type="password" name="new_password_confirm"></label>
    <?= error_html($errors, 'new_password_confirm') ?>
    <button type="submit">パスワードを再設定する</button>
</form>
<p>※ 再設定しても、ログインのロックは解除されません。</p>
<p><a href="login.php">ログインへ戻る</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
