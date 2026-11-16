<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['editaisedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/EditaisService.php';

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload) || !is_array($payload['edital_ids'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Lista de editais inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new EditaisService(Conection::getInstance()->getConnection());
    $count = $service->reconciliarEditais($payload['edital_ids']);
    echo json_encode(['success' => true, 'finalizados' => $count], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar etapas dos editais.'], JSON_UNESCAPED_UNICODE);
}
