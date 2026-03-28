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
  <script type="module" src="/main.js"></script>
  <style>
    .wrap{max-width:920px;margin:0 auto;padding:1.25rem}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:1rem;margin:0 0 1rem}
    .meta{font-size:.9rem;color:#6b7280}
    .btn{display:inline-block;background:#b42c2d;color:#fff;border-radius:999px;padding:.7rem 1rem;text-decoration:none;font-weight:700}
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
