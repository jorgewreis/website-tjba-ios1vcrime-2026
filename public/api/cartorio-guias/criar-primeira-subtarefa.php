<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['guiasedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GuiasService.php';

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$guiaId = trim((string) ($parsed['id'] ?? $parsed['guia_id'] ?? $parsed['guiaId'] ?? $_GET['id'] ?? $_GET['guia_id'] ?? ''));
$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

if ($guiaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da guia é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new GuiasService($connection);
    $service->criarPrimeiraSubtarefa($guiaId, $usuarioId !== '' ? $usuarioId : null);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar/ativar subtarefas: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
