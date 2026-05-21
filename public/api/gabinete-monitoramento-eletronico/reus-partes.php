<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/_auth.php';

gmeRequirePageAccess('monitoramentogabinetecreate');
$connection = Conection::getInstance()->getConnection();

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$numeroProcesso = trim((string) ($_GET['numero_processo'] ?? ''));
$classeCodigo = trim((string) ($_GET['classe_codigo'] ?? ''));
$classeDescricao = trim((string) ($_GET['classe_descricao'] ?? ''));

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);

    if ($processoId !== '') {
        $lista = $service->listarReusDoProcesso($processoId);
        echo json_encode(['processo_id' => $processoId, 'reus' => $lista], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($numeroProcesso === '') {
        echo json_encode(['processo_id' => '', 'reus' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = $service->ensureProcessoEListarReusParaCadastro($numeroProcesso, $classeCodigo, $classeDescricao);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar réus: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
