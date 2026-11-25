<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-oficios/concluir-subtarefa.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['oficioscartorioedit'], true);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/OficiosCartorioService.php';
require_once $basePath . '/app/services/DashboardResumoService.php';

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

$subtarefaId = trim((string) ($parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? $_GET['subtarefa_id'] ?? ''));
if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$uid = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

try {
    $before = DashboardResumoService::statusAnterior($connection, 'oficios_cartorio_subtarefas', $subtarefaId);
    $service = new OficiosCartorioService($connection);
    $resultado = $service->concluirSubtarefa($subtarefaId, $uid !== '' ? $uid : null);
    DashboardResumoService::registrarConclusao($connection, $before, 'oficios', 'CARTORIO', $uid);
    echo json_encode(array_merge(['success' => true], is_array($resultado) ? $resultado : []), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao concluir subtarefa: ' . ApiErrorReporter::genericMessage($exception, 'public/api/cartorio-oficios/concluir-subtarefa.php')], JSON_UNESCAPED_UNICODE);
}
