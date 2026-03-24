<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_admin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = blog_db();

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT p.*, c.id AS category_id, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id ORDER BY COALESCE(p.published_at, p.updated_at) DESC');
        $posts = array_map('map_post', $stmt->fetchAll());
        send_json(200, ['ok' => true, 'posts' => $posts]);
    }

    $input = json_input();
    $categoriesMap = fetch_categories_map($pdo);

    if ($method === 'POST') {
        $post = normalize_post($input, $categoriesMap);
        $post['id'] = $post['id'] ?: bin2hex(random_bytes(16));

        $check = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :slug LIMIT 1');
        $check->execute(['slug' => $post['slug']]);
        if ($check->fetch()) {
            send_json(409, ['ok' => false, 'error' => 'SLUG_ALREADY_EXISTS']);
        }

        $stmt = $pdo->prepare('INSERT INTO blog_posts (id, title, slug, excerpt, content, category_id, status, published_at, updated_at, created_at, author, image_url, image_alt, seo_title, seo_description, cta_label, cta_url, reading_time_minutes, related_slugs) VALUES (:id, :title, :slug, :excerpt, :content, :category_id, :status, :published_at, :updated_at, :created_at, :author, :image_url, :image_alt, :seo_title, :seo_description, :cta_label, :cta_url, :reading_time_minutes, :related_slugs)');
        $stmt->execute([
            'id' => $post['id'],
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'category_id' => $post['categoryId'],
            'status' => $post['status'],
            'published_at' => $post['publishedAt'],
            'updated_at' => $post['updatedAt'],
            'created_at' => $post['createdAt'],
            'author' => $post['author'],
            'image_url' => $post['image']['url'] ?? null,
            'image_alt' => $post['image']['alt'] ?? null,
            'seo_title' => $post['seo']['title'],
            'seo_description' => $post['seo']['description'],
            'cta_label' => $post['cta']['label'],
            'cta_url' => $post['cta']['url'],
            'reading_time_minutes' => $post['readingTimeMinutes'],
            'related_slugs' => json_encode($post['relatedSlugs'], JSON_UNESCAPED_UNICODE),
        ]);

        send_json(201, ['ok' => true, 'post' => $post]);
    }

    if ($method === 'PUT') {
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            send_json(400, ['ok' => false, 'error' => 'POST_ID_REQUIRED']);
        }

        $existingStmt = $pdo->prepare('SELECT p.*, c.id AS category_id, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.category_id WHERE p.id = :id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existingRow = $existingStmt->fetch();
        if (!$existingRow) {
            send_json(404, ['ok' => false, 'error' => 'POST_NOT_FOUND']);
        }

        $existing = map_post($existingRow);
        $post = normalize_post($input, $categoriesMap, $existing);
        $post['id'] = $id;

        $dup = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :slug AND id <> :id LIMIT 1');
        $dup->execute(['slug' => $post['slug'], 'id' => $id]);
        if ($dup->fetch()) {
            send_json(409, ['ok' => false, 'error' => 'SLUG_ALREADY_EXISTS']);
        }

        $stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, category_id = :category_id, status = :status, published_at = :published_at, updated_at = :updated_at, author = :author, image_url = :image_url, image_alt = :image_alt, seo_title = :seo_title, seo_description = :seo_description, cta_label = :cta_label, cta_url = :cta_url, reading_time_minutes = :reading_time_minutes, related_slugs = :related_slugs WHERE id = :id');
        $stmt->execute([
            'id' => $post['id'],
            'title' => $post['title'],
            'slug' => $post['slug'],
            'excerpt' => $post['excerpt'],
            'content' => $post['content'],
            'category_id' => $post['categoryId'],
            'status' => $post['status'],
            'published_at' => $post['publishedAt'],
            'updated_at' => $post['updatedAt'],
            'author' => $post['author'],
            'image_url' => $post['image']['url'] ?? null,
            'image_alt' => $post['image']['alt'] ?? null,
            'seo_title' => $post['seo']['title'],
            'seo_description' => $post['seo']['description'],
            'cta_label' => $post['cta']['label'],
            'cta_url' => $post['cta']['url'],
            'reading_time_minutes' => $post['readingTimeMinutes'],
            'related_slugs' => json_encode($post['relatedSlugs'], JSON_UNESCAPED_UNICODE),
        ]);

        send_json(200, ['ok' => true, 'post' => $post]);
    }

    if ($method === 'DELETE') {
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            send_json(400, ['ok' => false, 'error' => 'POST_ID_REQUIRED']);
        }

        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            send_json(404, ['ok' => false, 'error' => 'POST_NOT_FOUND']);
        }

        send_json(200, ['ok' => true]);
    }

    header('Allow: GET, POST, PUT, DELETE');
    send_json(405, ['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
} catch (InvalidArgumentException $e) {
    send_json(400, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    send_json(500, ['ok' => false, 'error' => 'SERVER_ERROR', 'detail' => $e->getMessage()]);
}
