<?php
require_once __DIR__ . '/db.php';

function blog_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function blog_is_admin(): bool
{
    blog_start_session();
    return !empty($_SESSION['melina_admin']);
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

    if ($username !== $admin['username']) {
        return false;
    }

    if (!password_verify($password, $admin['password_hash'])) {
        return false;
    }

    blog_start_session();
    $_SESSION['melina_admin'] = [
        'username' => $username,
        'logged_at' => time(),
    ];

    return true;
}

function blog_logout(): void
{
    blog_start_session();
    $_SESSION = [];
    session_destroy();
}
