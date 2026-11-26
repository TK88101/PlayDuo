<?php
// A10 注文管理一覧（GET で検索。条件なしは全件。参照のみ）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_order_repo.php';
require_once __DIR__ . '/../includes/admin_member_repo.php';
require_once __DIR__ . '/../includes/order_repo.php';
require_admin($admin_id);

$page_title = '注文管理';
$parsed = parse_order_criteria($_GET);
$errors = $parsed['errors'];
$orders = $errors === [] ? search_orders(db(), $parsed['criteria']) : [];
$old = [];
foreach (['date_from', 'date_to', 'member', 'product'] as $field) {
    $old[$field] = is_string($_GET[$field] ?? null) ? trim_all($_GET[$field]) : '';
}
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="admin-page-head">
<h1>注文管理</h1>
<form method="get" action="" class="admin-search">
    <label>期間 <input type="date" name="date_from" value="<?= h($old['date_from']) ?>"></label>
    〜 <input type="date" name="date_to" value="<?= h($old['date_to']) ?>" aria-label="期間（終了）">
    <label>会員 <input type="search" name="member" value="<?= h($old['member']) ?>" placeholder="氏名・会員ID"></label>
    <label>商品 <input type="search" name="product" value="<?= h($old['product']) ?>" placeholder="ゲーム名"></label>
    <button type="submit">検索</button>
</form>
</div>
<div class="admin-card">
<?php if ($errors !== []): ?>
    <div class="form-alert">
        <?php foreach ($errors as $error): ?>
            <p><?= h($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php elseif ($orders === []): ?>
    <p class="empty">該当する注文はありません</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr><th>注文番号</th><th>会員ID</th><th>氏名</th><th>購入日時</th><th>商品数</th><th>合計金額</th><th>操作</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr class="order-row">
                    <td><?= h(format_order_number((int) $order['order_id'], $order['order_date'])) ?></td>
                    <td><?= h(member_code((int) $order['member_id'])) ?></td>
                    <td><?= h($order['member_name']) ?></td>
                    <td><?= h(display_datetime($order['order_date'])) ?></td>
                    <td><?= h($order['item_count']) ?>点</td>
                    <td><?= h(yen($order['total'])) ?></td>
                    <td class="actions"><a class="button-small" href="order.php?id=<?= h($order['order_id']) ?>">詳細</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
