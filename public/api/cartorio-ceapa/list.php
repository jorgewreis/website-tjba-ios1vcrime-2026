<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/CeapaService.php';

$connection = Conection::getInstance()->getConnection();

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$tipoSuspensaoFilter = trim((string) ($_GET['tipo_suspensao'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $itemsPerPage;
$filters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
    'tipo_suspensao' => $tipoSuspensaoFilter,
];

try {
    $service = new CeapaService($connection);
    $listResult = $service->listTarefas($filters, $itemsPerPage, $offset);
    $totalItems = (int) ($listResult['total'] ?? 0);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0;

    echo json_encode([
        'data' => $listResult['rows'] ?? [],
        'pagination' => [
            'total' => $totalItems,
            'page' => $page,
            'per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
        ],
        'filters' => $filters,
        'status_counts' => $service->getStatusCounts($filters),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar cadastros CEAPA: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
