<?php
declare(strict_types=1);


require_once dirname(__DIR__, 4) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/instituicoes-publicas/setores/update.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
if (!is_file($basePath . '/app/helpers/Conection.php')) {
    $basePath = dirname(__DIR__, 4);
}
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['instituicoespublicasedit'], true);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('instituicoes-publicas/setores/update.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'id do setor é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['nome_setor', 'sigla', 'ativo'];
$data = array_intersect_key($parsed, array_flip($allowed));
if ($data === []) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $service->updateSetor($id, $data);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar setor.'], JSON_UNESCAPED_UNICODE);
}
