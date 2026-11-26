<?php
// U13 パスワードの変更。Plan 2026-11-26 §2.5
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/account.php';
send_no_store_headers();

$login = require_login($login_member, ['page' => 'password_change']);
$member_id = (int) $login['member_id'];
$page_title = 'パスワードの変更';
$mypage_active = 'password';

if (is_post()) {
    verify_csrf_or_fail();
    $current = post_string($_POST, 'current_password');
    $new = post_string($_POST, 'new_password');
    $errors = validate_new_password($new, post_string($_POST, 'new_password_confirm'), 'new_password', 'new_password_confirm');
    if ($current === '') {
        $errors['current_password'] = CURRENT_PASSWORD_REQUIRED;
    } elseif ($errors === [] && password_check_throttled($_SESSION, time())) {
        $errors['current_password'] = CURRENT_PASSWORD_THROTTLED;
    } elseif ($errors === []) {
        $status = change_password(db(), $member_id, $current, $new);
        if ($status === 'ok') {
            // 現在の端末は session ID を作り直す。他端末のログインはそのまま（Plan §2.0 Q19）
            renew_session_for_auth();
            clear_password_check_failures($_SESSION);
            flash_set($_SESSION, 'パスワードを変更しました');
            redirect('mypage.php');
        }
        if ($status === 'inactive') {
            destroy_session();
            redirect('login.php');
        }
        $errors['current_password'] = current_password_failure($_SESSION, $member_id, 'password_change', time());
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>マイページ</h1>
<div class="mypage-layout">
    <?php include __DIR__ . '/includes/mypage_menu.php'; ?>
    <section class="mypage-panel">
        <h2>パスワードの変更</h2>
        <p>ほかのサービスとは異なるパスワードを設定してください。</p>
        <form method="post" action="" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <label>現在のパスワード（必須） <input type="password" name="current_password" autocomplete="current-password"></label>
            <?= error_html($errors, 'current_password') ?>
            <label>新しいパスワード（必須） <input type="password" name="new_password" autocomplete="new-password"></label>
            <?= error_html($errors, 'new_password') ?>
            <p class="form-hint">半角英字・数字をそれぞれ含む8文字以上で入力してください。</p>
            <label>新しいパスワード（確認）（必須） <input type="password" name="new_password_confirm" autocomplete="new-password"></label>
            <?= error_html($errors, 'new_password_confirm') ?>
            <div class="form-actions">
                <a class="button-sub" href="mypage.php">キャンセル</a>
                <button type="submit">パスワードを変更する</button>
            </div>
        </form>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
