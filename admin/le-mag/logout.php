<?php
require_once __DIR__ . '/../../blog-lib/auth.php';

blog_start_session();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !blog_csrf_validate_request('logout')) {
    $_SESSION['melina_logout_error'] = BLOG_CSRF_ERROR_MESSAGE;
    header('Location: /admin/le-mag/index.php');
    exit;
}

blog_logout();
header('Location: /admin/le-mag/index.php');
exit;
