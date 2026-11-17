<?php

declare(strict_types=1);

// 年齢区分（CERO）による購入制限（要確認 Q16：誕生日当日からその年齢）
require_once __DIR__ . '/validation.php';   // today_string（日本時間の今日）

const CERO_MIN_AGES = ['A' => 0, 'B' => 12, 'C' => 15, 'D' => 17, 'Z' => 18];
const CERO_UNKNOWN_MIN_AGE = 18;   // 未知の区分は安全側（購入させない側）に倒す

// 日本時間の今日での満年齢。2/29 生まれは平年の 3/1 に加算される（月日の文字列比較）
function age_on(string $birthDate, int $now): int
{
    $today = today_string($now);
    $age = (int) substr($today, 0, 4) - (int) substr($birthDate, 0, 4);
    $birthdayNotYet = substr($today, 5) < substr($birthDate, 5);
    return $birthdayNotYet ? $age - 1 : $age;
}

function cero_min_age(string $rating): int
{
    return CERO_MIN_AGES[$rating] ?? CERO_UNKNOWN_MIN_AGE;
}

function is_age_allowed(string $birthDate, string $rating, int $now): bool
{
    return age_on($birthDate, $now) >= cero_min_age($rating);
}
