<?php
// U07 カート／U07-a 空のカート（ログイン必須）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop.php';

$member = require_login($login_member, ['page' => 'cart']);
$memberId = (int) $member['member_id'];

if (is_post()) {
    verify_csrf_or_fail();
    $productId = parse_positive_int($_POST['product_id'] ?? null);
    if (post_string($_POST, 'action') === 'delete' && $productId !== null) {
        delete_cart_items(db(), $memberId, [$productId]);
    }
    redirect('cart.php');
}

$page_title = 'カート';
$view = cart_view(cart_rows(db(), $memberId), $member['birth_date'], time());
$cart_items = $view['items'];
$total = $view['total'];
$item_count = $view['count'];
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>カート</h1>
<div class="cart-layout">
    <div class="cart-panel">
        <?php if ($cart_items === []): ?>
            <div class="cart-empty">
                <p class="cart-empty-title">カートは空です</p>
                <p>気になるゲームを探して、カートに追加してください。</p>
                <a class="button-primary" href="search.php">ゲームを探す</a>
            </div>
        <?php else: ?>
            <?php $item_mode = 'cart'; ?>
            <?php foreach ($cart_items as $item): ?>
                <?php include __DIR__ . '/includes/cart_item.php'; ?>
            <?php endforeach; ?>
            <p class="panel-note">※購入済みのゲーム・年齢制限に当たるゲームはカートに入りません<br>※「現在購入できません」の商品は合計に含まれません</p>
        <?php endif; ?>
    </div>

    <section class="order-summary">
        <h2>ご注文内容</h2>
        <p class="summary-row"><span>商品数</span> <span><?= h($item_count) ?>点</span></p>
        <p class="summary-row summary-total"><span>合計</span> <span><?= h(yen($total)) ?></span></p>
        <?php if ($cart_items === [] || $item_count === 0): ?>
            <span class="button-primary is-disabled" aria-disabled="true">購入手続きへ</span>
        <?php else: ?>
            <a class="button-primary" href="purchase_confirm.php">購入手続きへ</a>
        <?php endif; ?>
        <a class="summary-link" href="search.php">買い物を続ける</a>
        <?php if ($cart_items === []): ?>
            <p class="summary-note">カートが空のため、購入手続きには進めません</p>
        <?php elseif ($item_count === 0): ?>
            <p class="summary-note">購入できる商品がないため、購入手続きには進めません</p>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
