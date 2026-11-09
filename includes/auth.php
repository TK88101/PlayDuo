<?php

declare(strict_types=1);

// 会員登録（U17）・ログイン（U06）・パスワード再設定（U14/U15）の業務ロジック
require_once __DIR__ . '/member_repo.php';
require_once __DIR__ . '/totp.php';
require_once __DIR__ . '/recovery.php';

const RESET_AUTH_SECONDS = 600;   // U14 の本人確認から U15 の確定までの有効時間
// 存在しないメールでも照合時間をそろえるためのダミー（中身に意味はない）
const DUMMY_PASSWORD_HASH = '$2y$10$XNwZ4WCgUkjWfKeFU/4vFOKEulUfTALIJPvseWvpMNnf65iOV1sF6';

/**
 * U17：6 桁が合えば会員と復旧コードを登録する。
 * @param array{input: array, password_hash: string, totp_secret: string} $register
 * @return array{status: 'ok'|'invalid_code'|'duplicate', member_id?: int, recovery_codes?: list<string>}
 */
function register_member(PDO $pdo, array $register, string $code, int $now): array
{
    $step = totp_match_step($register['totp_secret'], trim($code), $now);
    if ($step === null) {
        return ['status' => 'invalid_code'];
    }

    $codes = generate_recovery_codes();
    $hashes = array_map(
        static fn (string $c): string => password_hash(normalize_recovery_code($c), PASSWORD_DEFAULT),
        $codes,
    );
    $data = $register['input'] + [
        'password_hash'  => $register['password_hash'],
        'totp_secret'    => $register['totp_secret'],
        'totp_last_step' => $step,
    ];

    try {
        $memberId = create_member_with_codes($pdo, $data, $hashes, $now);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return ['status' => 'duplicate'];
        }
        throw $e;
    }
    return ['status' => 'ok', 'member_id' => $memberId, 'recovery_codes' => $codes];
}

/** @return array{status: 'ok'|'invalid'|'locked', member_id?: int} */
function attempt_login(PDO $pdo, string $email, string $password, int $now): array
{
    $result = run_auth_attempt(
        $pdo,
        $email,
        $now,
        static fn (array $m): bool => password_verify($password, $m['password']),
        true,
    );
    if ($result['status'] !== 'ok') {
        return ['status' => $result['status']];
    }

    $member = $result['member'];
    if (password_needs_rehash($member['password'], PASSWORD_DEFAULT)) {
        update_password_hash($pdo, (int) $member['member_id'], password_hash($password, PASSWORD_DEFAULT));
    }
    return ['status' => 'ok', 'member_id' => (int) $member['member_id']];
}

/**
 * U14：メール＋（認証アプリの 6 桁 または 復旧コード）。成功してもロックのカウンタは触らない（設計書 U15-8）。
 * @return array{status: 'ok'|'invalid'|'locked', member_id?: int, password_fp?: string}
 */
function verify_reset_identity(PDO $pdo, string $email, string $code, int $now): array
{
    $result = run_auth_attempt(
        $pdo,
        $email,
        $now,
        static fn (array $m): bool => verify_second_factor($pdo, $m, $code, $now),
        false,
    );
    if ($result['status'] !== 'ok') {
        return ['status' => $result['status']];
    }

    $member = $result['member'];
    return [
        'status'      => 'ok',
        'member_id'   => (int) $member['member_id'],
        'password_fp' => password_fingerprint($member['password']),
    ];
}

/**
 * ログインと本人確認の共通の流れ：行ロック → ロック判定 → 照合 → 失敗回数の更新。
 * @param callable(array): bool $verify
 * @return array{status: 'ok'|'invalid'|'locked', member?: array}
 */
function run_auth_attempt(PDO $pdo, string $email, int $now, callable $verify, bool $resetOnSuccess): array
{
    $pdo->beginTransaction();
    try {
        $member = find_active_member_by_email_for_update($pdo, $email);
        if ($member === null) {
            $pdo->commit();
            password_verify('dummy', DUMMY_PASSWORD_HASH);
            return ['status' => 'invalid'];
        }
        if (is_locked($member, $now)) {
            $pdo->commit();
            return ['status' => 'locked'];
        }
        if (!$verify($member)) {
            $nowLocked = record_failure($pdo, $member, $now);
            $pdo->commit();
            return ['status' => $nowLocked ? 'locked' : 'invalid'];
        }
        if ($resetOnSuccess) {
            reset_failures($pdo, (int) $member['member_id']);
        }
        $pdo->commit();
        return ['status' => 'ok', 'member' => $member];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// 数字 6 桁なら認証アプリ、それ以外は復旧コードとして照合し、通ったものを消費する
function verify_second_factor(PDO $pdo, array $member, string $code, int $now): bool
{
    $code = trim($code);
    $memberId = (int) $member['member_id'];

    if (preg_match('/\A\d{' . TOTP_DIGITS . '}\z/', $code) === 1) {
        $step = totp_match_step($member['totp_secret'], $code, $now);
        return $step !== null && consume_totp_step($pdo, $memberId, $step);
    }

    $normalized = normalize_recovery_code($code);
    if (!is_recovery_code_format($normalized)) {
        return false;
    }
    foreach (unused_recovery_codes($pdo, $memberId) as $row) {
        if (password_verify($normalized, $row['code_hash'])) {
            return consume_recovery_code($pdo, (int) $row['recovery_code_id'], $now);
        }
    }
    return false;
}

// U14 時点のパスワードの「世代」。U15 で一致を確かめ、間に変更・退会があれば更新しない
function password_fingerprint(string $passwordHash): string
{
    return hash('sha256', $passwordHash);
}

function reset_auth_is_valid(?array $auth, int $now): bool
{
    return is_array($auth)
        && is_int($auth['member_id'] ?? null)
        && is_int($auth['issued_at'] ?? null)
        && is_string($auth['password_fp'] ?? null)
        && $now >= $auth['issued_at']
        && $now < $auth['issued_at'] + RESET_AUTH_SECONDS;
}

/**
 * U15：新しいパスワードを保存する（入力チェックは呼び出し側で済ませておく）。
 * @return 'ok'|'expired'|'stale'
 */
function complete_password_reset(PDO $pdo, ?array $auth, string $newPassword, int $now): string
{
    if (!reset_auth_is_valid($auth, $now)) {
        return 'expired';
    }
    $pdo->beginTransaction();
    try {
        $member = find_active_member_by_id_for_update($pdo, $auth['member_id']);
        if ($member === null || !hash_equals($auth['password_fp'], password_fingerprint($member['password']))) {
            $pdo->commit();
            return 'stale';
        }
        update_password_hash($pdo, $auth['member_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $pdo->commit();
        return 'ok';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
