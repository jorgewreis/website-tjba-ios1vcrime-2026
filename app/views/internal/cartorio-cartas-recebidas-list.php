<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }

require_once __DIR__ . '/../../services/CartasRecebidasService.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/CartasRecebidasUiCopy.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';

$connection = Conection::getInstance()->getConnection();
$routePage = 'cartasrecebidas';
$finalidadeFilter = trim((string) ($_GET['finalidade'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$itemsPerPage = 15;
$currentPage = max(1, (int) ($_GET['page_number'] ?? 1));
$filters = ['finalidade' => $finalidadeFilter, 'status' => $statusFilter, 'processo' => $processoFilter];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new CartasRecebidasService($connection);
    $listResult = $service->listCartas($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $listResult['rows'] ?? []);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$totalDocuments = (int) ($listResult['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalDocuments / $itemsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = 'itens';

$statusOptions = [
    'pendente',
    CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO,
    CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO,
    'concluída',
    'cancelada',
];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$legacyEmAndamento = (int) ($statusCounts['em_andamento'] ?? 0);
if ($legacyEmAndamento > 0) {
    $statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO] = (int) ($statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO] ?? 0) + $legacyEmAndamento;
}
$defaultFinalidades = [
    'Intimação para Audiência', 'Intimação de Decisão Interlocutória', 'Intimação da Sentença',
    'Citação para apresentar Defesa', 'Citação para apresentar Alegações Finais', 'Suspensão Condicional', 'Outra',
];
$rawFinalidadeCounts = $service->getFinalidadeCounts($filters);
$finalidadeCounts = [];
foreach (array_unique(array_merge($defaultFinalidades, array_keys($rawFinalidadeCounts))) as $term) {
    $finalidadeCounts[$term] = $rawFinalidadeCounts[$term] ?? 0;
}
$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($finalidadeFilter !== '') $baseQueryParams['finalidade'] = $finalidadeFilter;
if ($statusFilter !== '') $baseQueryParams['status'] = $statusFilter;
if ($processoFilter !== '') $baseQueryParams['processo'] = $processoFilter;
$exportQueryParams = $baseQueryParams;
$exportCsvUrl = '/api/internal-list-export/csv.php?' . http_build_query($exportQueryParams);
$exportPdfUrl = '/api/internal-list-export/pdf.php?' . http_build_query($exportQueryParams);
$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages <= 5) {
    $paginationButtons = range(1, $totalPages);
} else {
    $windowStart = max(1, min($currentPage - 2, $totalPages - 4));
    $windowEnd = min($totalPages, $windowStart + 4);
    for ($page = $windowStart; $page <= $windowEnd; $page++) $paginationButtons[] = $page;
    if ($windowEnd < $totalPages) {
        $paginationEllipsis = true;
        for ($page = max($windowEnd + 1, $totalPages - 1); $page <= $totalPages; $page++) $trailingPages[] = $page;
    }
}
$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$receivedRowsCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cartas-expedidas-rows.css') ?: time();
$v2ListJsV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-recebidas/v2-list.js') ?: time();
$searchListV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-recebidas/search-list.js') ?: time();
$sharedJs = [];
foreach (['user-context.js', 'page-helpers.js', 'toast-catalog.js', 'list-search.js', 'cartorio-task-list.js', 'registro-metadata-list.js', 'navigate-buttons.js'] as $asset) {
    $sharedJs[$asset] = @filemtime($assetsRoot . '/js/shared/' . $asset) ?: time();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartas Recebidas - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cartas-expedidas-rows.css?v=<?php echo (int) $receivedRowsCssV; ?>">
    <?php include __DIR__ . '/_internal_head_css.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/operational-list-mobile.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../../../public/assets/css/shared/operational-list-mobile.css') ?: time()); ?>">
</head>
<body class="page-internal page-operational-list">
    <div class="spacing-top-page"></div>
    <div class="header-page"><div class="location"><span class="all">Interno - Cartório - Cartas Recebidas -</span><span class="specific">Lista</span></div></div>
    <div class="container-full card-page">
        <div class="card-title">
            <h1 class="title-page">Cartas Recebidas</h1>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <input type="text" id="search-input" class="height-md list-search-input" placeholder="Pesquisar" aria-label="Pesquisar" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>" autocomplete="off">
                <select class="width-300 height-md list-export-select white" id="list-export-v2" data-csv-href="<?php echo htmlspecialchars($exportCsvUrl, ENT_QUOTES); ?>" data-pdf-href="<?php echo htmlspecialchars($exportPdfUrl, ENT_QUOTES); ?>" aria-label="Exportar lista">
                    <option value="" selected disabled>Exportar lista</option><option value="pdf">PDF</option><option value="csv">CSV</option><option value="print">Imprimir</option>
                </select>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm"><span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span></div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <select class="width-500 height-md" id="filter-status" aria-label="Filtrar por status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <?php foreach ($statusOptions as $option): $label = CartasRecebidasUiCopy::rotuloBadgeStatusTarefa($option); ?>
                        <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $statusFilter === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES); ?> (<?php echo (int) ($statusCounts[$option] ?? 0); ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select class="height-md" id="filter-tipo" aria-label="Filtrar por finalidade" style="width: 350px;">
                    <option value=""<?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>"<?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>><?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo (int) $count; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php foreach ($rows as $row): ?>
            <?php include __DIR__ . '/partials/cartas-recebidas-v2-row.php'; ?>
        <?php endforeach; ?>
        <?php if ($totalPages > 0): ?>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <div class="card-line left"><span class="pagination-info">Mostrando <?php echo (int) $startItem; ?> a <?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?> (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)</span></div>
            <div class="card-line right"><div class="pagination-controls">
                <?php $prevParams = $baseQueryParams; $prevParams['page_number'] = max(1, $currentPage - 1); $nextParams = $baseQueryParams; $nextParams['page_number'] = min($totalPages, $currentPage + 1); ?>
                <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" aria-label="Página anterior" title="Página anterior"<?php if ($currentPage > 1): ?> data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?> disabled aria-disabled="true"<?php endif; ?>><i class="fi fi-rr-angle-small-left" aria-hidden="true"></i></button>
                <?php foreach (array_merge($paginationButtons, $trailingPages) as $page): $params = $baseQueryParams; $params['page_number'] = $page; ?>
                    <?php if ($paginationEllipsis && $page === ($trailingPages[0] ?? null)): ?><span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><i class="fi fi-rr-menu-dots" aria-hidden="true"></i></span><?php endif; ?>
                    <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>"<?php if ($page !== $currentPage): ?> data-nav-href="?<?php echo htmlspecialchars(http_build_query($params), ENT_QUOTES); ?>"<?php else: ?> disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                <?php endforeach; ?>
                <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" aria-label="Próxima página" title="Próxima página"<?php if ($currentPage < $totalPages): ?> data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?> disabled aria-disabled="true"<?php endif; ?>><i class="fi fi-rr-angle-small-right" aria-hidden="true"></i></button>
            </div></div>
        </div>
        <?php endif; ?>
    </div>
    <script src="/assets/js/shared/user-context.js?v=<?php echo (int) $sharedJs['user-context.js']; ?>"></script>
    <script src="/assets/js/shared/page-helpers.js?v=<?php echo (int) $sharedJs['page-helpers.js']; ?>"></script>
    <script src="/assets/js/shared/toast-catalog.js?v=<?php echo (int) $sharedJs['toast-catalog.js']; ?>"></script>
    <script src="/assets/js/shared/list-search.js?v=<?php echo (int) $sharedJs['list-search.js']; ?>"></script>
    <script src="/assets/js/shared/cartorio-task-list.js?v=<?php echo (int) $sharedJs['cartorio-task-list.js']; ?>"></script>
    <script src="/assets/js/shared/registro-metadata-list.js?v=<?php echo (int) $sharedJs['registro-metadata-list.js']; ?>"></script>
    <script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $sharedJs['navigate-buttons.js']; ?>"></script>
    <script src="/assets/js/modules/cartorio/cartas-recebidas/search-list.js?v=<?php echo (int) $searchListV; ?>"></script>
    <script src="/assets/js/shared/cartas-reiniciar.js?v=<?php echo (int) @filemtime(dirname(__DIR__,3).'/public/assets/js/shared/cartas-reiniciar.js'); ?>"></script>
    <script src="/assets/js/modules/cartorio/cartas-recebidas/v2-list.js?v=<?php echo (int) $v2ListJsV; ?>"></script>
    <script>(function(){if(window.CartasRecebidasV2List)CartasRecebidasV2List.initialize({initialProcessFilter:<?php echo json_encode($processoFilter, JSON_UNESCAPED_UNICODE); ?>});})();</script>
</body>
</html>
