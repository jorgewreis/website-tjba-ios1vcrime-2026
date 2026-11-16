<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/SalaAudienciasPautaParteRepository.php';
require_once $basePath . '/app/services/ProcessoPrioridadesService.php';

ApiAccessGuard::requireAccess(['salapautaedit'], true);

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$processoId = trim((string) ($payload['processo_id'] ?? ''));
$codigo = trim((string) ($payload['codigo'] ?? ''));
if ($processoId === '' || $codigo === '') {
    http_response_code(400);
    echo json_encode(['error' => 'processo_id e codigo são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Conection::getInstance()->getConnection();
    $svc = new ProcessoPrioridadesService($pdo);
    $svc->adicionarManualPorCodigoOuRotulo($processoId, $codigo);

    $nPauta = (new SalaAudienciasPautaParteRepository($pdo))->countPartesMenoresAtivasPorProcesso($processoId);
    $state = $svc->getEstadoEdicaoPorProcessoId($processoId, $nPauta);

    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar prioridade: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
