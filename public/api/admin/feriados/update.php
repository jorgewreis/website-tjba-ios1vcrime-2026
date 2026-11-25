<?php
declare(strict_types=1);


require_once dirname(__DIR__, 4) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/admin/feriados/update.php');
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

$id = trim((string) ($parsed['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new FeriadosService($connection);
    $result = $service->update($id, $parsed);
    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('admin/feriados/update.php: falha interna.');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar feriado.'], JSON_UNESCAPED_UNICODE);
}
