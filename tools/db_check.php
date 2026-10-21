<?php

declare(strict_types=1);

// 開発環境の確認用（DB に接続できるかだけを見る）。localhost からしか開けない
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $row = db()->query('SELECT DATABASE() AS db_name, VERSION() AS version, @@character_set_database AS charset')->fetch();
    $result = sprintf('OK：%s に接続しました（%s／%s）', $row['db_name'], $row['version'], $row['charset']);
} catch (Throwable $e) {
    error_log('[db_check] ' . $e->getMessage());
    $result = 'NG：接続できませんでした（' . $e->getMessage() . '）';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head><meta charset="UTF-8"><title>DB 接続確認</title></head>
<body>
<p><?= htmlspecialchars($result, ENT_QUOTES, 'UTF-8') ?></p>
<p>PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>
