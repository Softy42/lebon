<?php
require_once __DIR__ . '/../blog-lib/utils.php';

try {
    $pdo = blog_pdo();
    $categories = blog_fetch_categories();
    $category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';

    $sql = "
    SELECT p.id, p.title, p.slug, p.excerpt, p.author_name, p.published_at, p.cta_variant, c.name AS category_name
    FROM blog_posts p
    JOIN blog_categories c ON c.id = p.category_id
    WHERE p.status = 'published'
    ";
    $params = [];
    if ($category !== '') {
        $sql .= " AND c.id = :category";
        $params['category'] = $category;
    }
    $sql .= ' ORDER BY p.published_at DESC, p.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    $tStmt = $pdo->query("SELECT quote_text, person_name, person_role, area_label FROM blog_testimonials WHERE status='published' AND consent_publication=1 ORDER BY published_at DESC, created_at DESC LIMIT 3");
    $topTestimonials = $tStmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(503);
    $contactUrl = (require __DIR__ . '/../blog-lib/config.php')['contact_url'] ?? '/contact';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Le Mag indisponible</title>
      <style>body{font-family:Arial,sans-serif;background:#f8fafc;padding:1rem}.box{max-width:720px;margin:2rem auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}.btn{display:inline-block;background:#b42c2d;color:#fff;border-radius:999px;padding:.7rem 1rem;text-decoration:none;font-weight:700}</style>
    </head>
    <body>
      <main class="box">
        <h1>Le Mag est temporairement indisponible</h1>
        <p>Le service blog n'est pas encore connecté à la base de données sur cet environnement.</p>
        <p><a class="btn" href="<?= blog_h($contactUrl) ?>">Prendre contact</a></p>
      </main>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Le Mag Maison Mélina</title>
  <meta name="description" content="Conseils, repères et témoignages pour accompagner vos proches en colocation senior.">
  <link rel="canonical" href="https://www.maison-melina.fr/le-mag/">
  <link rel="stylesheet" href="/_astro/index.BVHY39ld.css">
  <script type="module" src="/main.js"></script>
  <script type="module" src="/le-mag/menu.js"></script>
  <style>
    .mag-wrap{max-width:1120px;margin:0 auto;padding:1.25rem}
    .hero{background:#b42c2d;color:#fff;border-radius:14px;padding:1.25rem;margin:1rem 0 1.25rem}
    .filters{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
    .chip{display:inline-block;padding:.45rem .8rem;border-radius:999px;border:1px solid #d1d5db;text-decoration:none;color:#111827;background:#fff}
    .chip.active{background:#b42c2d;color:#fff;border-color:#b42c2d}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;display:flex;flex-direction:column;gap:.55rem}
    .meta{font-size:.9rem;color:#6b7280}
    .cat{font-size:.82rem;text-transform:uppercase;color:#0f766e;font-weight:700}
    .cta{display:inline-block;margin-top:.35rem;color:#b42c2d;font-weight:700;text-decoration:none}
    .section{margin-top:1.5rem;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    .testimonial{border-left:3px solid #b42c2d;padding-left:.75rem;margin-bottom:1rem}
    .btn{display:inline-block;background:#b42c2d;color:#fff;border-radius:999px;padding:.7rem 1rem;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
<div class="side-color left"></div>
<div class="side-color right"></div>
<div class="content-wrapper">
<?php require __DIR__ . '/partials/header.php'; ?>
<div class="main-content">
<main class="mag-wrap">
  <section class="hero">
    <p>Le Mag Maison Mélina</p>
    <h1>Des conseils humains pour bien accompagner vos proches</h1>
    <p>Des contenus utiles, simples et rassurants autour de la colocation senior.</p>
  </section>

  <nav class="filters" aria-label="Filtrer les catégories">
    <a class="chip <?= $category === '' ? 'active' : '' ?>" href="/le-mag/">Tous les sujets</a>
    <?php foreach ($categories as $c): ?>
      <a class="chip <?= $category === $c['id'] ? 'active' : '' ?>" href="/le-mag/?category=<?= blog_h($c['id']) ?>"><?= blog_h($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>

  <section class="grid">
    <?php if (empty($posts)): ?>
      <article class="card"><p>Aucun article publié pour le moment.</p></article>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
      <article class="card">
        <span class="cat"><?= blog_h($post['category_name']) ?></span>
        <h2><?= blog_h($post['title']) ?></h2>
        <p><?= blog_h($post['excerpt']) ?></p>
        <p class="meta"><?= blog_h(date('d/m/Y', strtotime((string)$post['published_at']))) ?> · <?= blog_h($post['author_name']) ?></p>
        <a class="cta" href="/le-mag/article.php?slug=<?= blog_h($post['slug']) ?>">Lire l'article</a>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="section">
    <h2>Témoignage</h2>
    <?php foreach ($topTestimonials as $item): ?>
      <article class="testimonial">
        <p>“<?= blog_h($item['quote_text']) ?>”</p>
        <p class="meta">— <?= blog_h($item['person_name']) ?><?= $item['person_role'] ? ', ' . blog_h($item['person_role']) : '' ?><?= $item['area_label'] ? ' · ' . blog_h($item['area_label']) : '' ?></p>
      </article>
    <?php endforeach; ?>
    <a class="cta" href="/le-mag/temoignage.php">Voir tous les témoignages</a>
  </section>

  <section class="section" style="text-align:center">
    <h2>Besoin d'un échange personnalisé ?</h2>
    <p>Notre équipe vous répond avec bienveillance.</p>
    <a class="btn" href="<?= blog_h(blog_config()['contact_url']) ?>">Prendre contact</a>
  </section>
</main>
</div>
</div>
</body>
</html>
