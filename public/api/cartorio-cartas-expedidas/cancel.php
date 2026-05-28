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

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador obrigatório para cancelar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CartasExpedidasService($connection);
    $updated = $service->cancel($id);
    echo json_encode(['success' => $updated], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao cancelar tarefa: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
