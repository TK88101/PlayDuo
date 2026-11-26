<?php
// U03 商品詳細／U03-a カート追加完了（ポップアップ）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/shop.php';

$product_id = parse_positive_int($_GET['id'] ?? null);
$product = $product_id === null ? null : find_public_product(db(), $product_id);
if ($product === null) {
    $not_found_message = 'この商品は見つかりません';   // 要確認 Q25：存在しない・販売終了
    require __DIR__ . '/includes/not_found.php';
}

if (is_post()) {
    verify_csrf_or_fail();
    $member = require_login($login_member, ['page' => 'product', 'id' => $product_id]);
    $memberId = (int) $member['member_id'];
    $status = try_add_to_cart(db(), $memberId, $member['birth_date'], $product_id, time());
    if ($status === 'ok') {
        $_SESSION['cart_added'] = $product_id;
        redirect("product.php?id={$product_id}");
    }
    // 画面を開いた後に状態が変わった場合（購入済み・年齢制限）
    $message = add_block_reason($product, $status === 'purchased', $member['birth_date'], time())
        ?? 'この商品はカートに追加できません';
}

$page_title = $product['name'];
$added = ($_SESSION['cart_added'] ?? null) === $product_id;
unset($_SESSION['cart_added']);

$add_block = null;
$in_cart = false;
$cart_summary = null;
if ($login_member !== null) {
    $memberId = (int) $login_member['member_id'];
    $add_block = add_block_reason($product, is_purchased(db(), $memberId, $product_id), $login_member['birth_date'], time());
    $rows = cart_rows(db(), $memberId);
    $in_cart = in_array($product_id, array_map('intval', array_column($rows, 'product_id')), true);
    if ($added) {
        $view = cart_view($rows, $login_member['birth_date'], time());
        $cart_summary = ['count' => $view['count'], 'total' => $view['total']];
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<nav class="breadcrumb"><a href="index.php">トップ</a> &gt; <a href="search.php">検索結果</a> &gt; <?= h($product['name']) ?></nav>

<?php $crossplay = crossplay_tag($product); ?>
<div class="product-detail">
    <div class="product-media">
        <img id="main-image" src="assets/games/<?= h($product['image']) ?>" alt="<?= h($product['name']) ?>">
        <ul class="screenshots">
            <?php foreach (['screenshot1', 'screenshot2', 'screenshot3'] as $col): ?>
                <li><img src="assets/games/<?= h($product[$col]) ?>" alt="スクリーンショット"></li>
            <?php endforeach; ?>
        </ul>
        <section class="product-description">
            <h2>説明</h2>
            <p class="description"><?= nl2br(h($product['description'])) ?></p>
        </section>
    </div>

    <div class="product-info">
        <h1><?= h($product['name']) ?></h1>
        <p class="product-sub"><span class="title-en"><?= h($product['title_en']) ?></span>／<?= h($product['genre']) ?>／開発元：<?= h($product['developer']) ?></p>
        <ul class="tags">
            <li class="tag"><?= h(players_label($product)) ?></li>
            <li class="tag"><?= h($product['play_style']) ?></li>
            <li class="tag"><?= h(os_tag_label($product)) ?></li>
            <li class="tag <?= $crossplay['available'] ? 'tag-ok' : 'tag-muted' ?>"><?= h($crossplay['label']) ?></li>
            <li class="tag tag-muted"><?= h(cero_tag_label($product['age_rating'])) ?></li>
        </ul>

        <div class="buy-row">
            <p class="price"><?= h(yen($product['price'])) ?></p>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="product_id" value="<?= h($product_id) ?>">
                <?php if ($add_block !== null || $in_cart): ?>
                    <button type="submit" disabled><?= $in_cart ? 'カートに追加済み' : 'カートに追加' ?></button>
                <?php else: ?>
                    <button type="submit">カートに追加</button>
                <?php endif; ?>
            </form>
            <!-- Figma U03：状態に関係なく常に出す注意書き（追加できない具体的な理由は下の .add-block に出す） -->
            <p class="note">※ログインが必要です<br>※購入済み・年齢制限の商品は追加できません</p>
        </div>
        <?php if ($add_block !== null): ?>
            <p class="add-block"><?= h($add_block) ?></p>
        <?php endif; ?>

        <section class="spec-box">
            <h2>みんなで遊べるか確認</h2>
            <dl>
                <dt>プレイ人数</dt><dd><?= h(players_detail_label($product)) ?></dd>
                <dt>遊び方</dt><dd><?= h(play_style_detail_label($product)) ?></dd>
                <dt>対応OS</dt><dd><?= h(os_detail_label($product)) ?></dd>
                <dt>クロスプレイ</dt><dd class="<?= $crossplay['available'] ? 'crossplay-ok' : 'crossplay-ng' ?>"><?= h(crossplay_detail_label($product)) ?></dd>
                <dt>容量</dt><dd><?= h($product['capacity']) ?>GB</dd>
                <dt>1回のプレイ時間</dt><dd><?= h($product['play_time']) ?></dd>
                <dt>年齢区分</dt><dd><?= h(cero_detail_label($product['age_rating'])) ?></dd>
                <dt>発売日</dt><dd><?= h(display_date($product['release_date'])) ?></dd>
            </dl>
        </section>
    </div>
</div>

<?php if ($added && $cart_summary !== null): ?>
    <div id="cart-added" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cart-added-title">
        <div class="modal">
            <!-- 閉じる＝同じ画面を開き直す（ポップアップはセッションで 1 回だけ出すので消える。JS 不要） -->
            <a class="modal-close" href="product.php?id=<?= h($product_id) ?>" aria-label="閉じる">&times;</a>
            <p class="modal-title" id="cart-added-title"><span class="modal-check" aria-hidden="true">&#10003;</span>カートに追加しました</p>
            <p class="modal-lead">以下の商品をカートに追加しました。引き続きお買い物をお楽しみください。</p>
            <div class="modal-item">
                <img src="assets/games/<?= h($product['image']) ?>" alt="">
                <div>
                    <p class="modal-item-name"><?= h($product['name']) ?></p>
                    <p><?= h($product['title_en']) ?></p>
                    <p><?= h($product['genre']) ?>／開発元：<?= h($product['developer']) ?></p>
                    <p class="modal-item-os"><?= h(os_tag_label($product)) ?></p>
                </div>
                <p class="modal-item-price"><?= h(yen($product['price'])) ?></p>
            </div>
            <div class="modal-summary">
                <p>カート内の商品：<?= h($cart_summary['count']) ?>点</p>
                <p class="modal-subtotal">小計 <?= h(yen($cart_summary['total'])) ?></p>
            </div>
            <div class="modal-actions">
                <a class="button-outline" href="search.php">&larr; 買い物を続ける</a>
                <a class="button-primary" href="cart.php">カートを確認する &rarr;</a>
            </div>
            <p class="modal-note">まだ購入は完了していません。カートから購入手続きへお進みください。</p>
        </div>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
