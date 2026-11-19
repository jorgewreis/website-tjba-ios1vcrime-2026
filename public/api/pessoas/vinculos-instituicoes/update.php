<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pessoas/vinculos-instituicoes/update.php');
require_once dirname(__DIR__, 4) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultapartesedit', 'partesinstituicoesedit', 'partesinstituicoescreate'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
if (!is_file($basePath . '/app/helpers/Conection.php')) {
    $basePath = dirname(__DIR__, 4);
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/PessoasService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do vínculo é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new PessoasService($connection);
    if (!$service->updateVinculoInstituicao($id, $parsed)) {
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/pessoas/vinculos-instituicoes/update.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar vínculo.'], JSON_UNESCAPED_UNICODE);
}
