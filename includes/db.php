<?php

declare(strict_types=1);

// 環境変数 PLAYDUO_DB_* があれば config.php の値を上書きする（テスト時だけ設定。Apache では未設定）
const DB_ENV_OVERRIDES = [
    'PLAYDUO_DB_HOST'     => 'host',
    'PLAYDUO_DB_NAME'     => 'name',
    'PLAYDUO_DB_USER'     => 'user',
    'PLAYDUO_DB_PASSWORD' => 'password',
];

function db_settings(array $fileConfig, array $env): array
{
    $settings = $fileConfig;
    foreach (DB_ENV_OVERRIDES as $envKey => $configKey) {
        $value = $env[$envKey] ?? '';
        if (is_string($value) && $value !== '') {
            $settings[$configKey] = $value;
        }
    }
    return $settings;
}

/**
 * $work をトランザクションの中で実行する。例外が出たら取り消して投げ直す。
 * @template T
 * @param callable(): T $work
 * @return T
 */
function db_transaction(PDO $pdo, callable $work): mixed
{
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

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
    $db = db_settings((require $configPath)['db'], getenv());

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
