<?php
// U16 退会（論理削除＋カート削除＋ログアウト）。Plan 2026-11-26 §2.6
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/account.php';
send_no_store_headers();

$login = require_login($login_member, ['page' => 'withdraw']);
$member_id = (int) $login['member_id'];
$page_title = '退会';
$mypage_active = 'withdraw';

if (is_post()) {
    verify_csrf_or_fail();
    $current = post_string($_POST, 'current_password');
    if ($current === '') {
        $errors['current_password'] = CURRENT_PASSWORD_REQUIRED;
    }
    if (post_string($_POST, 'agree') !== '1') {
        $errors['agree'] = '注意事項に同意してください';
    }
    if ($errors === [] && password_check_throttled($_SESSION, time())) {
        $errors['current_password'] = CURRENT_PASSWORD_THROTTLED;
    } elseif ($errors === []) {
        $status = withdraw_member(db(), $member_id, $current);
        if ($status === 'ok' || $status === 'already') {
            // 新しい session で完了メッセージを出す（Codex P1-6）
            destroy_session();
            start_app_session();
            flash_set($_SESSION, $status === 'ok' ? '退会が完了しました' : 'すでに退会済みです');
            redirect('index.php');
        }
        $errors['current_password'] = current_password_failure($_SESSION, $member_id, 'withdraw', time());
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>マイページ</h1>
<div class="mypage-layout">
    <?php include __DIR__ . '/includes/mypage_menu.php'; ?>
    <section class="mypage-panel">
        <h2>退会</h2>
        <p class="panel-lead">退会をご希望の方は、以下の注意事項をご確認のうえ、お手続きください。</p>
        <div class="withdraw-warning">
            <p class="withdraw-warning-title">退会前に必ずご確認ください</p>
            <ul>
                <li>退会すると、アカウントおよび登録した会員情報は復元できません。</li>
                <li>購入履歴の閲覧や、購入済みゲームの再ダウンロードができなくなります。</li>
                <li>退会による購入済み商品の返金は行われません。</li>
            </ul>
        </div>
        <form method="post" action="" class="mypage-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <div class="field">
                <label for="current_password">現在のパスワード <span class="badge-required">必須</span></label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                <p class="field-hint">本人確認のため、現在のパスワードを入力してください。</p>
            </div>
            <div class="field">
                <label class="agree"><input type="checkbox" name="agree" value="1"> 上記の注意事項を確認し、退会することに同意します。</label>
                <p class="field-warning">「退会する」を押すと退会が完了します。この操作は取り消せません。</p>
            </div>
            <div class="form-actions">
                <a class="button-cancel" href="mypage.php">退会せずに戻る</a>
                <button type="submit" class="button-danger">退会する</button>
            </div>
            <?php include __DIR__ . '/includes/form_errors.php'; ?>
        </form>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
