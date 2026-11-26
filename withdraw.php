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
        <div class="form-alert">
            <p class="form-alert-title">退会の前に必ずご確認ください</p>
            <ul>
                <li>退会すると、アカウントは元に戻せません。</li>
                <li>購入履歴の閲覧・ゲームの再ダウンロードができなくなります。</li>
                <li>購入済みのゲームの返金はありません。</li>
            </ul>
        </div>
        <form method="post" action="" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <label>現在のパスワード（必須） <input type="password" name="current_password" autocomplete="current-password"></label>
            <?= error_html($errors, 'current_password') ?>
            <label class="agree"><input type="checkbox" name="agree" value="1"> 上の注意事項を確認し、退会に同意します</label>
            <?= error_html($errors, 'agree') ?>
            <p class="confirm-note">この操作は取り消せません。</p>
            <div class="form-actions">
                <a class="button-sub" href="mypage.php">退会せずに戻る</a>
                <button type="submit">退会する</button>
            </div>
        </form>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
