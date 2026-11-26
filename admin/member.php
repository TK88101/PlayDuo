<?php
// A09 会員詳細（登録情報と購入履歴。参照のみ）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_member_repo.php';
require_once __DIR__ . '/../includes/order_repo.php';
require_once __DIR__ . '/../includes/age.php';
require_admin($admin_id);

$member_id = parse_positive_int($_GET['id'] ?? null);
$member = $member_id === null ? null : find_member_detail(db(), $member_id);
if ($member === null) {
    $not_found_message = 'この会員は見つかりません';   // A09-011
    require __DIR__ . '/../includes/admin_not_found.php';
}
$orders = member_purchases(db(), $member_id);
$page_title = '会員詳細';
$profile = [
    '会員ID'         => member_code($member_id),
    '氏名'           => $member['name'],
    'メールアドレス' => $member['email'],
    '生年月日'       => display_date($member['birth_date']) . '（' . age_on($member['birth_date'], time()) . '歳）',
    '登録日'         => display_date($member['created_at']),
    '状態'           => member_status_label($member),
];
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="admin-page-head">
    <h1>会員詳細</h1>
    <a class="admin-button-sub" href="members.php">一覧へ戻る</a>
</div>
<div class="admin-detail-layout">
<section class="admin-card admin-side-card">
    <h2>登録情報</h2>
    <dl class="admin-detail">
        <?php foreach ($profile as $label => $value): ?>
            <dt><?= h($label) ?></dt><dd><?= h($value) ?></dd>
        <?php endforeach; ?>
    </dl>
    <p class="note">※パスワードは表示しない</p>
</section>
<section class="admin-card">
    <h2>この会員の購入履歴</h2>
    <?php if ($orders === []): ?>
        <p class="empty">購入履歴はありません</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>購入日</th><th>注文番号</th><th>ゲーム</th><th>価格</th></tr></thead>
            <tbody>
                <?php foreach ($orders as $row): ?>
                    <tr class="purchase-row">
                        <td><?= h(display_date($row['order_date'])) ?></td>
                        <td><a href="order.php?id=<?= h($row['order_id']) ?>"><?= h(format_order_number((int) $row['order_id'], $row['order_date'])) ?></a></td>
                        <td><?= h($row['product_name']) ?></td>
                        <td><?= h(yen($row['price'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
