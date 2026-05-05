<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/InternoProdutividadeAutoHelper.php';
require_once $basePath . '/app/repositories/SalaAudienciasPautaParteRepository.php';
require_once $basePath . '/app/services/ProcessoPrioridadesService.php';

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
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Conection::getInstance()->getConnection();
    $svc = new ProcessoPrioridadesService($pdo);
    $incluiuNovaLinha = $svc->adicionarManualPorCodigoOuRotulo($processoId, $codigo);
    $toastKeyPrior = null;
    if ($incluiuNovaLinha) {
        $refPrior = 'pr:' . strtoupper(substr(hash('sha256', $processoId . '|' . $codigo), 0, 20));
        $prodRes = InternoProdutividadeAutoHelper::tryRegistrar(
            $pdo,
            $usuarioId,
            'processo_prioridade_add',
            $refPrior,
            'Prioridade processual incluída.'
        );
        if (!empty($prodRes['toast_key'])) {
            $toastKeyPrior = $prodRes['toast_key'];
        }
    }

    $nPauta = (new SalaAudienciasPautaParteRepository($pdo))->countPartesMenoresAtivasPorProcesso($processoId);
    $state = $svc->getEstadoEdicaoPorProcessoId($processoId, $nPauta);

    $outPrior = ['success' => true, 'state' => $state];
    if ($toastKeyPrior !== null) {
        $outPrior['produtividade_toast_key'] = $toastKeyPrior;
    }
    echo json_encode($outPrior, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar prioridade: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
