<?php
declare(strict_types=1);

/**
 * @deprecated Preferir GET /api/processos/prioridades-list.php?processo_id=...
 * Mantido para clientes que ainda enviam id_pauta.
 */
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/_auth.php';

$usuarioId = salaAudienciasPautaRequireAccess('salapautaedit');
$idPauta = trim((string) ($_GET['id_pauta'] ?? ''));
if ($idPauta === '') {
    http_response_code(400);
    echo json_encode(['error' => 'ID da audiência é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
    $state = $service->listPrioridadesEdicaoAudiencia($idPauta);
    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar prioridades: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
