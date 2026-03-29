<?php
require_once __DIR__ . '/../../blog-lib/auth.php';
require_once __DIR__ . '/../../blog-lib/utils.php';

blog_start_session();
$config = blog_config();
$authors = $config['authors'];
$error = '';
$success = '';

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
    ];

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE blog_posts SET title=:title, slug=:slug, excerpt=:excerpt, content_html=:content_html, category_id=:category_id, status=:status, author_name=:author_name, seo_title=:seo_title, seo_description=:seo_description, cta_variant=:cta_variant, published_at=IF(:status='published' AND published_at IS NULL, NOW(), published_at), updated_at=NOW() WHERE id=:id");
        $stmt->execute($payload + ['id' => $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content_html, category_id, status, author_name, seo_title, seo_description, cta_variant, published_at, created_at, updated_at) VALUES (:title,:slug,:excerpt,:content_html,:category_id,:status,:author_name,:seo_title,:seo_description,:cta_variant,IF(:status='published',NOW(),NULL),NOW(),NOW())");
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

$posts = $pdo->query("SELECT p.id,p.title,p.status,p.updated_at,p.slug,p.author_name,c.name category_name FROM blog_posts p JOIN blog_categories c ON c.id=p.category_id ORDER BY p.updated_at DESC")->fetchAll();
$testimonials = $pdo->query("SELECT id,quote_text,person_name,status,consent_publication FROM blog_testimonials ORDER BY updated_at DESC")->fetchAll();
$editPost = null;
$linkedTestimonials = [];
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
    .checklist{border:1px solid #d1d5db;border-radius:8px;padding:.6rem;max-height:180px;overflow:auto;display:flex;flex-direction:column;gap:.35rem}
    .check-item{display:flex;align-items:flex-start;gap:.45rem;font-size:.92rem}
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
      <form method="post" class="stack">
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
        <label>Témoignage(s) lié(s)</label>
        <div class="checklist" id="testimonial-checklist">
          <?php foreach ($testimonials as $t): ?>
            <label class="check-item">
              <input
                type="checkbox"
                name="testimonial_ids[]"
                value="<?= (int)$t['id'] ?>"
                <?= in_array((int)$t['id'], $linkedTestimonials, true) ? 'checked' : '' ?>
              >
              <span>#<?= (int)$t['id'] ?> - <?= blog_h($t['person_name']) ?> (<?= blog_h($t['status']) ?>)</span>
            </label>
          <?php endforeach; ?>
          <?php if (empty($testimonials)): ?>
            <p class="helper">Aucun témoignage disponible.</p>
          <?php endif; ?>
        </div>
        <div>
          <button class="btn alt" type="button" id="clear-testimonials">Tout désélectionner</button>
          <p class="helper">Laissez tout décoché si vous ne souhaitez lier aucun témoignage.</p>
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
    if (!clearButton) return;

    clearButton.addEventListener('click', function () {
      var checks = document.querySelectorAll('#testimonial-checklist input[type="checkbox"]');
      checks.forEach(function (item) { item.checked = false; });
    });
  });
</script>
</body>
</html>
