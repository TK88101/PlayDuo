<?php
// U06 ログイン（U06-a エラー／U06-b ロック中）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

$page_title = 'ログイン';
$locked = false;

if (is_post()) {
    verify_csrf_or_fail();
    $email = trim_all(post_string($_POST, 'email'));
    $result = attempt_login(db(), $email, post_string($_POST, 'password'), time());
    if ($result['status'] === 'ok') {
        renew_session_for_auth();
        $_SESSION['member_id'] = $result['member_id'];
        redirect('index.php');
    }
    $locked = $result['status'] === 'locked';
    if (!$locked) {
        $errors['password'] = 'メールアドレスまたはパスワードが違います';
    }
    $old['email'] = $email;
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>ログイン</h1>
<?php if ($locked): ?>
    <p class="locked">ログインに5回失敗したため、10分間ログインできません。時間をおいてからお試しください。</p>
<?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <label>メールアドレス <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"></label>
    <?= error_html($errors, 'email') ?>
    <label>パスワード <input type="password" name="password"></label>
    <?= error_html($errors, 'password') ?>
    <button type="submit">ログイン</button>
</form>
<p><a href="password_reset.php">パスワードを忘れた方はこちら</a></p>
<p><a href="register.php">会員登録はこちら</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
