<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/prioridades-list.php');
/**
 * Lista estado de edição das prioridades do processo (catálogo, bloqueios, linhas atuais).
 */
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['salapautaedit'], false);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/repositories/SalaAudienciasPautaParteRepository.php';
require_once $basePath . '/app/services/ProcessoPrioridadesService.php';

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
if ($processoId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'processo_id é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Conection::getInstance()->getConnection();
    if ((new ProcessosRepository($pdo))->findById($processoId) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $nPauta = (new SalaAudienciasPautaParteRepository($pdo))->countPartesMenoresAtivasPorProcesso($processoId);
    $state = (new ProcessoPrioridadesService($pdo))->getEstadoEdicaoPorProcessoId($processoId, $nPauta);

    echo json_encode(['success' => true, 'state' => $state], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar prioridades: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/prioridades-list.php')], JSON_UNESCAPED_UNICODE);
}
