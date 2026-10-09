<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/CartasRecebidasService.php';

$connection = Conection::getInstance()->getConnection();

$classeFilter = trim($_GET['classe'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$finalidadeFilter = trim($_GET['finalidade'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$outputFilters = [
    'classe' => $classeFilter,
    'status' => $statusFilter,
    'finalidade' => $finalidadeFilter,
    'processo' => $processoFilter,
];
$offset = ($page - 1) * $itemsPerPage;

try {
    $service = new CartasRecebidasService($connection);
    $listResult = $service->listCartas($outputFilters, $itemsPerPage, $offset);
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
        'class_counts' => $service->getClasseCounts($outputFilters),
        'finalidade_counts' => $service->getFinalidadeCounts($outputFilters),
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao buscar cartas recebidas: ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
