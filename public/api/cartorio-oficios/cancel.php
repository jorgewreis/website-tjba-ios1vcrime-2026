<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/OficiosCartorioService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$userId = $_SESSION['id_usuario'] ?? null;
if ($userId === null || trim((string) $userId) === '') {
    http_response_code(403);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

try {
    $service = new OficiosCartorioService($connection);
    $updated = $service->cancel($id, $uid !== '' ? $uid : null);
    $__mir = $updated;
    if ($__mir) {
        require_once $basePath . '/app/helpers/CartorioProdutividade.php';
        CartorioProdutividade::mirrorEstornoSaldoSession($connection, $id, 'cartorio-oficios', 'Ofício cancelado.');
    }
    echo json_encode(['success' => $updated], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao cancelar ofício: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
