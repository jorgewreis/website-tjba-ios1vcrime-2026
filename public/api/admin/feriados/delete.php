<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 4);
require_once dirname(__DIR__) . '/_auth_admin_diretora_json.php';
$connection = requireAdminOuDiretoraJson($basePath);

require_once $basePath . '/app/services/FeriadosService.php';

$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));

if ($id === '') {
    $payload = file_get_contents('php://input') ?: '';
    $parsed = json_decode($payload, true);
    if (is_array($parsed) && isset($parsed['id'])) {
        $id = trim((string) $parsed['id']);
    }
}

if ($id === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Identificador obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new FeriadosService($connection);
    $result = $service->delete($id);
    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir feriado: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
