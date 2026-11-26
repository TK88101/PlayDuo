<?php
// フォームのメッセージ表示欄（Figma U12・U13・U16：ボタンの下。エラーのときだけ出す）。使う変数：$errors
?>
<?php if (!empty($errors)): ?>
    <div class="form-alert form-errors" role="alert">
        <?php foreach ($errors as $field => $error): ?>
            <p class="error" data-field="<?= h($field) ?>"><?= h($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
