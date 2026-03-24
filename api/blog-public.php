<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    send_json(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
}

try {
    $pdo = blog_db();
    $categories = fetch_categories($pdo);
    $slug = isset($_GET['slug']) ? (string) $_GET['slug'] : null;
    $categoryId = isset($_GET['category']) ? (string) $_GET['category'] : null;

    if ($slug) {
        $stmt = $pdo->prepare('SELECT p.*, c.id AS category_id, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.slug = :slug AND p.status = "published" LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if (!$row) {
            send_json(404, ['ok' => false, 'error' => 'POST_NOT_FOUND']);
        }

        $post = map_post($row);

        $stmt2 = $pdo->query('SELECT p.*, c.id AS category_id, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.status = "published" ORDER BY COALESCE(p.published_at, p.updated_at) DESC');
        $all = array_map('map_post', $stmt2->fetchAll());

        $related = array_values(array_slice(array_filter($all, static function (array $item) use ($post): bool {
            if ($item['slug'] === $post['slug']) {
                return false;
            }
            return in_array($item['slug'], $post['relatedSlugs'], true)
                || (($item['category']['id'] ?? '') === ($post['category']['id'] ?? ''));
        }), 0, 3));

        send_json(200, ['ok' => true, 'categories' => $categories, 'post' => $post, 'relatedPosts' => $related]);
    }

    $sql = 'SELECT p.*, c.id AS category_id, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.status = "published"';
    $params = [];
    if ($categoryId) {
        $sql .= ' AND p.category_id = :category';
        $params['category'] = $categoryId;
    }
    $sql .= ' ORDER BY COALESCE(p.published_at, p.updated_at) DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $posts = array_map(static function (array $row): array {
        $p = map_post($row);
        return [
            'title' => $p['title'],
            'slug' => $p['slug'],
            'excerpt' => $p['excerpt'],
            'publishedAt' => $p['publishedAt'],
            'category' => $p['category'],
            'image' => $p['image'],
            'readingTimeMinutes' => $p['readingTimeMinutes'],
            'author' => $p['author'],
        ];
    }, $rows);

    send_json(200, ['ok' => true, 'categories' => $categories, 'posts' => $posts]);
} catch (Throwable $e) {
    send_json(500, ['ok' => false, 'error' => 'BLOG_STORAGE_UNAVAILABLE', 'detail' => $e->getMessage()]);
}
