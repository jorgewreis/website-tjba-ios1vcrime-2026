<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultaprocessos'], false);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/ConsultaProcessosService.php';

$connection = Conection::getInstance()->getConnection();

$numero = trim($_GET['numero'] ?? $_GET['numero_processo'] ?? '');
$parteNome = trim($_GET['parte_nome'] ?? $_GET['nome'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$pageNumber = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($pageNumber - 1) * $itemsPerPage;

$filters = [
    'numero' => $numero,
    'parte_nome' => $parteNome,
];

try {
    $service = new ConsultaProcessosService($connection);
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
    error_log('API error (public/api/consulta-processos/list.php): ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar processos.'], JSON_UNESCAPED_UNICODE);
}
