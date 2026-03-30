<?php
require_once __DIR__ . '/../../blog-lib/auth.php';
require_once __DIR__ . '/../../blog-lib/utils.php';

function blog_testimonial_image_dir(): string
{
    return dirname(__DIR__, 2) . '/img/le-mag';
}

function blog_testimonial_image_url(string $filename): string
{
    return '/img/le-mag/' . rawurlencode($filename);
}

function blog_delete_testimonial_image_file(?string $filename): void
{
    if (!$filename) {
        return;
    }

    $path = blog_testimonial_image_dir() . '/' . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function blog_create_image_from_upload(string $tmpPath, string $mime): array
{
    return match ($mime) {
        'image/jpeg' => ['im' => imagecreatefromjpeg($tmpPath), 'ext' => 'jpg'],
        'image/png' => ['im' => imagecreatefrompng($tmpPath), 'ext' => 'png'],
        'image/webp' => ['im' => imagecreatefromwebp($tmpPath), 'ext' => 'webp'],
        default => ['im' => null, 'ext' => ''],
    };
}

function blog_save_optimized_webp(string $tmpPath, string $targetPath): bool
{
    $info = @getimagesize($tmpPath);
    if (!$info || empty($info['mime'])) {
        return false;
    }

    $result = blog_create_image_from_upload($tmpPath, (string)$info['mime']);
    $image = $result['im'];
    if (!$image) {
        return false;
    }

    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);
    $ok = imagewebp($image, $targetPath, 82);
    imagedestroy($image);
    return $ok;
}

blog_start_session();
$config = blog_config();
$authors = $config['authors'];
$error = '';
$success = '';
$editPost = null;
$linkedTestimonials = [];

try {
    $pdo = blog_pdo();
} catch (Throwable $e) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Le Mag indisponible</title></head>
    <body style="font-family:Arial,sans-serif;padding:1rem;background:#f8fafc;">
      <main style="max-width:720px;margin:2rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;">
        <h1>Admin Le Mag indisponible</h1>
        <p>Connexion à la base impossible. Vérifiez les paramètres de <code>blog-lib/config.php</code> (host, base, utilisateur, mot de passe).</p>
      </main>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!blog_try_login($username, $password)) {
        $error = 'Identifiants invalides.';
    } else {
        header('Location: /admin/le-mag/index.php');
        exit;
    }
}

