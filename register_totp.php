<?php
// U17 認証アプリ設定（6 桁の確認が通ってから会員を登録する）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

$page_title = '認証アプリの設定';

if (!isset($_SESSION['register']['input'], $_SESSION['register']['totp_secret'])) {
    redirect('register.php');
}

if (is_post()) {
    verify_csrf_or_fail();
    $result = register_member(db(), $_SESSION['register'], post_string($_POST, 'totp_code'), time());
    if ($result['status'] === 'ok') {
        unset($_SESSION['register']);
        $_SESSION['register_done'] = ['recovery_codes' => $result['recovery_codes']];
        redirect('register_recovery.php');
    }
    if ($result['status'] === 'duplicate') {
        unset($_SESSION['register']);
        flash_set($_SESSION, 'このメールアドレスは登録済みです');
        redirect('register.php');
    }
    $errors['totp_code'] = '6桁のコードが正しくありません。認証アプリに表示されている最新の数字を入力してください';
}

$totp_secret = $_SESSION['register']['totp_secret'];
$totp_uri = totp_uri($totp_secret, $_SESSION['register']['input']['email']);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>認証アプリの設定</h1>
<p>認証アプリ（Google Authenticator など）で QR コードを読み取り、表示された 6 桁を入力してください。</p>
<div id="qrcode" data-uri="<?= h($totp_uri) ?>"></div>
<p>読み取れないときは次のキーを手入力してください：<code id="totp_secret"><?= h($totp_secret) ?></code></p>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <label>認証アプリの6桁 <input type="text" name="totp_code" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>
    <?= error_html($errors, 'totp_code') ?>
    <button type="submit">確認する</button>
</form>
<script src="assets/js/vendor/qrcode.min.js"></script>
<script>
    (function () {
        var el = document.getElementById('qrcode');
        new QRCode(el, { text: el.dataset.uri, width: 200, height: 200 });
    })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
