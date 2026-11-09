<?php
// U14 パスワード再設定（本人確認：メール＋認証アプリの 6 桁 または 復旧コード）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_auth_page_headers();

$page_title = 'パスワード再設定';

if (is_post()) {
    verify_csrf_or_fail();
    $email = trim_all(post_string($_POST, 'email'));
    $now = time();
    $result = verify_reset_identity(db(), $email, post_string($_POST, 'code'), $now);
    if ($result['status'] === 'ok') {
        renew_session_for_auth();
        // member_id は U15 でリクエストから受け取らず、ここに置いた値だけを使う
        $_SESSION['password_reset'] = [
            'member_id'   => $result['member_id'],
            'issued_at'   => $now,
            'password_fp' => $result['password_fp'],
        ];
        redirect('password_reset_new.php');
    }
    $errors['code'] = $result['status'] === 'locked'
        ? '失敗が5回続いたため、10分間お試しいただけません。時間をおいてからお試しください'
        : 'メールアドレスまたはコードが違います';
    $old['email'] = $email;
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>パスワード再設定</h1>
<p>登録したメールアドレスと、認証アプリの6桁 または 復旧コードを入力してください。</p>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <label>メールアドレス <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"></label>
    <?= error_html($errors, 'email') ?>
    <label>認証アプリの6桁 または 復旧コード <input type="text" name="code" autocomplete="one-time-code"></label>
    <?= error_html($errors, 'code') ?>
    <button type="submit">次へ</button>
</form>
<p><a href="login.php">ログインへ戻る</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>
