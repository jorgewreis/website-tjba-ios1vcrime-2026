<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';
require_once __DIR__ . '/_auth.php';

$userId = cartorio_cartas_expedidas_require_json_access('cartasexpedidasedit');
$connection = Conection::getInstance()->getConnection();
$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$entityId = trim((string) ($parsed['id'] ?? ''));
$targetId = trim((string) ($parsed['usuario_id'] ?? ''));

if ($entityId === '' || $targetId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Os campos id e usuario_id são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CartasExpedidasService($connection);
    $service->alocarUsuarioDirecionado($entityId, $targetId, $userId);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    $msg = $e->getMessage();
    http_response_code(strpos($msg, 'Sem permissão') !== false ? 403 : 400);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao alocar: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
