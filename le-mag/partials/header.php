<?php
?>
<header class="fixed-header">
  <nav aria-label="Navigation principale">
    <div class="nav-content">
      <a href="/" class="logo" aria-label="Accueil MAISON MÉLINA">
        <img src="/img/divers/logo.jpg" alt="Logo MAISON MÉLINA" class="logo-image" width="160" height="90" loading="eager">
      </a>
      <button class="hamburger-menu" aria-label="Menu principal" aria-expanded="false" aria-controls="main-nav">
        <span class="hamburger-box">
          <span class="hamburger-inner"></span>
        </span>
      </button>
      <ul id="main-nav" class="nav-links">
        <li><a href="/"><span class="nav-text">Accueil</span></a></li>
        <li><a href="/concept"><span class="nav-text">Le Concept</span></a></li>
        <li><a href="/nos-maisons"><span class="nav-text">Nos Maisons</span></a></li>
        <li><a href="/qui-sommes-nous"><span class="nav-text">Qui Sommes-Nous ?</span></a></li>
        <li><a href="/presse"><span class="nav-text">On parle de nous !</span></a></li>
        <li class="has-submenu">
          <button class="submenu-toggle" type="button" aria-expanded="false" aria-haspopup="true" aria-controls="submenu-creer-votre-maison">
            <span class="nav-text two-lines">
              <span class="line">Créer ou Investir</span>
              <span class="line">dans une maison partagée</span>
            </span>
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
