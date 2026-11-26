<?php
// A03 商品管理一覧
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_product.php';
require_once __DIR__ . '/../includes/admin_product_repo.php';
require_admin($admin_id);

$page_title = '商品管理';
$products = all_products_for_admin(db());
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<div class="admin-page-head">
    <h1>商品管理</h1>
    <a class="button-primary" href="product_edit.php">＋商品を登録</a>
</div>
<table class="admin-table">
    <thead>
        <tr>
            <th>ID</th><th>タイトル</th><th>ジャンル</th><th>プレイ人数</th><th>協力・対戦</th><th>対応OS</th>
            <th>クロスプレイ</th><th>価格</th><th>年齢区分</th><th>状態</th><th>操作</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product): ?>
            <?php $is_public = (int) $product['public_flag'] === 1; ?>
            <tr class="product-row" data-product-id="<?= h($product['product_id']) ?>">
                <td><?= h(product_code($product)) ?></td>
                <td><?= h($product['name']) ?></td>
                <td><?= h($product['genre']) ?></td>
                <td><?= h(players_label($product)) ?></td>
                <td><?= h($product['play_style']) ?></td>
                <td><?= h(admin_os_label($product)) ?></td>
                <td><?= h(admin_crossplay_mark($product)) ?></td>
                <td><?= h(yen($product['price'])) ?></td>
                <td><?= h($product['age_rating']) ?></td>
                <td><span class="status <?= $is_public ? 'status-public' : 'status-hidden' ?>"><?= h(public_status_label($product)) ?></span></td>
                <td class="actions">
                    <a href="product_edit.php?id=<?= h($product['product_id']) ?>">編集</a>
                    <a href="product_visibility.php?id=<?= h($product['product_id']) ?>"><?= $is_public ? '非公開' : '公開' ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
