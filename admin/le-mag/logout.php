<?php
require_once __DIR__ . '/../../blog-lib/auth.php';
blog_logout();
header('Location: /admin/le-mag/index.php');
exit;
