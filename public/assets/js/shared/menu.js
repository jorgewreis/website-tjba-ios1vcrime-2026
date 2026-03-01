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

  function openMenu(elements) {
    applyState(elements.wrapper, elements.menu, MENU_STATES.VISIBLE);
    if (elements.overlay) {
      elements.overlay.style.display = "block";
    }
    document.body.style.overflow = "hidden";
    sessionStorage.setItem("menuState", "visible");
  }

  function closeMenu(elements) {
    applyState(elements.wrapper, elements.menu, MENU_STATES.HIDDEN);
    if (elements.overlay) {
      elements.overlay.style.display = "none";
    }
    document.body.style.overflow = "";
    sessionStorage.setItem("menuState", "hidden");
  }

  function fixMenu(elements) {
    applyState(elements.wrapper, elements.menu, MENU_STATES.FIXED);
    if (elements.overlay) {
      elements.overlay.style.display = "none";
    }
    document.body.style.overflow = "";
  }

  function getCurrentBreakpoint() {
    const width = window.innerWidth;
    if (width < BREAKPOINTS.MOBILE) return "mobile";
    if (width < BREAKPOINTS.DESKTOP) return "tablet";
    return "desktop";
  }

  function adjustMenu(elements) {
    const breakpoint = getCurrentBreakpoint();
    const stored = sessionStorage.getItem("menuState");

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
      if (event.key === "Escape" && elements.wrapper.classList.contains(MENU_STATES.VISIBLE)) {
        closeMenu(elements);
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
      toggle.addEventListener("click", (event) => {
        event.preventDefault();
        const item = toggle.closest(".menu-item");
        const sub = item?.querySelector(".menu-sub");
        if (!item || !sub) return;

        const isOpen = item.classList.toggle("open");
        sub.style.maxHeight = isOpen ? `${sub.scrollHeight}px` : "0";

        document.querySelectorAll(".menu-item.open").forEach((other) => {
          if (other !== item) {
            other.classList.remove("open");
            const otherSub = other.querySelector(".menu-sub");
            if (otherSub) {
              otherSub.style.maxHeight = "0";
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

        const href = link.getAttribute("href");
        if (href && iframe) {
          iframe.src = href;
        }
      });
    });
  }

  async function fetchUserPerfis(endpoint = "/admin/api/getPerfis.php") {
    const result = [];
    try {
      const response = await fetch(endpoint);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      if (Array.isArray(payload.perfis)) {
        return payload.perfis;
      }
    } catch (error) {
      console.error("Erro ao buscar perfis do usuário:", error);
    }
    return result;
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
