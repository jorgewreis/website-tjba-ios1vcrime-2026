<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';
require_once __DIR__ . '/../app/helpers/Conection.php';

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
$userSetores = [];

$sessionUserId = trim((string) ($_SESSION['id_usuario'] ?? ($user['id_usuario'] ?? '')));
if ($sessionUserId !== '') {
    try {
        $connection = Conection::getInstance()->getConnection();
        $stmtSetores = $connection->prepare("
            SELECT s.nome
            FROM usuario_setor us
            INNER JOIN setores s ON s.id_setor = us.setor
            WHERE us.usuario = ?
        ");
        $stmtSetores->execute([$sessionUserId]);
        $userSetores = $stmtSetores->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userSetores)) {
            $stmtFallback = $connection->prepare("SELECT instituicao FROM usuarios WHERE id_usuario = ? LIMIT 1");
            $stmtFallback->execute([$sessionUserId]);
            $fallbackSetor = trim((string) $stmtFallback->fetchColumn());
            if ($fallbackSetor !== '' && $fallbackSetor !== '-') {
                $userSetores = [$fallbackSetor];
            }
        }
    } catch (Throwable $e) {
        $userSetores = [];
    }
}

$userPerfisDb = [];
if ($sessionUserId !== '') {
    try {
        $userPerfisDb = $usuario->listarPerfisUsuario($sessionUserId);
    } catch (Throwable $e) {
        $userPerfisDb = [];
    }
}

require_once __DIR__ . '/../app/helpers/UserAccessHelper.php';
require_once __DIR__ . '/../app/helpers/AvatarCatalog.php';
require_once __DIR__ . '/../app/helpers/AvatarInitialsPalette.php';
$isUsuarioExterno = $sessionUserId !== '' && UserAccessHelper::isUsuarioExterno($userPerfisDb);
$hasPerfilEstudante = false;
foreach ($userPerfisDb as $p) {
    if (strtoupper(trim((string) $p)) === 'ESTUDANTE') {
        $hasPerfilEstudante = true;
        break;
    }
}
$defaultHomePage = $isUsuarioExterno ? 'dashboardexterno' : 'dashboard';

/** Nome completo e linha de perfil/lotação para o badge do avatar (navbar). */
$userDisplayFullName = trim((string) ($userName ?? ''));
$avatarBadgePerfisLine = '';
$avatarNomeNav = '';
$avatarSobrenomeNav = '';

