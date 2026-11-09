<?php
// U18 復旧コード表示（この画面で 1 回だけ表示する）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

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
<p>認証アプリが使えなくなったときのパスワード再設定に使います。<strong>この画面でしか表示されません。</strong>コピーまたはテキストで保存して保管してください（各コードは 1 回だけ使えます）。</p>
<ol id="recovery-codes">
    <?php foreach ($recovery_codes as $code): ?>
        <li class="recovery-code"><?= h($code) ?></li>
    <?php endforeach; ?>
</ol>
<p>
    <button type="button" id="copy-codes">コピー</button>
    <button type="button" id="download-codes">テキストで保存</button>
    <span id="codes-status" role="status"></span>
</p>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <button type="submit">次へ</button>
</form>
<script>
    (function () {
        // コピー・保存はブラウザの中だけで行う（サーバーにもう一度取りに行く道は作らない）
        function codesText() {
            var codes = Array.prototype.map.call(document.querySelectorAll('.recovery-code'), function (li) {
                return li.textContent;
            });
            return 'Play Duo 復旧コード（各コードは 1 回だけ使えます）\n\n' + codes.join('\n') + '\n';
        }
        function showStatus(text) { document.getElementById('codes-status').textContent = text; }

        document.getElementById('copy-codes').addEventListener('click', function () {
            if (!navigator.clipboard) { showStatus('コピーできませんでした。書き写してください'); return; }
            navigator.clipboard.writeText(codesText()).then(
                function () { showStatus('コピーしました'); },
                function () { showStatus('コピーできませんでした。書き写してください'); }
            );
        });

        document.getElementById('download-codes').addEventListener('click', function () {
            var url = URL.createObjectURL(new Blob([codesText()], { type: 'text/plain;charset=utf-8' }));
            var a = document.createElement('a');
            a.href = url;
            a.download = 'PlayDuo_復旧コード.txt';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            showStatus('保存しました');
        });

        // 戻るボタン（bfcache）で古い画面が出ないようにする
        window.addEventListener('pageshow', function (e) { if (e.persisted) { location.reload(); } });
        window.addEventListener('pagehide', function () {
            var list = document.getElementById('recovery-codes');
            if (list) { list.remove(); }
        });
    })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
