<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/AssuntosRepository.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';
require_once __DIR__ . '/_auth.php';

processosApiRequireTrustedPost();
$usuarioId = processosApiRequireAccess();

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$processoId = trim((string) ($payload['processo_id'] ?? ''));
$codigo = trim((string) ($payload['codigo'] ?? ''));
$descricao = trim((string) ($payload['descricao'] ?? ''));

try {
    if ($processoId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'processo_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $codigoNormalizado = AssuntosRepository::normalizeCodigo($codigo);
    if ($codigoNormalizado === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Código do assunto é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();
    $service = new ProcessoAssuntoSyncService($connection);
    $inserted = $service->linkManual($processoId, $codigoNormalizado, $descricao);
    $assuntoRepo = new AssuntosRepository($connection);
    $assunto = $assuntoRepo->findByCodigo($codigoNormalizado);

    echo json_encode([
        'action' => $inserted ? 'created' : 'noop',
        'assunto' => $assunto === null ? null : [
            'id' => (string) ($assunto['id'] ?? ''),
            'codigo' => (string) ($assunto['codigo'] ?? $codigoNormalizado),
            'descricao' => (string) ($assunto['descricao'] ?? ''),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao vincular assunto: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
