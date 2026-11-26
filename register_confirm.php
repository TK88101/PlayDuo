<?php
// U05 会員登録確認
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

$page_title = '会員登録確認';
$minimal_header = true;   // Figma U05：ロゴだけのヘッダー

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
<div class="auth-card auth-card-wide">
    <h1>会員登録（確認）</h1>
    <dl class="confirm-list">
        <dt>氏名</dt><dd><?= h($input['name']) ?></dd>
        <dt>メールアドレス</dt><dd><?= h($input['email']) ?></dd>
        <dt>パスワード</dt><dd><span class="password-mask" aria-hidden="true">●●●●●●●●</span>（表示しません）</dd>
        <dt>生年月日</dt><dd><?= h(display_japanese_date((string) $input['birth_date'])) ?></dd>
        <dt>ニックネーム（任意）</dt><dd><?= h(or_unset($input['nickname'] ?? null)) ?></dd>
        <dt>電話番号（任意）</dt><dd><?= h(or_unset($input['phone'] ?? null)) ?></dd>
    </dl>
    <p class="confirm-note">※生年月日は登録後に変更できません。内容をご確認ください。</p>
    <form method="post" action="" class="confirm-actions">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <button type="submit" name="action" value="back" class="button-sub">修正する</button>
        <button type="submit" name="action" value="register">登録する</button>
    </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
