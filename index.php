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
<h1 class="hero-title">友達全員で遊べるゲームが、買う前に分かる</h1>
<p class="hero-lead">人数・対応OS・クロスプレイを並べて比べられる、マルチプレイ専門のPCゲームストア</p>

<section class="search-panel">
    <h2>条件から探す</h2>
    <?php include __DIR__ . '/includes/search_form.php'; ?>
</section>

<section class="recommend">
    <h2>おすすめのゲーム</h2>
    <div class="card-grid">
        <?php foreach ($recommend_products as $p): ?>
            <?php $crossplay = crossplay_tag($p); ?>
            <article class="product-card">
                <img src="assets/games/<?= h($p['image']) ?>" alt="<?= h($p['name']) ?>">
                <h3><?= h($p['name']) ?></h3>
                <ul class="tags">
                    <li class="tag"><?= h(players_label($p)) ?></li>
                    <li class="tag"><?= h($p['play_style']) ?></li>
                    <li class="tag-break" aria-hidden="true"></li>
                    <li class="tag"><?= h(os_tag_label($p)) ?></li>
                    <li class="tag <?= $crossplay['available'] ? 'tag-ok' : 'tag-muted' ?>"><?= h($crossplay['label']) ?></li>
                </ul>
                <div class="card-footer">
                    <p class="price"><?= h(yen($p['price'])) ?></p>
                    <a href="product.php?id=<?= h($p['product_id']) ?>">詳細を見る</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
