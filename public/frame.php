<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';
require_once __DIR__ . '/../bd/Conection.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['token'])) {
    http_response_code(401);
    die('Acesso negado');
}

$usuario = new Usuario();
$user = $usuario->validarToken($_SESSION['token']);

if (!$user) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    die('Sessão expirada');
}

$page = $_GET['page'] ?? 'dashboard';

/*
  userId é VARCHAR.
  Preferência: id vindo do validarToken (mais confiável).
  Fallback: id_usuario da sessão.
*/
$userId = null;

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
    http_response_code(401);
    die('Sessão inválida');
}

if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $userId)) {
    error_log('Sessão inválida: userId com formato inesperado: ' . var_export($userId, true));
    http_response_code(401);
    die('Sessão inválida');
}

$BASE  = realpath(__DIR__ . '/..');
$PAGES = $BASE . '/internal/pages';

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
];


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
];

function hasAccessToSection(array $userPerfils, ?string $requiredSection): bool
{
    if ($requiredSection === null) {
        return true;
    }

    $sectionMap = [
        'All' => ['ESTUDANTE', 'SERVIDOR', 'ADMIN'],
        'Cartório' => ['SERVIDOR', 'ADMIN'],
        'Gabinete' => ['SERVIDOR', 'ADMIN'],
        'Sala de Audiências' => ['SERVIDOR', 'ADMIN'],
        'Estudantes' => ['ESTUDANTE', 'ADMIN'],
        'Admin' => ['ADMIN'],
    ];

    $requiredPerfils = $sectionMap[$requiredSection] ?? [];

    foreach ($userPerfils as $userPerfil) {
        if (in_array($userPerfil, $requiredPerfils, true)) {
            return true;
        }
    }

    return false;
}

if (!isset($allowed_pages[$page])) {
    http_response_code(404);
    die('Página não encontrada');
}

$userPerfils = $usuario->listarPerfisUsuario($userId);

$requiredSection = $page_to_section[$page] ?? null;

if (!hasAccessToSection($userPerfils, $requiredSection)) {
    http_response_code(403);
    error_log('Acesso negado: userId=' . $userId . ' perfis=' . implode(',', $userPerfils) . ' page=' . $page);
    die('Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.');
}

if (!isset($allowed_pages[$page])) {
    http_response_code(404);
    die('Página não encontrada');
}

$pagePath = $allowed_pages[$page];
$resolved = realpath($pagePath);

if ($resolved === false || !is_file($resolved)) {
    error_log('Arquivo de página não encontrado. page=' . $page . ' path=' . $pagePath . ' base=' . var_export($BASE, true));
    http_response_code(500);
    die('Erro interno: página não localizada no servidor.');
}

require $resolved;
