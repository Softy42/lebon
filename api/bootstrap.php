<?php

declare(strict_types=1);

function blog_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) {
        throw new RuntimeException('CONFIG_NOT_FOUND');
    }

    $config = require $path;
    return $config;
}

function blog_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = blog_config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');

    $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function send_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9\s-]/', '', strtolower($value)) ?? '';
    $value = preg_replace('/\s+/', '-', trim($value)) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';
    return trim($value, '-');
}

function excerpt_from_html(string $html, int $max = 180): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
    return mb_strlen($text) <= $max ? $text : (mb_substr($text, 0, $max - 3) . '...');
}

function estimate_reading_time(string $content): int
{
    $words = str_word_count(strip_tags($content));
    return max(1, (int) ceil($words / 200));
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $pad = 4 - (strlen($data) % 4);
    if ($pad < 4) {
        $data .= str_repeat('=', $pad);
    }
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

function create_admin_token(): string
{
    $cfg = blog_config()['admin'];
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'sub' => $cfg['username'],
        'exp' => time() + (int) ($cfg['token_ttl_seconds'] ?? 28800),
    ]));

    $sig = hash_hmac('sha256', "$header.$payload", $cfg['token_secret'], true);
    return "$header.$payload." . base64url_encode($sig);
}

function verify_admin_token(?string $token): bool
{
    if (!$token) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return false;
    }

    [$h, $p, $s] = $parts;
    $cfg = blog_config()['admin'];
    $expected = base64url_encode(hash_hmac('sha256', "$h.$p", $cfg['token_secret'], true));

    if (!hash_equals($expected, $s)) {
        return false;
    }

    $payload = json_decode(base64url_decode($p), true);
    return is_array($payload) && isset($payload['exp']) && (int) $payload['exp'] >= time();
}

function bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) {
        return null;
    }
    return substr($header, 7);
}

function require_admin(): void
{
    if (!verify_admin_token(bearer_token())) {
        send_json(401, ['ok' => false, 'error' => 'UNAUTHORIZED']);
    }
}

function fetch_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, description, sort_order AS `order` FROM blog_categories ORDER BY sort_order ASC, name ASC');
    return $stmt->fetchAll();
}

function fetch_categories_map(PDO $pdo): array
{
    $rows = fetch_categories($pdo);
    $map = [];
    foreach ($rows as $row) {
        $map[$row['id']] = $row;
    }
    return $map;
}

function map_post(array $row): array
{
    $related = [];
    if (!empty($row['related_slugs'])) {
        $parsed = json_decode($row['related_slugs'], true);
        if (is_array($parsed)) {
            $related = $parsed;
        }
    }

    return [
        'id' => $row['id'],
        'title' => $row['title'],
        'slug' => $row['slug'],
        'excerpt' => $row['excerpt'],
        'content' => $row['content'],
        'status' => $row['status'],
        'publishedAt' => $row['published_at'],
        'updatedAt' => $row['updated_at'],
        'createdAt' => $row['created_at'],
        'author' => $row['author'],
        'image' => !empty($row['image_url']) ? ['url' => $row['image_url'], 'alt' => $row['image_alt'] ?: $row['title']] : null,
        'seo' => ['title' => $row['seo_title'] ?: $row['title'], 'description' => $row['seo_description'] ?: $row['excerpt']],
        'cta' => ['label' => $row['cta_label'] ?: 'Demander une visite', 'url' => $row['cta_url'] ?: '/contact'],
        'readingTimeMinutes' => (int) ($row['reading_time_minutes'] ?: 1),
        'relatedSlugs' => $related,
        'category' => $row['category_id'] ? ['id' => $row['category_id'], 'name' => $row['category_name']] : null,
        'categoryId' => $row['category_id'],
    ];
}

function normalize_post(array $input, array $categoriesMap, array $existing = []): array
{
    $title = trim((string) ($input['title'] ?? $existing['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('TITLE_REQUIRED');
    }

    $slug = slugify((string) ($input['slug'] ?? $existing['slug'] ?? $title));
    if ($slug === '') {
        throw new InvalidArgumentException('SLUG_REQUIRED');
    }

    $content = trim((string) ($input['content'] ?? $existing['content'] ?? ''));
    if ($content === '') {
        throw new InvalidArgumentException('CONTENT_REQUIRED');
    }

    $categoryId = (string) ($input['categoryId'] ?? $existing['categoryId'] ?? 'actualites-maison-melina');
    if (!isset($categoriesMap[$categoryId])) {
        throw new InvalidArgumentException('INVALID_CATEGORY');
    }

    $status = (($input['status'] ?? $existing['status'] ?? 'published') === 'draft') ? 'draft' : 'published';
    $publishedAt = $status === 'published'
        ? (string) ($input['publishedAt'] ?? $existing['publishedAt'] ?? date('Y-m-d H:i:s'))
        : null;

    $img = $input['image'] ?? $existing['image'] ?? null;
    $imageUrl = is_array($img) ? trim((string) ($img['url'] ?? '')) : null;
    $imageAlt = is_array($img) ? trim((string) ($img['alt'] ?? $title)) : null;

    $excerpt = trim((string) ($input['excerpt'] ?? $existing['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = excerpt_from_html($content);
    }

    $seo = $input['seo'] ?? $existing['seo'] ?? [];
    $cta = $input['cta'] ?? $existing['cta'] ?? [];

    return [
        'id' => (string) ($input['id'] ?? $existing['id'] ?? ''),
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'categoryId' => $categoryId,
        'status' => $status,
        'publishedAt' => $publishedAt,
        'updatedAt' => date('Y-m-d H:i:s'),
        'createdAt' => (string) ($existing['createdAt'] ?? date('Y-m-d H:i:s')),
        'author' => trim((string) ($input['author'] ?? $existing['author'] ?? 'Équipe Maison Mélina')),
        'image' => $imageUrl ? ['url' => $imageUrl, 'alt' => $imageAlt ?: $title] : null,
        'seo' => [
            'title' => trim((string) ($seo['title'] ?? $title)),
            'description' => trim((string) ($seo['description'] ?? $excerpt)),
        ],
        'cta' => [
            'label' => trim((string) ($cta['label'] ?? 'Demander une visite')),
            'url' => trim((string) ($cta['url'] ?? '/contact')),
        ],
        'readingTimeMinutes' => estimate_reading_time($content),
        'relatedSlugs' => array_values(array_filter(array_map(static fn($s) => slugify((string) $s), $input['relatedSlugs'] ?? $existing['relatedSlugs'] ?? []))),
    ];
}

function normalize_category(array $input, int $index): array
{
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('INVALID_CATEGORY');
    }

    $id = slugify((string) ($input['id'] ?? $name));
    return [
        'id' => $id,
        'name' => $name,
        'description' => trim((string) ($input['description'] ?? '')),
        'order' => isset($input['order']) ? (int) $input['order'] : $index,
    ];
}
