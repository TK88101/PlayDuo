<?php
// A04 非公開確認／A05 再公開確認（JS なしの確認画面。POST で切り替えて A03 に戻る）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_product.php';
require_once __DIR__ . '/../includes/admin_product_repo.php';
require_admin($admin_id);

const VISIBILITY_ACTIONS = ['hide' => false, 'show' => true];

if (is_post()) {
    verify_csrf_or_fail();
    $action = $_POST['action'] ?? null;
    if (!is_string($action) || !isset(VISIBILITY_ACTIONS[$action])) {
        http_response_code(400);
        exit;
    }
    $productId = parse_positive_int($_POST['product_id'] ?? null);
    $product = $productId === null ? null : find_product_any(db(), $productId);
    if ($product === null) {
        require __DIR__ . '/../includes/admin_not_found.php';
    }
    $public = VISIBILITY_ACTIONS[$action];
    set_public_flag(db(), $productId, $public);
    flash_set($_SESSION, sprintf('「%s」を%sにしました', $product['name'], $public ? '公開' : '非公開'));
    redirect('products.php');
}

$productId = parse_positive_int($_GET['id'] ?? null);
$product = $productId === null ? null : find_product_any(db(), $productId);
if ($product === null) {
    require __DIR__ . '/../includes/admin_not_found.php';
}
$is_public = (int) $product['public_flag'] === 1;
$page_title = $is_public ? '非公開確認' : '再公開確認';
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="admin-dialog" role="dialog" aria-labelledby="dialog-title">
    <?php if ($is_public): ?>
        <h1 id="dialog-title">この商品を非公開にしますか？</h1>
    <?php else: ?>
        <h1 id="dialog-title">この商品を公開しますか？</h1>
    <?php endif; ?>
    <p class="dialog-target"><?= h(product_code($product)) ?> <?= h($product['name']) ?></p>
    <ul class="dialog-notes">
        <?php if ($is_public): ?>
            <li>会員サイトで検索・購入できなくなります</li>
            <li>購入済みの会員は引き続きダウンロードできます</li>
            <li>商品は削除されず、あとで「公開」に戻せます</li>
        <?php else: ?>
            <li>会員サイトで検索・購入できるようになります</li>
            <li>あとで非公開に切り替えられます</li>
        <?php endif; ?>
    </ul>
    <form method="post" action="" class="dialog-actions">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="product_id" value="<?= h($product['product_id']) ?>">
        <input type="hidden" name="action" value="<?= $is_public ? 'hide' : 'show' ?>">
        <a class="button-secondary" href="products.php">キャンセル</a>
        <button type="submit" class="<?= $is_public ? 'button-danger' : 'button-primary' ?>"><?= $is_public ? '非公開にする' : '公開する' ?></button>
    </form>
</div>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
