<?php
// 404 画面（画面ファイルのトップレベルから require する。ここで処理を終える）
// 使う変数：$not_found_message（なければ既定の文言）＋ header.php の変数
http_response_code(404);
$page_title = '見つかりません';
include __DIR__ . '/header.php';
?>
<h1><?= h($not_found_message ?? 'お探しのページは見つかりませんでした') ?></h1>
<p><a href="search.php">ゲームを探す</a></p>
<?php
include __DIR__ . '/footer.php';
exit;
