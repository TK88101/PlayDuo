<?php
// U07 カート・U08 購入確認・U09 購入できない商品 の商品 1 行（Figma U07〜U09 で共通の形）
// 使う変数：$item（cart_view の items の 1 件）、$item_mode（'cart'：削除ボタンあり ／ 'confirm'：なし ／ 'error'：理由の帯あり）
// class="cart-item" は E2E が件数を数えているので変えない。購入できない商品は data-unavailable で見分ける
$crossplay_tag = crossplay_tag($item);
$unavailable = $item_mode === 'cart' && !$item['can_buy'];
?>
<div class="cart-item"<?= $unavailable ? ' data-unavailable' : '' ?>>
    <img src="assets/games/<?= h($item['image']) ?>" alt="">
    <div class="cart-item-body">
        <h2><?= h($item['name']) ?></h2>
        <ul class="tags">
            <li class="tag"><?= h(players_label($item)) ?></li>
            <li class="tag"><?= h($item['play_style']) ?></li>
            <li class="tag"><?= h(os_tag_label($item)) ?></li>
            <li class="tag <?= $crossplay_tag['available'] ? 'tag-ok' : 'tag-muted' ?>"><?= h($crossplay_tag['label']) ?></li>
        </ul>
        <?php if ($unavailable): ?>
            <p class="cannot-buy"><strong>現在購入できません</strong> <span class="reason"><?= h(cart_reason_message($item)) ?></span></p>
        <?php elseif ($item_mode === 'error'): ?>
            <p class="cannot-buy">購入できません</p>
        <?php endif; ?>
    </div>
    <div class="cart-item-side">
        <p class="price"><?= h(yen($item['price'])) ?></p>
        <?php if ($item_mode === 'cart'): ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?= h($item['product_id']) ?>">
                <button type="submit" class="link-danger">削除</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($item_mode === 'error'): ?>
        <p class="reason-strip reason-<?= $item['reason'] === REASON_PURCHASED ? 'purchased' : 'blocked' ?>">
            <span class="reason-badge"><?= h($item['reason']) ?></span><?= h(unbuyable_detail_message($item)) ?>
        </p>
    <?php endif; ?>
</div>
