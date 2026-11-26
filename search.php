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
<?php $sort_labels = ['recommend' => 'おすすめ順', 'new' => '新着順', 'price_asc' => '価格の安い順', 'price_desc' => '価格の高い順']; ?>
<div class="search-layout">
    <aside class="filter-sidebar">
        <h2>絞り込み</h2>
        <?php include __DIR__ . '/includes/search_form.php'; ?>
    </aside>

    <section class="results">
        <div class="results-head">
            <div>
                <h1 class="result-count">検索結果 <?= h($total_count) ?>件</h1>
                <p class="results-note">
                    <span>条件：<?= h(criteria_summary($criteria)) ?></span>
                    <span>メンバー全員で遊べるかを横並びで比べられます</span>
                </p>
            </div>
            <!-- form 属性で左の絞り込みフォームと一緒に送る（Figma U02 の右上の位置に置くため） -->
            <label class="sort-select">並び替え：
                <select name="sort" form="search-filters">
                    <?php foreach ($sort_labels as $value => $label): ?>
                        <option value="<?= h($value) ?>"<?= $criteria['sort'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

<?php if ($total_count === 0): ?>
    <p class="no-result">条件に合うゲームが見つかりませんでした。条件を変えてもう一度お試しください。</p>
<?php else: ?>
    <table>
        <thead>
            <tr><th>ゲーム</th><th>ジャンル</th><th>プレイ人数</th><th>協力／対戦</th><th>対応OS</th><th>クロスプレイ</th><th>容量</th><th>価格</th><th><span class="visually-hidden">詳細</span></th></tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr class="product-row">
                    <td><div class="game-cell"><img src="assets/games/<?= h($p['image']) ?>" alt=""> <span><?= h($p['name']) ?></span></div></td>
                    <td><?= h($p['genre']) ?></td>
                    <td><?= h(players_label($p)) ?></td>
                    <td><?= h($p['play_style']) ?></td>
                    <?php $both_os = (int) $p['os_windows'] === 1 && (int) $p['os_mac'] === 1; ?>
                    <?php $crossplay = crossplay_tag($p); ?>
                    <td<?= $both_os ? '' : ' class="os-limited"' ?>><?= h(os_tag_label($p)) ?></td>
                    <td class="<?= $crossplay['available'] ? 'crossplay-ok' : 'crossplay-ng' ?>"><?= h(crossplay_label($p)) ?></td>
                    <td><?= h($p['capacity']) ?>GB</td>
                    <td><?= h(yen($p['price'])) ?></td>
                    <td><a href="product.php?id=<?= h($p['product_id']) ?>">詳細</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
