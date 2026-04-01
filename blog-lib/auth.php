<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const BLOG_ADMIN_SESSION_KEY = 'melina_admin';
const BLOG_SESSION_NAME = 'melina_admin_session';

function blog_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === 443);

    session_name(BLOG_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

function blog_is_admin(): bool
{
    blog_start_session();
    return !empty($_SESSION[BLOG_ADMIN_SESSION_KEY]);
}

function blog_require_admin(): void
{
    if (!blog_is_admin()) {
        header('Location: /admin/le-mag/index.php');
        exit;
    }
}

function blog_try_login(string $username, string $password): bool
{
    $admin = blog_config()['admin'];

    if (!hash_equals($admin['username'], $username)) {
        return false;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    blog_start_session();
    session_regenerate_id(true);

    $_SESSION[BLOG_ADMIN_SESSION_KEY] = [
        'username' => $username,
        'logged_at' => time(),
    ];

    return true;
}

function blog_logout(): void
{
    blog_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Strict',
            ]
        );
    }

    session_destroy();
}
