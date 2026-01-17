<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';

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

    <!-- Reset CSS (Fundação) -->
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/color.css">

    <!-- Layout e Navegação -->
    <link rel="stylesheet" href="/css/layout.css">
    <link rel="stylesheet" href="/css/menu-style.css">

    <!-- Componentes Reutilizáveis -->
    <link rel="stylesheet" href="/css/components.css">

    <!-- Páginas Internas -->
    <link rel="stylesheet" href="/css/pages.css">
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
                    <li class="menu-header">
                        <span class="menu-header-text">Cartório</span>
                    </li>
                    <li class="menu-item">
                        <a class="menu-link menu-toggle">
                            <i class="fi fi-rs-envelope-open-text"></i>
                            <span class="text">Cartas Precatórias</span>
                        </a>
                        <ul class="menu-sub">
                            <li class="menu-item active">
                                <a class="menu-link" data-target="cartasexpedidas">
                                    <span class="text">Cartas Expedidas</span>
                                </a>    
                            </li>
                            <li class="menu-item">
                                <a class="menu-link" data-target="cartasrecebidas">
                                    <span class="text">Cartas Recebidas</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="cejusc">
                            <i class="fi fi-rs-money-check-edit"></i>
                            <span class="text">Cejusc</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="editais">                            
                            <i class="fi fi-rs-document"></i>
                            <span class="text">Editais</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="guias">                            
                            <i class="fi fi-rs-blog-text"></i>
                            <span class="text">Guias</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="medidasprotetivas">                            
                            <i class="fi fi-rs-user-shield"></i>
                            <span class="text">Medidas protetivas</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="oficioscartorio">                            
                            <i class="fi fi-rs-license"></i>
                            <span class="text">Ofícios</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="produtividade">                            
                            <i class="fi fi-rs-clock-up-arrow"></i>
                            <span class="text">Produtividade</span>
                        </a>
                    </li>
                    <li class="menu-header">
                        <span class="menu-header-text">Gabinete</span>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="oficiosgabinete">                            
                            <i class="fi fi-ss-license"></i>
                            <span class="text">Ofícios</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link" data-target="reuspresos">                            
                            <i class="fi fi-rs-court-order"></i>
                            <span class="text">Réus Presos</span>
                        </a>
                    </li>
                    <li class="menu-header">
                        <span class="menu-header-text">Sala de Audiências</span>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-users-alt"></i>
                            <span class="text">Partes</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-clipboard-list-check"></i>
                            <span class="text">Pauta</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-screen"></i>
                            <span class="text">Painel</span>
                        </a>
                    </li>
                    <li class="menu-header">
                        <span class="menu-header-text">Estudantes</span>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-clipboard-list-check"></i>
                            <span class="text">Pauta de Audiências</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-document"></i>
                            <span class="text">Atas</span>
                        </a>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-screen"></i>
                            <span class="text">Painel em tempo real</span>
                        </a>
                    </li>
                    <li class="menu-header">
                        <span class="menu-header-text">Admin</span>
                    </li>
                    <li class="menu-item ">
                        <a class="menu-link">                            
                            <i class="fi fi-rs-id-badge"></i>
                            <span class="text">Servidores</span>
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
                            <li class="nav-item">
                                <a class="nav-link disabled">
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
                            <li class="nav-item">
                                <a class="nav-link disabled">
                                    <i class="fi fi-rs-settings"></i>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link">
                                    <div class="avatar avatar-online"> <!-- avatar-online -->
                                        <img src="/img/avatar3d_007.jpg">
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

    <script src="/js/common.js" defer></script>
    
    <script type="module">
        import { initMenu, initMenuActive, setupMenuLayout } from '/js/menu.js';

        document.addEventListener("DOMContentLoaded", function() {
            initMenu();
            initMenuActive();

            const menuController = setupMenuLayout();

            if (menuController) {
                window.menuController = menuController;
            }
        });
    </script>

    <script>
        const frame = document.getElementById('frame');

        // Lista de páginas permitidas
        const allowedPages = new Set([
            'dashboard', 'cejusc', 'editais', 'guias', 'medidasprotetivas',
            'oficioscartorio', 'oficiosgabinete', 'cartasexpedidas', 
            'cartasrecebidas', 'produtividade'
        ]);

        document.querySelectorAll('[data-target]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.getAttribute('data-target');
                
                // Validação de segurança: só permite páginas listadas
                if (allowedPages.has(page)) {
                    frame.src = '/frame.php?page=' + encodeURIComponent(page);
                } else {
                    console.warn('Tentativa de acesso a página não permitida:', page);
                }
            });
        });
    </script>


</body>
</html>