<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    send_json(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

$input = json_input();
$username = (string) ($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');

$admin = blog_config()['admin'];
if (!isset($admin['username'], $admin['password'], $admin['token_secret'])) {
    send_json(500, ['ok' => false, 'error' => 'ADMIN_CONFIG_MISSING']);
}

if (!hash_equals((string) $admin['username'], $username) || !hash_equals((string) $admin['password'], $password)) {
    send_json(401, ['ok' => false, 'error' => 'INVALID_CREDENTIALS']);
}

send_json(200, ['ok' => true, 'token' => create_admin_token()]);
