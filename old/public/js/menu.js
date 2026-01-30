export function initMenu() {
    const menuToggles = document.querySelectorAll(".menu-item .menu-toggle");

    if (menuToggles.length === 0) {
        console.warn('Menu: Nenhum toggle encontrado');
        return;
    }

    menuToggles.forEach(function(toggle) {
        toggle.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();

            const menuItem = this.closest(".menu-item");
            const subMenu = menuItem.querySelector(".menu-sub");

            if (!subMenu) return;

            const isOpen = menuItem.classList.contains("open");

            // Fecha todos os outros submenus
            document.querySelectorAll(".menu-item.open").forEach(function(item) {
                if (item !== menuItem) {
                    item.classList.remove("open");
                    const otherSubMenu = item.querySelector(".menu-sub");
                    if (otherSubMenu) {
                        otherSubMenu.style.maxHeight = "0";
                    }
                }
            });

            // Toggle do submenu atual
            if (!isOpen) {
                menuItem.classList.add("open");
                subMenu.style.maxHeight = subMenu.scrollHeight + "px";
            } else {
                menuItem.classList.remove("open");
                subMenu.style.maxHeight = "0";
            }
        });
    });
}

export function initMenuActive() {
    const menuLinks = document.querySelectorAll(".menu-link");
    const targetIframe = document.querySelector("iframe.target-page");

    if (menuLinks.length === 0) {
        console.warn('Menu: Nenhum link encontrado');
        return;
    }

    if (!targetIframe) {
        console.error('Menu: iframe.target-page não encontrado');
        return;
    }

    menuLinks.forEach(function(link) {
        link.addEventListener("click", function(e) {
            // Se o link também tem a classe .menu-toggle, não faz nada
            if (this.classList.contains("menu-toggle")) {
                return;
            }

            e.preventDefault();

            const clickedMenuItem = this.closest(".menu-item");
            const menuInner = document.querySelector(".menu-inner");

            if (!clickedMenuItem || !menuInner) return;         

            // Remove 'active' de todos os itens
            menuInner.querySelectorAll(".menu-item").forEach(function(item) {
                item.classList.remove("active");
            });

            // Adiciona 'active' no item clicado
            clickedMenuItem.classList.add("active");

            // Se o item clicado está dentro de um submenu
            const parentSubMenu = clickedMenuItem.closest(".menu-sub");
            if (parentSubMenu) {
                const parentMenuItem = parentSubMenu.closest(".menu-item");
                if (parentMenuItem) {
                    parentMenuItem.classList.add("active");
                }
            }
        });
    });
}


