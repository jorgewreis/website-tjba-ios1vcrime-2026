<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/InstituicoesPublicasService.php';

$connection = Conection::getInstance()->getConnection();

$nome = trim($_GET['nome'] ?? '');
$ativo = isset($_GET['ativo']) ? $_GET['ativo'] : null;
if ($ativo !== null && $ativo !== '') {
    $ativo = (int) (bool) $ativo;
}
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$pageNumber = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($pageNumber - 1) * $itemsPerPage;

$filters = ['nome' => $nome];
if ($ativo !== null) {
    $filters['ativo'] = $ativo;
}

try {
    $service = new InstituicoesPublicasService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
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
        'filters' => $filters,
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar instituições: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