if (!blog_is_admin()) {
    ?>
    <!DOCTYPE html>
    <html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Connexion Le Mag</title>
      <style>body{font-family:Arial;background:#f8fafc;padding:1rem}.box{max-width:420px;margin:2rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}input{width:100%;padding:.6rem;margin:.4rem 0;border:1px solid #d1d5db;border-radius:8px}.btn{background:#b42c2d;color:#fff;border:0;padding:.6rem .9rem;border-radius:999px;cursor:pointer}</style>
    </head><body><main class="box"><h1>Admin Le Mag</h1><?php if ($error): ?><p style="color:#b91c1c"><?= blog_h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="action" value="login"><label>Identifiant</label><input name="username" required><label>Mot de passe</label><input name="password" type="password" required><p><button class="btn" type="submit">Se connecter</button></p></form></main></body></html>
    <?php
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_category') {
    $id = blog_slugify((string)($_POST['id'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    if ($id && $name) {
        $stmt = $pdo->prepare("INSERT INTO blog_categories (id,name,description,sort_order) VALUES (:id,:name,:description,99) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)");
        $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
        $success = 'Catégorie enregistrée.';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_testimonial') {
    $id = (int)($_POST['testimonial_id'] ?? 0);
    $data = [
        'quote_text' => trim((string)($_POST['quote_text'] ?? '')),
        'person_name' => trim((string)($_POST['person_name'] ?? '')),
        'person_role' => trim((string)($_POST['person_role'] ?? '')),
        'area_label' => trim((string)($_POST['area_label'] ?? '')),
        'status' => ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft',
        'consent_publication' => isset($_POST['consent_publication']) ? 1 : 0,
    ];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE blog_testimonials SET quote_text=:quote_text, person_name=:person_name, person_role=:person_role, area_label=:area_label, status=:status, consent_publication=:consent_publication, published_at=IF(:status='published', NOW(), published_at), updated_at=NOW() WHERE id=:id");
        $stmt->execute($data + ['id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO blog_testimonials (quote_text, person_name, person_role, area_label, status, consent_publication, published_at, created_at, updated_at) VALUES (:quote_text,:person_name,:person_role,:area_label,:status,:consent_publication,IF(:status='published',NOW(),NULL),NOW(),NOW())");
        $stmt->execute($data);
    }
    $success = 'Témoignage enregistré.';
}

if (isset($_GET['delete_post'])) {
    $id = (int)$_GET['delete_post'];
    $postStmt = $pdo->prepare('SELECT testimonial_image_path FROM blog_posts WHERE id=:id LIMIT 1');
    $postStmt->execute(['id' => $id]);
    $postToDelete = $postStmt->fetch();
    blog_delete_testimonial_image_file($postToDelete['testimonial_image_path'] ?? null);
    $pdo->prepare('DELETE FROM blog_post_testimonials WHERE post_id=:id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM blog_posts WHERE id=:id')->execute(['id' => $id]);
    $success = 'Article supprimé.';
}

if (isset($_GET['delete_testimonial'])) {
    $id = (int)$_GET['delete_testimonial'];
    $pdo->prepare('DELETE FROM blog_post_testimonials WHERE testimonial_id=:id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM blog_testimonials WHERE id=:id')->execute(['id' => $id]);
    $success = 'Témoignage supprimé.';
}

$categories = blog_fetch_categories();
$categoryIds = array_column($categories, 'id');

if (isset($_POST['action']) && $_POST['action'] === 'save_post') {
    $categoryId = trim((string)($_POST['category_id'] ?? ''));
    if (!in_array($categoryId, $categoryIds, true)) {
        $error = 'Catégorie invalide : veuillez choisir une catégorie disponible.';
    }
}

if ($error === '' && isset($_POST['action']) && $_POST['action'] === 'save_post') {
    $id = (int)($_POST['post_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $slug = blog_slugify((string)($_POST['slug'] ?? $title));
    $excerpt = trim((string)($_POST['excerpt'] ?? ''));
    $content = (string)($_POST['content_html'] ?? '');
    $categoryId = trim((string)($_POST['category_id'] ?? ''));
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $author = in_array($_POST['author_name'] ?? '', $authors, true) ? $_POST['author_name'] : $authors[0];
    $seoTitle = trim((string)($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string)($_POST['seo_description'] ?? ''));
    $ctaVariant = ($_POST['cta_variant'] ?? 'contact') === 'visit' ? 'visit' : 'contact';
    $testimonialImageAlt = trim((string)($_POST['testimonial_image_alt'] ?? ''));
    $removeImage = isset($_POST['remove_testimonial_image']) ? 1 : 0;
    $existingImagePath = '';

    if ($id > 0) {
        $existingStmt = $pdo->prepare('SELECT testimonial_image_path FROM blog_posts WHERE id=:id LIMIT 1');
        $existingStmt->execute(['id' => $id]);
        $existingPost = $existingStmt->fetch();
        $existingImagePath = (string)($existingPost['testimonial_image_path'] ?? '');
    }

    if ($removeImage === 1) {
        $existingImagePath = '';
        $testimonialImageAlt = '';
    }

    $imageInput = $_FILES['testimonial_image'] ?? null;
    $hasNewUpload = is_array($imageInput) && (($imageInput['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $finalImagePath = $existingImagePath;
    $finalImageAlt = $testimonialImageAlt;

    if ($hasNewUpload) {
        $uploadError = (int)($imageInput['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $error = 'Le téléversement de l’image a échoué.';
        } else {
            $tmpPath = (string)($imageInput['tmp_name'] ?? '');
            $originalName = (string)($imageInput['name'] ?? '');
            $size = (int)($imageInput['size'] ?? 0);

            if ($size > 5 * 1024 * 1024) {
                $error = 'Image trop lourde : 5 Mo maximum.';
            }

            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $error = 'Format invalide : JPG, JPEG, PNG ou WEBP uniquement.';
            }

            if ($testimonialImageAlt === '') {
                $error = 'Le texte alternatif est obligatoire pour l’image.';
            }

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($baseName)) ?: 'image';
            $targetName = $safeBase . '.webp';
            $dir = blog_testimonial_image_dir();
            $targetPath = $dir . '/' . $targetName;

            if (is_file($targetPath)) {
                $error = 'Le nom de fichier existe déjà. Merci de renommer votre image avant envoi.';
            }

            if ($error === '') {
                if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                    $error = 'Impossible de créer le dossier /img/le-mag/.';
                } elseif (!blog_save_optimized_webp($tmpPath, $targetPath)) {
                    $error = 'Impossible d’optimiser l’image (GD).';
                } else {
                    blog_delete_testimonial_image_file($existingImagePath);
                    $finalImagePath = $targetName;
                    $finalImageAlt = $testimonialImageAlt;
                }
            }
        }
    } elseif ($finalImagePath !== '' && $testimonialImageAlt === '') {
        $error = 'Le texte alternatif est obligatoire si une image est associée.';
    }

    if ($error !== '') {
        $editPost = $editPost ?? [];
        $editPost['title'] = $title;
        $editPost['slug'] = $slug;
        $editPost['excerpt'] = $excerpt;
        $editPost['content_html'] = $content;
        $editPost['category_id'] = $categoryId;
        $editPost['status'] = $status;
        $editPost['author_name'] = $author;
        $editPost['seo_title'] = $seoTitle;
        $editPost['seo_description'] = $seoDescription;
        $editPost['cta_variant'] = $ctaVariant;
        $editPost['testimonial_image_path'] = $finalImagePath;
        $editPost['testimonial_image_alt'] = $finalImageAlt;
    } else {
        $payload = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content_html' => $content,
            'category_id' => $categoryId,
            'status' => $status,
            'author_name' => $author,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'cta_variant' => $ctaVariant,
            'testimonial_image_path' => $finalImagePath !== '' ? $finalImagePath : null,
            'testimonial_image_alt' => $finalImagePath !== '' ? $finalImageAlt : null,
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE blog_posts SET title=:title, slug=:slug, excerpt=:excerpt, content_html=:content_html, category_id=:category_id, status=:status, author_name=:author_name, seo_title=:seo_title, seo_description=:seo_description, cta_variant=:cta_variant, testimonial_image_path=:testimonial_image_path, testimonial_image_alt=:testimonial_image_alt, published_at=IF(:status='published' AND published_at IS NULL, NOW(), published_at), updated_at=NOW() WHERE id=:id");
            $stmt->execute($payload + ['id' => $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content_html, category_id, status, author_name, seo_title, seo_description, cta_variant, testimonial_image_path, testimonial_image_alt, published_at, created_at, updated_at) VALUES (:title,:slug,:excerpt,:content_html,:category_id,:status,:author_name,:seo_title,:seo_description,:cta_variant,:testimonial_image_path,:testimonial_image_alt,IF(:status='published',NOW(),NULL),NOW(),NOW())");
            $stmt->execute($payload);
            $id = (int)$pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM blog_post_testimonials WHERE post_id=:id')->execute(['id' => $id]);
        $selected = $_POST['testimonial_ids'] ?? [];
        if (is_array($selected)) {
            $insertRel = $pdo->prepare('INSERT INTO blog_post_testimonials (post_id, testimonial_id, position) VALUES (:post_id, :testimonial_id, :position)');
            $position = 1;
            foreach ($selected as $tidRaw) {
                $tid = (int)$tidRaw;
                if ($tid > 0) {
                    $insertRel->execute(['post_id' => $id, 'testimonial_id' => $tid, 'position' => $position]);
                    $position++;
                }
            }
        }

        $success = 'Article enregistré.';
    }
}

$posts = $pdo->query("SELECT p.id,p.title,p.status,p.updated_at,p.slug,p.author_name,c.name category_name FROM blog_posts p JOIN blog_categories c ON c.id=p.category_id ORDER BY p.updated_at DESC")->fetchAll();
$testimonials = $pdo->query("SELECT id,quote_text,person_name,status,consent_publication FROM blog_testimonials ORDER BY updated_at DESC")->fetchAll();
if (isset($_GET['edit_post'])) {
    $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => (int)$_GET['edit_post']]);
    $editPost = $stmt->fetch();
    if ($editPost) {
        $rel = $pdo->prepare('SELECT testimonial_id FROM blog_post_testimonials WHERE post_id=:post_id ORDER BY position ASC');
        $rel->execute(['post_id' => $editPost['id']]);
        $linkedTestimonials = array_map('intval', array_column($rel->fetchAll(), 'testimonial_id'));
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Le Mag</title>
  <style>
    body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:1rem;color:#111827}
    .wrap{max-width:1200px;margin:0 auto}
    .top{display:flex;justify-content:space-between;align-items:center;gap:1rem;background:#fff;padding:1rem;border:1px solid #e5e7eb;border-radius:12px}
    .grid{display:grid;grid-template-columns:1.2fr 1fr;gap:1rem;margin-top:1rem}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    .stack{display:flex;flex-direction:column;gap:.55rem}
    input,textarea,select{width:100%;padding:.55rem;border:1px solid #d1d5db;border-radius:8px}
    table{width:100%;border-collapse:collapse}th,td{padding:.5rem;border-bottom:1px solid #e5e7eb;text-align:left;font-size:.92rem}
    .btn{background:#b42c2d;color:#fff;text-decoration:none;border:0;border-radius:999px;padding:.5rem .9rem;cursor:pointer;display:inline-block}
    .btn.alt{background:#fff;color:#b42c2d;border:1px solid #b42c2d}
    .testi-tools{display:flex;gap:.55rem;align-items:center}
    .testi-search{flex:1}
    .testi-results{border:1px solid #d1d5db;border-radius:8px;max-height:180px;overflow:auto;background:#fff}
    .testi-row{display:flex;justify-content:space-between;align-items:center;gap:.6rem;padding:.5rem .65rem;border-bottom:1px solid #eef2f7}
    .testi-row:last-child{border-bottom:0}
    .testi-row button{border:1px solid #b42c2d;background:#fff;color:#b42c2d;border-radius:999px;padding:.2rem .6rem;cursor:pointer}
    .testi-row button.active{background:#b42c2d;color:#fff}
    .testi-selected{display:flex;flex-wrap:wrap;gap:.45rem}
    .testi-tag{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .5rem;border-radius:999px;background:#f3f4f6;border:1px solid #d1d5db;font-size:.85rem}
    .testi-tag button{border:0;background:none;color:#b42c2d;font-weight:700;cursor:pointer;padding:0}
    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .helper{font-size:.85rem;color:#6b7280}
    .meta{font-size:.86rem;color:#6b7280}
    @media (max-width: 980px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div><h1>Back-office Le Mag</h1><p class="meta">Articles, catégories et témoignages.</p></div>
    <div>
      <a class="btn alt" href="/le-mag/" target="_blank" rel="noopener">Voir Le Mag</a>
      <a class="btn" href="/admin/le-mag/logout.php">Se déconnecter</a>
    </div>
  </header>

  <?php if ($success): ?><p style="color:#166534"><?= blog_h($success) ?></p><?php endif; ?>
  <?php if ($error): ?><p style="color:#b91c1c"><?= blog_h($error) ?></p><?php endif; ?>

  <main class="grid">
    <section class="card">
      <h2>Articles</h2>
      <table>
        <thead><tr><th>Titre</th><th>Catégorie</th><th>Statut</th><th>Auteur</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td><?= blog_h($p['title']) ?></td>
            <td><?= blog_h($p['category_name']) ?></td>
            <td><?= blog_h($p['status']) ?></td>
            <td><?= blog_h($p['author_name']) ?></td>
            <td>
              <a href="?edit_post=<?= (int)$p['id'] ?>">Modifier</a> |
              <a href="?delete_post=<?= (int)$p['id'] ?>" onclick="return confirm('Supprimer cet article ?')">Supprimer</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h3 style="margin-top:1rem"><?= $editPost ? 'Modifier article' : 'Créer un article' ?></h3>
      <form method="post" class="stack" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_post">
        <input type="hidden" name="post_id" value="<?= (int)($editPost['id'] ?? 0) ?>">
        <label>Titre</label><input name="title" required value="<?= blog_h((string)($editPost['title'] ?? '')) ?>">
        <label>Slug URL</label><input name="slug" value="<?= blog_h((string)($editPost['slug'] ?? '')) ?>">
        <label>Extrait</label><textarea name="excerpt" rows="2" required><?= blog_h((string)($editPost['excerpt'] ?? '')) ?></textarea>
        <label>Catégorie</label>
        <select name="category_id" required>
          <?php foreach ($categories as $c): ?>
            <option value="<?= blog_h($c['id']) ?>" <?= (($editPost['category_id'] ?? '') === $c['id']) ? 'selected' : '' ?>><?= blog_h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Auteur</label>
        <select name="author_name" required>
          <?php foreach ($authors as $author): ?>
            <option value="<?= blog_h($author) ?>" <?= (($editPost['author_name'] ?? '') === $author) ? 'selected' : '' ?>><?= blog_h($author) ?></option>
          <?php endforeach; ?>
        </select>
        <label>Statut</label>
        <select name="status"><option value="draft" <?= (($editPost['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Brouillon</option><option value="published" <?= (($editPost['status'] ?? '') === 'published') ? 'selected' : '' ?>>Publié</option></select>
        <label>Type de CTA</label>
        <select name="cta_variant"><option value="contact" <?= (($editPost['cta_variant'] ?? '') === 'contact') ? 'selected' : '' ?>>Prendre contact</option><option value="visit" <?= (($editPost['cta_variant'] ?? '') === 'visit') ? 'selected' : '' ?>>Demander une visite</option></select>
        <label>Titre SEO</label><input name="seo_title" value="<?= blog_h((string)($editPost['seo_title'] ?? '')) ?>">
        <label>Meta description</label><textarea name="seo_description" rows="2"><?= blog_h((string)($editPost['seo_description'] ?? '')) ?></textarea>
        <label>Contenu de l'article (HTML simple)</label><textarea name="content_html" rows="10" required><?= blog_h((string)($editPost['content_html'] ?? '')) ?></textarea>

        <label>Image au-dessus du bloc Témoignage (horizontal)</label>
        <input type="file" name="testimonial_image" accept=".jpg,.jpeg,.png,.webp" id="testimonial-image-input">
        <p class="helper">Formats autorisés : JPG, JPEG, PNG, WEBP. Taille max 5 Mo. Nom recommandé pour le SEO : mots-clés-en-minuscules.webp</p>
        <?php if (!empty($editPost['testimonial_image_path'])): ?>
          <img
            id="testimonial-image-preview"
            src="<?= blog_h(blog_testimonial_image_url((string)$editPost['testimonial_image_path'])) ?>"
            alt="<?= blog_h((string)($editPost['testimonial_image_alt'] ?? '')) ?>"
            style="max-width:100%;height:140px;object-fit:cover;border-radius:10px;border:1px solid #d1d5db"
          >
          <label><input type="checkbox" name="remove_testimonial_image" value="1"> Supprimer l'image actuelle</label>
        <?php else: ?>
          <img id="testimonial-image-preview" src="" alt="" style="display:none;max-width:100%;height:140px;object-fit:cover;border-radius:10px;border:1px solid #d1d5db">
        <?php endif; ?>

        <label>Texte alternatif de l’image (obligatoire si image)</label>
        <input
          name="testimonial_image_alt"
          value="<?= blog_h((string)($editPost['testimonial_image_alt'] ?? '')) ?>"
          placeholder="Ex : Résidents échangeant dans le salon de la colocation senior"
        >

        <label>Témoignage(s) lié(s)</label>
        <div class="testi-tools">
          <input type="search" id="testimonial-search" class="testi-search" placeholder="Rechercher par nom ou ID...">
          <button class="btn alt" type="button" id="clear-testimonials">Tout désélectionner</button>
        </div>
        <p class="helper">Sélectionnez un ou plusieurs témoignages. Laissez vide si vous ne souhaitez en lier aucun.</p>
        <div class="testi-selected" id="testimonial-selected"></div>
        <div class="testi-results" id="testimonial-results">
          <?php foreach ($testimonials as $t): ?>
            <div
              class="testi-row"
              data-testid="<?= (int)$t['id'] ?>"
              data-testi-label="<?= blog_h(mb_strtolower((string)$t['person_name'])) ?>"
            >
              <span>#<?= (int)$t['id'] ?> - <?= blog_h($t['person_name']) ?> (<?= blog_h($t['status']) ?>)</span>
              <button type="button" data-toggle-testimonial="<?= (int)$t['id'] ?>" class="<?= in_array((int)$t['id'], $linkedTestimonials, true) ? 'active' : '' ?>">
                <?= in_array((int)$t['id'], $linkedTestimonials, true) ? 'Sélectionné' : 'Sélectionner' ?>
              </button>
              <input
                type="checkbox"
                name="testimonial_ids[]"
                value="<?= (int)$t['id'] ?>"
                class="sr-only testimonial-input"
                data-input-testimonial="<?= (int)$t['id'] ?>"
                <?= in_array((int)$t['id'], $linkedTestimonials, true) ? 'checked' : '' ?>
              >
            </div>
          <?php endforeach; ?>
          <?php if (empty($testimonials)): ?>
            <p class="helper">Aucun témoignage disponible.</p>
          <?php endif; ?>
        </div>
        <button class="btn" type="submit">Enregistrer l'article</button>
      </form>
    </section>

    <section class="card stack">
      <h2>Catégories</h2>
      <form method="post" class="stack">
        <input type="hidden" name="action" value="save_category">
        <label>ID (slug)</label><input name="id" required placeholder="conseils-aux-familles">
        <label>Nom</label><input name="name" required>
        <label>Description</label><textarea name="description" rows="2"></textarea>
        <button class="btn" type="submit">Enregistrer catégorie</button>
      </form>

      <h2>Témoignage</h2>
      <form method="post" class="stack">
        <input type="hidden" name="action" value="save_testimonial">
        <input type="hidden" name="testimonial_id" value="0">
        <label>Texte du témoignage</label><textarea name="quote_text" rows="4" required></textarea>
        <label>Nom affiché (anonyme ou prénom)</label><input name="person_name" required>
        <label>Rôle (ex: fille d'une résidente)</label><input name="person_role">
        <label>Zone (ex: Loire)</label><input name="area_label">
        <label>Statut</label><select name="status"><option value="draft">Brouillon</option><option value="published">Publié</option></select>
        <label><input type="checkbox" name="consent_publication" value="1"> Autorisation de diffusion</label>
        <button class="btn" type="submit">Enregistrer témoignage</button>
      </form>

      <table>
        <thead><tr><th>Nom</th><th>Statut</th><th>Autorisation</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($testimonials as $t): ?>
            <tr><td><?= blog_h($t['person_name']) ?></td><td><?= blog_h($t['status']) ?></td><td><?= (int)$t['consent_publication'] === 1 ? 'Oui' : 'Non' ?></td><td><a href="?delete_testimonial=<?= (int)$t['id'] ?>" onclick="return confirm('Supprimer ce témoignage ?')">Supprimer</a></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var clearButton = document.getElementById('clear-testimonials');
    var imageInput = document.getElementById('testimonial-image-input');
    var imagePreview = document.getElementById('testimonial-image-preview');
    var searchInput = document.getElementById('testimonial-search');
    var selectedBox = document.getElementById('testimonial-selected');
    var rows = Array.from(document.querySelectorAll('#testimonial-results .testi-row'));
    if (!clearButton || !selectedBox) return;

    function setButtonState(id, checked) {
      var btn = document.querySelector('[data-toggle-testimonial="' + id + '"]');
      if (!btn) return;
      btn.classList.toggle('active', checked);
      btn.textContent = checked ? 'Sélectionné' : 'Sélectionner';
    }

    function renderSelected() {
      var checkedInputs = Array.from(document.querySelectorAll('.testimonial-input:checked'));
      selectedBox.innerHTML = '';
      if (checkedInputs.length === 0) {
        selectedBox.innerHTML = '<span class="helper">Aucun témoignage sélectionné.</span>';
        return;
      }

      checkedInputs.forEach(function (input) {
        var id = input.getAttribute('data-input-testimonial');
        var row = document.querySelector('.testi-row[data-testid="' + id + '"]');
        if (!row) return;
        var label = row.querySelector('span')?.textContent || ('#' + id);
        var tag = document.createElement('span');
        tag.className = 'testi-tag';
        tag.innerHTML = '<span>' + label + '</span><button type="button" data-remove-testimonial="' + id + '" aria-label="Retirer le témoignage">×</button>';
        selectedBox.appendChild(tag);
      });
    }

    rows.forEach(function (row) {
      var id = row.getAttribute('data-testid');
      var toggleButton = row.querySelector('[data-toggle-testimonial]');
      var input = row.querySelector('.testimonial-input');
      if (!id || !toggleButton || !input) return;
      toggleButton.addEventListener('click', function () {
        input.checked = !input.checked;
        setButtonState(id, input.checked);
        renderSelected();
      });
    });

    clearButton.addEventListener('click', function () {
      document.querySelectorAll('.testimonial-input').forEach(function (item) {
        item.checked = false;
        var id = item.getAttribute('data-input-testimonial');
        if (id) setButtonState(id, false);
      });
      renderSelected();
    });

    selectedBox.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof HTMLElement)) return;
      var id = target.getAttribute('data-remove-testimonial');
      if (!id) return;
      var input = document.querySelector('.testimonial-input[data-input-testimonial="' + id + '"]');
      if (!input) return;
      input.checked = false;
      setButtonState(id, false);
      renderSelected();
    });

    searchInput?.addEventListener('input', function () {
      var term = (searchInput.value || '').toLowerCase().trim();
      rows.forEach(function (row) {
        var label = row.getAttribute('data-testi-label') || '';
        var id = row.getAttribute('data-testid') || '';
        var visible = term === '' || label.indexOf(term) !== -1 || id.indexOf(term) !== -1;
        row.style.display = visible ? '' : 'none';
      });
    });

    imageInput?.addEventListener('change', function () {
      var file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
      if (!file || !imagePreview) return;
      imagePreview.src = URL.createObjectURL(file);
      imagePreview.style.display = 'block';
    });

    renderSelected();
  });
</script>
</body>
</html>
