<?php

declare(strict_types=1);

// マイページ配下の会員操作：U12 会員情報の変更・U13 パスワードの変更・U16 退会（Plan 2026-11-26 §2.4〜§2.6）
// どれも「会員行を FOR UPDATE で取る → 退会済みでないか → 現在のパスワードを照合 → 更新」を 1 トランザクションで行う
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/member_repo.php';

const PASSWORD_CHECK_MAX_FAILURES = 5;
const PASSWORD_CHECK_BLOCK_SECONDS = 600;
const MYSQL_DUPLICATE_ENTRY = 1062;
const CURRENT_PASSWORD_REQUIRED = '現在のパスワードを入力してください';
const CURRENT_PASSWORD_WRONG = '現在のパスワードが正しくありません';
const CURRENT_PASSWORD_THROTTLED = '現在のパスワードの入力に続けて失敗したため、しばらくしてからもう一度お試しください';

/**
 * U12 の入力。氏名・生年月日は受け取らない（送られても無視：U12-014）。
 * @return array{errors: array<string, string>, input: array{email: string, nickname: ?string, phone: ?string}}
 */
function validate_account_input(array $post): array
{
    $email = trim_all(post_string($post, 'email'));
    $nickname = trim_all(post_string($post, 'nickname'));
    $phone = trim_all(post_string($post, 'phone'));

    $errors = array_filter([
        'email'    => email_error($email),
        'nickname' => nickname_error($nickname),
        'phone'    => phone_error($phone),
    ]);
    return [
        'errors' => $errors,
        'input'  => [
            'email'    => $email,
            'nickname' => $nickname === '' ? null : $nickname,
            'phone'    => $phone === '' ? null : $phone,
        ],
    ];
}

/**
 * 会員行をロックして現在のパスワードを照合し、合っていれば $update を実行する（トランザクションはここで閉じる）。
 * @param callable(array): string $update 照合済みの会員行を受け、結果の文字列を返す
 * @return string 'inactive'（退会済み・存在しない）｜'wrong_password'｜$update の戻り値
 */
function with_verified_member(PDO $pdo, int $memberId, #[\SensitiveParameter] string $currentPassword, callable $update): string
{
    $pdo->beginTransaction();
    try {
        $member = find_active_member_by_id_for_update($pdo, $memberId);
        if ($member === null) {
            $pdo->rollBack();
            return 'inactive';
        }
        if (!password_verify($currentPassword, $member['password'])) {
            $pdo->rollBack();
            return 'wrong_password';
        }
        $result = $update($member);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * U12。メールは自分以外の会員（退会済みを含む＝UNIQUE）と重複できない。
 * @param array{email: string, nickname: ?string, phone: ?string} $input
 */
function update_account(PDO $pdo, int $memberId, array $input, #[\SensitiveParameter] string $currentPassword): string
{
    try {
        return with_verified_member($pdo, $memberId, $currentPassword, static function () use ($pdo, $memberId, $input): string {
            $stmt = $pdo->prepare('SELECT 1 FROM members WHERE email = ? AND member_id <> ?');
            $stmt->execute([$input['email'], $memberId]);
            if ($stmt->fetchColumn() !== false) {
                return 'email_taken';
            }
            $pdo->prepare('UPDATE members SET email = ?, nickname = ?, phone = ? WHERE member_id = ?')
                ->execute([$input['email'], $input['nickname'], $input['phone'], $memberId]);
            return 'ok';
        });
    } catch (PDOException $e) {
        // 確認の後で別の会員が同じメールにした場合（UNIQUE 違反）
        if (($e->errorInfo[1] ?? null) === MYSQL_DUPLICATE_ENTRY) {
            return 'email_taken';
        }
        throw $e;
    }
}

// U13。同じパスワードでも許可し、新しいハッシュで保存する（要確認 Q19：Plan §2.0）
function change_password(PDO $pdo, int $memberId, #[\SensitiveParameter] string $currentPassword, #[\SensitiveParameter] string $newPassword): string
{
    return with_verified_member($pdo, $memberId, $currentPassword, static function () use ($pdo, $memberId, $newPassword): string {
        update_password_hash($pdo, $memberId, password_hash($newPassword, PASSWORD_DEFAULT));
        return 'ok';
    });
}

// U16。論理削除（withdrawal_flag）とカートの削除を同じトランザクションで行う。退会済みなら 'already'
function withdraw_member(PDO $pdo, int $memberId, #[\SensitiveParameter] string $currentPassword): string
{
    $result = with_verified_member($pdo, $memberId, $currentPassword, static function () use ($pdo, $memberId): string {
        $pdo->prepare('UPDATE members SET withdrawal_flag = 1 WHERE member_id = ?')->execute([$memberId]);
        $pdo->prepare('DELETE FROM carts WHERE member_id = ?')->execute([$memberId]);
        return 'ok';
    });
    return $result === 'inactive' ? 'already' : $result;
}

// ---- 現在のパスワード確認の失敗をセッションごとに数える（ログインのロックとは別。Plan §2.0 Q18 d・Codex P1-10） ----
function password_check_throttled(array $session, int $now): bool
{
    return ($session['password_check']['blocked_until'] ?? 0) > $now;
}

function record_password_check_failure(array &$session, int $now): void
{
    $count = (int) ($session['password_check']['failures'] ?? 0) + 1;
    $session['password_check'] = $count >= PASSWORD_CHECK_MAX_FAILURES
        ? ['failures' => 0, 'blocked_until' => $now + PASSWORD_CHECK_BLOCK_SECONDS]
        : ['failures' => $count, 'blocked_until' => 0];
}

function clear_password_check_failures(array &$session): void
{
    unset($session['password_check']);
}

// 照合に失敗したとき：回数を数え、ログに会員 ID だけ残し、画面に出す文言を返す
function current_password_failure(array &$session, int $memberId, string $action, int $now): string
{
    record_password_check_failure($session, $now);
    error_log(sprintf('[playduo] current password mismatch: member_id=%d action=%s', $memberId, $action));
    return password_check_throttled($session, $now) ? CURRENT_PASSWORD_THROTTLED : CURRENT_PASSWORD_WRONG;
}
