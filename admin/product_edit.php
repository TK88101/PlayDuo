<?php
// A06 商品登録（新規）／A06-a 商品編集（?id=）。確認は A07（product_confirm.php）
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_product.php';
require_once __DIR__ . '/../includes/admin_product_repo.php';
require_once __DIR__ . '/../includes/admin_product_draft.php';
require_admin($admin_id);

$product_id = null;
$product = null;
if (isset($_GET['id'])) {
    $product_id = parse_positive_int($_GET['id']);
    $product = $product_id === null ? null : find_product_any(db(), $product_id);
    if ($product === null) {
        require __DIR__ . '/../includes/admin_not_found.php';   // A06a-014
    }
}
$is_new = $product === null;
$page_title = $is_new ? '商品登録' : '商品編集';
$tmp_dir = admin_upload_tmp_dir();
$draft = product_draft($_SESSION, $product_id);

if (post_too_large($_SERVER, $_POST, $_FILES, (string) ini_get('post_max_size'))) {
    // 送信が大きすぎて PHP が中身を捨てた（CSRF も読めないので何も変えずに画面だけ出す）
    $errors['files'] = 'ファイルが大きすぎます（画像は1枚3MB・配布ファイルは25MBまで）';
    $old = product_form_values($draft['input'] ?? $product ?? []);
} elseif (is_post()) {
    verify_csrf_or_fail();
    purge_stale_uploads($tmp_dir, time());
    if ($draft === null) {
        discard_product_draft($_SESSION, $tmp_dir);   // 別の商品の確認待ちは捨てる
    }
    $checked = validate_product_input($_POST);
    $collected = collect_product_files($_FILES, $draft['files'] ?? [], $is_new, $tmp_dir);
    $errors = $checked['errors'] + $collected['errors'];
    $draft = [
        'product_id' => $product_id, 'input' => $checked['input'], 'files' => $collected['files'], 'ready' => $errors === [],
    ];
    $_SESSION[PRODUCT_DRAFT_KEY] = $draft;
    if ($errors === []) {
        redirect('product_confirm.php');
    }
    $old = product_form_values($_POST);   // エラーのときは送られたとおりに戻す（A06-022）
} elseif (($_GET['resume'] ?? null) === '1' && $draft !== null) {
    $old = product_form_values($draft['input']);   // A07 の「修正する」から戻った
} else {
    discard_product_draft($_SESSION, $tmp_dir);   // A03 から開き直した：前の入力は捨てる（A06-025）
    $draft = null;
    $old = product_form_values($product ?? []);
}
$kept_files = $draft['files'] ?? [];
$status_label = $is_new ? '公開中（登録すると公開されます）' : public_status_label($product);
?>
<?php include __DIR__ . '/../includes/admin_header.php'; ?>
<h1><?= $is_new ? '商品登録' : '商品編集' ?></h1>
<form method="post" action="" enctype="multipart/form-data" class="admin-form admin-card">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
    <section class="form-basic">
        <h2>基本情報</h2>
        <label>タイトル <input type="text" name="name" value="<?= h($old['name']) ?>" maxlength="100"></label>
        <?= error_html($errors, 'name') ?>
        <label>英語名（任意） <input type="text" name="title_en" value="<?= h($old['title_en']) ?>" maxlength="100"></label>
        <?= error_html($errors, 'title_en') ?>
        <label>ジャンル
            <select name="genre">
                <option value="">選択してください</option>
                <?php foreach (PRODUCT_GENRES as $genre): ?>
                    <option value="<?= h($genre) ?>"<?= $old['genre'] === $genre ? ' selected' : '' ?>><?= h($genre) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?= error_html($errors, 'genre') ?>
        <label>価格（円） <input type="number" name="price" value="<?= h($old['price']) ?>" min="0" max="1000000" step="1"></label>
        <?= error_html($errors, 'price') ?>
        <label>容量（GB） <input type="number" name="capacity" value="<?= h($old['capacity']) ?>" min="0.1" max="999.9" step="0.1"></label>
        <?= error_html($errors, 'capacity') ?>
        <label>発売日 <input type="date" name="release_date" value="<?= h($old['release_date']) ?>"></label>
        <?= error_html($errors, 'release_date') ?>
        <label>開発元 <input type="text" name="developer" value="<?= h($old['developer']) ?>" maxlength="100"></label>
        <?= error_html($errors, 'developer') ?>
        <label>年齢区分（CERO）
            <select name="age_rating">
                <option value="">選択してください</option>
                <?php foreach (CERO_TARGET_LABELS as $rating => $target): ?>
                    <option value="<?= h($rating) ?>"<?= $old['age_rating'] === $rating ? ' selected' : '' ?>><?= h(cero_detail_label($rating)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?= error_html($errors, 'age_rating') ?>
        <label>説明文 <textarea name="description" maxlength="2000" rows="6"><?= h($old['description']) ?></textarea></label>
        <?= error_html($errors, 'description') ?>
        <?php include __DIR__ . '/../includes/admin_file_field.php'; /* game_file */ ?>
    </section>
    <section class="form-multi">
        <h2>マルチプレイ情報（検索・比較に使う項目）</h2>
        <label>プレイ人数（最小） <input type="number" name="min_players" value="<?= h($old['min_players']) ?>" min="1" max="99"></label>
        <label>プレイ人数（最大） <input type="number" name="max_players" value="<?= h($old['max_players']) ?>" min="1" max="99"></label>
        <?= error_html($errors, 'min_players') ?>
        <label>遊び方
            <select name="play_style">
                <option value="">選択してください</option>
                <?php foreach (PRODUCT_PLAY_STYLES as $style): ?>
                    <option value="<?= h($style) ?>"<?= $old['play_style'] === $style ? ' selected' : '' ?>><?= h($style) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?= error_html($errors, 'play_style') ?>
        <label>1回のプレイ時間 <input type="text" name="play_time" value="<?= h($old['play_time']) ?>" maxlength="20"></label>
        <?= error_html($errors, 'play_time') ?>
        <fieldset>
            <legend>対応OS</legend>
            <label><input type="checkbox" name="os_windows" value="1"<?= checked($old['os_windows'] === '1') ?>> Windows</label>
            <label><input type="checkbox" name="os_mac" value="1"<?= checked($old['os_mac'] === '1') ?>> Mac</label>
        </fieldset>
        <?= error_html($errors, 'os') ?>
        <label><input type="checkbox" name="crossplay_flag" value="1"<?= checked($old['crossplay_flag'] === '1') ?>> クロスプレイ（WindowsとMacの両方に対応する場合だけ）</label>
        <?= error_html($errors, 'crossplay_flag') ?>
    </section>
    <section class="form-images">
        <h2>画像（PNG・JPEG、1枚3MBまで）</h2>
        <?php foreach (['image', 'screenshot1', 'screenshot2', 'screenshot3'] as $file_field): ?>
            <?php include __DIR__ . '/../includes/admin_file_field.php'; ?>
        <?php endforeach; ?>
    </section>
    <section class="admin-status">
        <h2>状態</h2>
        <span class="status-badge<?= !$is_new && (int) $product['public_flag'] !== 1 ? ' status-badge-hidden' : '' ?>"><?= h($status_label) ?></span>
        <p class="note">※商品は削除せず、「非公開」に切り替えて販売を止めます（切り替えは商品管理一覧から）</p>
    </section>
    <div class="form-footer">
        <?php if ($errors !== []): ?>
            <div class="form-alert">
                <p class="form-alert-title">入力内容を確認してください</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="form-actions">
            <a class="button-secondary" href="products.php">キャンセル</a>
            <button type="submit" class="button-primary"><?= $is_new ? '確認画面へ' : '更新する' ?></button>
        </div>
    </div>
</form>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
