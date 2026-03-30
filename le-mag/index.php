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
        $sql .= " AND c.id = ?";
        $params[] = $category;
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
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/_astro/benefices-economiques.DA2vvG5t.css">
  <link rel="stylesheet" href="/_astro/index.BVHY39ld.css">
  <script type="module" src="/main.js"></script>
  <style>
    .mag-wrap{max-width:1120px;margin:0 auto;padding:1.25rem}
    .hero{background:#b42c2d;color:#fff;border-radius:14px;padding:.7rem 1rem;margin:.65rem 0 .9rem;text-align:center}
    .hero p{margin:.35rem 0}
    .hero h1{margin:.35rem 0}
    .filters{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem}
    .chip{display:inline-block;padding:.45rem .8rem;border-radius:999px;border:1px solid #d1d5db;text-decoration:none;color:#111827;background:#fff}
    .chip.active{background:#b42c2d;color:#fff;border-color:#b42c2d}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;display:flex;flex-direction:column;gap:.3rem}
    .card h2,.card p,.card .cat,.card .cta{margin:0}
    .meta{font-size:.9rem;color:#6b7280}
    .cat{font-size:.82rem;text-transform:uppercase;color:#0f766e;font-weight:700}
    .cta{display:inline-block;margin-top:.35rem;color:#b42c2d;font-weight:700;text-decoration:none}
    .section{margin-top:1.5rem;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem}
    .testimonial{border-left:3px solid #b42c2d;padding-left:.75rem;margin-bottom:1rem}
    .btn{display:inline-block;background:#b42c2d;color:#fff;border-radius:999px;padding:.7rem 1rem;text-decoration:none;font-weight:700}
    .mag-pill{background:#b42c2d !important;color:#fff !important;border-radius:999px;padding:.42rem .85rem;font-weight:700}
    .mag-banner{width:min(1120px,calc(100% - 2.5rem));margin:.7rem auto 0;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:.75rem 1rem;display:flex;justify-content:space-between;align-items:center;gap:.75rem;box-sizing:border-box}
    .mag-banner a{display:inline-block;background:#b42c2d;color:#fff;text-decoration:none;border-radius:999px;padding:.5rem .85rem;font-weight:700}
    @media (max-width: 768px){.mag-banner{flex-direction:column;align-items:flex-start}}
  </style>
</head>
<body data-astro-cid-sckkx6r4>
<div class="side-color left" data-astro-cid-sckkx6r4></div>
<div class="side-color right" data-astro-cid-sckkx6r4></div>
<div class="content-wrapper" data-astro-cid-sckkx6r4>
<header class="fixed-header" data-astro-cid-sckkx6r4>
  <nav aria-label="Navigation principale" data-astro-cid-sckkx6r4>
    <div class="nav-content" data-astro-cid-sckkx6r4>
      <a href="/" class="logo" aria-label="Accueil MAISON MÉLINA" data-astro-cid-sckkx6r4>
        <img src="/img/divers/logo.jpg" alt="Logo MAISON MÉLINA" class="logo-image" width="160" height="90" loading="eager" data-astro-cid-sckkx6r4>
      </a>
      <button class="hamburger-menu" aria-label="Menu principal" aria-expanded="false" aria-controls="main-nav" data-astro-cid-sckkx6r4>
        <span class="hamburger-box" data-astro-cid-sckkx6r4>
          <span class="hamburger-inner" data-astro-cid-sckkx6r4></span>
        </span>
      </button>
      <ul id="main-nav" class="nav-links" data-astro-cid-sckkx6r4>
        <li data-astro-cid-sckkx6r4><a href="/" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>Accueil</span></a></li>
        <li data-astro-cid-sckkx6r4><a href="/concept" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>Le Concept</span></a></li>
        <li data-astro-cid-sckkx6r4><a href="/nos-maisons" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>Nos Maisons</span></a></li>
        <li data-astro-cid-sckkx6r4><a href="/qui-sommes-nous" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>Qui Sommes-Nous ?</span></a></li>
        <li data-astro-cid-sckkx6r4><a href="/presse" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>On parle de nous !</span></a></li>
        <li class="has-submenu" data-astro-cid-sckkx6r4>
          <button class="submenu-toggle" type="button" aria-expanded="false" aria-haspopup="true" aria-controls="submenu-creer-votre-maison" data-astro-cid-sckkx6r4>
            <span class="nav-text two-lines" data-astro-cid-sckkx6r4>
              <span class="line" data-astro-cid-sckkx6r4>Créer ou Investir</span>
              <span class="line" data-astro-cid-sckkx6r4>dans une maison partagée</span>
            </span>
            <span class="submenu-indicator" aria-hidden="true" data-astro-cid-sckkx6r4></span>
          </button>
          <ul id="submenu-creer-votre-maison" class="submenu" data-astro-cid-sckkx6r4>
            <li data-astro-cid-sckkx6r4><a href="/creer-votre-maison" data-astro-cid-sckkx6r4>Créer votre maison partagée</a></li>
            <li data-astro-cid-sckkx6r4><a href="/investir-dans-une-fonciere" data-astro-cid-sckkx6r4>Investissez dans la "Foncière Mélina"</a></li>
          </ul>
        </li>
        <li data-astro-cid-sckkx6r4><a href="/contact" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>Contact</span></a></li>
        <li data-astro-cid-sckkx6r4><a class="mag-pill" href="/le-mag" data-astro-cid-sckkx6r4><span class="nav-text" data-astro-cid-sckkx6r4>✦ Le Mag</span></a></li>
      </ul>
    </div>
  </nav>
</header>
<section class="mag-banner">
  <div><strong>Le Mag Maison Mélina</strong><br><span class="meta">Conseils, témoignages et repères pour les familles.</span></div>
  <a href="/le-mag/">Découvrir Le Mag</a>
</section>
<div class="main-content" data-astro-cid-sckkx6r4>
<main class="mag-wrap">
  <section class="hero">
    <p>Le Mag Maison Mélina, BLOG</p>
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
      if (navLinks && navLinks.classList.contains("is-active") && !clickInsideNav && !clickHamburger) {
        closeMenu();
      }
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
