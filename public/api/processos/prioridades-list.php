<?php
declare(strict_types=1);

/**
 * Lista estado de edição das prioridades do processo (catálogo, bloqueios, linhas atuais).
 */
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/SalaAudienciasPautaParteRepository.php';
require_once $basePath . '/app/services/ProcessoPrioridadesService.php';
require_once dirname(__DIR__) . '/sala-audiencias-pauta/_auth.php';

sala_audiencias_pauta_require_access('salapautaedit');
$processoId = trim((string) ($_GET['processo_id'] ?? ''));
if ($processoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'processo_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = Conection::getInstance()->getConnection();
    $nPauta = (new SalaAudienciasPautaParteRepository($pdo))->countPartesMenoresAtivasPorProcesso($processoId);
    $state = (new ProcessoPrioridadesService($pdo))->getEstadoEdicaoPorProcessoId($processoId, $nPauta);

    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar prioridades: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
