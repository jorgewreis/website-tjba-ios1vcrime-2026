<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';

// Impedir cache: a home só pode ser servida após verificação em cada request
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// 1) Exige sessão com token e id_usuario (redireciona se não autenticado)
Security::requireAuthentication();

// 2) Validar token no banco (expiração e consistência)
$usuario = new Usuario();
$user = $usuario->validarToken($_SESSION['token']);

if (!$user) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: /index.php');
    exit;
}

$userName = $_SESSION['nome'] ?? ($user['nome'] ?? '');
$userProfile = Security::getUserProfile() ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">
    
    <!-- Ícones: fi-rs-* (regular-straight), fi-br-* (bold-rounded) -->
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-straight/css/uicons-regular-straight.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-bold-rounded/css/uicons-bold-rounded.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    <script>
        (function() {
            var name = 'tjba_1vcrime_theme';
            var i = document.cookie.indexOf(name + '=');
            if (i === -1) return;
            i += name.length + 1;
            var end = document.cookie.indexOf(';', i);
            var t = decodeURIComponent((end === -1 ? document.cookie.slice(i) : document.cookie.slice(i, end)).trim());
            if (t === 'claro' || t === 'escuro' || t === 'auto') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
    
    <!-- PAGE DISPOSITIVOS INCOMPATIVEIS -->
    <div class="noview"></div>

    <!-- CONTEÚDO DO SITE -->
    <div class="view layout-wraper">

        <div class="layout-container">

            <div class="layout-menu">
                <div class="brand-name">
                    <a class="brand-name-link">
                        <span class="name-primary">Sistema de Gestão</span>
                        <span class="name-secondary">Primeira Vara Crime</span>
                    </a>
                    <a class="layout-menu-toggle">
                        <i class="fi fi-rs-angle-circle-left"></i>
                    </a>
                </div>
                <ul class="menu-inner">
                    <li class="menu-item active">
                        <a class="menu-link" data-target="dashboard">
                            <i class="fi fi-rs-home"></i>
                            <span class="text">Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="layout-page">            
                <!-- NAVBAR -->
                <nav class="layout-navbar">
                    <div class="layout-menu-toggle">
                        <a class="nav-item nav-link">
                            <i class="fi fi-br-menu-burger"></i>
                        </a>
                    </div>
                    <div class="navbar-nav-right">
                        <ul class="navbar-nav">
                            <li class="nav-item" data-target="dashboard">
                                <a class="nav-link">
                                    <i class="fi fi-br-layout-fluid"></i>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="themeToggle" href="#" role="button" tabindex="0" aria-label="Alternar tema" title="Alternar tema (claro / escuro / automático)">
                                    <i class="fi fi-rs-eclipse-alt"></i>
                                </a>
                            </li>                        
                            <li class="nav-item">
                                <a class="nav-link disabled"> <!-- new-message -->
                                    <i class="fi fi-rs-bell"></i>
                                </a>
                            </li>                        
                            <li class="nav-item nav-item-settings" id="nav-item-settings">
                                <a class="nav-link" id="settings-menu-trigger" href="#" role="button" aria-expanded="false" aria-haspopup="true" aria-label="Configurações e perfil">
                                    <i class="fi fi-rs-settings"></i>
                                </a>
                                <div class="settings-dropdown" id="settings-dropdown" role="menu" aria-label="Menu Perfil" hidden>
                                    <div class="settings-dropdown-inner">
                                        <div class="menu-header">
                                            <span class="menu-header-text">Perfil</span>
                                        </div>
                                        <ul class="settings-dropdown-list">
                                            <li class="menu-item">
                                                <a class="menu-link" href="#" role="menuitem" data-settings="alterar-senha">Alterar senha</a>
                                            </li>
                                            <li class="menu-item">
                                                <a class="menu-link" href="#" role="menuitem" data-settings="alterar-dados">Alterar dados pessoais</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link">
                                    <div class="avatar avatar-online"> <!-- avatar-online -->
                                        <img src="/assets/img/avatars/3d/avatar3d_007.jpg">
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- /NAVBAR -->
                
                <!-- CONTENT WRAPER -->
                <div class="content-wrapper">
                    <iframe class="target-page" id="frame" src="/frame.php?page=dashboard"></iframe>
                </div>
            </div>            
        </div>        

        <div class="layout-overlay"></div>
        <div class="drag-target"></div>

        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/prefs.js" defer></script>
    <script src="/assets/js/shared/toast.js" defer></script>
    <script src="/assets/js/shared/common.js" defer></script>
    <script src="/assets/js/shared/menu.js" defer></script>

    <script>

        const PERFIL_MENU_MAP = {
            'ESTUDANTE': ['Estudantes'],
            'CONSULTOR': ['Consultas'],
            'AUDIENCIA': ['Cartório', 'Sala de Audiências', 'Consultas'],
            'CARTORIO': ['Cartório', 'Consultas'],
            'GABINETE': ['Cartório', 'Sala de Audiências', 'Gabinete', 'Consultas'],
            'ADMIN': ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Consultas', 'Audiências', 'Admin'],
            'DIRETORA': ['Consultas', 'Admin']
        };

        const MENU_SECTIONS = [
            {
                section: 'Cartório',
                items: [
                    {
                        type: 'submenu',
                        icon: 'fi fi-rs-envelope-open-text',
                        label: 'Cartas Precatórias',
                        children: [
                            { label: 'Cartas Expedidas', target: 'cartasexpedidas' },
                            { label: 'Cartas Recebidas', target: 'cartasrecebidas' }
                        ]
                    },
                    { icon: 'fi fi-rs-money-check-edit', label: 'Cejusc', target: 'cejusc' },
                    { icon: 'fi fi-rs-document', label: 'Editais', target: 'editais' },
                    { icon: 'fi fi-rs-blog-text', label: 'Guias', target: 'guias' },
                    { icon: 'fi fi-rs-user-shield', label: 'Medidas protetivas', target: 'medidasprotetivas' },
                    { icon: 'fi fi-rs-license', label: 'Ofícios', target: 'oficioscartorio' },
                    { icon: 'fi fi-rs-clock-up-arrow', label: 'Produtividade', target: 'produtividade' }
                ]
            },
            {
                section: 'Gabinete',
                items: [
                    { icon: 'fi fi-rs-license', label: 'Ofícios', target: 'oficiosgabinete' },
                    { icon: 'fi fi-rs-lock', label: 'Réus Presos', target: 'reuspresos' }
                ]
            },
            {
                section: 'Sala de Audiências',
                items: [                    
                    { icon: 'fi fi-rs-clipboard-list-check', label: 'Pauta', target: 'salapauta' },
                    { icon: 'fi fi-rs-document', label: 'Atas', target: 'salaata' },
                    { icon: 'fi fi-rs-screen', label: 'Painel', target: 'salapainel' }
                ]
            },
            {
                section: 'Estudantes',
                items: [
                    { icon: 'fi fi-rs-clipboard-list-check', label: 'Pauta de Audiências', target: 'estudapauta' },
                    { icon: 'fi fi-rs-document', label: 'Atas', target: 'estudaata' },
                    { icon: 'fi fi-rs-screen', label: 'Painel em tempo real', target: 'estudapainel' }
                ]
            },
            {
                section: 'Consultas',
                items: [
                    { icon: 'fi fi-rs-users-alt', label: 'Consulta Partes', target: 'consultapartes' },
                    { icon: 'fi fi-rs-folder-open', label: 'Consulta Processos', target: 'consultaprocessos' },
                    { icon: 'fi fi-rs-building', label: 'Instituições Públicas', target: 'instituicoespublicas' },
                    { icon: 'fi fi-rs-marker', label: 'CEP', target: 'consultacep' }
                ]
            },
            {
                section: 'Audiências',
                items: [
                    { icon: 'fi fi-rs-clipboard-list-check', label: 'Pauta de Audiências', target: 'pauta' },
                    { icon: 'fi fi-rs-document', label: 'Atas', target: 'atas' },
                    { icon: 'fi fi-rs-screen', label: 'Painel em tempo real', target: 'painel' }
                ]
            },
            {
                section: 'Admin',
                items: [
                    { icon: 'fi fi-rs-id-badge', label: 'Usuários', target: 'usuarioslist', allowedPerfis: ['ADMIN'] },
                    { icon: 'fi fi-rs-calendar', label: 'Feriados', target: 'feriadoslist', allowedPerfis: ['ADMIN', 'DIRETORA'] }
                ]
            }
        ];

        const FALLBACK_PERFIS = <?php echo json_encode($userProfile ? [$userProfile] : []); ?>;

        async function fetchUserPerfis() {
            try {
                const response = await fetch('/admin/api/getPerfis.php');

                if (!response.ok) {
                    return FALLBACK_PERFIS;
                }

                const result = await response.json();

                if (result.success && Array.isArray(result.perfis) && result.perfis.length > 0) {
                    return result.perfis;
                }

                return FALLBACK_PERFIS;
            } catch (error) {
                return FALLBACK_PERFIS;
            }
        }

        function getPermittedSections(perfis) {
            const permittedSections = new Set();

            perfis.forEach(perfil => {
                const sections = PERFIL_MENU_MAP[perfil];

                if (sections && Array.isArray(sections)) {
                    sections.forEach(section => {
                        permittedSections.add(section);
                    });
                }
            });

            return permittedSections;
        }

        function createMenuLink({ icon, label, target }) {
            const link = document.createElement('a');
            link.className = 'menu-link';

            if (target) {
                link.dataset.target = target;
            }

            if (icon) {
                const iconElement = document.createElement('i');
                iconElement.className = icon;
                link.appendChild(iconElement);
            }

            const text = document.createElement('span');
            text.className = 'text';
            text.textContent = label;
            link.appendChild(text);

            return link;
        }

        function createMenuItem(item) {
            const menuItem = document.createElement('li');
            menuItem.className = 'menu-item';

            if (item.type === 'submenu') {
                const link = document.createElement('a');
                link.className = 'menu-link menu-toggle';

                if (item.icon) {
                    const iconElement = document.createElement('i');
                    iconElement.className = item.icon;
                    link.appendChild(iconElement);
                }

                const text = document.createElement('span');
                text.className = 'text';
                text.textContent = item.label;
                link.appendChild(text);

                menuItem.appendChild(link);

                const submenu = document.createElement('ul');
                submenu.className = 'menu-sub';

                item.children.forEach(child => {
                    const childItem = document.createElement('li');
                    childItem.className = 'menu-item';
                    childItem.appendChild(createMenuLink(child));
                    submenu.appendChild(childItem);
                });

                menuItem.appendChild(submenu);
                return menuItem;
            }

            menuItem.appendChild(createMenuLink(item));
            return menuItem;
        }

        function appendLogoutItem() {
            const menuRoot = document.querySelector('.menu-inner');
            if (!menuRoot) return;

            const li = document.createElement('li');
            li.className = 'menu-item menu-item-logout';
            const link = document.createElement('a');
            link.href = '#';
            link.className = 'menu-link';
            link.id = 'logout-btn';
            link.dataset.logout = '';
            link.title = 'Sair com segurança';
            link.innerHTML = '<i class="fi fi-rs-sign-out-alt"></i><span class="text">Sair</span>';
            link.addEventListener('click', function(e) {
                e.preventDefault();
                App.Common.performLogout('/logout.php', true);
            });
            li.appendChild(link);
            menuRoot.appendChild(li);
        }

        function itemAllowedForUser(item, perfis) {
            if (!item.allowedPerfis || item.allowedPerfis.length === 0) {
                return true;
            }
            return item.allowedPerfis.some(p => perfis.includes(p));
        }

        function buildMenuForSections(permittedSections, perfis) {
            const menuRoot = document.querySelector('.menu-inner');

            if (!menuRoot) {
                return;
            }

            MENU_SECTIONS.forEach(section => {
                if (!permittedSections.has(section.section)) {
                    return;
                }

                const visibleItems = [];
                section.items.forEach(item => {
                    if (!itemAllowedForUser(item, perfis)) {
                        return;
                    }
                    if (item.type === 'submenu') {
                        const children = (item.children || []).filter(c => itemAllowedForUser(c, perfis));
                        if (children.length === 0) {
                            return;
                        }
                        visibleItems.push(Object.assign({}, item, { children }));
                    } else {
                        visibleItems.push(item);
                    }
                });

                if (visibleItems.length === 0) {
                    return;
                }

                const header = document.createElement('li');
                header.className = 'menu-header';

                const headerText = document.createElement('span');
                headerText.className = 'menu-header-text';
                headerText.textContent = section.section;
                header.appendChild(headerText);

                menuRoot.appendChild(header);

                visibleItems.forEach(item => {
                    menuRoot.appendChild(createMenuItem(item));
                });
            });
        }

        async function initDynamicMenu() {
            const perfis = await fetchUserPerfis();
            const permittedSections = getPermittedSections(perfis);
            buildMenuForSections(permittedSections, perfis);
            appendLogoutItem();
        }

        function syncThemeToFrame(theme) {
            var frameEl = document.getElementById('frame');
            if (!frameEl) return;
            try {
                var doc = frameEl.contentDocument;
                if (!doc || !doc.documentElement) return;
                var effective = theme === 'auto' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro') : theme;
                doc.documentElement.setAttribute('data-theme', effective);
            } catch (err) { /* cross-origin or not loaded */ }
        }

        /** Atualiza o ícone do botão de tema na barra superior (sol / lua / eclipse). */
        function updateThemeIcon(theme) {
            var themeToggle = document.getElementById('themeToggle');
            if (!themeToggle) return;
            var icon = themeToggle.querySelector('i');
            if (!icon) return;
            var iconClasses = { claro: 'fi fi-rs-sun', escuro: 'fi fi-rs-moon', auto: 'fi fi-rs-eclipse-alt' };
            icon.className = iconClasses[theme] || iconClasses.auto;
        }

        document.addEventListener("DOMContentLoaded", async function() {
            if (window.App && App.Prefs) App.Prefs.applyThemeFromCookie();
            var currentTheme = App.Prefs ? App.Prefs.getTheme() : 'claro';
            syncThemeToFrame(currentTheme);
            updateThemeIcon(currentTheme);
            var frameEl = document.getElementById('frame');
            if (frameEl) frameEl.addEventListener('load', function() { syncThemeToFrame(App.Prefs ? App.Prefs.getTheme() : 'claro'); });

            var themeToggle = document.getElementById('themeToggle');
            if (themeToggle && App.Prefs) {
                themeToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    var next = { claro: 'escuro', escuro: 'auto', auto: 'claro' };
                    var current = App.Prefs.getTheme();
                    var nextTheme = next[current] || 'claro';
                    App.Prefs.setTheme(nextTheme);
                    App.Prefs.applyTheme();
                    syncThemeToFrame(nextTheme);
                    updateThemeIcon(nextTheme);
                    if (window.App && App.Toast && App.Toast.show) {
                        var labels = { claro: 'Claro', escuro: 'Escuro', auto: 'Automático' };
                        App.Toast.show({ type: 'info', variant: 'simple', message: 'Tema alterado para ' + (labels[nextTheme] || nextTheme) + '.' });
                    }
                });
            }

            await initDynamicMenu();
            App.Menu.initMenu();
            App.Menu.initActive();

            /* Submenu flutuante do botão Configurações (ícone engrenagem) */
            (function initSettingsDropdown() {
                var trigger = document.getElementById('settings-menu-trigger');
                var dropdown = document.getElementById('settings-dropdown');
                var navItem = document.getElementById('nav-item-settings');
                if (!trigger || !dropdown) return;

                function openDropdown() {
                    dropdown.removeAttribute('hidden');
                    dropdown.classList.add('is-open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
                function closeDropdown() {
                    dropdown.setAttribute('hidden', '');
                    dropdown.classList.remove('is-open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
                function toggleDropdown(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var isOpen = dropdown.classList.contains('is-open');
                    if (isOpen) closeDropdown();
                    else openDropdown();
                }

                trigger.addEventListener('click', toggleDropdown);

                document.addEventListener('click', function(e) {
                    if (!dropdown.classList.contains('is-open')) return;
                    if (navItem && navItem.contains(e.target)) return;
                    closeDropdown();
                });

                dropdown.querySelectorAll('.menu-link[data-settings]').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        var action = link.getAttribute('data-settings');
                        closeDropdown();
                        if (action === 'alterar-senha') {
                            window.location.href = '/alterar-senha.php';
                        } else if (action === 'alterar-dados') {
                            var frame = document.getElementById('frame');
                            if (frame) frame.src = '/frame.php?page=alterardados';
                        }
                    });
                });
            })();

            const menuController = App.Menu.initLayout();

            if (menuController) {
                window.menuController = menuController;
            }
        });
    </script>

    <script>
        const frame = document.getElementById('frame');

        document.addEventListener('click', (event) => {
            const link = event.target.closest('[data-target]');

            if (!link) {
                return;
            }

            event.preventDefault();
            const page = link.getAttribute('data-target');

            if (!page) {
                return;
            }

            frame.src = '/frame.php?page=' + encodeURIComponent(page);
            const layoutWrapper = document.querySelector('.view.layout-wraper');
            const isDesktop = layoutWrapper?.classList.contains('menu-fixed');

            if (!isDesktop) {
                window.menuController?.close?.();
            }
        });        
    </script>
    
</body>

</html>
