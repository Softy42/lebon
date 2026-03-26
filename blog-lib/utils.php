<?php
require_once __DIR__ . '/db.php';

function blog_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
