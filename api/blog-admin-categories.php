<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

require_admin();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = blog_db();

    if ($method === 'GET') {
        send_json(200, ['ok' => true, 'categories' => fetch_categories($pdo)]);
    }

    $input = json_input();

    if ($method === 'POST') {
        $categories = fetch_categories($pdo);
        $category = normalize_category($input, count($categories) + 1);

        $check = $pdo->prepare('SELECT id FROM blog_categories WHERE id = :id LIMIT 1');
        $check->execute(['id' => $category['id']]);
        if ($check->fetch()) {
            send_json(409, ['ok' => false, 'error' => 'CATEGORY_EXISTS']);
        }

        $stmt = $pdo->prepare('INSERT INTO blog_categories (id, name, description, sort_order) VALUES (:id, :name, :description, :sort_order)');
        $stmt->execute([
            'id' => $category['id'],
            'name' => $category['name'],
            'description' => $category['description'],
            'sort_order' => $category['order'],
        ]);

        send_json(201, ['ok' => true, 'category' => $category]);
    }

    if ($method === 'PUT') {
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            send_json(400, ['ok' => false, 'error' => 'CATEGORY_ID_REQUIRED']);
        }

        $check = $pdo->prepare('SELECT id FROM blog_categories WHERE id = :id LIMIT 1');
        $check->execute(['id' => $id]);
        if (!$check->fetch()) {
            send_json(404, ['ok' => false, 'error' => 'CATEGORY_NOT_FOUND']);
        }

        $category = normalize_category(array_merge($input, ['id' => $id]), 1);

        $stmt = $pdo->prepare('UPDATE blog_categories SET name = :name, description = :description, sort_order = :sort_order WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $category['name'],
            'description' => $category['description'],
            'sort_order' => $category['order'],
        ]);

        send_json(200, ['ok' => true, 'category' => $category]);
    }

    if ($method === 'DELETE') {
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            send_json(400, ['ok' => false, 'error' => 'CATEGORY_ID_REQUIRED']);
        }

        $inUse = $pdo->prepare('SELECT id FROM blog_posts WHERE category_id = :id LIMIT 1');
        $inUse->execute(['id' => $id]);
        if ($inUse->fetch()) {
            send_json(409, ['ok' => false, 'error' => 'CATEGORY_IN_USE']);
        }

        $stmt = $pdo->prepare('DELETE FROM blog_categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() === 0) {
            send_json(404, ['ok' => false, 'error' => 'CATEGORY_NOT_FOUND']);
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
