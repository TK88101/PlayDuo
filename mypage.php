<?php
// U11 マイページ（暫定：準備中。購入履歴・ダウンロードはガント「マイページ・ダウンロード」で実装する）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

require_login($login_member, ['page' => 'mypage']);
$page_title = 'マイページ';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>マイページ</h1>
<p>購入履歴とダウンロードは準備中です。</p>
<a href="index.php">トップへ戻る</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
