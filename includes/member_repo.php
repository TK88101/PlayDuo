<?php

declare(strict_types=1);

// members / recovery_codes の DB 操作。SQL はここに集める（山﨑版 DB と差があっても直す場所を限定するため）
require_once __DIR__ . '/functions.php';

// ---- ロックの決まり（ログインとパスワード再設定で共通のカウンタ） ----
const MAX_LOGIN_FAILURES = 5;
const LOCK_SECONDS = 600;

function is_locked(array $member, int $now): bool
{
    $lockedAt = $member['locked_at'] ?? null;
    return is_string($lockedAt) && $now < db_timestamp($lockedAt) + LOCK_SECONDS;
}

// ロックが切れた行は失敗回数 0 から数え直す
function effective_failure_count(array $member, int $now): int
{
    $lockedAt = $member['locked_at'] ?? null;
    if (is_string($lockedAt) && !is_locked($member, $now)) {
        return 0;
    }
    return (int) $member['login_failure_count'];
}

// ---- 参照 ----

function find_member_by_id(PDO $pdo, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM members WHERE member_id = ? AND withdrawal_flag = 0');
    $stmt->execute([$memberId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function email_exists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM members WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetchColumn() !== false;
}

// 呼び出し側でトランザクションを開始しておくこと（行ロックで同時の試行を順番にする）
function find_active_member_by_email_for_update(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM members WHERE email = ? AND withdrawal_flag = 0 FOR UPDATE');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function find_active_member_by_id_for_update(PDO $pdo, int $memberId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM members WHERE member_id = ? AND withdrawal_flag = 0 FOR UPDATE');
    $stmt->execute([$memberId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** @return list<array{recovery_code_id: int|string, code_hash: string}> */
function unused_recovery_codes(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare(
        'SELECT recovery_code_id, code_hash FROM recovery_codes WHERE member_id = ? AND used_at IS NULL ORDER BY recovery_code_id'
    );
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

// ---- 登録 ----

/**
 * 会員と復旧コード（ハッシュ済み）を 1 トランザクションで登録する。メール重複は PDOException(23000)。
 * @param list<string> $codeHashes
 */
function create_member_with_codes(PDO $pdo, array $data, array $codeHashes, int $now): int
{
    $createdAt = db_datetime($now);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO members (name, email, password, birth_date, nickname, phone, totp_secret, totp_last_step, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'], $data['email'], $data['password_hash'], $data['birth_date'],
            $data['nickname'], $data['phone'], $data['totp_secret'], $data['totp_last_step'], $createdAt,
        ]);
        $memberId = (int) $pdo->lastInsertId();

        $codeStmt = $pdo->prepare('INSERT INTO recovery_codes (member_id, code_hash, created_at) VALUES (?, ?, ?)');
        foreach ($codeHashes as $hash) {
            $codeStmt->execute([$memberId, $hash, $createdAt]);
        }
        $pdo->commit();
        return $memberId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---- 1 回限りの消費（条件付き UPDATE。影響行数 1 のときだけ成功＝同じコードは二度通らない） ----

function consume_totp_step(PDO $pdo, int $memberId, int $step): bool
{
    $stmt = $pdo->prepare(
        'UPDATE members SET totp_last_step = ? WHERE member_id = ? AND (totp_last_step IS NULL OR totp_last_step < ?)'
    );
    $stmt->execute([$step, $memberId, $step]);
    return $stmt->rowCount() === 1;
}

function consume_recovery_code(PDO $pdo, int $recoveryCodeId, int $now): bool
{
    $stmt = $pdo->prepare('UPDATE recovery_codes SET used_at = ? WHERE recovery_code_id = ? AND used_at IS NULL');
    $stmt->execute([db_datetime($now), $recoveryCodeId]);
    return $stmt->rowCount() === 1;
}

// ---- 失敗回数・パスワード ----

// $member は同じトランザクションで FOR UPDATE 済みの行。ロックが始まったら true
function record_failure(PDO $pdo, array $member, int $now): bool
{
    $count = effective_failure_count($member, $now) + 1;
    $lockedAt = $count >= MAX_LOGIN_FAILURES ? db_datetime($now) : null;
    $stmt = $pdo->prepare('UPDATE members SET login_failure_count = ?, locked_at = ? WHERE member_id = ?');
    $stmt->execute([$count, $lockedAt, $member['member_id']]);
    return $lockedAt !== null;
}

function reset_failures(PDO $pdo, int $memberId): void
{
    $stmt = $pdo->prepare('UPDATE members SET login_failure_count = 0, locked_at = NULL WHERE member_id = ?');
    $stmt->execute([$memberId]);
}

function update_password_hash(PDO $pdo, int $memberId, string $passwordHash): void
{
    $stmt = $pdo->prepare('UPDATE members SET password = ? WHERE member_id = ?');
    $stmt->execute([$passwordHash, $memberId]);
}
