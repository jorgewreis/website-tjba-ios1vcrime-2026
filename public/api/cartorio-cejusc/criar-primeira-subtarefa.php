<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/CejuscService.php';

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$tarefaId = trim((string) ($parsed['id'] ?? $parsed['tarefa_id'] ?? $parsed['tarefaId'] ?? $parsed['carta_id'] ?? $_GET['tarefa_id'] ?? ''));
$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

if ($tarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da tarefa Cejusc é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new CejuscService($connection);
    $service->criarPrimeiraSubtarefa($tarefaId, $usuarioId !== '' ? $usuarioId : null);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar/ativar subtarefas: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
