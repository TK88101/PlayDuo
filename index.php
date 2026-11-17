<?php
// U01 トップ（おすすめのゲーム・条件から探す）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_repo.php';

$page_title = 'トップ';
$recommend_products = find_recommended_products(db());
$criteria = parse_search_criteria([])['criteria'];
$full_filters = false;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>友達全員で遊べるゲームが、買う前に分かる</h1>
<p>人数・対応OS・クロスプレイを横並びで比べて、メンバー全員で遊べるゲームを探せます。</p>

<section>
    <h2>条件から探す</h2>
    <?php include __DIR__ . '/includes/search_form.php'; ?>
</section>

<section>
    <h2>おすすめのゲーム</h2>
    <?php foreach ($recommend_products as $p): ?>
        <article class="product-card">
            <img src="assets/games/<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>">
            <h3><?= h($p['name']) ?></h3>
            <p><?= h(spec_line($p)) ?></p>
            <p class="price"><?= h(yen($p['price'])) ?></p>
            <a href="product.php?id=<?= h($p['product_id']) ?>">詳細を見る</a>
        </article>
    <?php endforeach; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
