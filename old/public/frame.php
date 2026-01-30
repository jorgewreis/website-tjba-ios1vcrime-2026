<?php
// Dependências base do sistema (configuração, segurança e acesso a dados).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';
require_once __DIR__ . '/../bd/Conection.php';

// Garante que a sessão esteja ativa antes de acessar dados do usuário.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Página solicitada (padrão: dashboard).
$page = $_GET['page'] ?? 'dashboard';

// Extrai o ID do usuário do retorno do validarToken.
$userId = null;

// Diretórios base para resolução de páginas internas.
$BASE  = realpath(__DIR__ . '/..');
$PAGES = $BASE . '/internal/pages';
$ERRORS = $BASE . '/internal/errors';

// Lista de páginas permitidas e seus caminhos.
$allowed_pages = [
    'dashboard'         => $PAGES . '/dashboard.php',
    'cejusc'            => $PAGES . '/cejusc.php',
    'editais'           => $PAGES . '/editais.php',
    'guias'             => $PAGES . '/guias.php',
    'medidasprotetivas' => $PAGES . '/medidasprotetivas.php',
    'oficioscartorio'   => $PAGES . '/oficioscartorio.php',
    'oficiosgabinete'   => $PAGES . '/oficiosgabinete.php',
    'cartasexpedidas'   => $PAGES . '/cartasexpedidas.php',
    'cartasrecebidas'   => $PAGES . '/cartasrecebidas.php',
    'produtividade'     => $PAGES . '/produtividade.php',

    '401'=> $ERRORS . '/401.php',
    '403'=> $ERRORS . '/403.php',
    '404'=> $ERRORS . '/404.php',
    '500'=> $ERRORS . '/500.php'
];


// Mapeia páginas para suas seções, usadas no controle de acesso.
$page_to_section = [
    'dashboard'         => 'All',
    'cartasexpedidas'   => 'Cartório',
    'cartasrecebidas'   => 'Cartório',
    'cejusc'            => 'Cartório',
    'editais'           => 'Cartório',
    'guias'             => 'Cartório',
    'medidasprotetivas' => 'Cartório',
    'oficioscartorio'   => 'Cartório',
    'oficiosgabinete'   => 'Gabinete',
    'produtividade'     => 'Cartório',
    'reuspresos'        => 'Gabinete'
];

// Verifica se o usuário possui perfil compatível com a seção exigida.
function hasAccessToSection(array $userPerfils, ?string $requiredSection): bool
{
    if ($requiredSection === null) {
        return true;
    }

    // Mapeamento de seções para perfis permitidos.
    $sectionMap = [
        'All' => ['ESTUDANTE', 'CONSULTOR', 'CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN'],
        'Cartório' => ['CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN'],
        'Gabinete' => ['GABINETE', 'ADMIN'],
        'Sala de Audiências' => ['AUDIENCIA', 'GABINETE', 'ADMIN'],
        'Estudantes' => ['ESTUDANTE', 'ADMIN'],
        'Consultor'  => ['CONSULTOR', 'ADMIN'],
        'Admin' => ['ADMIN']
    ];

    $requiredPerfils = $sectionMap[$requiredSection] ?? [];

    // Se algum perfil do usuário está na lista permitida, libera acesso.
    foreach ($userPerfils as $userPerfil) {
        if (in_array($userPerfil, $requiredPerfils, true)) {
            return true;
        }
    }

    return false;
}

// Responde com página de erro correspondente ao status HTTP.
function respondWithError(int $status, string $message, string $errorsDir): void
{
    http_response_code($status);
    $errorPage = $errorsDir . '/' . $status . '.php';

    if (is_file($errorPage)) {
        require $errorPage;
        exit;
    }

    echo $message;
    exit;
}

// Bloco centralizado de validações para sessão, usuário, página e acesso.
$usuario = new Usuario();
$user = null;
$userPerfils = [];
$pagePath = null;
$resolved = null;
$validationStatus = null;
$validationMessage = '';

do {
    if (empty($_SESSION['token'])) {
        $validationStatus = 401;
        $validationMessage = 'Acesso negado';
        break;
    }

    $user = $usuario->validarToken($_SESSION['token']);

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        $validationStatus = 401;
        $validationMessage = 'Sessão expirada';
        break;
    }

    if (is_array($user)) {
        $userId = $user['id_usuario'] ?? ($user['id'] ?? null);
    } elseif (is_object($user)) {
        $userId = $user->id_usuario ?? ($user->id ?? null);
    }

    if ($userId === null) {
        $userId = $_SESSION['id_usuario'] ?? null;
    }

    $userId = is_scalar($userId) ? trim((string)$userId) : '';

    if ($userId === '') {
        $validationStatus = 401;
        $validationMessage = 'Sessão inválida';
        break;
    }

    if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $userId)) {
        error_log('Sessão inválida: userId com formato inesperado: ' . var_export($userId, true));
        $validationStatus = 401;
        $validationMessage = 'Sessão inválida';
        break;
    }

    if (!isset($allowed_pages[$page])) {
        $validationStatus = 404;
        $validationMessage = 'Página não encontrada';
        break;
    }

    $userPerfils = $usuario->listarPerfisUsuario($userId);
    $requiredSection = $page_to_section[$page] ?? null;

    if (!hasAccessToSection($userPerfils, $requiredSection)) {
        error_log('Acesso negado: userId=' . $userId . ' perfis=' . implode(',', $userPerfils) . ' page=' . $page);
        $validationStatus = 403;
        $validationMessage = 'Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.';
        break;
    }

    $pagePath = $allowed_pages[$page];
    $resolved = realpath($pagePath);

    if ($resolved === false || !is_file($resolved)) {
        error_log('Arquivo de página não encontrado. page=' . $page . ' path=' . $pagePath . ' base=' . var_export($BASE, true));
        $validationStatus = 500;
        $validationMessage = 'Erro interno: página não localizada no servidor.';
        break;
    }
} while (false);

if ($validationStatus !== null) {
    respondWithError($validationStatus, $validationMessage, $ERRORS);
}

// Inclui a página solicitada.
require $resolved;