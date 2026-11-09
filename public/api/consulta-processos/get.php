<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultaprocessos'], false);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ConsultaProcessosService.php';

$connection = Conection::getInstance()->getConnection();

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do processo é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new ConsultaProcessosService($connection);
    $processo = $service->get($id);
    if ($processo === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode($processo, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('API error (public/api/consulta-processos/get.php): ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar processo.'], JSON_UNESCAPED_UNICODE);
}
