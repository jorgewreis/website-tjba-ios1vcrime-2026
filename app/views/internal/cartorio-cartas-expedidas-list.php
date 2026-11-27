<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasExpedidasService.php';
require_once __DIR__ . '/../../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/CartasExpedidasUiCopy.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';

require_once __DIR__ . '/../../helpers/CartasExpedidasEnvioRegra.php';
$connection = Conection::getInstance()->getConnection();
$responsavelRegraEnvio = null;
try {
    $responsavelRegraEnvio = CartasExpedidasEnvioRegra::responsavel($connection);
} catch (Throwable $e) {
    error_log('cartas expedidas: não foi possível carregar a regra de envio');
}

$finalidadeFilter = trim((string) ($_GET['finalidade'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$itemsPerPage = 15;

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} else {
    $currentPage = 1;
}

$filters = [
    'finalidade' => $finalidadeFilter,
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

$envioPiloto = [];
$envioFiltro = in_array($statusFilter, ['aguardando_envio', 'aguardando envio', 'em_andamento'], true);
try {
    $service = new CartasExpedidasService($connection);
    $filtrosPaginados = $filters;
    if ($statusFilter === '' || $envioFiltro) {
        $filtrosEnvio = array_replace($filters, ['status' => 'aguardando_envio']);
        $envioPiloto = $service->listCartas($filtrosEnvio, null, 0)['rows'];
        $filtrosPaginados['piloto_sem_envio'] = true;
    }
    $listResult = $envioFiltro
        ? ['rows' => [], 'total' => 0]
        : $service->listCartas($filtrosPaginados, $itemsPerPage, $offset);
    if ($listResult['total'] > 0
        && $currentPage > ($ultima = (int) ceil($listResult['total'] / $itemsPerPage))) {
        $currentPage = $ultima;
        $listResult = $service->listCartas($filtrosPaginados, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
    }
} catch (Throwable $exception) {
    http_response_code(503);
    error_log('cartas expedidas: não foi possível carregar a lista');
    echo '<p>Não foi possível carregar a lista. Tente novamente.</p>';
    return;
}

$linhasPaginadas = $listResult['rows'] ?? [];
if ($envioPiloto !== []) {
    // Uma transição concorrente entre as duas consultas não pode duplicar a carta na página.
    $idsEnvio = array_fill_keys(array_column($envioPiloto, 'IdCarta'), true);
    $linhasPaginadas = array_values(array_filter($linhasPaginadas,
        static fn (array $carta): bool => !isset($idsEnvio[$carta['IdCarta']])));
}
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, array_merge($envioPiloto, $linhasPaginadas), true);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 1;
    $currentPage = 1;
} else {
    $totalPages = (int) ceil($totalDocuments / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($linhasPaginadas);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$rawStatusCounts = $service->getStatusCounts($filters);
$statusCounts = [
    'aguardando_envio' => (array_key_exists('em_andamento', $rawStatusCounts) && $rawStatusCounts['em_andamento'] === null) ? null : (int) ($rawStatusCounts['em_andamento'] ?? 0) + (int) ($rawStatusCounts['aguardando envio'] ?? 0),
    'aguardando_cumprimento' => (array_key_exists('aguardando cumprimento', $rawStatusCounts) && $rawStatusCounts['aguardando cumprimento'] === null) ? null : (int) ($rawStatusCounts['aguardando cumprimento'] ?? 0),
    'concluída' => (int) ($rawStatusCounts['concluída'] ?? 0),
    'cancelada' => (int) ($rawStatusCounts['cancelada'] ?? 0),
];

$defaultFinalidades = [
    'Intimação para Audiência',
    'Intimação de Decisão Interlocutória',
    'Intimação da Sentença',
    'Citação para apresentar Defesa',
    'Citação para apresentar Alegações Finais',
    'Suspensão Condicional',
    'Outra',
];
$rawFinalidadeCounts = $service->getFinalidadeCounts($filters);
$finalidadeOrder = array_unique(array_merge($defaultFinalidades, array_keys($rawFinalidadeCounts)));
$finalidadeCounts = [];
foreach ($finalidadeOrder as $term) {
    $finalidadeCounts[$term] = $rawFinalidadeCounts[$term] ?? 0;
}

$baseQueryParams = ['page' => 'cartasexpedidas'];
if ($finalidadeFilter !== '') {
    $baseQueryParams['finalidade'] = $finalidadeFilter;
}
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

$exportQueryParams = $baseQueryParams;
$exportCsvUrl = '/api/internal-list-export/csv.php?' . http_build_query($exportQueryParams);
$exportPdfUrl = '/api/internal-list-export/pdf.php?' . http_build_query($exportQueryParams);

// Esta página usa somente a listagem aprovada.
$cartasPiloto = true;
require __DIR__ . '/partials/cartas-expedidas-piloto-list.php';
