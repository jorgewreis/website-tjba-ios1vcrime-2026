<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection  = Conection::getInstance()->getConnection();
$rawPayload  = file_get_contents('php://input') ?: '';
$parsed      = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$alocadoId = trim((string) ($parsed['alocado_id'] ?? ''));
$tipo      = trim((string) ($parsed['tipo'] ?? ''));

if ($alocadoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'alocado_id obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
if (!Security::isAuthenticated() || $userId === '') {
    Security::clearAuthenticatedSession();
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = new Usuario();
$token = trim((string) ($_SESSION['token'] ?? ''));
if ($token === '' || !$usuario->validarToken($token)) {
    Security::clearAuthenticatedSession();
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
$perfisGestao = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
$isGestao  = count(array_intersect($perfis, $perfisGestao)) > 0;

if (!AccessEvaluator::canAccessPage('cartasexpedidas', $perfis)) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para remover alocações de cartas expedidas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (in_array($tipo, ['rule', 'all'], true) && !$isGestao) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas gestores podem remover alocações por regra ou atribuição direta.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CartasExpedidasService($connection);
    $deleted  = $service->removeAlocado($alocadoId, $tipo, $userId, $isGestao);
    echo json_encode(['deleted' => $deleted], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover alocação: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
