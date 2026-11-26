<?php
// U12 会員情報の変更。Plan 2026-11-26 §2.4
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/account.php';
send_no_store_headers();

$login = require_login($login_member, ['page' => 'account_edit']);
$member_id = (int) $login['member_id'];
$page_title = '会員情報の変更';
$mypage_active = 'account';

if (is_post()) {
    verify_csrf_or_fail();
    $result = validate_account_input($_POST);
    $errors = $result['errors'];
    $old = $result['input'];
    $current = post_string($_POST, 'current_password');
    if ($current === '') {
        $errors['current_password'] = CURRENT_PASSWORD_REQUIRED;
    } elseif ($errors === [] && password_check_throttled($_SESSION, time())) {
        $errors['current_password'] = CURRENT_PASSWORD_THROTTLED;
    } elseif ($errors === []) {
        $status = update_account(db(), $member_id, $result['input'], $current);
        if ($status === 'ok') {
            clear_password_check_failures($_SESSION);
            flash_set($_SESSION, '会員情報を変更しました');
            redirect('mypage.php');
        }
        if ($status === 'inactive') {
            destroy_session();
            redirect('login.php');
        }
        if ($status === 'email_taken') {
            $errors['email'] = 'このメールアドレスは登録済みです';
        } else {
            $errors['current_password'] = current_password_failure($_SESSION, $member_id, 'account_edit', time());
        }
    }
}

// 初期表示は現在の登録値（要確認 Q18 a）。エラーで戻ったときは入力値
$member = find_account(db(), $member_id) ?? ['nickname' => null, 'email' => '', 'phone' => null];
$form = $old !== [] ? $old : $member;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>マイページ</h1>
<div class="mypage-layout">
    <?php include __DIR__ . '/includes/mypage_menu.php'; ?>
    <section class="mypage-panel">
        <h2>会員情報の変更</h2>
        <p>変更したい項目を入力し、現在のパスワードを入力して保存してください。氏名・生年月日は変更できません。</p>
        <form method="post" action="" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <label>ニックネーム（任意） <input type="text" name="nickname" value="<?= h($form['nickname'] ?? '') ?>" placeholder="2〜20文字"></label>
            <?= error_html($errors, 'nickname') ?>
            <label>メールアドレス（必須） <input type="email" name="email" value="<?= h($form['email'] ?? '') ?>"></label>
            <?= error_html($errors, 'email') ?>
            <label>電話番号（任意） <input type="tel" name="phone" value="<?= h($form['phone'] ?? '') ?>" placeholder="090-1234-5678"></label>
            <?= error_html($errors, 'phone') ?>
            <label>現在のパスワード（必須） <input type="password" name="current_password" autocomplete="current-password"></label>
            <?= error_html($errors, 'current_password') ?>
            <div class="form-actions">
                <a class="button-sub" href="mypage.php">キャンセル</a>
                <button type="submit">変更を保存する</button>
            </div>
        </form>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
