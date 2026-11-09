<?php

declare(strict_types=1);

const APP_TIMEZONE = 'Asia/Tokyo';

// Unix 秒 ⇔ DB の DATETIME 文字列（日本時間）
function db_datetime(int $timestamp): string
{
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone(APP_TIMEZONE))->format('Y-m-d H:i:s');
}

function db_timestamp(string $datetime): int
{
    return (new DateTimeImmutable($datetime, new DateTimeZone(APP_TIMEZONE)))->getTimestamp();
}

// HTML に値を出すときは必ずこれを通す（XSS 対策）
function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 同じサイト内の画面へ移動して処理を終える（外部 URL は渡さないこと）
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

// 次の画面で 1 回だけ出すメッセージ（$message になる）
function flash_set(array &$session, string $message): void
{
    $session['flash'] = $message;
}

function flash_take(array &$session): ?string
{
    $message = $session['flash'] ?? null;
    unset($session['flash']);
    return is_string($message) ? $message : null;
}
