<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ProcessoClasseSyncService.php';
require_once __DIR__ . '/_common.php';

processos_api_require_trusted_post();

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$digits = preg_replace('/\D+/', '', $numero);
$codigo = trim((string) ($payload['codigo'] ?? ''));
$descricao = trim((string) ($payload['descricao'] ?? ''));

try {
    processos_api_require_access();

    if (strlen($digits) !== 20) {
        http_response_code(400);
        echo json_encode(['error' => 'Número do processo inválido (20 dígitos).'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($codigo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Código da classe é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoClasseSyncService(Conection::getInstance()->getConnection());
    $result = $service->syncFromDataJud($digits, $codigo, $descricao);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao sincronizar classe: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
