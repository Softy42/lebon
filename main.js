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

  const rawPath = window.location.pathname;
  const normalizedPath = rawPath.endsWith("/") && rawPath.length > 1 ? rawPath.slice(0, -1) : rawPath;

  const matchesPath = (link) => {
    const href = link.getAttribute("href");
    if (!href || href.startsWith("http")) {
      return false;
    }
    if (href === "/") {
      return normalizedPath === "" || normalizedPath === "/" || normalizedPath === "/index.html";
    }
    const normalizedHref = href.endsWith("/") && href.length > 1 ? href.slice(0, -1) : href;
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
