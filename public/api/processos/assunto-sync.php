<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/assunto-sync.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$assuntos = $payload['assuntos'] ?? [];
if (!is_array($assuntos)) {
    $assuntos = [];
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $digits = CnjValidator::digits20OrThrow($numero);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoAssuntoSyncService(Conection::getInstance()->getConnection());
    $result = $service->syncFromDataJud($digits, $assuntos);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao sincronizar assuntos: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/assunto-sync.php')], JSON_UNESCAPED_UNICODE);
}
