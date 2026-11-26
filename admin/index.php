<?php
// A02 管理メニュー
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_admin($admin_id);

$page_title = '管理メニュー';
$menu_cards = [
    ['href' => 'products.php', 'icon' => 'box', 'title' => '商品管理', 'button' => '商品管理へ',
        'text' => '販売するゲームの情報や価格、公開状態を管理します。', 'points' => ['商品の一覧・登録・編集', '公開／非公開の切り替え']],
    ['href' => 'members.php', 'icon' => 'person', 'title' => '会員管理', 'button' => '会員管理へ',
        'text' => '会員の登録情報や利用状態、購入履歴を確認します。', 'points' => ['会員の登録情報を確認', '会員ごとの購入履歴を確認']],
    ['href' => 'orders.php', 'icon' => 'list', 'title' => '注文管理', 'button' => '注文管理へ',
        'text' => '注文の一覧や購入内容、合計金額を確認します。', 'points' => ['期間・会員・商品で検索', '注文ごとの詳細を確認']],
];
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1>管理メニュー</h1>
<p class="admin-lead">ご利用になる管理項目を選択してください。</p>
<ul class="admin-menu">
    <?php foreach ($menu_cards as $card): ?>
        <li class="admin-menu-card">
            <span class="admin-menu-icon icon-<?= h($card['icon']) ?>" aria-hidden="true"></span>
            <h2 class="admin-menu-title"><?= h($card['title']) ?></h2>
            <p class="admin-menu-text"><?= h($card['text']) ?></p>
            <ul class="admin-menu-points">
                <?php foreach ($card['points'] as $point): ?>
                    <li><?= h($point) ?></li>
                <?php endforeach; ?>
            </ul>
            <a class="admin-menu-button" href="<?= h($card['href']) ?>"><?= h($card['button']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
<p class="admin-note">作業を終了するときは、ログアウトしてください。</p>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
