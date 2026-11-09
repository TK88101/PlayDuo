<?php
// U18 復旧コード表示（この画面で 1 回だけ表示する）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_auth_page_headers();

$page_title = '復旧コード';

if (is_post()) {
    verify_csrf_or_fail();
    flash_set($_SESSION, '会員登録が完了しました。ログインしてください');
    redirect('login.php');
}

$recovery_codes = $_SESSION['register_done']['recovery_codes'] ?? null;
unset($_SESSION['register_done']);
if (!is_array($recovery_codes)) {
    redirect('login.php');
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>復旧コード</h1>
<p>認証アプリが使えなくなったときのパスワード再設定に使います。<strong>この画面でしか表示されません。</strong>書き写すなどして保管してください（各コードは 1 回だけ使えます）。</p>
<ol id="recovery-codes">
    <?php foreach ($recovery_codes as $code): ?>
        <li class="recovery-code"><?= h($code) ?></li>
    <?php endforeach; ?>
</ol>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <button type="submit">次へ</button>
</form>
<script>
    // 戻るボタン（bfcache）で古い画面が出ないようにする
    window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });
    window.addEventListener('pagehide', function () {
        var list = document.getElementById('recovery-codes');
        if (list) { list.remove(); }
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
