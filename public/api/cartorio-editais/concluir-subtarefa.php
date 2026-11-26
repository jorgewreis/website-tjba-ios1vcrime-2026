<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-editais/concluir-subtarefa.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['editaisedit'], true);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CartorioSubtarefaUsuarioSessao.php';
require_once $basePath . '/app/services/EditaisService.php';
require_once $basePath . '/app/services/DashboardResumoService.php';

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$subtarefaId = trim((string) ($parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? $_GET['subtarefa_id'] ?? ''));
$dadosEspecificos = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao(
    $connection,
    $parsed['dados_especificos'] ?? null
);

if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $connection->beginTransaction();
    $before = DashboardResumoService::statusAnterior($connection, 'editais_subtarefas', $subtarefaId);
    $service = new EditaisService($connection);
    $resultado = $service->concluirSubtarefa($subtarefaId, $dadosEspecificos);
    if (($resultado['replayed'] ?? false) !== true) {
        DashboardResumoService::registrarConclusao($connection, $before, 'editais', 'CARTORIO', trim((string)($_SESSION['id_usuario'] ?? '')));
    }
    $connection->commit();
    echo json_encode(array_merge(['success' => true], is_array($resultado) ? $resultado : []), JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/cartorio-editais/concluir-subtarefa.php');
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao concluir subtarefa.'], JSON_UNESCAPED_UNICODE);
}
