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
// 商品カード（画像・タグ・価格）を出すため、購入できない商品は items 側の行を使う
$ng_rows = array_values(array_filter($view['items'], static fn (array $item): bool => !$item['can_buy']));
$page_title = '購入できない商品の確認';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>購入できない商品の確認</h1>
<div class="cart-layout">
    <div class="cart-panel">
        <div class="form-alert error-summary">
            <p class="form-alert-title">カート内に購入できない商品が<?= h(count($ng_items)) ?>点あります</p>
            <p>注文は登録されていません。各商品の理由をご確認のうえ、対象商品を削除してから、もう一度購入手続きを行ってください。</p>
        </div>
        <p class="panel-heading"><span>対象商品</span> <span><?= h(count($ng_items)) ?>点</span></p>
        <?php $item_mode = 'error'; ?>
        <?php foreach ($ng_rows as $item): ?>
            <?php include __DIR__ . '/includes/cart_item.php'; ?>
        <?php endforeach; ?>
        <p class="panel-note">※購入済みのゲームは、マイページで確認できます。<br><a href="mypage.php">マイページで購入済み商品を確認</a></p>
    </div>

    <section class="order-summary">
        <h2>ご確認ください</h2>
        <p class="summary-row"><span>購入できない商品</span> <span class="summary-alert"><?= h(count($ng_items)) ?>点</span></p>
        <?php foreach ($reason_counts as $reason => $count): ?>
            <p class="summary-row summary-sub"><span><?= h($reason) ?></span> <span><?= h($count) ?>点</span></p>
        <?php endforeach; ?>
        <p class="summary-text">対象商品をカートから削除します。削除後、カートに戻ります。</p>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <button type="submit" class="button-primary">対象商品を削除してカートへ</button>
        </form>
        <a class="summary-link" href="cart.php">削除せずにカートに戻る</a>
        <p class="summary-note">対象商品がカートに残っている間は、購入手続きに進めません。</p>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
