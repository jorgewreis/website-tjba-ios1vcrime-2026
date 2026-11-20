<?php

declare(strict_types=1);

/**
 * Leitura de prioridades processuais por número CNJ (20 dígitos).
 *
 * Resposta:
 * {
 *   "processo": { "id": string, "numero_processo": string },
 *   "prioridades": [
 *     { "codigo": string, "label": string, "origem": string }
 *   ]
 * }
 *
 * Usada pelo cadastro de carta para exibir prioridades de processo já cadastrado localmente.
 * Retorna 404 quando o número CNJ não corresponde a processo local.
 */

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['cartasexpedidascreate'], false);
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/PrioridadesProcessuaisCatalog.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/repositories/ProcessoPrioridadesRepository.php';

$rawNumero = $_GET['numero'] ?? '';

$digits = preg_replace('/\D+/', '', (string) $rawNumero);

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strlen($digits) !== 20) {
        http_response_code(400);
        echo json_encode(['error' => 'Informe o número do processo com 20 dígitos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();
    $processosRepo = new ProcessosRepository($connection);
    $processo = $processosRepo->findByNumero($digits);

    if ($processo === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $processoId = trim((string) ($processo['id'] ?? ''));
    if ($processoId === '') {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
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
