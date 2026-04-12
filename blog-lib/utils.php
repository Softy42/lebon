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

function blog_is_allowed_href(string $href): bool
{
    $href = trim($href);
    if ($href === '') {
        return false;
    }

    if (str_starts_with($href, '/')) {
        return true;
    }

    if (str_starts_with($href, '#')) {
        return true;
    }

    return preg_match('/^https:\/\/[^\s]+$/iu', $href) === 1;
}

function blog_sanitize_content_html(string $html, ?bool &$wasModified = null): string
{
    $allowedTags = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'blockquote', 'a', 'br'];
    $wasModified = false;

    $wrappedHtml = '<div id="blog-sanitizer-root">' . $html . '</div>';

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $wrappedHtml,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    if (!$loaded) {
        $wasModified = true;
        return '';
    }

    $xpath = new DOMXPath($dom);
    $root = $xpath->query('//*[@id="blog-sanitizer-root"]')->item(0);
    if (!$root instanceof DOMElement) {
        $wasModified = true;
        return '';
    }

    $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $dom, $allowedTags, &$wasModified): void {
        if ($node instanceof DOMElement) {
            $tagName = strtolower($node->tagName);

            if (!in_array($tagName, $allowedTags, true)) {
                $wasModified = true;

                if (in_array($tagName, ['script', 'style', 'iframe'], true)) {
                    $node->parentNode?->removeChild($node);
                    return;
                }

                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);
                return;
            }

            $allowedAttributes = $tagName === 'a' ? ['href', 'target', 'rel'] : [];
            $attributes = [];
            foreach ($node->attributes ?? [] as $attribute) {
                $attributes[] = $attribute;
            }

            foreach ($attributes as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, $allowedAttributes, true)) {
                    $node->removeAttributeNode($attribute);
                    $wasModified = true;
                }
            }

            if ($tagName === 'a') {
                $href = (string) $node->getAttribute('href');
                if (!blog_is_allowed_href($href)) {
                    while ($node->firstChild) {
                        $node->parentNode?->insertBefore($node->firstChild, $node);
                    }
                    $node->parentNode?->removeChild($node);
                    $wasModified = true;
                    return;
                }

                $target = strtolower((string) $node->getAttribute('target'));
                if ($target !== '_blank') {
                    if ($target !== '') {
                        $wasModified = true;
                    }
                    $node->removeAttribute('target');
                    $node->removeAttribute('rel');
                } else {
                    $node->setAttribute('target', '_blank');
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $sanitizeNode($child);
        }
    };

    $rootChildren = [];
    foreach ($root->childNodes as $child) {
        $rootChildren[] = $child;
    }
    foreach ($rootChildren as $child) {
        $sanitizeNode($child);
    }

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }

    return trim($result);
}
