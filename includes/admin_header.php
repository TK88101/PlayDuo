<?php
// 管理画面の共通ヘッダー（骨架。見た目は admin.css ができたら置き換える）
// 使う変数：$page_title, $admin_id, $message, $csrf_token
$admin_css = __DIR__ . '/../assets/css/admin.css';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? '') ?> | Play Duo 管理画面</title>
<?php if (is_file($admin_css)): ?>
    <link rel="stylesheet" href="../assets/css/admin.css">
<?php endif; ?>
</head>
<body class="admin">
<header class="admin-header">
    <a href="index.php">Play Duo 管理画面</a>
<?php if (!empty($admin_id)): ?>
    <form method="post" action="logout.php">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
        <button type="submit">ログアウト</button>
    </form>
<?php endif; ?>
</header>
<main class="admin-main">
<?php if (!empty($message)): ?>
    <p class="message"><?= h($message) ?></p>
<?php endif; ?>
