<?php

declare(strict_types=1);

// 入力チェック（画面設計書（利用者）v1.1 の U04・U15 入力チェック表に合わせる）
const NAME_MAX_LENGTH = 50;
const EMAIL_MAX_LENGTH = 255;
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 72;   // bcrypt の上限（半角のみなので文字数＝バイト数）
const NICKNAME_MIN_LENGTH = 2;
const NICKNAME_MAX_LENGTH = 20;
const BIRTH_YEAR_MIN = 1900;
const PHONE_PATTERN = '/\A\d{3}-?\d{4}-?\d{4}\z/';   // 携帯 xxx-xxxx-xxxx（ハイフン有無どちらも可）

require_once __DIR__ . '/functions.php';

function post_string(array $post, string $key): string
{
    $value = $post[$key] ?? '';
    return is_string($value) ? $value : '';
}

function trim_all(string $value): string
{
    // 全角スペースも前後から取り除く
    return (string) preg_replace('/\A[\s　]+|[\s　]+\z/u', '', $value);
}

/**
 * U04 の入力を検証する。
 * @return array{errors: array<string, string>, input: array{name: string, email: string, birth_date: ?string, nickname: ?string, phone: ?string}}
 */
function validate_register_input(array $post, int $now): array
{
    $name = trim_all(post_string($post, 'name'));
    $email = trim_all(post_string($post, 'email'));
    $nickname = trim_all(post_string($post, 'nickname'));
    $phone = trim_all(post_string($post, 'phone'));
    $birthDate = build_birth_date($post, $now);

    $errors = [];
    if ($name === '') {
        $errors['name'] = '氏名を入力してください';
    } elseif (mb_strlen($name) > NAME_MAX_LENGTH) {
        $errors['name'] = '氏名は' . NAME_MAX_LENGTH . '文字以内で入力してください';
    }

    if ($email === '') {
        $errors['email'] = 'メールアドレスを入力してください';
    } elseif (strlen($email) > EMAIL_MAX_LENGTH || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'メールアドレスの形式が正しくありません';
    }

    $errors += validate_new_password(
        post_string($post, 'password'),
        post_string($post, 'password_confirm'),
        'password',
        'password_confirm',
    );

    if ($birthDate['error'] !== null) {
        $errors['birth_date'] = $birthDate['error'];
    }

    $nicknameLength = mb_strlen($nickname);
    if ($nickname !== '' && ($nicknameLength < NICKNAME_MIN_LENGTH || $nicknameLength > NICKNAME_MAX_LENGTH)) {
        $errors['nickname'] = 'ニックネームは' . NICKNAME_MIN_LENGTH . '〜' . NICKNAME_MAX_LENGTH . '文字で入力してください';
    }

    if ($phone !== '' && preg_match(PHONE_PATTERN, $phone) !== 1) {
        $errors['phone'] = '電話番号は 090-1234-5678 の形で入力してください';
    }

    return [
        'errors' => $errors,
        'input'  => [
            'name'       => $name,
            'email'      => $email,
            'birth_date' => $birthDate['value'],
            'nickname'   => $nickname === '' ? null : $nickname,
            'phone'      => $phone === '' ? null : $phone,
        ],
    ];
}

// エラーで戻ったときの入力値（パスワードは戻さない）
const REGISTER_OLD_FIELDS = ['name', 'email', 'birth_year', 'birth_month', 'birth_day', 'nickname', 'phone'];

/** @return array<string, string> */
function register_old_values(array $post): array
{
    $old = [];
    foreach (REGISTER_OLD_FIELDS as $field) {
        $old[$field] = post_string($post, $field);
    }
    return $old;
}

// U05 から「修正する」で戻ったとき、保存済みの入力を U04 の欄に戻す
/** @return array<string, string> */
function register_old_from_input(array $input): array
{
    [$year, $month, $day] = explode('-', (string) $input['birth_date']);
    return [
        'name'        => (string) $input['name'],
        'email'       => (string) $input['email'],
        'birth_year'  => $year,
        'birth_month' => (string) (int) $month,
        'birth_day'   => (string) (int) $day,
        'nickname'    => (string) ($input['nickname'] ?? ''),
        'phone'       => (string) ($input['phone'] ?? ''),
    ];
}

/** @return array{value: ?string, error: ?string} */
function build_birth_date(array $post, int $now): array
{
    $parts = [post_string($post, 'birth_year'), post_string($post, 'birth_month'), post_string($post, 'birth_day')];
    if (in_array('', $parts, true)) {
        return ['value' => null, 'error' => '生年月日を選択してください'];
    }
    foreach ($parts as $part) {
        if (!ctype_digit($part)) {
            return ['value' => null, 'error' => '生年月日が正しくありません'];
        }
    }
    [$year, $month, $day] = array_map('intval', $parts);
    if ($year < BIRTH_YEAR_MIN || !checkdate($month, $day, $year)) {
        return ['value' => null, 'error' => '生年月日が正しくありません'];
    }
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    if ($date > today_string($now)) {
        return ['value' => null, 'error' => '生年月日に未来の日付は選べません'];
    }
    return ['value' => $date, 'error' => null];
}

function today_string(int $now): string
{
    return substr(db_datetime($now), 0, 10);
}

/**
 * パスワードと確認用の検証（U04 は password/password_confirm、U15 は new_password/new_password_confirm）。
 * @return array<string, string>
 */
function validate_new_password(string $password, string $confirm, string $field, string $confirmField): array
{
    $errors = [];
    if ($password === '') {
        $errors[$field] = 'パスワードを入力してください';
    } elseif (!is_valid_password_format($password)) {
        $errors[$field] = 'パスワードは半角英字・数字をそれぞれ含む' . PASSWORD_MIN_LENGTH . '〜' . PASSWORD_MAX_LENGTH . '文字で入力してください';
    }

    if ($confirm === '') {
        $errors[$confirmField] = '確認用のパスワードを入力してください';
    } elseif ($password !== $confirm) {
        $errors[$confirmField] = 'パスワードが一致しません';
    }
    return $errors;
}

function is_valid_password_format(string $password): bool
{
    $pattern = '/\A[\x21-\x7E]{' . PASSWORD_MIN_LENGTH . ',' . PASSWORD_MAX_LENGTH . '}\z/';
    return preg_match($pattern, $password) === 1
        && preg_match('/[A-Za-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}
