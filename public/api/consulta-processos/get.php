<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/InternoProdutividadeAutoHelper.php';
require_once $basePath . '/app/services/ConsultaProcessosService.php';

$connection = Conection::getInstance()->getConnection();

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do processo é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new ConsultaProcessosService($connection);
    $processo = $service->get($id);
    if ($processo === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uid = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    $toastKeyProc = null;
    if ($uid !== '') {
        $ref = 'cg:' . strtoupper(substr(hash('sha256', $id), 0, 20));
        $numeroProc = trim((string) ($processo['numero_processo'] ?? ''));
        $prodRes = InternoProdutividadeAutoHelper::tryRegistrar(
            $connection,
            $uid,
            'consulta_processos_get',
            $ref,
            'Consulta processo',
            $numeroProc
        );
        if (!empty($prodRes['toast_key'])) {
            $toastKeyProc = $prodRes['toast_key'];
        }
    }

    $payloadGet = $processo;
    if ($toastKeyProc !== null) {
        $payloadGet['produtividade_toast_key'] = $toastKeyProc;
    }
    echo json_encode($payloadGet, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar processo: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
