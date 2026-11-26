(function (global) {
  const App = global.App || (global.App = {});

  const MENU_STATES = {
    HIDDEN: "menu-hidden",
    VISIBLE: "menu-visible",
    FIXED: "menu-fixed"
  };

  const BREAKPOINTS = {
    MOBILE: 768,
    DESKTOP: 1200
  };

  function getElements() {
    return {
      wrapper: document.querySelector(".view.layout-wraper"),
      menu: document.querySelector(".layout-menu"),
      page: document.querySelector(".layout-page"),
      overlay: document.querySelector(".layout-overlay"),
      openBtn: document.querySelector(
        ".layout-navbar .layout-menu-toggle .nav-link"
      ),
      closeBtn: document.querySelector(".brand-name .layout-menu-toggle")
    };
  }

  function resetMenuState(wrapper, menu) {
    wrapper.classList.remove(MENU_STATES.HIDDEN, MENU_STATES.VISIBLE, MENU_STATES.FIXED);
    if (menu) {
      menu.classList.remove("active");
    }
  }

  function applyState(wrapper, menu, state) {
    resetMenuState(wrapper, menu);
    wrapper.classList.add(state);
    if (state !== MENU_STATES.HIDDEN && menu) {
      menu.classList.add("active");
    }
  }

  let storedMenuState = "hidden";

  function persistMenuState(state) {
    storedMenuState = state;
    try { sessionStorage.setItem("menuState", state); } catch (_) { /* Storage may be unavailable. */ }
  }

  function menuState() {
    try { return sessionStorage.getItem("menuState") || storedMenuState; }
    catch (_) { return storedMenuState; }
  }

  function setMenuAccessibility(elements, state) {
    const open = state === MENU_STATES.VISIBLE;
    const hidden = state === MENU_STATES.HIDDEN;
    elements.openBtn?.setAttribute("aria-expanded", String(open));
    if (elements.menu) {
      elements.menu.inert = hidden;
      if (open) {
        elements.menu.setAttribute("role", "dialog");
        elements.menu.setAttribute("aria-modal", "true");
      } else {
        elements.menu.removeAttribute("role");
        elements.menu.removeAttribute("aria-modal");
      }
    }
    if (elements.page) elements.page.inert = open;
  }

  function openMenu(elements) {
    if (!elements.wrapper || !elements.menu) return;
    const wasOpen = elements.wrapper.classList.contains(MENU_STATES.VISIBLE);
    applyState(elements.wrapper, elements.menu, MENU_STATES.VISIBLE);
    if (elements.overlay) elements.overlay.style.display = "block";
    document.body.style.overflow = "hidden";
    setMenuAccessibility(elements, MENU_STATES.VISIBLE);
    if (!wasOpen) elements.closeBtn?.focus({ preventScroll: true });
    persistMenuState("visible");
  }

  function closeMenu(elements) {
    if (!elements.wrapper || !elements.menu) return;
    const wasOpen = elements.wrapper.classList.contains(MENU_STATES.VISIBLE);
    applyState(elements.wrapper, elements.menu, MENU_STATES.HIDDEN);
    if (elements.overlay) elements.overlay.style.display = "none";
    document.body.style.overflow = "";
    setMenuAccessibility(elements, MENU_STATES.HIDDEN);
    if (wasOpen) elements.openBtn?.focus({ preventScroll: true });
    persistMenuState("hidden");
  }

  function fixMenu(elements) {
    applyState(elements.wrapper, elements.menu, MENU_STATES.FIXED);
    if (elements.overlay) elements.overlay.style.display = "none";
    document.body.style.overflow = "";
    setMenuAccessibility(elements, MENU_STATES.FIXED);
  }

  function getCurrentBreakpoint() {
    const width = window.innerWidth;
    if (width < BREAKPOINTS.MOBILE) return "mobile";
    if (width < BREAKPOINTS.DESKTOP) return "tablet";
    return "desktop";
  }

  function adjustMenu(elements) {
    const breakpoint = getCurrentBreakpoint();
    const stored = menuState();

    if (!elements.wrapper || !elements.menu) return;

    switch (breakpoint) {
      case "mobile":
      case "tablet":
        if (stored === "visible") {
          openMenu(elements);
        } else {
          closeMenu(elements);
        }
        break;
      default:
        fixMenu(elements);
        break;
    }
  }

  function initMenuLayout() {
    const elements = getElements();
    if (!elements.wrapper || !elements.menu || !elements.overlay) {
      return null;
    }

    const handleOverlay = () => closeMenu(elements);
    const handleEscape = (event) => {
      if (!elements.wrapper.classList.contains(MENU_STATES.VISIBLE)) return;
      if (event.key === "Escape") {
        event.preventDefault();
        closeMenu(elements);
      } else if (event.key === "Tab") {
        const items = Array.from(elements.menu.querySelectorAll("button:not(:disabled), a[href]"))
          .filter((item) => item.tabIndex >= 0 && !item.closest("[inert]")
            && item.getClientRects().length && getComputedStyle(item).visibility === "visible");
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (!elements.menu.contains(document.activeElement) || (event.shiftKey && document.activeElement === first)) {
          event.preventDefault();
          (event.shiftKey ? last : first).focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    };

    elements.overlay.addEventListener("click", handleOverlay);
    document.addEventListener("keydown", handleEscape);

    if (elements.openBtn) {
      elements.openBtn.addEventListener("click", (e) => {
        e.preventDefault();
        openMenu(elements);
      });
    }

    if (elements.closeBtn) {
      elements.closeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        closeMenu(elements);
      });
    }

    window.addEventListener("resize", () => adjustMenu(elements));

    adjustMenu(elements);

    return {
      open: () => openMenu(elements),
      close: () => closeMenu(elements),
      refresh: () => adjustMenu(elements),
      fix: () => fixMenu(elements)
    };
  }

  function initMenuToggles() {
    const toggles = document.querySelectorAll(".menu-item .menu-toggle");
    toggles.forEach((toggle) => {
      const initialItem = toggle.closest(".menu-item");
      const initialSub = initialItem?.querySelector(".menu-sub");
      const initialOpen = initialItem?.classList.contains("open") === true;
      toggle.setAttribute("aria-expanded", String(initialOpen));
      if (initialSub) initialSub.inert = !initialOpen;
      toggle.addEventListener("click", (event) => {
        event.preventDefault();
        const item = toggle.closest(".menu-item");
        const sub = item?.querySelector(".menu-sub");
        if (!item || !sub) return;

        const isOpen = item.classList.toggle("open");
        sub.style.maxHeight = isOpen ? `${sub.scrollHeight}px` : "0";
        sub.inert = !isOpen;
        toggle.setAttribute("aria-expanded", String(isOpen));

        document.querySelectorAll(".menu-item.open").forEach((other) => {
          if (other !== item) {
            other.classList.remove("open");
            const otherSub = other.querySelector(".menu-sub");
            if (otherSub) {
              otherSub.style.maxHeight = "0";
              otherSub.inert = true;
              other.querySelector(".menu-toggle")?.setAttribute("aria-expanded", "false");
            }
          }
        });
      });
    });
  }

  function initMenuActive() {
    const links = document.querySelectorAll(".menu-link");
    const iframe = document.querySelector("iframe.target-page");

    if (!iframe) {
      console.warn("iframe.target-page não encontrado");
    }

    links.forEach((link) => {
      link.addEventListener("click", (event) => {
        if (link.classList.contains("menu-toggle")) return;
        event.preventDefault();

        const menuInner = document.querySelector(".menu-inner");
        menuInner?.querySelectorAll(".menu-item").forEach((item) => {
          item.classList.remove("active");
        });

        const parentItem = link.closest(".menu-item");
        parentItem?.classList.add("active");

        const parentSub = link.closest(".menu-sub");
        const parentHeading = parentSub?.closest(".menu-item");
        parentHeading?.classList.add("active");

        const target = link.getAttribute("data-target");
        if (target && iframe) {
          iframe.src = "/frame.php?page=" + encodeURIComponent(target);
          return;
        }
        const href = link.getAttribute("href");
        if (href && href !== "#" && iframe) {
          iframe.src = href;
        }
      });
    });
  }

  async function fetchUserPerfis(endpoint = "/admin/api/getPerfis.php") {
    if (App.User && typeof App.User.fetchPerfis === "function") {
      return App.User.fetchPerfis(endpoint);
    }
    return [];
  }

  function getPermittedSections(perfis) {
    const mapping = {
      ESTUDANTE: ["Estudantes"],
      SERVIDOR: ["Cartório", "Gabinete", "Sala de Audiências"],
      ADMIN: ["Cartório", "Gabinete", "Sala de Audiências", "Estudantes", "Admin"]
    };

    const permitted = new Set(["Dashboard"]);
    perfis.forEach((perfil) => {
      const sections = mapping[perfil];
      (sections || []).forEach((section) => permitted.add(section));
    });
    return permitted;
  }

  function filterMenuByAccess(permittedSections) {
    const headers = document.querySelectorAll(".menu-header");
    headers.forEach((header) => {
      const name = header.querySelector(".menu-header-text")?.textContent?.trim();
      if (!name) return;
      if (!permittedSections.has(name)) {
        header.style.display = "none";
        let cursor = header.nextElementSibling;
        while (cursor && !cursor.classList.contains("menu-header")) {
          cursor.style.display = "none";
          cursor = cursor.nextElementSibling;
        }
      }
    });
  }

  async function initMenuAccess() {
    const perfis = await fetchUserPerfis();
    if (perfis.length === 0) return;
    const permitted = getPermittedSections(perfis);
    filterMenuByAccess(permitted);
  }

  App.Menu = {
    initLayout: initMenuLayout,
    initMenu: initMenuToggles,
    initActive: initMenuActive,
    initAccess: initMenuAccess,
    fetchUserPerfis,
    open: () => {
      const elements = getElements();
      openMenu(elements);
    },
    close: () => {
      const elements = getElements();
      closeMenu(elements);
    }
  };
})(window);
