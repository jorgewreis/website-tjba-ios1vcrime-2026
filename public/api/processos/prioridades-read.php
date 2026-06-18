<?php

declare(strict_types=1);

/**
 * Leitura de prioridades processuais por número CNJ (20 dígitos).
 *
 * Resposta:
 * {
 *   "processo": { "id": string, "numero_processo": string } | null,
 *   "prioridades": [
 *     { "codigo": string, "label": string, "origem": string }
 *   ]
 * }
 *
 * Usada por telas de cadastro/edição para exibir prioridades já cadastradas
 * sem depender do `processo_id` resolvido (que pode não ser conhecido no front).
 */

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once __DIR__ . '/_auth.php';
require_once $basePath . '/app/helpers/PrioridadesProcessuaisCatalog.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/repositories/ProcessoPrioridadesRepository.php';

processos_api_require_access('consultaprocessosedit');

$rawNumero = $_GET['numero'] ?? '';
if ($rawNumero === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (is_array($payload)) {
        $rawNumero = $payload['numero'] ?? '';
    }
}

$digits = preg_replace('/\D+/', '', (string) $rawNumero);

try {
    if (strlen($digits) !== 20) {
        http_response_code(400);
        echo json_encode(['error' => 'Informe o número do processo com 20 dígitos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();
    $processosRepo = new ProcessosRepository($connection);
    $processo = $processosRepo->findByNumero($digits);

    if ($processo === null) {
        echo json_encode(['processo' => null, 'prioridades' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $processoId = trim((string) ($processo['id'] ?? ''));
    if ($processoId === '') {
        echo json_encode(['processo' => null, 'prioridades' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = (new ProcessoPrioridadesRepository($connection))->findByProcessoId($processoId);

    $vistos = [];
    $prioridades = [];
    foreach ($rows as $row) {
        $codigo = trim((string) ($row['codigo'] ?? ''));
        if ($codigo === '' || isset($vistos[$codigo])) {
            continue;
        }
        $vistos[$codigo] = true;
        $prioridades[] = [
            'codigo' => $codigo,
            'label'  => PrioridadesProcessuaisCatalog::labelForCodigo($codigo),
            'origem' => trim((string) ($row['origem'] ?? '')),
        ];
    }

    echo json_encode([
        'processo' => [
            'id' => $processoId,
            'numero_processo' => trim((string) ($processo['numero_processo'] ?? '')),
        ],
        'prioridades' => $prioridades,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar prioridades do processo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
