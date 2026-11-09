<?php
// ログアウト（画面なし）。CSRF を防ぐため POST だけ受け付ける
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
verify_csrf_or_fail();
destroy_session();
redirect('login.php');
