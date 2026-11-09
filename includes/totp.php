<?php

declare(strict_types=1);

// 認証アプリ（TOTP, RFC 6238 / RFC 4226）。hash_hmac で自作、外部ライブラリなし
const TOTP_PERIOD_SECONDS = 30;
const TOTP_DIGITS = 6;
const TOTP_WINDOW = 1;            // 前後 1 窓（±30 秒）まで許す
const TOTP_SECRET_BYTES = 20;     // 160 bit
const TOTP_ISSUER = 'PlayDuo';
const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32_encode(string $binary): string
{
    $bits = '';
    foreach (str_split($binary) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $encoded .= BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $encoded;
}

function base32_decode(string $encoded): string
{
    $encoded = strtoupper($encoded);
    if ($encoded === '' || preg_match('/\A[A-Z2-7]+\z/', $encoded) !== 1) {
        throw new InvalidArgumentException('Base32 の形式が正しくありません');
    }
    $bits = '';
    foreach (str_split($encoded) as $char) {
        $bits .= str_pad(decbin(strpos(BASE32_ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binary .= chr(bindec($byte));
        }
    }
    return $binary;
}

function totp_generate_secret(): string
{
    return base32_encode(random_bytes(TOTP_SECRET_BYTES));
}

function totp_step(int $now): int
{
    return intdiv($now, TOTP_PERIOD_SECONDS);
}

function totp_code(string $secret, int $step): string
{
    // pack('J') は 64bit big-endian（64bit PHP 前提）
    $hmac = hash_hmac('sha1', pack('J', $step), base32_decode($secret), true);
    $offset = ord($hmac[19]) & 0x0F;
    $value = ((ord($hmac[$offset]) & 0x7F) << 24)
        | (ord($hmac[$offset + 1]) << 16)
        | (ord($hmac[$offset + 2]) << 8)
        | ord($hmac[$offset + 3]);
    return str_pad((string) ($value % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

// 一致した時間ステップを返す（無ければ null）。同じコードの再利用拒否は DB 側（totp_last_step）で行う
function totp_match_step(string $secret, string $code, int $now): ?int
{
    if (preg_match('/\A\d{' . TOTP_DIGITS . '}\z/', $code) !== 1) {
        return null;
    }
    $current = totp_step($now);
    for ($offset = -TOTP_WINDOW; $offset <= TOTP_WINDOW; $offset++) {
        if (hash_equals(totp_code($secret, $current + $offset), $code)) {
            return $current + $offset;
        }
    }
    return null;
}

// QR コードにする文字列（認証アプリが読む形式）
function totp_uri(string $secret, string $email): string
{
    return sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
        rawurlencode(TOTP_ISSUER),
        rawurlencode($email),
        $secret,
        rawurlencode(TOTP_ISSUER),
        TOTP_DIGITS,
        TOTP_PERIOD_SECONDS,
    );
}
