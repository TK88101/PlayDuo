<?php
// U05 会員登録確認
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_auth_page_headers();

$page_title = '会員登録確認';

if (!isset($_SESSION['register']['input'])) {
    redirect('register.php');
}

if (is_post()) {
    verify_csrf_or_fail();
    $action = post_string($_POST, 'action');
    if ($action === 'back') {
        redirect('register.php');
    }
    if ($action === 'register') {
        if (email_exists(db(), $_SESSION['register']['input']['email'])) {
            flash_set($_SESSION, 'このメールアドレスは登録済みです');
            redirect('register.php');
        }
        $_SESSION['register']['totp_secret'] ??= totp_generate_secret();
        redirect('register_totp.php');
    }
}

$input = $_SESSION['register']['input'];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>会員登録確認</h1>
<dl>
    <dt>氏名</dt><dd><?= h($input['name']) ?></dd>
    <dt>メールアドレス</dt><dd><?= h($input['email']) ?></dd>
    <dt>生年月日</dt><dd><?= h($input['birth_date']) ?></dd>
    <dt>ニックネーム</dt><dd><?= h($input['nickname'] ?? '') ?></dd>
    <dt>電話番号</dt><dd><?= h($input['phone'] ?? '') ?></dd>
</dl>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <button type="submit" name="action" value="back">修正する</button>
    <button type="submit" name="action" value="register">登録する</button>
</form>
<?php include __DIR__ . '/includes/footer.php'; ?>
