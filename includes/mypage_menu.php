<?php
// U11〜U16 共通の左メニュー（Figma U11）。使う変数：$mypage_active（library / account / password / withdraw）
$mypage_items = [
    'library'  => ['mypage.php', '購入履歴（ライブラリ）'],
    'account'  => ['account_edit.php', '会員情報の変更'],
    'password' => ['password_change.php', 'パスワードの変更'],
    'withdraw' => ['withdraw.php', '退会'],
];
?>
<nav class="mypage-menu" aria-label="マイページのメニュー">
    <ul>
        <?php foreach ($mypage_items as $key => [$href, $label]): ?>
            <li><a href="<?= h($href) ?>"<?= ($mypage_active ?? '') === $key ? ' aria-current="page"' : '' ?>><?= h($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
</nav>
