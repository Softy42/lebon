<?php
require_once __DIR__ . '/../blog-lib/utils.php';

try {
    $stmt = blog_pdo()->query("SELECT quote_text, person_name, person_role, area_label, published_at FROM blog_testimonials WHERE status='published' AND consent_publication=1 ORDER BY published_at DESC, created_at DESC");
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(503);
    echo 'La page témoignage est temporairement indisponible.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Témoignage | Le Mag Maison Mélina</title>
  <meta name="description" content="Découvrez les témoignages de familles autour des maisons partagées Maison Mélina.">
  <link rel="canonical" href="https://www.maison-melina.fr/le-mag/temoignage.php">
  <link rel="stylesheet" href="/_astro/index.BVHY39ld.css">
  <style>
    .wrap{max-width:920px;margin:0 auto;padding:1.25rem}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;margin:0 0 1rem}
    .meta{font-size:.9rem;color:#6b7280}
    .btn{display:inline-block;background:#b42c2d;color:#fff;border-radius:999px;padding:.7rem 1rem;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
<main class="wrap">
  <nav><a href="/">Accueil</a> / <a href="/le-mag/">Le Mag</a> / <span>Témoignage</span></nav>
  <h1>Témoignage</h1>
  <p>Des retours concrets pour aider les familles à se projeter sereinement.</p>

  <?php if (!$rows): ?>
    <article class="item"><p>Aucun témoignage publié pour le moment.</p></article>
  <?php endif; ?>

  <?php foreach ($rows as $row): ?>
    <article class="item">
      <p>“<?= blog_h($row['quote_text']) ?>”</p>
      <p class="meta">— <?= blog_h($row['person_name']) ?><?= $row['person_role'] ? ', ' . blog_h($row['person_role']) : '' ?><?= $row['area_label'] ? ' · ' . blog_h($row['area_label']) : '' ?> · <?= blog_h(date('d/m/Y', strtotime((string)$row['published_at']))) ?></p>
    </article>
  <?php endforeach; ?>

  <p><a class="btn" href="<?= blog_h(blog_config()['contact_url']) ?>">Prendre contact</a></p>
</main>
</body>
</html>
