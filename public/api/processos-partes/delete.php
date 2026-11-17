<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['processospartesedit', 'consultaprocessosedit'], true);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('processos-partes: falha de conexão.');
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => 'Identificador do vínculo processo-parte é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $connection->beginTransaction();
    $processId = ProcessoSetorAuthorization::findProcessIdByLink($connection, $id);
    if ($processId === null) {
        $connection->commit();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!ProcessoSetorAuthorization::canModifyProcess(
        $connection,
        trim((string) ($_SESSION['id_usuario'] ?? '')),
        $processId
    )) {
        $connection->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!ProcessoSetorAuthorization::linkBelongsToProcess($connection, $id, $processId)) {
        $connection->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $repository = new ProcessosPartesRepository($connection);
    $removed = $repository->delete($id);
    $connection->commit();
    echo json_encode(['deleted' => $removed], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    error_log('processos-partes: falha ao desvincular parte.');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao desvincular parte.'], JSON_UNESCAPED_UNICODE);
}
