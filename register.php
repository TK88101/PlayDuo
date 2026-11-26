<?php
// U04 会員登録
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
send_no_store_headers();

$page_title = '会員登録';
$minimal_header = true;   // Figma U04：会員登録の流れはロゴだけのヘッダー

if (is_post()) {
    verify_csrf_or_fail();
    $result = validate_register_input($_POST, time());
    $errors = $result['errors'];
    if (!isset($errors['email']) && email_exists(db(), $result['input']['email'])) {
        $errors['email'] = 'このメールアドレスは登録済みです';
    }
    if ($errors === []) {
        // これから秘密鍵・復旧コードを session に置くので、ID を作り直して固定化を防ぐ
        // （renew_session_for_auth() は登録の途中状態まで消すので、ここでは ID だけ作り直す）
        session_regenerate_id(true);
        // 平文のパスワードは session に置かない
        $_SESSION['register'] = [
            'input'         => $result['input'],
            'password_hash' => password_hash(post_string($_POST, 'password'), PASSWORD_DEFAULT),
        ];
        redirect('register_confirm.php');
    }
    $old = register_old_values($_POST);
} elseif (isset($_SESSION['register']['input'])) {
    $old = register_old_from_input($_SESSION['register']['input']);
}

$this_year = (int) substr(today_string(time()), 0, 4);
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="auth-card auth-card-wide">
<h1>会員登録</h1>
<form method="post" action="" class="auth-form">
    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

    <label>氏名 <input type="text" name="name" placeholder="山田 花子" value="<?= h($old['name'] ?? '') ?>"></label>
    <?= error_html($errors, 'name') ?>

    <label>メールアドレス <input type="email" name="email" placeholder="example@example.com" value="<?= h($old['email'] ?? '') ?>"></label>
    <?= error_html($errors, 'email') ?>

    <label>パスワード <input type="password" name="password" placeholder="半角英字・数字をそれぞれ含む8文字以上"></label>
    <?= error_html($errors, 'password') ?>

    <label>パスワード（確認用） <input type="password" name="password_confirm" placeholder="もう一度入力"></label>
    <?= error_html($errors, 'password_confirm') ?>

    <fieldset class="birth-date">
        <legend>生年月日</legend>
        <select name="birth_year">
            <option value="">--</option>
            <?php for ($y = $this_year; $y >= BIRTH_YEAR_MIN; $y--): ?>
                <option value="<?= $y ?>"<?= ($old['birth_year'] ?? '') === (string) $y ? ' selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>年
        <select name="birth_month">
            <option value="">--</option>
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>"<?= ($old['birth_month'] ?? '') === (string) $m ? ' selected' : '' ?>><?= $m ?></option>
            <?php endfor; ?>
        </select>月
        <select name="birth_day">
            <option value="">--</option>
            <?php for ($d = 1; $d <= 31; $d++): ?>
                <option value="<?= $d ?>"<?= ($old['birth_day'] ?? '') === (string) $d ? ' selected' : '' ?>><?= $d ?></option>
            <?php endfor; ?>
        </select>日
    </fieldset>
    <?= error_html($errors, 'birth_date') ?>

    <label>ニックネーム（任意） <input type="text" name="nickname" placeholder="デュオプレイヤー" value="<?= h($old['nickname'] ?? '') ?>"></label>
    <?= error_html($errors, 'nickname') ?>

    <label>電話番号（任意） <input type="tel" name="phone" placeholder="090-1234-5678" value="<?= h($old['phone'] ?? '') ?>"></label>
    <?= error_html($errors, 'phone') ?>

    <button type="submit">登録する</button>
</form>
<p class="auth-switch">すでに会員の方は <a href="login.php">ログイン</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
