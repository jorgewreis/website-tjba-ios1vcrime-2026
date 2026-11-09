<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['editaiscreate'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/EditaisService.php';

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$editalId = trim((string) ($parsed['edital_id'] ?? $parsed['editalId'] ?? $_GET['edital_id'] ?? ''));

if ($editalId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do edital é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new EditaisService($connection);
    $service->criarPrimeiraSubtarefa($editalId);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar subtarefas.'], JSON_UNESCAPED_UNICODE);
}
