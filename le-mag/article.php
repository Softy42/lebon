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
  <script type="module" src="/main.js"></script>
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
<div class="side-color left"></div>
<div class="side-color right"></div>
<div class="content-wrapper">
<header class="fixed-header">
  <nav aria-label="Navigation principale">
    <div class="nav-content">
      <a href="/" class="logo" aria-label="Accueil MAISON MÉLINA">
        <img src="/img/divers/logo.jpg" alt="Logo MAISON MÉLINA" class="logo-image" width="160" height="90" loading="eager">
      </a>
      <button class="hamburger-menu" aria-label="Menu principal" aria-expanded="false" aria-controls="main-nav">
        <span class="hamburger-box"><span class="hamburger-inner"></span></span>
      </button>
      <ul id="main-nav" class="nav-links">
        <li><a href="/"><span class="nav-text">Accueil</span></a></li>
        <li><a href="/concept"><span class="nav-text">Le Concept</span></a></li>
        <li><a href="/nos-maisons"><span class="nav-text">Nos Maisons</span></a></li>
        <li><a href="/qui-sommes-nous"><span class="nav-text">Qui Sommes-Nous ?</span></a></li>
        <li><a href="/presse"><span class="nav-text">On parle de nous !</span></a></li>
        <li class="has-submenu">
          <button class="submenu-toggle" type="button" aria-expanded="false" aria-haspopup="true" aria-controls="submenu-creer-votre-maison">
            <span class="nav-text two-lines"><span class="line">Créer ou Investir</span><span class="line">dans une maison partagée</span></span>
            <span class="submenu-indicator" aria-hidden="true"></span>
          </button>
          <ul id="submenu-creer-votre-maison" class="submenu">
            <li><a href="/creer-votre-maison">Créer votre maison partagée</a></li>
            <li><a href="/investir-dans-une-fonciere">Investissez dans la "Foncière Mélina"</a></li>
          </ul>
        </li>
        <li><a href="/contact"><span class="nav-text">Contact</span></a></li>
      </ul>
    </div>
  </nav>
</header>
<div class="main-content">
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
</div>
</div>
<script type="module">
  document.addEventListener("DOMContentLoaded", () => {
    const hamburger = document.querySelector(".hamburger-menu");
    const navLinks = document.querySelector(".nav-links");
    const body = document.body;
    const closeSubmenus = () => {
      if (!navLinks) return;
      navLinks.querySelectorAll(".has-submenu").forEach((item) => item.classList.remove("open"));
      navLinks.querySelectorAll(".submenu-toggle").forEach((toggle) => toggle.setAttribute("aria-expanded", "false"));
    };
    const toggleMenu = () => {
      if (!hamburger || !navLinks) return;
      const isExpanded = hamburger.getAttribute("aria-expanded") === "true";
      hamburger.setAttribute("aria-expanded", (!isExpanded).toString());
      hamburger.classList.toggle("is-active");
      navLinks.classList.toggle("is-active");
      body.classList.toggle("menu-open");
      if (isExpanded) closeSubmenus();
    };
    const closeMenu = () => {
      if (hamburger) {
        hamburger.setAttribute("aria-expanded", "false");
        hamburger.classList.remove("is-active");
      }
      if (navLinks) navLinks.classList.remove("is-active");
      body.classList.remove("menu-open");
      closeSubmenus();
    };
    hamburger?.addEventListener("click", (event) => {
      event.stopPropagation();
      toggleMenu();
    });
    navLinks?.addEventListener("click", (event) => {
      const target = event.target;
      if (target instanceof HTMLAnchorElement) closeMenu();
    });
    document.addEventListener("click", (event) => {
      const target = event.target;
      const clickInsideNav = navLinks ? navLinks.contains(target) : false;
      const clickHamburger = hamburger ? hamburger.contains(target) : false;
      if (!clickInsideNav) closeSubmenus();
      if (navLinks && navLinks.classList.contains("is-active") && !clickInsideNav && !clickHamburger) closeMenu();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        if (navLinks && navLinks.classList.contains("is-active")) closeMenu();
        else closeSubmenus();
      }
    });
    const submenuToggles = navLinks ? Array.from(navLinks.querySelectorAll(".submenu-toggle")) : [];
    const collapseOthers = (current) => {
      submenuToggles.forEach((toggle) => {
        if (toggle !== current) {
          toggle.setAttribute("aria-expanded", "false");
          const otherParent = toggle.closest(".has-submenu");
          if (otherParent) otherParent.classList.remove("open");
        }
      });
    };
    submenuToggles.forEach((toggle) => {
      toggle.addEventListener("click", (event) => {
        event.stopPropagation();
        const parent = toggle.closest(".has-submenu");
        if (!parent) return;
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        if (expanded) {
          toggle.setAttribute("aria-expanded", "false");
          parent.classList.remove("open");
        } else {
          collapseOthers(toggle);
          toggle.setAttribute("aria-expanded", "true");
          parent.classList.add("open");
        }
      });
    });
  });
</script>
</body>
</html>
