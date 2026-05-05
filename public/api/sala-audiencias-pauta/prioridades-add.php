<?php
declare(strict_types=1);

/**
 * @deprecated Preferir POST /api/processos/prioridades-add.php com processo_id + codigo.
 */
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/InternoProdutividadeAutoHelper.php';
require_once $basePath . '/app/services/SalaAudienciasPautaService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$idPauta = trim((string) ($payload['id_pauta'] ?? ''));
$prioridade = trim((string) ($payload['prioridade'] ?? ''));
if ($idPauta === '' || $prioridade === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Audiência e prioridade são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $connection = Conection::getInstance()->getConnection();
    $service = new SalaAudienciasPautaService($connection);
    $incluiuNovaLinha = $service->addPrioridadeCatalogoAudiencia($idPauta, $prioridade, $usuarioId);
    $toastKeyPriorPauta = null;
    if ($incluiuNovaLinha) {
        $pautaRow = $service->find($idPauta);
        $processoIdRef = is_array($pautaRow) ? trim((string) ($pautaRow['processo'] ?? '')) : '';
        $codNorm = trim($prioridade);
        if ($processoIdRef !== '' && $codNorm !== '') {
            $refPrior = 'pr:' . strtoupper(substr(hash('sha256', $processoIdRef . '|' . $codNorm), 0, 20));
            $prodRes = InternoProdutividadeAutoHelper::tryRegistrar(
                $connection,
                $usuarioId,
                'processo_prioridade_add',
                $refPrior,
                'Prioridade processual incluída.'
            );
            if (!empty($prodRes['toast_key'])) {
                $toastKeyPriorPauta = $prodRes['toast_key'];
            }
        }
    }
    $state = $service->listPrioridadesEdicaoAudiencia($idPauta);
    $outPriorPauta = ['success' => true, 'state' => $state];
    if ($toastKeyPriorPauta !== null) {
        $outPriorPauta['produtividade_toast_key'] = $toastKeyPriorPauta;
    }
    echo json_encode($outPriorPauta, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao adicionar prioridade: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
