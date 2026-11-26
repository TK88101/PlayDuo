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
        <p class="panel-lead">登録内容を変更できます。変更する項目を入力して、保存してください。</p>
        <form method="post" action="" class="mypage-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <div class="field">
                <label for="nickname">ニックネーム <span class="badge-optional">任意</span></label>
                <input type="text" id="nickname" name="nickname" value="<?= h($form['nickname'] ?? '') ?>" placeholder="デュオプレイヤー">
                <p class="field-hint">2〜20文字で入力してください。</p>
            </div>
            <div class="field">
                <label for="email">メールアドレス <span class="badge-required">必須</span></label>
                <input type="email" id="email" name="email" value="<?= h($form['email'] ?? '') ?>" placeholder="duo.player@example.com">
            </div>
            <div class="field">
                <label for="phone">電話番号 <span class="badge-optional">任意</span></label>
                <input type="tel" id="phone" name="phone" value="<?= h($form['phone'] ?? '') ?>" placeholder="090-1234-5678">
            </div>
            <div class="field">
                <label for="current_password">現在のパスワード <span class="badge-required">必須</span></label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                <p class="field-hint">本人確認のため、現在のパスワードを入力してください。</p>
            </div>
            <div class="form-actions">
                <a class="button-cancel" href="mypage.php">キャンセル</a>
                <button type="submit" class="button-save">変更を保存する</button>
            </div>
            <?php include __DIR__ . '/includes/form_errors.php'; ?>
        </form>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
