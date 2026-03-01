<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';

if (empty($_SESSION['token']) || empty($_SESSION['id_usuario'])) {
    header('Location: /index.php');
    exit;
}

// Verificar validade do token
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

    <link rel="stylesheet" href="/assets/css/legacy/reset.css">
  	<link rel="stylesheet" href="/assets/css/legacy/color.css">
    <link rel="stylesheet" href="/assets/css/legacy/components.css">
    <link rel="stylesheet" href="/assets/css/legacy/form.css">
    <link rel="stylesheet" href="/assets/css/legacy/layout.css">
    <link rel="stylesheet" href="/assets/css/legacy/login.css">
    <link rel="stylesheet" href="/assets/css/legacy/menu-style.css">
    <link rel="stylesheet" href="/assets/css/legacy/pages.css">
    <link rel="stylesheet" href="/assets/css/legacy/style.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
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
                                <a class="nav-link disabled">
                                    <i class="fi fi-rs-eclipse-alt"></i>
                                </a>
                            </li>                        
                            <li class="nav-item">
                                <a class="nav-link disabled"> <!-- new-message -->
                                    <i class="fi fi-rs-bell"></i>
                                </a>
                            </li>                        
                            <li class="nav-item" data-target="settingsuser">
                                <a class="nav-link">
                                    <i class="fi fi-rs-settings"></i>
                                </a>
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
        
    </div>    

    <script src="/assets/js/shared/common.js" defer></script>
    <script src="/assets/js/shared/menu.js" defer></script>

    <script>

        const PERFIL_MENU_MAP = {
            'ESTUDANTE': ['Estudantes'],
            'CONSULTOR': ['Consultas'],
            'AUDIENCIA': ['Cartório', 'Sala de Audiências', 'Consultas'],
            'CARTORIO': ['Cartório', 'Consultas'],
            'GABINETE': ['Cartório', 'Sala de Audiências', 'Gabinete', 'Consultas'],
            'ADMIN': ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Consultas', 'Audiências', 'Admin']
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
                    { icon: 'fi fi-ss-license', label: 'Ofícios', target: 'oficiosgabinete' },
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
                    { icon: 'fi fi-rs-users-alt', label: 'Partes', target: 'consultaparte' },
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
                    { icon: 'fi fi-rs-id-badge', label: 'Servidores', target: 'admservidores' }
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

        function buildMenuForSections(permittedSections) {
            const menuRoot = document.querySelector('.menu-inner');

            if (!menuRoot) {
                return;
            }

            MENU_SECTIONS.forEach(section => {
                if (!permittedSections.has(section.section)) {
                    return;
                }

                const header = document.createElement('li');
                header.className = 'menu-header';

                const headerText = document.createElement('span');
                headerText.className = 'menu-header-text';
                headerText.textContent = section.section;
                header.appendChild(headerText);

                menuRoot.appendChild(header);

                section.items.forEach(item => {
                    menuRoot.appendChild(createMenuItem(item));
                });
            });
        }

        async function initDynamicMenu() {
            const perfis = await fetchUserPerfis();
            const permittedSections = getPermittedSections(perfis);
            buildMenuForSections(permittedSections);
        }

        document.addEventListener("DOMContentLoaded", async function() {
            await initDynamicMenu();
            App.Menu.initMenu();
            App.Menu.initActive();

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
