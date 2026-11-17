<?php
// U10 購入完了（自分の注文だけ表示。GET は読むだけなので再読込しても再注文しない）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop.php';
send_no_store_headers();   // 注文内容を共用 PC のブラウザ履歴に残さない

$order_id = parse_positive_int($_GET['id'] ?? null);
$member = require_login($login_member, $order_id === null ? ['page' => 'cart'] : ['page' => 'purchase_complete', 'id' => $order_id]);
$found = $order_id === null ? null : find_member_order(db(), (int) $member['member_id'], $order_id);
if ($found === null) {
    $not_found_message = 'この注文は見つかりません';
    require __DIR__ . '/includes/not_found.php';
}
$order = $found['order'];
$order_items = $found['items'];
$order_number = format_order_number((int) $order['order_id'], $order['order_date']);
$page_title = '購入完了';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>購入が完了しました</h1>
<dl>
    <dt>注文番号</dt><dd class="order-number"><?= h($order_number) ?></dd>
    <dt>購入日時</dt><dd><?= h(display_datetime($order['order_date'])) ?></dd>
</dl>
<?php foreach ($order_items as $item): ?>
    <div class="order-item">
        <img src="assets/games/<?= h($item['image']) ?>" alt="">
        <p><?= h($item['product_name']) ?>（<?= h(os_label($item)) ?>）</p>
    </div>
<?php endforeach; ?>
<p>購入したゲームはマイページからダウンロードできます。</p>
<a href="mypage.php">マイページでダウンロード</a>
<a href="index.php">トップへ戻る</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
