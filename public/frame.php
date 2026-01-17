<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';
require_once __DIR__ . '/../bd/Conection.php';

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
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

$userId = Security::getUserId();
$page   = $_GET['page'] ?? 'dashboard';

$allowed_pages = [
    'dashboard'         => __DIR__ . '/../internal/pages/dashboard.php',
    'cejusc'            => __DIR__ . '/../internal/pages/cejusc.php',
    'editais'           => __DIR__ . '/../internal/pages/editais.php',
    'guias'             => __DIR__ . '/../internal/pages/guias.php',
    'medidasprotetivas' => __DIR__ . '/../internal/pages/medidasprotetivas.php',
    'oficioscartorio'   => __DIR__ . '/../internal/pages/oficioscartorio.php',
    'oficiosgabinete'   => __DIR__ . '/../internal/pages/oficiosgabinete.php',
    'cartasexpedidas'   => __DIR__ . '/../internal/pages/cartasexpedidas.php',
    'cartasrecebidas'   => __DIR__ . '/../internal/pages/cartasrecebidas.php',
    'produtividade'     => __DIR__ . '/../internal/pages/produtividade.php',
];

// Mapa de páginas para seções de menu (controle de acesso)
$page_to_section = [
    'dashboard'         => null, // Sempre acessível
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

/**
 * Busca os perfis do usuário
 * @param int $userId ID do usuário
 * @return array Array com perfis do usuário
 */
function getUserPerfils(int $userId): array {
    try {
        $pdo = Conection::getInstance()->getConnection();
        $stmt = $pdo->prepare("
            SELECT DISTINCT perfil 
            FROM usuario_perfil 
            WHERE usuario = ? 
            ORDER BY perfil ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Erro ao buscar perfis do usuário {$userId}: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se o usuário tem acesso à seção
 * @param array $userPerfils Perfis do usuário
 * @param string|null $requiredSection Seção requerida
 * @return bool True se tem acesso
 */
function hasAccessToSection(array $userPerfils, ?string $requiredSection): bool {
    // Dashboard sempre acessível
    if ($requiredSection === null) {
        return true;
    }

    // Verificar contra o mapa de perfis
    $sectionMap = [
        'Cartório' => ['SERVIDOR', 'ADMIN'],
        'Gabinete' => ['SERVIDOR', 'ADMIN'],
        'Sala de Audiências' => ['SERVIDOR', 'ADMIN'],
        'Estudantes' => ['ESTUDANTE', 'ADMIN'],
        'Admin' => ['ADMIN']
    ];

    $requiredPerfils = $sectionMap[$requiredSection] ?? [];
    
    // Verificar se usuário tem algum dos perfis requeridos
    foreach ($userPerfils as $userPerfil) {
        if (in_array($userPerfil, $requiredPerfils, true)) {
            return true;
        }
    }

    return false;
}

// Validar se página existe
if (!isset($allowed_pages[$page])) {
    http_response_code(404);
    die("Página não encontrada");
}

// Validar acesso à seção
$requiredSection = $page_to_section[$page] ?? null;
$userPerfils = getUserPerfils($userId);

if (!hasAccessToSection($userPerfils, $requiredSection)) {
    http_response_code(403);
    die('Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.');
}

// Carregar a página dentro do iframe
require $allowed_pages[$page];
