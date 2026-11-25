<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pessoas/list.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultapartes'], false);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/PessoasService.php';
require_once $basePath . '/app/helpers/InstituicoesConsultaPolicy.php';

$connection = Conection::getInstance()->getConnection();

$nome = trim($_GET['nome'] ?? '');
$cpf = trim($_GET['cpf'] ?? '');
$instituicao_id = trim($_GET['instituicao_id'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$pageNumber = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($pageNumber - 1) * $itemsPerPage;

$filters = [
    'nome' => $nome,
    'cpf' => $cpf,
    'instituicao_id' => $instituicao_id,
];

try {
    $filters['somente_instituicoes_ativas'] = InstituicoesConsultaPolicy::onlyActiveForUser((string) ($_SESSION['id_usuario'] ?? ''));
    $service = new PessoasService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
    $totalItems = (int) ($listResult['total'] ?? 0);
    $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $itemsPerPage) : 0;
    unset($filters['somente_instituicoes_ativas']);

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
    error_log('API error (public/api/pessoas/list.php): ' . ApiErrorReporter::genericMessage($exception, 'public/api/pessoas/list.php'));
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao listar pessoas.'], JSON_UNESCAPED_UNICODE);
}
