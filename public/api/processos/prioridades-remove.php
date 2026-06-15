<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/SalaAudienciasPautaParteRepository.php';
require_once $basePath . '/app/services/ProcessoPrioridadesService.php';
require_once dirname(__DIR__) . '/sala-audiencias-pauta/_auth.php';

sala_audiencias_pauta_require_access('salapautaedit', true);
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
    $nPauta = (new SalaAudienciasPautaParteRepository($pdo))->countPartesMenoresAtivasPorProcesso($processoId);
    $svc = new ProcessoPrioridadesService($pdo);
    $svc->removerPorCodigoOuRotulo($processoId, $codigo, $nPauta);

    $state = $svc->getEstadoEdicaoPorProcessoId($processoId, $nPauta);

    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao remover prioridade: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
