<?php
// A02 管理メニュー
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_admin($admin_id);

$page_title = '管理メニュー';
$menu_cards = [
    ['href' => 'products.php', 'title' => '商品管理', 'text' => '商品の登録・編集・公開／非公開'],
    ['href' => 'members.php',  'title' => '会員管理', 'text' => '会員の検索・詳細と購入履歴'],
    ['href' => 'orders.php',   'title' => '注文管理', 'text' => '注文の検索・詳細'],
];
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1>管理メニュー</h1>
<ul class="admin-menu">
    <?php foreach ($menu_cards as $card): ?>
        <li class="admin-menu-card">
            <a href="<?= h($card['href']) ?>">
                <span class="admin-menu-title"><?= h($card['title']) ?></span>
                <span class="admin-menu-text"><?= h($card['text']) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
