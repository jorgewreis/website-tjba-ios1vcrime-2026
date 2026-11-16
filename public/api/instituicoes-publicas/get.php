<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['instituicoespublicas']);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('instituicoes-publicas/get.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da instituição é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $instituicao = $service->find($id);
    if ($instituicao === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Instituição não encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode($instituicao, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Instituições públicas: ' . get_class($exception));
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
}
