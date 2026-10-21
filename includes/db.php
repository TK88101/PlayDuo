<?php

declare(strict_types=1);

// DB 接続は全画面でこの関数を使う（SQL は必ずプリペアドステートメントで実行する）
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configPath = __DIR__ . '/../config/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('config/config.php がありません。config.sample.php をコピーして作成してください。');
    }
    $db = (require $configPath)['db'];

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
