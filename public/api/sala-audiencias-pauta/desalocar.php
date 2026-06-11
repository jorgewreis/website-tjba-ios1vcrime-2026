<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$alocadoId = trim((string) ($parsed['alocado_id'] ?? ''));
$tipo = trim((string) ($parsed['tipo'] ?? ''));

if ($alocadoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'alocado_id obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = salaAudienciasPautaRequireJsonAccess('salapautaedit', false);

$perfisGestao = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
$isGestao = count(array_intersect($perfis, $perfisGestao)) > 0;

if (in_array($tipo, ['rule', 'all'], true) && !$isGestao) {
    http_response_code(403);
    echo json_encode(['error' => 'Apenas gestores podem remover este tipo de alocação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new SalaAudienciasPautaService($connection);
    $deleted = $service->removeAlocado($alocadoId, $tipo, $userId);
    echo json_encode(['deleted' => $deleted], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover alocação: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
