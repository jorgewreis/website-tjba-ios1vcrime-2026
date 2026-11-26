<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/gabinete-monitoramento-eletronico/reus-partes.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';

ApiAccessGuard::requireAccess(['monitoramentogabinetecreate'], true);

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('gabinete-monitoramento-eletronico/reus-partes.php: falha de conexão.');
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$processoId = trim((string) ($parsed['processo_id'] ?? ''));
$numeroProcesso = trim((string) ($parsed['numero_processo'] ?? ''));
// Mantém POST de mesma origem para não colocar o número do processo na URL.
header('Cache-Control: no-store');

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);
    if ($processoId === '' && $numeroProcesso !== '') {
        $processoId = $service->buscarIdProcessoParaConsulta($numeroProcesso);
    }
    if ($processoId !== '' && !ProcessoSetorAuthorization::canAccessProcess(
        $connection,
        trim((string) ($_SESSION['id_usuario'] ?? '')),
        $processoId,
        false
    )) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado ao processo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reus = $service->listarReusDoProcesso($processoId);
    $result = ['processo_id' => $processoId, 'reus' => $reus];
    if ($reus === [] && ($processoId !== '' || $numeroProcesso !== '')) {
        $result['mensagem'] = 'Nenhum réu vinculado encontrado. Informe o nome manualmente.';
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    error_log('Monitoramento eletrônico: ' . get_class($exception));
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
}
