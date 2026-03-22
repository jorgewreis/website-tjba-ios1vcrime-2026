<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/GuiasService.php';

$connection = Conection::getInstance()->getConnection();

$statusFilter = trim($_GET['status'] ?? '');
$tipoFilter = trim($_GET['tipo'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$outputFilters = [
    'status' => $statusFilter,
    'tipo' => $tipoFilter,
    'processo' => $processoFilter,
];
$offset = ($page - 1) * $itemsPerPage;

try {
    $service = new GuiasService($connection);
    $listResult = $service->listGuias($outputFilters, $itemsPerPage, $offset);
    $totalItems = (int) ($listResult['total'] ?? 0);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0;

    $response = [
        'data' => $listResult['rows'] ?? [],
        'pagination' => [
            'total' => $totalItems,
            'page' => $page,
            'per_page' => $itemsPerPage,
            'total_pages' => $totalPages,
        ],
        'filters' => $outputFilters,
        'status_counts' => $service->getStatusCounts($outputFilters),
        'tipo_counts' => $service->getTipoCounts($outputFilters),
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar guias: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
