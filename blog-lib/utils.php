<?php
require_once __DIR__ . '/db.php';

function blog_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function blog_current_origin(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    $scheme = $isHttps ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'www.maison-melina.fr');

    return sprintf('%s://%s', $scheme, $host);
}

function blog_canonical_url(string $path, array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $url = blog_current_origin() . $path;

    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    return $url;
}

function blog_slugify(string $text): string
{
    $text = trim(mb_strtolower($text));
    $text = preg_replace('/[^a-z0-9\s-]/u', '', $text) ?? '';
    $text = preg_replace('/[\s-]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function blog_cta_data(string $variant): array
{
    $url = blog_config()['contact_url'];

    if ($variant === 'visit') {
        return ['label' => 'Demander une visite', 'url' => $url];
    }

    return ['label' => 'Prendre contact', 'url' => $url];
}

function blog_fetch_categories(): array
{
    $stmt = blog_pdo()->query('SELECT id, name, description, sort_order FROM blog_categories ORDER BY sort_order ASC, name ASC');
    return $stmt->fetchAll();
}
