<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/assunto-unlink.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$processoId = trim((string) ($payload['processo_id'] ?? ''));
$assuntoId = trim((string) ($payload['assunto_id'] ?? ''));

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($processoId === '' || $assuntoId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'processo_id e assunto_id são obrigatórios.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoAssuntoSyncService(Conection::getInstance()->getConnection());
    $service->unlinkManual($processoId, $assuntoId);
    echo json_encode(['action' => 'deleted'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao desvincular assunto: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/assunto-unlink.php')], JSON_UNESCAPED_UNICODE);
}
