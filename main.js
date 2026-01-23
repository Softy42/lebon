document.addEventListener("DOMContentLoaded", () => {
  const navLinks = Array.from(document.querySelectorAll(".nav-links a[href]"));
  if (navLinks.length === 0) {
    return;
  }

  navLinks.forEach((link) => {
    link.classList.remove("active");
    link.removeAttribute("aria-current");
  });

  document.querySelectorAll(".nav-links li.has-submenu").forEach((item) => {
    item.classList.remove("current");
  });

  const normalizePath = (value) => {
    if (!value) {
      return "";
    }
    let normalized = value;
    if (normalized.endsWith("/index.html")) {
      normalized = normalized.slice(0, -"/index.html".length);
    }
    if (normalized.length > 1 && normalized.endsWith("/")) {
      normalized = normalized.slice(0, -1);
    }
    return normalized;
  };

  const normalizedPath = normalizePath(window.location.pathname);

  const matchesPath = (link) => {
    const href = link.getAttribute("href");
    if (!href || href.startsWith("http")) {
      return false;
    }
    const normalizedHref = normalizePath(href);
    if (normalizedHref === "/") {
      return normalizedPath === "" || normalizedPath === "/";
    }
    return normalizedPath === normalizedHref || normalizedPath.startsWith(normalizedHref + "/");
  };

  const activeLink = navLinks.find(matchesPath);
  if (!activeLink) {
    return;
  }

  activeLink.classList.add("active");
  activeLink.setAttribute("aria-current", "page");

  const parentSubmenu = activeLink.closest("li.has-submenu");
  if (parentSubmenu) {
    parentSubmenu.classList.add("current");
  }
});
