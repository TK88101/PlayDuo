<?php
// A11 注文詳細（参照のみ。名称・価格は購入時）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_order_repo.php';
require_once __DIR__ . '/../includes/admin_member_repo.php';
require_once __DIR__ . '/../includes/admin_product.php';
require_once __DIR__ . '/../includes/order_repo.php';
require_admin($admin_id);

$order_id = parse_positive_int($_GET['id'] ?? null);
$detail = $order_id === null ? null : find_order_detail(db(), $order_id);
if ($detail === null) {
    $not_found_message = 'この注文は見つかりません';   // A11-007
    require __DIR__ . '/../includes/admin_not_found.php';
}
$order = $detail['order'];
$order_items = $detail['items'];
$page_title = '注文詳細';
$summary = [
    '注文番号' => format_order_number($order_id, $order['order_date']),
    '購入日時' => display_datetime($order['order_date']),
    '会員ID'   => member_code((int) $order['member_id']),
    '氏名'     => $order['member_name'],
    '商品数'   => count($order_items) . '点',
    '合計金額' => yen($detail['total']),
];
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1>注文詳細</h1>
<section>
    <h2>注文情報</h2>
    <dl class="admin-detail">
        <?php foreach ($summary as $label => $value): ?>
            <dt><?= h($label) ?></dt><dd><?= h($value) ?></dd>
        <?php endforeach; ?>
    </dl>
</section>
<section>
    <h2>購入したゲーム（ダウンロード権利あり）</h2>
    <table class="admin-table">
        <thead><tr><th>商品ID</th><th>ゲーム</th><th>購入時の価格</th></tr></thead>
        <tbody>
            <?php foreach ($order_items as $item): ?>
                <tr class="order-item-row">
                    <td><?= h(product_code($item)) ?></td>
                    <td><?= h($item['product_name']) ?></td>
                    <td><?= h(yen($item['price'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<p><a href="orders.php">一覧へ戻る</a></p>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
