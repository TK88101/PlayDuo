<?php

declare(strict_types=1);

// A06 のファイル受け取り・中身の判定・一時保存（Plan 2026-11-26-php-admin §2.0・付録 A）
// 元のファイル名は使わない。保存名はいつもサーバ側の乱数。ZIP はサーバで展開しない

const UPLOAD_IMAGE_MAX_BYTES = 3 * 1024 * 1024;
const UPLOAD_ZIP_MAX_BYTES = 25 * 1024 * 1024;   // 画像 4 枚＋ZIP で 37MB（XAMPP の post_max_size 40M 未満）
const UPLOAD_IMAGE_MAX_SIDE = 4096;               // 縦横の上限（巨大画像で処理が重くなるのを防ぐ）
const UPLOAD_STASH_TTL = 3600;                    // 確認待ちのまま放置された一時ファイルを消すまでの秒数
const UPLOAD_STASH_NAME = '/\A[0-9a-f]{32}\z/';
const UPLOAD_IMAGE_TYPES = [IMAGETYPE_PNG => ['image/png', 'png'], IMAGETYPE_JPEG => ['image/jpeg', 'jpg']];

/**
 * $_FILES の 1 項目。決まった name の単一ファイルだけ受ける（配列・アップロード以外のパスは error）
 * @param callable(string): bool $isUploaded 本番は is_uploaded_file
 * @return array{status: 'none'|'ok'|'error'|'too_large', tmp?: string}
 */
function uploaded_file(array $files, string $field, ?callable $isUploaded = null): array
{
    $isUploaded ??= is_uploaded_file(...);
    $file = $files[$field] ?? null;
    if ($file === null) {
        return ['status' => 'none'];
    }
    if (!is_array($file) || !is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null)) {
        return ['status' => 'error'];
    }
    return match ($file['error']) {
        UPLOAD_ERR_NO_FILE => ['status' => 'none'],
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => ['status' => 'too_large'],
        UPLOAD_ERR_OK => $isUploaded($file['tmp_name']) ? ['status' => 'ok', 'tmp' => $file['tmp_name']] : ['status' => 'error'],
        default => ['status' => 'error'],
    };
}

/** PNG/JPEG だけ。MIME（finfo）と画像の種類（getimagesize）が一致したものを通す（A06-016） */
function check_image(string $path): array
{
    if (filesize($path) > UPLOAD_IMAGE_MAX_BYTES) {
        return ['error' => '画像は1枚3MBまでです'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $info = @getimagesize($path);
    $type = UPLOAD_IMAGE_TYPES[$info[2] ?? 0] ?? null;
    if ($info === false || $type === null || $type[0] !== $mime) {
        return ['error' => '画像はPNGまたはJPEGのファイルを選択してください'];
    }
    if ($info[0] < 1 || $info[1] < 1 || $info[0] > UPLOAD_IMAGE_MAX_SIDE || $info[1] > UPLOAD_IMAGE_MAX_SIDE) {
        return ['error' => '画像の縦横は4096px以内にしてください'];
    }
    return ['ext' => $type[1]];
}

/** ZIP として開けるものだけ（A06-019）。中身は展開しない */
function check_zip(string $path): ?string
{
    if (filesize($path) > UPLOAD_ZIP_MAX_BYTES) {
        return '配布ファイルは25MBまでです';
    }
    $zip = new ZipArchive();
    $opened = $zip->open($path, ZipArchive::RDONLY);
    if ($opened !== true) {
        return '配布ファイルはZIP形式のファイルを選択してください';
    }
    $zip->close();
    return null;
}

/**
 * 一時フォルダへ乱数名で移す（拡張子なし）。返すのはファイル名だけ
 * @param callable(string, string): bool $move 本番は move_uploaded_file
 */
function stash_upload(string $tmpPath, string $dir, ?callable $move = null): string
{
    $move ??= move_uploaded_file(...);
    $name = bin2hex(random_bytes(16));
    if (!$move($tmpPath, "{$dir}/{$name}")) {
        throw new RuntimeException('アップロードしたファイルを一時フォルダへ移せませんでした');
    }
    return $name;
}

/** @param list<string> $names 自分の形式の名前だけ消す（パスは受けない） */
function discard_stashed(string $dir, array $names): void
{
    foreach ($names as $name) {
        if (is_string($name) && preg_match(UPLOAD_STASH_NAME, $name) === 1 && is_file("{$dir}/{$name}")) {
            unlink("{$dir}/{$name}");
        }
    }
}

function purge_stale_uploads(string $dir, int $now): void
{
    foreach (scandir($dir) ?: [] as $name) {
        $path = "{$dir}/{$name}";
        if (preg_match(UPLOAD_STASH_NAME, $name) === 1 && is_file($path) && filemtime($path) < $now - UPLOAD_STASH_TTL) {
            unlink($path);
        }
    }
}

// 一時フォルダ（Web 公開フォルダの外）。無ければ作る
function admin_upload_tmp_dir(): string
{
    $dir = sys_get_temp_dir() . '/playduo_admin_uploads';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('一時フォルダを作れませんでした');
    }
    return $dir;
}

// POST が post_max_size を超えると PHP は $_POST と $_FILES を空にする。その状態かどうか
function post_too_large(array $server, array $post, array $files, string $postMaxSize): bool
{
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST' || $post !== [] || $files !== []) {
        return false;
    }
    return (int) ($server['CONTENT_LENGTH'] ?? 0) > ini_bytes($postMaxSize);
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    $unit = strtoupper(substr($value, -1));
    $number = (int) $value;
    return match ($unit) {
        'G' => $number * 1024 ** 3,
        'M' => $number * 1024 ** 2,
        'K' => $number * 1024,
        default => $number,
    };
}
