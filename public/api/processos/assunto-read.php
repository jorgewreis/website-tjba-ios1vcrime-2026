<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/assunto-read.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultaprocessos'], false);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';

$rawNumero = $_GET['numero'] ?? '';
if ($rawNumero === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (is_array($payload)) {
        $rawNumero = $payload['numero'] ?? '';
    }
}

$digits = preg_replace('/\D+/', '', (string) $rawNumero);

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strlen($digits) !== 20) {
        http_response_code(400);
        echo json_encode(['error' => 'Informe o número do processo com 20 dígitos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoAssuntoSyncService(Conection::getInstance()->getConnection());
    $data = $service->readByNumero20($digits);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('processos/assunto-read.php: falha interna.');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar assuntos do processo: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/assunto-read.php')], JSON_UNESCAPED_UNICODE);
}
