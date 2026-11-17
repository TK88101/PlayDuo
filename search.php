<?php
// U02 検索結果（比較一覧）。条件は GET（対応表 U01・U02 の name）
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/product_repo.php';

$page_title = '検索結果';
$parsed = parse_search_criteria($_GET);
$criteria = $parsed['criteria'];
$errors = $parsed['errors'];
$products = search_products(db(), $criteria);
$total_count = count($products);
$full_filters = true;
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<h1>ゲームを探す</h1>
<?php include __DIR__ . '/includes/search_form.php'; ?>

<p class="result-count">検索結果 <?= h($total_count) ?>件</p>
<?php if ($criteria['keyword'] !== ''): ?>
    <p>キーワード：<?= h($criteria['keyword']) ?></p>
<?php endif; ?>

<?php if ($total_count === 0): ?>
    <p class="no-result">条件に合うゲームが見つかりませんでした。条件を変えてもう一度お試しください。</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th>ゲーム</th><th>ジャンル</th><th>プレイ人数</th><th>協力・対戦</th><th>対応OS</th><th>クロスプレイ</th><th>容量</th><th>価格</th><th>詳細</th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr class="product-row">
                    <td><img src="assets/games/<?= h($p['image']) ?>" alt=""> <?= h($p['name']) ?></td>
                    <td><?= h($p['genre']) ?></td>
                    <td><?= h(players_label($p)) ?></td>
                    <td><?= h($p['play_style']) ?></td>
                    <td><?= h(os_label($p)) ?></td>
                    <td><?= h(crossplay_label($p)) ?></td>
                    <td><?= h($p['capacity']) ?>GB</td>
                    <td><?= h(yen($p['price'])) ?></td>
                    <td><a href="product.php?id=<?= h($p['product_id']) ?>">詳細</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
