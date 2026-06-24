<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ProcessoClasseSyncService.php';
require_once __DIR__ . '/_auth.php';

processosApiRequireTrustedPost();
$usuarioId = processosApiRequireAccess();

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$processoId = trim((string) ($payload['processo_id'] ?? ''));
$codigo = (string) ($payload['codigo'] ?? '');
$nome = isset($payload['nome']) ? (string) $payload['nome'] : null;

try {
    if ($processoId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Identificador do processo é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new ProcessoClasseSyncService(Conection::getInstance()->getConnection());
    $result = $service->updateClasseManual($processoId, $codigo, $nome);

    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar classe do processo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
