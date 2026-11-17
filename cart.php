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
<?php if ($cart_items === []): ?>
    <p>カートは空です</p>
    <p>気になるゲームを探して、カートに追加してください。</p>
    <a href="search.php">ゲームを探す</a>
<?php else: ?>
    <?php foreach ($cart_items as $item): ?>
        <div class="cart-item">
            <img src="assets/games/<?= h($item['image']) ?>" alt="">
            <h2><?= h($item['name']) ?></h2>
            <p><?= h(spec_line($item)) ?></p>
            <p class="price"><?= h(yen($item['price'])) ?></p>
            <?php if (!$item['can_buy']): ?>
                <p class="cannot-buy">現在購入できません</p>
                <p class="reason"><?= h(cart_reason_message($item)) ?></p>
            <?php endif; ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?= h($item['product_id']) ?>">
                <button type="submit">削除</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<section class="order-summary">
    <h2>ご注文内容</h2>
    <p>商品数 <?= h($item_count) ?>点</p>
    <p>合計 <?= h(yen($total)) ?></p>
    <?php if ($cart_items === []): ?>
        <p>カートが空のため、購入手続きには進めません</p>
    <?php elseif ($item_count === 0): ?>
        <p>購入できる商品がないため、購入手続きには進めません</p>
    <?php else: ?>
        <a href="purchase_confirm.php">購入手続きへ</a>
    <?php endif; ?>
    <a href="search.php">買い物を続ける</a>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
