<?php

declare(strict_types=1);

// 開発用のダミー配布ファイルを作る（本物のゲームは無いので、説明テキスト 1 枚だけの ZIP）。
// 使い方：php tools/make_dummy_downloads.php（sql/seed.sql の file_path をすべて作る。既にあれば上書き）
// Web からは .htaccess で downloads/ を 404 にしてあり、download.php だけが本人の購入を確かめて送る
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
preg_match_all("#'(downloads/([A-Za-z0-9_-]+)\\.zip)'#", (string) file_get_contents($root . '/sql/seed.sql'), $m, PREG_SET_ORDER);
if (!is_dir($root . '/downloads')) {
    mkdir($root . '/downloads', 0755);
}
foreach ($m as [, $path, $id]) {
    $zip = new ZipArchive();
    if ($zip->open($root . '/' . $path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "作れませんでした：{$path}\n");
        exit(1);
    }
    $zip->addFromString('README.txt', "Play Duo 開発用のダミー配布ファイルです（{$id}）。\r\n本物のゲームは入っていません。\r\n");
    $zip->close();
    echo "{$path}\n";
}
