<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const BLOG_ADMIN_SESSION_KEY = 'melina_admin';
const BLOG_SESSION_NAME = 'melina_admin_session';
const BLOG_CSRF_TOKEN_KEY = 'blog_csrf_token';
const BLOG_CSRF_ISSUED_AT_KEY = 'blog_csrf_issued_at';
const BLOG_CSRF_TTL_SECONDS = 7200;
const BLOG_CSRF_ERROR_MESSAGE = 'Session expirée, merci de recharger la page.';

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

function blog_log_security_event(string $event, array $context = []): void
{
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $logFile = $logDir . '/security.log';
    $payload = [
        'time' => gmdate('c'),
        'event' => $event,
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'context' => $context,
    ];

    @file_put_contents($logFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function blog_csrf_token(): string
{
    blog_start_session();

    $token = (string) ($_SESSION[BLOG_CSRF_TOKEN_KEY] ?? '');
    $issuedAt = (int) ($_SESSION[BLOG_CSRF_ISSUED_AT_KEY] ?? 0);
    $isExpired = $issuedAt <= 0 || (time() - $issuedAt) >= BLOG_CSRF_TTL_SECONDS;

    if ($token === '' || $isExpired) {
        $token = bin2hex(random_bytes(32));
        $_SESSION[BLOG_CSRF_TOKEN_KEY] = $token;
        $_SESSION[BLOG_CSRF_ISSUED_AT_KEY] = time();
    }

    return $token;
}

function blog_csrf_validate_request(string $action): bool
{
    blog_start_session();
    blog_csrf_token();

    $candidate = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION[BLOG_CSRF_TOKEN_KEY] ?? '');
    if ($candidate === '' || $sessionToken === '' || !hash_equals($sessionToken, $candidate)) {
        blog_log_security_event('csrf_validation_failed', [
            'action' => $action,
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        ]);
        return false;
    }

    return true;
}
