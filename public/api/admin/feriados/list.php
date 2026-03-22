<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 4);
require_once dirname(__DIR__) . '/_auth_admin_diretora_json.php';
$connection = requireAdminOuDiretoraJson($basePath);

require_once $basePath . '/app/services/FeriadosService.php';

$tipo = trim($_GET['tipo_feriado'] ?? '');
$q = trim($_GET['q'] ?? '');
$ativo = isset($_GET['ativo']) ? $_GET['ativo'] : null;
if ($ativo !== null && $ativo !== '') {
    $ativo = (int) (bool) $ativo;
} else {
    $ativo = null;
}

$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$pageNumber = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($pageNumber - 1) * $itemsPerPage;

$filters = ['q' => $q];
if ($tipo !== '') {
    $filters['tipo_feriado'] = $tipo;
}
if ($ativo !== null) {
    $filters['ativo'] = $ativo;
}

try {
    $service = new FeriadosService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
    $totalItems = (int) ($listResult['total'] ?? 0);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0;

    echo json_encode([
        'data' => $listResult['rows'] ?? [],
        'pagination' => [
            'total' => $totalItems,
            'page' => $pageNumber,
            'per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
        ],
        'filters' => $filters,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar feriados: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
