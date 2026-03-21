<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';

if (empty($_SESSION['id_usuario'] ?? null)) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
$numeroProcesso = trim((string) ($_GET['numero_processo'] ?? ''));

try {
    if ($processoId === '' && $numeroProcesso !== '') {
        $procRepo = new ProcessosRepository($connection);
        $p = $procRepo->findByNumero($numeroProcesso);
        if ($p !== null) {
            $processoId = (string) ($p['id'] ?? '');
        }
    }

    if ($processoId === '') {
        echo json_encode(['processo_id' => '', 'reus' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new GabineteMonitoramentoEletronicoService($connection);
    $lista = $service->listarReusDoProcesso($processoId);
    echo json_encode(['processo_id' => $processoId, 'reus' => $lista], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar réus: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
