<?php
// U08 購入内容確認（「購入を確定する」で注文を登録する。決済はしない：要確認 Q12）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop.php';
send_no_store_headers();

const CART_CHANGED_MESSAGE = 'カートの内容が変わりました。もう一度確認してください';

$member = require_login($login_member, ['page' => 'purchase_confirm']);
$memberId = (int) $member['member_id'];

if (is_post()) {
    verify_csrf_or_fail();
    $token = $_POST['confirm_token'] ?? null;
    // 確定済みの確認を再送した（連打・戻る→再送）：同じ完了画面へ
    $doneOrderId = finished_order_id($_SESSION, $token);
    if ($doneOrderId !== null) {
        redirect("purchase_complete.php?id={$doneOrderId}");
    }
    $snapshot = purchase_snapshot($_SESSION, $token, time());
    if ($snapshot === null) {
        flash_set($_SESSION, 'もう一度ご確認のうえ「購入を確定する」を押してください');
        redirect('purchase_confirm.php');
    }

    $result = place_order(db(), $memberId, $member['birth_date'], $snapshot, time());
    if ($result['status'] === 'ok') {
        finish_purchase($_SESSION, $token, $result['order_id']);
        redirect("purchase_complete.php?id={$result['order_id']}");
    }
    clear_purchase($_SESSION);
    if ($result['status'] === 'ng') {
        redirect('purchase_error.php');
    }
    flash_set($_SESSION, CART_CHANGED_MESSAGE);
    redirect($result['status'] === 'empty' ? 'cart.php' : 'purchase_confirm.php');
}

$rows = cart_rows(db(), $memberId);
if ($rows === []) {
    redirect('cart.php');
}
$view = cart_view($rows, $member['birth_date'], time());
if ($view['ng_items'] !== []) {
    redirect('purchase_error.php');   // 要確認 Q10・Q11：購入できない商品があれば U09
}
$cart_items = $view['items'];
$total = $view['total'];
$item_count = $view['count'];
$confirm_token = start_purchase($_SESSION, purchase_snapshot_of($rows), time());
$page_title = '購入内容確認';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>購入内容の確認</h1>
<?php foreach ($cart_items as $item): ?>
    <div class="cart-item">
        <img src="assets/games/<?= h($item['image']) ?>" alt="">
        <h2><?= h($item['name']) ?></h2>
        <p><?= h(spec_line($item)) ?></p>
        <p class="price"><?= h(yen($item['price'])) ?></p>
    </div>
<?php endforeach; ?>
<section class="order-summary">
    <p>商品数 <?= h($item_count) ?>点</p>
    <p>合計 <?= h(yen($total)) ?></p>
    <p>「購入を確定する」を押すと注文が登録され、ダウンロードできるようになります。</p>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="confirm_token" value="<?= h($confirm_token) ?>">
        <button type="submit">購入を確定する</button>
    </form>
    <a href="cart.php">カートに戻る</a>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
