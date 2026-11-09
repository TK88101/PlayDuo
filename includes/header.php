<?php
// 共通ヘッダー（骨架。見た目は吉松さんの HTML/CSS で置き換える）
// 使う変数：$page_title, $login_member, $message, $csrf_token
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? '') ?> | Play Duo</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header>
    <a href="index.php">Play Duo</a>
    <nav>
        <?php if (empty($login_member)): ?>
            <a href="login.php">ログイン</a>
            <a href="register.php">会員登録</a>
        <?php else: ?>
            <a href="mypage.php">マイページ</a>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token ?? '') ?>">
                <button type="submit">ログアウト</button>
            </form>
        <?php endif; ?>
    </nav>
</header>
<main>
<?php if (!empty($message)): ?>
    <p class="message"><?= h($message) ?></p>
<?php endif; ?>
