<?php
// U06 ログイン（U06-a エラー／U06-b ロック中）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin_auth.php';
send_no_store_headers();

$page_title = 'ログイン';
$message_in_page = true;   // 登録完了・再設定完了のメッセージはカードの中に出す（Figma U06 のメッセージ表示欄）
$locked = false;

if (is_post()) {
    verify_csrf_or_fail();
    $email = trim_all(post_string($_POST, 'email'));
    $result = attempt_login(db(), $email, post_string($_POST, 'password'), time());
    if ($result['status'] === 'ok') {
        // 戻り先は session 更新の前に取り出して消す（1 回だけ使う。要確認 Q05・Q06）
        $returnTo = take_login_return($_SESSION);
        renew_session_for_auth();
        forget_product_draft($_SESSION);   // 管理者と会員の両方には同時にならない（Plan 2026-11-26-php-admin §2.0）
        unset($_SESSION['admin_id']);
        $_SESSION['member_id'] = $result['member_id'];
        redirect($returnTo ?? 'index.php');
    }
    $locked = $result['status'] === 'locked';
    if (!$locked) {
        $errors['password'] = 'メールアドレスまたはパスワードが違います';
    }
    $old['email'] = $email;
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="auth-card">
    <h1>ログイン</h1>
    <?php if (!empty($message)): ?>
        <p class="message"><?= h($message) ?></p>
    <?php endif; ?>
    <?php if ($locked): ?>
        <div class="locked">
            <p class="form-alert-title">10分後にもう一度お試しください</p>
            <p>ログインに5回続けて失敗したため、一時的にログインできません。</p>
        </div>
    <?php elseif (isset($errors['password'])): ?>
        <div class="form-alert">
            <p class="form-alert-title"><?= h($errors['password']) ?></p>
            <p>入力内容をご確認のうえ、もう一度お試しください。</p>
        </div>
    <?php endif; ?>
    <form method="post" action="" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <label>メールアドレス <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>"></label>
        <?= error_html($errors, 'email') ?>
        <label>パスワード <input type="password" name="password"></label>
        <a class="form-link" href="password_reset.php">パスワードを忘れた場合</a>
        <button type="submit">ログイン</button>
    </form>
    <p class="auth-switch">はじめての方は <a href="register.php">新規会員登録</a></p>
</div>
<p class="auth-note">※カートに追加するときにログインが必要です（閲覧・検索はログインなしで利用可）</p>
<?php include __DIR__ . '/includes/footer.php'; ?>
