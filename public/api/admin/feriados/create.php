<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 4);
require_once dirname(__DIR__) . '/_auth_admin_diretora_json.php';
$connection = requireAdminOuDiretoraJson($basePath);
require_once $basePath . '/app/helpers/BootstrapHelper.php';
if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $basePath . '/app/services/FeriadosService.php';

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

try {
    $service = new FeriadosService($connection);
    $result = $service->create($parsed);
    http_response_code(201);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('admin/feriados/create.php: falha interna.');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao cadastrar feriado.'], JSON_UNESCAPED_UNICODE);
}
