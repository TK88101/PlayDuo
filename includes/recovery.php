<?php

declare(strict_types=1);

// 復旧コード（会員 1 人につき 10 個・各 1 回限り。DB には password_hash で保存）
const RECOVERY_CODE_COUNT = 10;
const RECOVERY_CODE_LENGTH = 10;
// 0/O・1/I は読み間違えやすいので使わない（32 文字 × 10 桁 = 50 bit）
const RECOVERY_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/** @return list<string> 表示用（XXXXX-XXXXX） */
function generate_recovery_codes(): array
{
    $codes = [];
    while (count($codes) < RECOVERY_CODE_COUNT) {
        $raw = '';
        for ($i = 0; $i < RECOVERY_CODE_LENGTH; $i++) {
            $raw .= RECOVERY_CODE_ALPHABET[random_int(0, strlen(RECOVERY_CODE_ALPHABET) - 1)];
        }
        $codes[$raw] = substr($raw, 0, 5) . '-' . substr($raw, 5);
    }
    return array_values($codes);
}

// 入力のゆれ（小文字・ハイフン・空白）をそろえる。ハッシュ化もこの形で行う
function normalize_recovery_code(string $input): string
{
    return strtoupper((string) preg_replace('/[\s\-]+/', '', $input));
}

function is_recovery_code_format(string $normalized): bool
{
    return preg_match('/\A[' . RECOVERY_CODE_ALPHABET . ']{' . RECOVERY_CODE_LENGTH . '}\z/', $normalized) === 1;
}
