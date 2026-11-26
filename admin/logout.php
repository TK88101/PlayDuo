<?php
// 管理者ログアウト（画面なし）。CSRF を防ぐため POST だけ受け付ける
declare(strict_types=1);
require __DIR__ . '/../includes/admin_bootstrap.php';
require_once __DIR__ . '/../includes/admin_product_draft.php';

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
verify_csrf_or_fail();
discard_product_draft($_SESSION, admin_upload_tmp_dir());   // 確認待ちの一時ファイルも残さない
admin_sign_out();
redirect('login.php');
