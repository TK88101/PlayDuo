<?php
// U01「条件から探す」・U02 絞り込みパネル（GET search.php。name は対応表 U01・U02）
// 使う変数：$criteria（parse_search_criteria の結果）、$full_filters（U02 だけ true：ジャンル・価格・並び替え）
$filter_groups = [
    'players'    => ['プレイ人数', ['2' => '2人', '3-4' => '3〜4人', '5plus' => '5人以上']],
    'os'         => ['対応OS', ['windows' => 'Windows', 'mac' => 'Mac']],
    'play_style' => ['遊び方', ['協力' => '協力', '対戦' => '対戦']],
];
if ($full_filters) {
    $filter_groups['genre'] = ['ジャンル', array_combine(SEARCH_GENRES, SEARCH_GENRES)];
}
$sort_labels = ['recommend' => 'おすすめ順', 'new' => '新着順', 'price_asc' => '価格の安い順', 'price_desc' => '価格の高い順'];
?>
<form method="get" action="search.php" class="search-filters">
    <?php if ($full_filters): ?>
        <label>キーワード <input type="search" name="keyword" value="<?= h($criteria['keyword']) ?>"></label>
        <?= error_html($errors, 'keyword') ?>
    <?php endif; ?>
    <?php foreach ($filter_groups as $name => [$legend, $options]): ?>
        <fieldset>
            <legend><?= h($legend) ?></legend>
            <?php foreach ($options as $value => $label): ?>
                <label><input type="checkbox" name="<?= h($name) ?>[]" value="<?= h($value) ?>"<?= checked(in_array((string) $value, $criteria[$name], true)) ?>> <?= h($label) ?></label>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>
    <fieldset>
        <legend>クロスプレイ</legend>
        <label><input type="checkbox" name="crossplay" value="1"<?= checked($criteria['crossplay']) ?>> できる</label>
    </fieldset>
    <?php if ($full_filters): ?>
        <fieldset>
            <legend>価格</legend>
            <input type="number" name="price_min" min="0" value="<?= h($criteria['price_min']) ?>"> 〜
            <input type="number" name="price_max" min="0" value="<?= h($criteria['price_max']) ?>"> 円
            <?= error_html($errors, 'price') ?>
        </fieldset>
        <label>並び替え
            <select name="sort">
                <?php foreach ($sort_labels as $value => $label): ?>
                    <option value="<?= h($value) ?>"<?= $criteria['sort'] === $value ? ' selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">この条件で絞り込む</button>
    <?php else: ?>
        <button type="submit">この条件で探す</button>
    <?php endif; ?>
</form>
