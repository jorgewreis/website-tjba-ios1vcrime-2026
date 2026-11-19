<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos-partes/list.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultaprocessos', 'processosparteslist'], false);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('processos-partes: falha de conexão.');
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$parteId = trim((string) ($_GET['parte_id'] ?? ''));

try {
    $repository = new ProcessosPartesRepository($connection);
    if ($processoId !== '') {
        $rows = $repository->findByProcessoId($processoId);
    } elseif ($parteId !== '') {
        $rows = $repository->findByParteId($parteId);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Informe processo_id ou parte_id.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ApiErrorReporter::logUnexpected($e, 'public/api/processos-partes/list.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar vínculos.'], JSON_UNESCAPED_UNICODE);
}
