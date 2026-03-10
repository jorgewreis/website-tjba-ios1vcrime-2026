<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/EditaisService.php';

$connection = Conection::getInstance()->getConnection();

$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$tipoEditalFilter = trim($_GET['tipo_edital'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$pageNumber = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : $page;
$offset = ($pageNumber - 1) * $itemsPerPage;
$page = $pageNumber;

$outputFilters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
    'tipo_edital' => $tipoEditalFilter,
];

try {
    $service = new EditaisService($connection);
    $listResult = $service->listEditais($outputFilters, $itemsPerPage, $offset);
    $totalItems = (int) ($listResult['total'] ?? 0);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0;

    $response = [
        'data' => $listResult['rows'] ?? [],
        'pagination' => [
            'total' => $totalItems,
            'page' => $pageNumber,
            'per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
        ],
        'filters' => $outputFilters,
        'status_counts' => $service->getStatusCounts($outputFilters),
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar editais: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
