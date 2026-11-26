<?php
// A06 のファイル 1 項目（product_edit.php から include）
// 使う変数：$file_field（無ければ game_file）、$kept_files、$is_new、$errors、$product
$file_field ??= PRODUCT_ZIP_FIELD;
$is_zip = $file_field === PRODUCT_ZIP_FIELD;
?>
<div class="file-field">
    <label><?= h(PRODUCT_FILE_LABELS[$file_field]) ?><?= $is_zip ? '（ZIP、25MBまで）' : '' ?>
        <input type="file" name="<?= h($file_field) ?>" accept="<?= $is_zip ? '.zip,application/zip' : 'image/png,image/jpeg' ?>">
    </label>
    <?php if (isset($kept_files[$file_field])): ?>
        <p class="file-kept">選択済み（選び直さなければこのファイルを使います）</p>
    <?php elseif (!$is_new): ?>
        <p class="file-kept">登録済みのファイルを使います（変える場合だけ選択）</p>
    <?php endif; ?>
    <?= error_html($errors, $file_field) ?>
</div>
<?php
unset($file_field);
