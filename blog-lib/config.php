<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }

    $trimmed = trim((string) $value);
    return $trimmed === '' ? $default : $trimmed;
};

$startsWith = static function (string $value, string $prefix): bool {
    return strncmp($value, $prefix, strlen($prefix)) === 0;
};

$appEnv = strtolower((string) $env('BLOG_APP_ENV', $env('APP_ENV', 'production')));
$isProduction = in_array($appEnv, ['prod', 'production'], true);

$dbHost = (string) $env('BLOG_DB_HOST', '127.0.0.1');
$dbPort = (int) $env('BLOG_DB_PORT', '3306');
$dbName = (string) $env('BLOG_DB_NAME');
$dbUser = (string) $env('BLOG_DB_USER');
$dbPass = (string) $env('BLOG_DB_PASS');
$dbCharset = (string) $env('BLOG_DB_CHARSET', 'utf8mb4');

if ($dbName === '' || $dbUser === '' || $dbPass === '') {
    throw new RuntimeException('Configuration invalide: BLOG_DB_NAME, BLOG_DB_USER et BLOG_DB_PASS sont obligatoires.');
}

if ($isProduction && strtolower($dbUser) === 'root') {
    throw new RuntimeException('Configuration invalide: utilisateur MySQL root interdit en production. Utilisez un compte applicatif dédié.');
}

$adminUsername = (string) $env('BLOG_ADMIN_USER');
$adminPasswordHash = (string) $env('BLOG_ADMIN_PASSWORD_HASH');

if ($adminUsername === '' || $adminPasswordHash === '') {
    throw new RuntimeException('Configuration invalide: BLOG_ADMIN_USER et BLOG_ADMIN_PASSWORD_HASH sont obligatoires.');
}

if ($isProduction && in_array(strtolower($adminUsername), ['admin', 'administrator'], true)) {
    throw new RuntimeException('Configuration invalide: nom d\'utilisateur admin trop prévisible en production.');
}

$isBcrypt = $startsWith($adminPasswordHash, '$2y$');
$isArgon = $startsWith($adminPasswordHash, '$argon2i$') || $startsWith($adminPasswordHash, '$argon2id$');

if (!$isBcrypt && !$isArgon) {
    throw new RuntimeException('Configuration invalide: BLOG_ADMIN_PASSWORD_HASH doit être un hash bcrypt ou argon2.');
}

return [
    'db' => [
        'host' => $dbHost,
        'port' => $dbPort,
        'name' => $dbName,
        'user' => $dbUser,
        'password' => $dbPass,
        'charset' => $dbCharset,
    ],
    'admin' => [
        'username' => $adminUsername,
        'password_hash' => $adminPasswordHash,
    ],
    'contact_url' => (string) $env('BLOG_CONTACT_URL', 'https://www.maison-melina.fr/contact'),
    'authors' => array_values(array_filter(array_map('trim', explode(',', (string) $env('BLOG_AUTHORS', 'Thierry,Christine'))))),
];