if ($sessionUserId !== '') {
    try {
        $pdoName = Conection::getInstance()->getConnection();
        $stName = $pdoName->prepare('SELECT nome, sobrenome FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stName->execute([$sessionUserId]);
        $rowName = $stName->fetch(PDO::FETCH_ASSOC);
        if ($rowName) {
            $avatarNomeNav = trim((string) ($rowName['nome'] ?? ''));
            $avatarSobrenomeNav = trim((string) ($rowName['sobrenome'] ?? ''));
            $userDisplayFullName = trim($avatarNomeNav . ' ' . $avatarSobrenomeNav);
        }
    } catch (Throwable $e) {
        // mantém $userName
    }

    $badgeParts = [];
    foreach ($userPerfisDb as $p) {
        $u = strtoupper(trim((string) $p));
        if ($u === 'DIRETORA' || $u === 'MAGISTRADA') {
            $u = 'CHEFIA';
        }
        if ($u !== '' && !in_array($u, $badgeParts, true)) {
            $badgeParts[] = $u;
        }
    }

    $suppressSetor = in_array('ADMIN', $badgeParts, true) || in_array('CHEFIA', $badgeParts, true);
    if (!$suppressSetor) {
        $needSetor = false;
        foreach (['ESTAGIARIO', 'SERVIDOR'] as $role) {
            if (in_array($role, $badgeParts, true)) {
                $needSetor = true;
                break;
            }
        }
        if ($needSetor) {
            foreach ($userSetores as $sn) {
                $nomeSetor = trim((string) $sn);
                if ($nomeSetor === '') {
                    continue;
                }
                $t = mb_strtolower($nomeSetor, 'UTF-8');
                $cod = '';
                if (strpos($t, 'gab') !== false) {
                    $cod = 'GABINETE';
                } elseif (strpos($t, 'cart') !== false) {
                    $cod = 'CARTORIO';
                } elseif (strpos($t, 'audi') !== false) {
                    $cod = 'AUDIENCIA';
                } elseif (strpos($t, 'vara') !== false) {
                    $cod = 'VARA';
                } else {
                    $cod = strtoupper(preg_replace('/\s+/u', ' ', $nomeSetor));
                }
                if ($cod !== '' && !in_array($cod, $badgeParts, true)) {
                    $badgeParts[] = $cod;
                }
            }
        }
    }

    $avatarBadgePerfisLine = implode(' | ', $badgeParts);
}

$avatarPalette = AvatarInitialsPalette::getOrCreate();
$avatarNavbarDisplay = [
    'mode' => 'initials',
    'initials' => AvatarCatalog::initialsFromNames($avatarNomeNav, $avatarSobrenomeNav),
    'url' => null,
];
if ($sessionUserId !== '') {
    $avatarNavbarDisplay = $usuario->getAvatarNavbarDisplay($sessionUserId, $isUsuarioExterno, $avatarNomeNav, $avatarSobrenomeNav);
}

$avatarSummaryTitle = $userDisplayFullName;
if ($avatarBadgePerfisLine !== '') {
    $avatarSummaryTitle .= ' | ' . $avatarBadgePerfisLine;
}

$flashProfileSavedToast = null;
if (!empty($_SESSION['flash_profile_saved_toast'])) {
    $flashProfileSavedToast = (string) $_SESSION['flash_profile_saved_toast'];
    unset($_SESSION['flash_profile_saved_toast']);
}
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
    <style>
        /* Navbar: avatar + resumo à esquerda (modelo model-page-home-header) */
        .navbar-nav-left {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            flex: 1;
            min-width: 0;
            position: relative;
        }
        .nav-avatar-navbar-wrap {
            position: relative;
            flex-shrink: 0;
            outline: none;
        }
        .nav-new-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 120px;
            height: 64px;
            margin-left: -24px;
            border-radius: 6px 0 0 6px;
            overflow: hidden;
            background: var(--color-border-subtle);
        }
        .nav-new-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px 0 0 6px;
        }
        .nav-new-avatar .nav-new-avatar-initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 1.25rem;
            font-weight: 600;
            border-radius: 6px 0 0 6px;
        }
        .navbar-nav-left .layout-menu-toggle {
            position: absolute;
            left: 58px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            margin-right: 0;
            z-index: 2;
        }
        .navbar-nav-left .layout-menu-toggle .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
        }
        .nav-item-user-summary {
            display: none;
            align-items: center;
            flex: 0 1 auto;
            max-width: 400px;
            margin-left: 10px;
            min-width: 0;
        }
        .user-summary-inline {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            max-width: 400px;
            min-width: 0;
            text-align: left;
            margin-left: 10px;
        }
        .user-summary-name {
            font-weight: 600;
            font-size: 14px;
            line-height: 1.25;
            color: var(--color-text-primary);
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-summary-roles {
            font-size: 11px;
            line-height: 1.3;
            color: var(--color-text-secondary);
            margin-top: 2px;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (min-width: 1280px) {
            .nav-item-user-summary {
                display: flex;
            }
            /* Com o resumo visível à esquerda, não exibe o popover no hover */
            .nav-avatar-navbar-wrap .avatar-popover {
                display: none !important;
            }
        }

        /* Tooltip nome/perfis em telas estreitas */
        .avatar-popover {
            position: absolute;
            inset-block-start: calc(100% + 8px);
            inset-inline-start: 0;
            inset-inline-end: auto;
            min-width: 220px;
            max-width: min(400px, 92vw);
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--color-background-elevated);
            border: 1px solid var(--color-border);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            z-index: 1100;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.15s ease, visibility 0.15s ease;
        }
        .nav-avatar-navbar-wrap:hover .avatar-popover,
        .nav-avatar-navbar-wrap:focus-within .avatar-popover {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .avatar-popover-name {
            font-weight: 600;
            font-size: 12px;
            color: var(--color-text-primary);
            line-height: 1.35;
            margin-bottom: 6px;
            word-break: break-word;
        }
        .avatar-popover-roles {
            font-size: 9px;
            color: var(--color-text-secondary);
            line-height: 1.4;
            word-break: break-word;
        }
    </style>
    
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
                        <a class="menu-link" data-target="<?php echo htmlspecialchars($defaultHomePage, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fi fi-rs-home"></i>
                            <span class="text"><?php echo $isUsuarioExterno ? 'Início' : 'Dashboard'; ?></span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="layout-page">            
                <!-- NAVBAR -->
                <nav class="layout-navbar">
                    <div class="navbar-nav-left">
                        <div class="nav-avatar-navbar-wrap" tabindex="0" aria-label="Usuário logado" aria-describedby="user-summary-navbar">
                            <div class="nav-new-avatar">
                                <?php if (($avatarNavbarDisplay['mode'] ?? '') === 'image' && !empty($avatarNavbarDisplay['url'])): ?>
                                    <img src="<?php echo htmlspecialchars((string) $avatarNavbarDisplay['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                <?php else: ?>
                                    <span class="nav-new-avatar-initials" style="background-color: <?php echo htmlspecialchars($avatarPalette['bg'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($avatarPalette['fg'], ENT_QUOTES, 'UTF-8'); ?>;"><?php echo htmlspecialchars((string) ($avatarNavbarDisplay['initials'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div id="avatar-profile-popover" class="avatar-popover" role="tooltip">
                                <div class="avatar-popover-name"><?php echo htmlspecialchars($userDisplayFullName !== '' ? $userDisplayFullName : 'Usuário', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="avatar-popover-roles"><?php echo htmlspecialchars($avatarBadgePerfisLine !== '' ? $avatarBadgePerfisLine : '—', ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <div class="layout-menu-toggle">
                            <a class="nav-item nav-link">
                                <i class="fi fi-br-menu-burger"></i>
                            </a>
                        </div>
                        <div class="nav-item nav-item-user-summary">
                            <div class="user-summary-inline" id="user-summary-navbar" title="<?php echo htmlspecialchars($avatarSummaryTitle !== '' ? $avatarSummaryTitle : 'Usuário', ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="user-summary-name"><?php echo htmlspecialchars($userDisplayFullName !== '' ? $userDisplayFullName : 'Usuário', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="user-summary-roles"><?php echo htmlspecialchars($avatarBadgePerfisLine !== '' ? $avatarBadgePerfisLine : '—', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="navbar-nav-right">
                        <ul class="navbar-nav">
                            <li class="nav-item" data-target="<?php echo htmlspecialchars($defaultHomePage, ENT_QUOTES, 'UTF-8'); ?>">
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
                                <a class="nav-link disabled">
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
                        </ul>
                    </div>
                </nav>
                <!-- /NAVBAR -->
                
                <!-- CONTENT WRAPER -->
                <div class="content-wrapper">
                    <iframe class="target-page" id="frame" src="/frame.php?page=<?php echo htmlspecialchars($defaultHomePage, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
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
        /** Página inicial do iframe (dashboard interno ou início externo). Alinhado ao PHP $defaultHomePage. */
        const DEFAULT_HOME_PAGE = <?php echo json_encode($defaultHomePage, JSON_UNESCAPED_UNICODE); ?>;
        /** Mesmo critério de app/helpers/UserAccessHelper.php — menu não deve exibir rotas internas. */
        const IS_USUARIO_EXTERNO = <?php echo json_encode($isUsuarioExterno, JSON_UNESCAPED_UNICODE); ?>;
        /** Perfil ESTUDANTE no cadastro (externo: menu só CEP; ver public/frame.php). */
        const HAS_PERFIL_ESTUDANTE = <?php echo json_encode($hasPerfilEstudante, JSON_UNESCAPED_UNICODE); ?>;
        /** Consultor externo: partes, instituições e CEP (alinhado a frame.php — ConsultasPartesInstituicoes + ConsultasCep). */
        const MENU_TARGETS_CONSULTAS_EXTERNO_CONSULTOR = new Set(['consultapartes', 'instituicoespublicas', 'consultacep']);
        /** Estudante externo: apenas CEP. */
        const MENU_TARGETS_CONSULTAS_EXTERNO_ESTUDANTE = new Set(['consultacep', 'estudapauta']);

        /** Mesma regra de app/access/AccessEvaluator.php (recalculada no cliente após fetch de perfis). */
        const COMPOSITE_PROFILE_RE = /^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/;

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
                    { icon: 'fi fi-rs-sensor-on', label: 'Monitoramento Eletrônico', target: 'monitoramentogabinete' },
                    { icon: 'fi fi-rs-lock', label: 'Réus Presos', target: 'reuspresos' }
                ]
            },
            {
                section: 'Sala de Audiências',
                items: [                    
                    { icon: 'fi fi-rs-clipboard-list-check', label: 'Pauta de Audiências', target: 'salapauta' },
                    { icon: 'fi fi-rs-document', label: 'Atas de Audiências', target: 'salaata' },
                    { icon: 'fi fi-rs-screen', label: 'Painel em tempo real', target: 'salapainel' }
                ]
            },
            {
                section: 'Estudantes',
                items: [
                    { icon: 'fi fi-rs-clipboard-list-check', label: 'Pauta de Audiências', target: 'estudapauta' },
                    { icon: 'fi fi-rs-document', label: 'Atas de Audiências', target: 'estudaata' },
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
                section: 'Admin',
                items: [
                    { icon: 'fi fi-rs-id-badge', label: 'Usuários', target: 'usuarioslist', allowedPerfis: ['ADMIN'] },
                    { icon: 'fi fi-rs-calendar', label: 'Feriados', target: 'feriadoslist', allowedPerfis: ['ADMIN', 'CHEFIA', 'DIRETORA'] }
                ]
            }
        ];

        const INITIAL_USER_PERFIS = <?php echo json_encode(array_values(array_filter(array_map(static function ($p) {
            return trim((string) $p);
        }, $userPerfisDb))), JSON_UNESCAPED_UNICODE); ?>;
        const FALLBACK_PERFIS = <?php echo json_encode($userProfile ? [$userProfile] : []); ?>;
        const USER_SETORES = <?php echo json_encode(array_values(array_filter(array_map(static function ($s) {
            return trim((string) $s);
        }, $userSetores))), JSON_UNESCAPED_UNICODE); ?>;

        /**
         * Unifica fontes de perfil, normaliza maiúsculas e mapeia perfis legados para CHEFIA (mesmo critério do sistema).
         */
        function normalizePerfis(perfis) {
            const out = [];
            const seen = new Set();
            (perfis || []).forEach((raw) => {
                let p = String(raw || '').trim().toUpperCase();
                if (p === 'DIRETORA' || p === 'MAGISTRADA') {
                    p = 'CHEFIA';
                }
                if (!p || seen.has(p)) {
                    return;
                }
                seen.add(p);
                out.push(p);
            });
            return out;
        }

        async function fetchUserPerfis() {
            let fromApi = [];
            try {
                const response = await fetch('/admin/api/getPerfis.php');

                if (response.ok) {
                    const result = await response.json();
                    if (result.success && Array.isArray(result.perfis) && result.perfis.length > 0) {
                        fromApi = result.perfis;
                    }
                }
            } catch (error) {
                fromApi = [];
            }

            return normalizePerfis([].concat(INITIAL_USER_PERFIS || [], fromApi, FALLBACK_PERFIS || []));
        }

        function computePermittedMenuSectionsFromPerfis(perfis) {
            const norm = normalizePerfis(perfis);
            if (IS_USUARIO_EXTERNO) {
                if (HAS_PERFIL_ESTUDANTE) {
                    return ['Consultas', 'Estudantes'];
                }
                return ['Consultas'];
            }
            if (norm.includes('ADMIN') || norm.includes('CHEFIA')) {
                return ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Consultas', 'Admin'];
            }
            const sections = [];
            norm.forEach((p) => {
                const m = COMPOSITE_PROFILE_RE.exec(p);
                if (m) {
                    if (m[2] === 'CARTORIO') {
                        sections.push('Cartório');
                    } else if (m[2] === 'GABINETE') {
                        sections.push('Gabinete');
                    } else {
                        sections.push('Sala de Audiências');
                    }
                }
            });
            let hasComposite = false;
            norm.forEach((p) => {
                if (COMPOSITE_PROFILE_RE.test(p)) {
                    hasComposite = true;
                }
            });
            if (hasComposite) {
                sections.push('Consultas');
            }
            if (norm.some((p) => p.indexOf('SERVIDOR-') === 0)) {
                sections.push('Estudantes');
            }
            const order = ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Consultas', 'Admin'];
            const uniq = [...new Set(sections)];
            const ordered = [];
            order.forEach((label) => {
                if (uniq.includes(label)) {
                    ordered.push(label);
                }
            });
            return ordered;
        }

        function getPermittedSections(perfis) {
            return new Set(computePermittedMenuSectionsFromPerfis(perfis));
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
            const n = normalizePerfis(perfis);
            return item.allowedPerfis.some((p) => {
                const u = String(p || '').trim().toUpperCase();
                const mapped = u === 'DIRETORA' || u === 'MAGISTRADA' ? 'CHEFIA' : u;
                return n.includes(mapped);
            });
        }

        /**
         * Visibilidade final do item: perfis admin + filtro de alvos para usuário externo (estudante/consultor).
         */
        function itemVisibleForUser(item, perfis) {
            if (!itemAllowedForUser(item, perfis)) {
                return false;
            }
            if (!IS_USUARIO_EXTERNO) {
                return true;
            }
            const t = (item.target || '').trim();
            if (!t) {
                return true;
            }
            if (HAS_PERFIL_ESTUDANTE) {
                return MENU_TARGETS_CONSULTAS_EXTERNO_ESTUDANTE.has(t);
            }
            return MENU_TARGETS_CONSULTAS_EXTERNO_CONSULTOR.has(t);
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
                    if (!itemVisibleForUser(item, perfis)) {
                        return;
                    }
                    if (item.type === 'submenu') {
                        const children = (item.children || []).filter(c => itemVisibleForUser(c, perfis));
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
            buildMenuForSections(permittedSections, normalizePerfis(perfis));
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

        function normalizeMenuTarget(page) {
            const p = String(page || '').trim().toLowerCase();
            if (!p) return '';

            if (p === 'dashboard' || p === 'dashboardexterno') {
                return String(DEFAULT_HOME_PAGE || 'dashboard').toLowerCase();
            }

            if (p === 'usuarios' || p === 'usuariosedit' || p === 'usuarioscreate') return 'usuarioslist';
            if (p.startsWith('feriados')) return 'feriadoslist';

            if (p.endsWith('edit')) return p.slice(0, -4);
            if (p.endsWith('create')) return p.slice(0, -6);
            if (p.endsWith('rules')) return p.slice(0, -5);

            return p;
        }

        function setMenuActiveByTarget(target) {
            const normalized = normalizeMenuTarget(target);
            if (!normalized) return;

            const menuInner = document.querySelector('.menu-inner');
            if (!menuInner) return;

            menuInner.querySelectorAll('.menu-item').forEach((item) => item.classList.remove('active'));

            const link = menuInner.querySelector('.menu-link[data-target="' + normalized + '"]');
            if (!link) return;

            const item = link.closest('.menu-item');
            if (item) item.classList.add('active');

            const parentSub = link.closest('.menu-sub');
            const parentItem = parentSub ? parentSub.closest('.menu-item') : null;
            if (parentItem) parentItem.classList.add('active');
        }

        function getFramePageParam() {
            if (!frame) return '';

            // Preferir a URL real do iframe (após redirecionamentos internos).
            try {
                const cw = frame.contentWindow;
                if (cw && cw.location) {
                    const page = new URLSearchParams(cw.location.search || '').get('page');
                    if (page) return page;
                }
            } catch (e) {
                // fallback em caso de restrição de acesso
            }

            try {
                const url = new URL(frame.src || '', window.location.origin);
                return url.searchParams.get('page') || '';
            } catch (e) {
                return '';
            }
        }

        function syncMenuFromFrameSrc() {
            const page = getFramePageParam();
            if (page) setMenuActiveByTarget(page);
        }

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

            setMenuActiveByTarget(page);
            frame.src = '/frame.php?page=' + encodeURIComponent(page);
            const layoutWrapper = document.querySelector('.view.layout-wraper');
            const isDesktop = layoutWrapper?.classList.contains('menu-fixed');

            if (!isDesktop) {
                window.menuController?.close?.();
            }
        });

        if (frame) {
            frame.addEventListener('load', syncMenuFromFrameSrc);
            setTimeout(syncMenuFromFrameSrc, 0);
            // Backup para mudanças de rota internas no iframe.
            setInterval(syncMenuFromFrameSrc, 500);
        }

        document.addEventListener('DOMContentLoaded', function () {
            var flashMsg = <?php echo json_encode($flashProfileSavedToast, JSON_UNESCAPED_UNICODE); ?>;
            if (flashMsg && window.App && App.Toast && App.Toast.show) {
                App.Toast.show({ type: 'success', variant: 'full', title: 'Sucesso', message: flashMsg });
            }
        });
    </script>
    
</body>

</html>