export function setupMenuLayout() {
    // Seleção dos elementos do DOM
    const layoutWrapper = document.querySelector('.view.layout-wraper');
    const layoutMenu = document.querySelector('.layout-menu');
    const layoutOverlay = document.querySelector('.layout-overlay');
    const btnOpenMenu = document.querySelector('.layout-navbar .layout-menu-toggle .nav-link');
    const btnCloseMenu = document.querySelector('.brand-name .layout-menu-toggle');

    // Verifica se todos os elementos necessários existem
    if (!layoutWrapper || !layoutMenu || !layoutOverlay || !btnOpenMenu || !btnCloseMenu) {
        console.error('Menu: Elementos obrigatórios não encontrados no DOM', {
            layoutWrapper: !!layoutWrapper,
            layoutMenu: !!layoutMenu,
            layoutOverlay: !!layoutOverlay,
            btnOpenMenu: !!btnOpenMenu,
            btnCloseMenu: !!btnCloseMenu
        });
        return null;
    }

    // Estados do menu (apenas 3 modos)
    const MENU_STATES = {
        HIDDEN: 'menu-hidden',      // Oculto (mobile/tablet)
        VISIBLE: 'menu-visible',    // Visível temporariamente (mobile/tablet)
        FIXED: 'menu-fixed'         // Fixo permanente (desktop)
    };

    // Breakpoints responsivos
    const BREAKPOINTS = {
        MOBILE: 768,
        DESKTOP: 1200
    };

    /**
     * Obtém o breakpoint atual baseado na largura da tela
     */
    function getCurrentBreakpoint() {
        const width = window.innerWidth;

        if (width < BREAKPOINTS.MOBILE) {
            return 'mobile';
        } else if (width < BREAKPOINTS.DESKTOP) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    /**
     * Abre o menu lateral (modo VISÍVEL)
     */
    function openMenu() {
        layoutWrapper.classList.remove(MENU_STATES.HIDDEN, MENU_STATES.FIXED);
        layoutWrapper.classList.add(MENU_STATES.VISIBLE);
        layoutMenu.classList.add('active');
        layoutOverlay.style.display = 'block';

        // Previne scroll do body
        document.body.style.overflow = 'hidden';

        // Armazena estado no sessionStorage
        sessionStorage.setItem('menuState', 'visible');
    }

    /**
     * Fecha o menu lateral (modo OCULTO)
     */
    function closeMenu() {
        layoutWrapper.classList.remove(MENU_STATES.VISIBLE, MENU_STATES.FIXED);
        layoutWrapper.classList.add(MENU_STATES.HIDDEN);
        layoutMenu.classList.remove('active');
        layoutOverlay.style.display = 'none';

        // Restaura scroll do body
        document.body.style.overflow = '';

        // Atualiza estado no sessionStorage
        sessionStorage.setItem('menuState', 'hidden');
    }

    /**
     * Fixa o menu permanentemente (modo FIXO - desktop)
     */
    function fixMenu() {
        layoutWrapper.classList.remove(MENU_STATES.HIDDEN, MENU_STATES.VISIBLE);
        layoutWrapper.classList.add(MENU_STATES.FIXED);
        layoutMenu.classList.add('active');
        layoutOverlay.style.display = 'none';

        // Restaura scroll do body
        document.body.style.overflow = '';
    }

    /**
     * Alterna entre abrir e fechar o menu
     */
    function toggleMenu() {
        const isVisible = layoutWrapper.classList.contains(MENU_STATES.VISIBLE);

        if (isVisible) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    /**
     * Ajusta o layout do menu baseado na resolução
     */
    function adjustMenuLayout() {
        const breakpoint = getCurrentBreakpoint();
        const menuState = sessionStorage.getItem('menuState');

        console.log('Ajustando layout para:', breakpoint);

        // Remove todas as classes de estado
        layoutWrapper.classList.remove(
            MENU_STATES.HIDDEN,
            MENU_STATES.VISIBLE,
            MENU_STATES.FIXED
        );

        switch (breakpoint) {
            case 'mobile':
            case 'tablet':
                // Mobile/Tablet: menu oculto por padrão, pode ser aberto temporariamente
                if (menuState === 'visible') {
                    openMenu();
                } else {
                    closeMenu();
                }
                break;

            case 'desktop':
                // Desktop: menu fixo permanentemente
                fixMenu();
                break;
        }
    }

    /**
     * Fecha o menu ao clicar no overlay
     */
    function handleOverlayClick() {
        closeMenu();
    }

    /**
     * Fecha o menu ao pressionar ESC
     */
    function handleEscapeKey(event) {
        if (event.key === 'Escape' && layoutWrapper.classList.contains(MENU_STATES.VISIBLE)) {
            closeMenu();
        }
    }

    /**
     * Debounce para otimizar o resize
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Event Listeners
    btnOpenMenu.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log('Botão abrir clicado');
        openMenu();
    });

    btnCloseMenu.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        console.log('Botão fechar clicado');
        closeMenu();
    });

    layoutOverlay.addEventListener('click', handleOverlayClick);

    document.addEventListener('keydown', handleEscapeKey);

    // Ajusta layout ao redimensionar a janela (com debounce)
    const debouncedResize = debounce(adjustMenuLayout, 250);
    window.addEventListener('resize', debouncedResize);

    // Inicialização
    adjustMenuLayout();

    // Retorna métodos públicos para uso externo
    return {
        open: openMenu,
        close: closeMenu,
        fix: fixMenu,
        toggle: toggleMenu,
        refresh: adjustMenuLayout
    };
}
