<?php
// U09 購入できない商品の確認（注文は登録していない）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop.php';

$member = require_login($login_member, ['page' => 'purchase_error']);
$memberId = (int) $member['member_id'];

if (is_post()) {
    verify_csrf_or_fail();
    remove_unbuyable_items(db(), $memberId, $member['birth_date'], time());
    redirect('cart.php');
}

$view = cart_view(cart_rows(db(), $memberId), $member['birth_date'], time());
$ng_items = $view['ng_items'];
if ($ng_items === []) {
    redirect('cart.php');   // U09-011
}
$reason_counts = array_count_values(array_column($ng_items, 'reason'));
$page_title = '購入できない商品の確認';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>カート内に購入できない商品が<?= h(count($ng_items)) ?>点あります</h1>
<p>注文は登録されていません。対象の商品をカートから削除してから、もう一度購入手続きを行ってください。</p>
<ul>
    <?php foreach ($ng_items as $item): ?>
        <li><?= h($item['name']) ?>：購入できません（<?= h($item['reason']) ?>）</li>
    <?php endforeach; ?>
</ul>
<section>
    <h2>購入できない商品 <?= h(count($ng_items)) ?>点</h2>
    <?php foreach ($reason_counts as $reason => $count): ?>
        <p><?= h($reason) ?> <?= h($count) ?>点</p>
    <?php endforeach; ?>
</section>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <button type="submit">対象商品を削除してカートへ</button>
</form>
<a href="cart.php">削除せずにカートに戻る</a>
<a href="mypage.php">マイページで購入済み商品を確認</a>
<?php include __DIR__ . '/includes/footer.php'; ?>
