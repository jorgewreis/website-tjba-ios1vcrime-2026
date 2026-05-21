<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/_auth.php';

gmeRequirePageAccess('monitoramentogabinete', true);
$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$subtarefaId = trim((string) ($parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? ''));
$dataInicio = isset($parsed['data_inicio']) ? trim((string) $parsed['data_inicio']) : null;

if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);
    $resultado = $service->concluirSubtarefa($subtarefaId, $dataInicio);
    echo json_encode(array_merge(['success' => true], is_array($resultado) ? $resultado : []), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao concluir subtarefa: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
