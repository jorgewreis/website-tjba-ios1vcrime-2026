<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pessoas/telefones/delete.php');
require_once dirname(__DIR__, 4) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultapartesedit', 'partesinstituicoesedit', 'partesinstituicoescreate'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/PessoasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}
$id = trim((string) ($parsed['id'] ?? $_GET['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do telefone é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new PessoasService($connection);
    $removed = $service->deleteTelefone($id);
    echo json_encode(['deleted' => $removed], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/pessoas/telefones/delete.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao excluir telefone.'], JSON_UNESCAPED_UNICODE);
}
