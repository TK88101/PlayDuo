<?php
// 管理画面の 404（画面ファイルのトップレベルから require する。ここで処理を終える）
http_response_code(404);
$page_title = '見つかりません';
include __DIR__ . '/admin_header.php';
?>
<h1><?= h($not_found_message ?? '対象が見つかりませんでした') ?></h1>
<p><a href="index.php">管理メニューへ</a></p>
<?php
include __DIR__ . '/admin_footer.php';
exit;
