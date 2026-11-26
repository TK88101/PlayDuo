<?php

declare(strict_types=1);

// 配布ファイル（products.file_path）の解決。Plan 2026-11-26 §2.3（Codex P1-3）：
// 形は downloads/<英数字・_・->.zip だけ。実体が downloads/ の中の読める普通のファイルのときだけ絶対パスを返す
const DOWNLOAD_DIR = 'downloads';
const DOWNLOAD_PATH_PATTERN = '#\Adownloads/[A-Za-z0-9_-]+\.zip\z#';

function download_file_path(string $filePath, string $baseDir = __DIR__ . '/..'): ?string
{
    if (preg_match(DOWNLOAD_PATH_PATTERN, $filePath) !== 1) {
        return null;
    }
    $root = realpath($baseDir . '/' . DOWNLOAD_DIR);
    $file = realpath($baseDir . '/' . $filePath);
    if ($root === false || $file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return is_file($file) && is_readable($file) ? $file : null;
}
