<?php
require_once __DIR__ . '/../blog-lib/utils.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($slug === '') {
    http_response_code(404);
    echo 'Article introuvable';
    exit;
}

try {
    $pdo = blog_pdo();
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM blog_posts p JOIN blog_categories c ON c.id = p.category_id WHERE p.slug = :slug AND p.status='published' LIMIT 1");
    $stmt->execute(['slug' => $slug]);
    $post = $stmt->fetch();
    if (!$post) {
        http_response_code(404);
        echo 'Article introuvable';
        exit;
    }

    $relatedStmt = $pdo->prepare("SELECT slug, title FROM blog_posts WHERE status='published' AND category_id=:category AND id <> :id ORDER BY published_at DESC LIMIT 3");
    $relatedStmt->execute(['category' => $post['category_id'], 'id' => $post['id']]);
    $related = $relatedStmt->fetchAll();

    $testStmt = $pdo->prepare("SELECT t.quote_text, t.person_name, t.person_role, t.area_label
    FROM blog_testimonials t
    JOIN blog_post_testimonials pt ON pt.testimonial_id = t.id
    WHERE pt.post_id = :post_id AND t.status='published' AND t.consent_publication=1
    ORDER BY pt.position ASC, t.published_at DESC");
    $testStmt->execute(['post_id' => $post['id']]);
    $testimonials = $testStmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(503);
    echo 'Le blog n\'est pas encore connecté à la base de données.';
    exit;
}

$cta = blog_cta_data($post['cta_variant'] ?? 'contact');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= blog_h($post['seo_title'] ?: ($post['title'] . ' | Le Mag Maison Mélina')) ?></title>
  <meta name="description" content="<?= blog_h($post['seo_description'] ?: $post['excerpt']) ?>">
  <link rel="canonical" href="https://www.maison-melina.fr/le-mag/article.php?slug=<?= blog_h($post['slug']) ?>">
  <link rel="stylesheet" href="/_astro/index.BVHY39ld.css">
  <style>
    .wrap{max-width:900px;margin:0 auto;padding:1.25rem}
    .meta{color:#6b7280;font-size:.92rem}
    .content{line-height:1.75;color:#1f2937}
    .box{margin:1.3rem 0;padding:1rem;border-radius:12px;background:#fff;border:1px solid #e5e7eb}
    .cta{display:inline-block;background:#b42c2d;color:#fff;text-decoration:none;border-radius:999px;padding:.66rem 1rem;font-weight:700}
    .testimonial{border-left:3px solid #b42c2d;padding-left:.75rem;margin:.8rem 0}
  </style>
</head>
<body>
<main class="wrap">
  <nav><a href="/">Accueil</a> / <a href="/le-mag/">Le Mag</a> / <span><?= blog_h($post['title']) ?></span></nav>
  <article>
    <p><?= blog_h($post['category_name']) ?></p>
    <h1><?= blog_h($post['title']) ?></h1>
    <p class="meta"><?= blog_h(date('d/m/Y', strtotime((string)$post['published_at']))) ?> · <?= blog_h($post['author_name']) ?></p>
    <div class="content"><?= $post['content_html'] ?></div>
  </article>

  <?php if (!empty($testimonials)): ?>
    <section class="box">
      <h2>Témoignage</h2>
      <?php foreach ($testimonials as $t): ?>
        <article class="testimonial">
          <p>“<?= blog_h($t['quote_text']) ?>”</p>
          <p class="meta">— <?= blog_h($t['person_name']) ?><?= $t['person_role'] ? ', ' . blog_h($t['person_role']) : '' ?><?= $t['area_label'] ? ' · ' . blog_h($t['area_label']) : '' ?></p>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <section class="box">
    <h2>Vous souhaitez en parler ?</h2>
    <p>Notre équipe vous accompagne pas à pas.</p>
    <a class="cta" href="<?= blog_h($cta['url']) ?>"><?= blog_h($cta['label']) ?></a>
  </section>

  <?php if (!empty($related)): ?>
    <section class="box">
      <h2>Articles similaires</h2>
      <ul>
        <?php foreach ($related as $r): ?>
          <li><a href="/le-mag/article.php?slug=<?= blog_h($r['slug']) ?>"><?= blog_h($r['title']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
