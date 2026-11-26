<?php
// A07 商品登録・編集確認（「修正する」で A06 へ戻る・「登録する」で保存して A03 へ）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_product.php';
require_once __DIR__ . '/../includes/admin_product_repo.php';
require_once __DIR__ . '/../includes/admin_product_draft.php';
require_admin($admin_id);

$draft = $_SESSION[PRODUCT_DRAFT_KEY] ?? null;
if (!is_array($draft) || ($draft['ready'] ?? false) !== true) {
    redirect('products.php');   // 直接開いた・保存済み（A07-007・A07-008）
}
$product_id = $draft['product_id'];
$edit_query = $product_id === null ? '' : "id={$product_id}&";

if (is_post()) {
    verify_csrf_or_fail();
    $action = $_POST['action'] ?? null;
    if ($action === 'back') {
        $_SESSION[PRODUCT_DRAFT_KEY]['ready'] = false;
        redirect("product_edit.php?{$edit_query}resume=1");   // 入力は保持（A07-006）
    }
    if ($action !== 'save') {
        http_response_code(400);
        exit;
    }
    $draft = take_product_draft($_SESSION);
    $tmp_dir = admin_upload_tmp_dir();
    flash_set($_SESSION, save_product_draft(db(), $draft, $tmp_dir, time()));
    redirect('products.php');
}

$input = $draft['input'];
$is_new = $product_id === null;
$product = $is_new ? null : find_product_any(db(), $product_id);
$page_title = $is_new ? '商品登録確認' : '商品編集確認';
$rows = [
    'タイトル' => $input['name'], '英語名' => or_unset($input['title_en']), 'ジャンル' => $input['genre'],
    '価格' => yen($input['price']), '容量' => "{$input['capacity']}GB", '発売日' => display_japanese_date($input['release_date']),
    '開発元' => $input['developer'], '年齢区分' => cero_detail_label($input['age_rating']),
    'プレイ人数' => players_label($input), '遊び方' => $input['play_style'], '1回のプレイ時間' => $input['play_time'],
    '対応OS' => os_label($input), 'クロスプレイ' => crossplay_label($input),
];
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1><?= $is_new ? '商品登録確認' : '商品編集確認' ?></h1>
<p>この内容で<?= $is_new ? '登録' : '更新' ?>します。よろしいですか？</p>
<?php if ($product !== null): ?>
    <p class="dialog-target"><?= h(product_code($product)) ?> <?= h($product['name']) ?></p>
<?php endif; ?>
<dl class="admin-confirm">
    <?php foreach ($rows as $label => $value): ?>
        <dt><?= h($label) ?></dt><dd><?= h($value) ?></dd>
    <?php endforeach; ?>
    <dt>説明文</dt><dd class="description"><?= nl2br(h($input['description'])) ?></dd>
    <?php foreach (PRODUCT_FILE_LABELS as $field => $label): ?>
        <dt><?= h($label) ?></dt>
        <dd><?= isset($draft['files'][$field]) ? '新しいファイルを選択済み' : '変更なし' ?></dd>
    <?php endforeach; ?>
</dl>
<form method="post" action="" class="form-actions">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <button type="submit" name="action" value="back" class="button-secondary">修正する</button>
    <button type="submit" name="action" value="save" class="button-primary"><?= $is_new ? '登録する' : '更新する' ?></button>
</form>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
