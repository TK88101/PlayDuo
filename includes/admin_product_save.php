<?php

declare(strict_types=1);

// A07 の保存（新規・編集）。Plan 2026-11-26-php-admin §2.0「A07 保存の失敗」・付録 A（P0）
// ファイルと DB は同時には確定できないので：新しいファイルは毎回別名で置く → DB を更新 → commit 後に古いファイルを消す。
// 途中で失敗したら rollback し、置いた新しいファイルを消す。プロセスごと落ちたときの孤児ファイルは許容する
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin_upload.php';

// 画像の name → DB の列・保存名に使う種類
const PRODUCT_IMAGE_FIELDS = ['image' => 'cover', 'screenshot1' => 'ss1', 'screenshot2' => 'ss2', 'screenshot3' => 'ss3'];
const PRODUCT_ZIP_FIELD = 'game_file';
const PRODUCT_UPLOAD_IMAGE_DIR = 'uploads';   // assets/games/ の下（DB の image 列は uploads/…）

/**
 * @param array<string, mixed> $input validate_product_input の input
 * @param array<string, array{name: string, ext: string}> $files 一時フォルダに置いた新しいファイル（name → 一時名・拡張子）
 * @return int 保存した product_id
 */
function save_product(PDO $pdo, ?int $productId, array $input, array $files, string $tmpDir, string $rootDir, int $now): int
{
    $placed = [];
    $pdo->beginTransaction();
    try {
        if ($productId === null) {
            $productId = insert_product_row($pdo, $input, $now);
        } else {
            $old = lock_product_row($pdo, $productId);
        }
        $paths = [];
        foreach ($files as $field => $file) {
            $relative = product_file_destination($productId, $field, $file['ext'], isset($old));
            place_stashed_file("{$tmpDir}/{$file['name']}", product_file_absolute($rootDir, $relative));
            $placed[] = product_file_absolute($rootDir, $relative);
            $paths[product_file_column($field)] = $relative;
        }
        update_product_row($pdo, $productId, $input, $paths);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        array_map('unlink', array_filter($placed, 'is_file'));
        throw $e;
    }
    if (isset($old)) {
        remove_replaced_files($rootDir, $productId, $old, $paths);
    }
    return $productId;
}

function insert_product_row(PDO $pdo, array $input, int $now): int
{
    // 画像・ファイルの列は直後の UPDATE で埋める（同じトランザクションの中なので外からは見えない）
    $pdo->prepare(
        'INSERT INTO products (name, title_en, price, description, genre, capacity, age_rating, developer, release_date,
            image, screenshot1, screenshot2, screenshot3, min_players, max_players, play_style, crossplay_flag,
            os_windows, os_mac, play_time, recommend_order, file_path, public_flag, created_at)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, \'\', \'\', \'\', \'\', ?, ?, ?, ?, ?, ?, ?, COALESCE(MAX(recommend_order), 0) + 1, \'\', 1, ?
         FROM products',
    )->execute([
        $input['name'], $input['title_en'], $input['price'], $input['description'], $input['genre'], $input['capacity'],
        $input['age_rating'], $input['developer'], $input['release_date'], $input['min_players'], $input['max_players'],
        $input['play_style'], $input['crossplay_flag'], $input['os_windows'], $input['os_mac'], $input['play_time'],
        db_datetime($now),
    ]);
    return (int) $pdo->lastInsertId();
}

function lock_product_row(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ? FOR UPDATE');
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new RuntimeException('編集する商品がありません');
    }
    return $row;
}

/** @param array<string, string> $paths 列 → 新しいパス（指定の無い列は今のまま） */
function update_product_row(PDO $pdo, int $productId, array $input, array $paths): void
{
    $columns = ['name', 'title_en', 'price', 'description', 'genre', 'capacity', 'age_rating', 'developer', 'release_date',
        'min_players', 'max_players', 'play_style', 'crossplay_flag', 'os_windows', 'os_mac', 'play_time'];
    $values = array_map(static fn (string $c): mixed => $input[$c], $columns);
    foreach ($paths as $column => $path) {
        $columns[] = $column;   // 列名は PRODUCT_IMAGE_FIELDS・file_path の固定値だけ
        $values[] = $path;
    }
    $set = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", $columns));
    $pdo->prepare("UPDATE products SET {$set} WHERE product_id = ?")->execute([...$values, $productId]);
}

function product_file_column(string $field): string
{
    return $field === PRODUCT_ZIP_FIELD ? 'file_path' : $field;
}

// DB に入れる相対パス。新規の ZIP は downloads/p{id}.zip、差し替えと画像は毎回乱数付きの別名（上書きしない）
function product_file_destination(int $productId, string $field, string $ext, bool $isReplace): string
{
    $suffix = bin2hex(random_bytes(8));
    if ($field === PRODUCT_ZIP_FIELD) {
        return $isReplace ? "downloads/p{$productId}_{$suffix}.zip" : "downloads/p{$productId}.zip";
    }
    $kind = PRODUCT_IMAGE_FIELDS[$field] ?? throw new InvalidArgumentException('知らないファイル項目です');
    return PRODUCT_UPLOAD_IMAGE_DIR . "/p{$productId}_{$kind}_{$suffix}.{$ext}";
}

function product_file_absolute(string $rootDir, string $relative): string
{
    return str_starts_with($relative, 'downloads/') ? "{$rootDir}/{$relative}" : "{$rootDir}/assets/games/{$relative}";
}

function place_stashed_file(string $from, string $to): void
{
    if (!is_file($from) || file_exists($to) || !rename($from, $to)) {
        throw new RuntimeException('ファイルを保存できませんでした');
    }
    chmod($to, 0644);
}

// 差し替えた古いファイルを消す。消すのはアップロードで作った名前（p{id}_…）だけ：seed の画像・ZIP は残す
function remove_replaced_files(string $rootDir, int $productId, array $old, array $paths): void
{
    $own = '#\A(?:' . PRODUCT_UPLOAD_IMAGE_DIR . "/p{$productId}_[a-z0-9]+_[0-9a-f]{16}\\.(?:png|jpg)|downloads/p{$productId}(?:_[0-9a-f]{16})?\\.zip)\\z#";
    foreach (array_keys($paths) as $column) {
        $previous = (string) $old[$column];
        $file = product_file_absolute($rootDir, $previous);
        if (preg_match($own, $previous) === 1 && is_file($file) && !unlink($file)) {
            error_log(sprintf('[playduo] could not remove replaced file: product_id=%d column=%s', $productId, $column));
        }
    }
}
