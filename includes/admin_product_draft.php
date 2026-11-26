<?php

declare(strict_types=1);

// A06 → A07 の「確認待ち」（session に 1 組だけ）。Plan 2026-11-26-php-admin §2.0・付録 A
// 中身：product_id（新規は null）・input（検証済みの値）・files（name → 一時名・拡張子）・ready（A07 に進めるか）
require_once __DIR__ . '/admin_upload.php';
require_once __DIR__ . '/admin_product_save.php';

const PRODUCT_DRAFT_KEY = 'admin_product_draft';
const PRODUCT_FILE_LABELS = [
    'image' => 'カバー画像', 'screenshot1' => 'スクリーンショット1', 'screenshot2' => 'スクリーンショット2',
    'screenshot3' => 'スクリーンショット3', 'game_file' => '配布ファイル',
];

/** その商品（新規は null）の確認待ちだけ返す。別の商品のものは null */
function product_draft(array $session, ?int $productId): ?array
{
    $draft = $session[PRODUCT_DRAFT_KEY] ?? null;
    if (!is_array($draft) || !array_key_exists('product_id', $draft) || $draft['product_id'] !== $productId) {
        return null;
    }
    return $draft;
}

// 確認待ちと一時ファイルを捨てる（A06 を開き直したとき・ログアウト・保存の失敗）
function discard_product_draft(array &$session, string $tmpDir): void
{
    $draft = $session[PRODUCT_DRAFT_KEY] ?? null;
    unset($session[PRODUCT_DRAFT_KEY]);
    if (is_array($draft) && is_array($draft['files'] ?? null)) {
        discard_stashed($tmpDir, array_column($draft['files'], 'name'));
    }
}

// 身分が変わるとき（管理者・会員のログイン、管理者のログアウト）：確認待ちがあれば一時ファイルごと捨てる（セキュリティ評審 MEDIUM-2）
function forget_product_draft(array &$session): void
{
    if (isset($session[PRODUCT_DRAFT_KEY])) {
        discard_product_draft($session, admin_upload_tmp_dir());
    }
}

// A07 の保存：取り出したらすぐ消す（二重送信は 2 回目が「確認待ちなし」になる：A07-007〜009）
function take_product_draft(array &$session): ?array
{
    $draft = $session[PRODUCT_DRAFT_KEY] ?? null;
    unset($session[PRODUCT_DRAFT_KEY]);
    return is_array($draft) && ($draft['ready'] ?? false) === true ? $draft : null;
}

/**
 * A06 で送られたファイルを判定して一時フォルダへ。選ばれなかった項目は前回の確認待ちのファイルを使う。
 * 新規はすべて必須、編集は未選択なら今のファイルのまま（A06-017・A06-020・A06a-005）
 * @param array<string, array{name: string, ext: string}> $kept
 * @return array{files: array<string, array{name: string, ext: string}>, errors: array<string, string>}
 */
function collect_product_files(array $files, array $kept, bool $isNew, string $tmpDir, ?callable $isUploaded = null, ?callable $move = null): array
{
    $result = ['files' => [], 'errors' => []];
    foreach (PRODUCT_FILE_LABELS as $field => $label) {
        $upload = uploaded_file($files, $field, $isUploaded);
        if ($upload['status'] === 'none') {
            if (isset($kept[$field])) {
                $result['files'][$field] = $kept[$field];
            } elseif ($isNew) {
                $result['errors'][$field] = "{$label}を選択してください";
            }
            continue;
        }
        $checked = $upload['status'] === 'ok' ? check_product_file($field, $upload['tmp']) : null;
        if ($checked === null || isset($checked['error'])) {
            $result['errors'][$field] = $checked['error'] ?? ($upload['status'] === 'too_large'
                ? "{$label}が大きすぎます" : "{$label}をアップロードできませんでした");
            if (isset($kept[$field])) {
                $result['files'][$field] = $kept[$field];   // 失敗した選び直しでは前のファイルを捨てない
            }
            continue;
        }
        if (isset($kept[$field])) {
            discard_stashed($tmpDir, [$kept[$field]['name']]);
        }
        $result['files'][$field] = ['name' => stash_upload($upload['tmp'], $tmpDir, $move), 'ext' => $checked['ext']];
    }
    return $result;
}

/** @return array{ext?: string, error?: string} */
function check_product_file(string $field, string $path): array
{
    if ($field !== PRODUCT_ZIP_FIELD) {
        return check_image($path);
    }
    $error = check_zip($path);
    return $error === null ? ['ext' => 'zip'] : ['error' => $error];
}

// 画面に出す値（DB の行・検証済みの input・送られた POST のどれからでも文字列にそろえる）
function product_form_values(array $source): array
{
    $values = [];
    foreach (PRODUCT_FORM_ORDER as $field) {
        $value = $source[$field] ?? '';
        $values[$field] = is_scalar($value) ? (string) $value : '';
    }
    return $values;
}

/**
 * A07 の保存。値はもう一度検証し（A07-011）、一時ファイルの中身も確かめ直す。結果は A03 に出すメッセージ
 */
function save_product_draft(PDO $pdo, ?array $draft, string $tmpDir, int $now, ?string $rootDir = null): string
{
    if ($draft === null) {
        return '';
    }
    $files = is_array($draft['files'] ?? null) ? $draft['files'] : [];
    $checked = validate_product_input(product_form_values($draft['input'] ?? []));
    $filesOk = array_reduce(
        array_keys($files),
        static fn (bool $ok, string $field): bool => $ok && stashed_file_is_valid($tmpDir, $field, $files[$field]),
        true,
    );
    $productId = $draft['product_id'] ?? null;
    if ($checked['errors'] !== [] || !$filesOk || ($productId === null && count($files) !== count(PRODUCT_FILE_LABELS))) {
        discard_stashed($tmpDir, array_column($files, 'name'));
        return '入力内容を確認できなかったため保存していません。もう一度入力してください';
    }
    try {
        save_product($pdo, $productId, $checked['input'], $files, $tmpDir, $rootDir ?? dirname(__DIR__), $now);
    } catch (Throwable $e) {
        error_log(sprintf('[playduo] product save failed: %s at %s:%d', $e::class, $e->getFile(), $e->getLine()));
        discard_stashed($tmpDir, array_column($files, 'name'));
        return '保存できませんでした。もう一度お試しください';
    }
    $name = $checked['input']['name'];
    return $productId === null ? "「{$name}」を登録しました" : "「{$name}」を更新しました";
}

function stashed_file_is_valid(string $tmpDir, string $field, mixed $file): bool
{
    if (!is_array($file) || !is_string($file['name'] ?? null) || preg_match(UPLOAD_STASH_NAME, $file['name']) !== 1
        || !isset(PRODUCT_FILE_LABELS[$field])) {
        return false;
    }
    $path = "{$tmpDir}/{$file['name']}";
    $checked = is_file($path) ? check_product_file($field, $path) : ['error' => 'missing'];
    return ($checked['ext'] ?? null) === ($file['ext'] ?? null);
}
