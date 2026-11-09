<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['reuspresos', 'reuspresossubstituto'], false);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$pageNumber = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
$offset = ($pageNumber - 1) * $itemsPerPage;

$q = trim((string) ($_GET['q'] ?? ''));
$filters = $q !== ''
    ? ['q' => $q]
    : [
        'nome' => trim((string) ($_GET['nome'] ?? '')),
        'processo' => trim((string) ($_GET['processo'] ?? '')),
    ];

try {
    $service = new GabineteReusPresosService($connection, (($_GET['contexto'] ?? '') === 'substituto'));
    $result = $service->listAtivos($filters, $itemsPerPage, $offset);
    echo json_encode([
        'rows' => $result['rows'],
        'total' => $result['total'],
        'page_number' => $pageNumber,
        'items_per_page' => $itemsPerPage,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar.'], JSON_UNESCAPED_UNICODE);
}
