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
