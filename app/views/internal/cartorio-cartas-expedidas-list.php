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

$routePage = !empty($cartasPiloto) ? 'cartasexpedidasnova' : trim((string) ($_GET['page'] ?? 'cartasexpedidas'));
if ($routePage === '') {
    $routePage = 'cartasexpedidas';
}
$finalidadeFilter = trim((string) ($_GET['finalidade'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}

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
    if (!empty($cartasPiloto) && ($statusFilter === '' || $envioFiltro)) {
        $filtrosEnvio = array_replace($filters, ['status' => 'aguardando_envio']);
        $envioPiloto = $service->listCartas($filtrosEnvio, null, 0)['rows'];
        $filtrosPaginados['piloto_sem_envio'] = true;
    }
    $listResult = !empty($cartasPiloto) && $envioFiltro
        ? ['rows' => [], 'total' => 0]
        : $service->listCartas($filtrosPaginados, $itemsPerPage, $offset);
    if (!empty($cartasPiloto) && $listResult['total'] > 0
        && $currentPage > ($ultima = (int) ceil($listResult['total'] / $itemsPerPage))) {
        $currentPage = $ultima;
        $listResult = $service->listCartas($filtrosPaginados, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
    }
} catch (\Throwable $exception) {
    if (!empty($cartasPiloto)) {
        http_response_code(503);
        error_log('cartas expedidas (nova): não foi possível carregar a lista');
        echo '<p>Não foi possível carregar a lista. <a href="?page=cartasexpedidas">Voltar à versão atual</a>.</p>';
        return;
    }
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$linhasPaginadas = $listResult['rows'] ?? [];
if ($envioPiloto !== []) {
    // Uma transição concorrente entre as duas consultas não pode duplicar a carta na página.
    $idsEnvio = array_fill_keys(array_column($envioPiloto, 'IdCarta'), true);
    $linhasPaginadas = array_values(array_filter($linhasPaginadas,
        static fn (array $carta): bool => !isset($idsEnvio[$carta['IdCarta']])));
}
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, array_merge($envioPiloto, $linhasPaginadas), !empty($cartasPiloto));
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

$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

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

$baseQueryParams = ['page' => $routePage];
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
$exportQueryParams['page'] = 'cartasexpedidas';
unset($exportQueryParams['page_number']);
$exportCsvUrl = '/api/internal-list-export/csv.php?' . http_build_query($exportQueryParams);
$exportPdfUrl = '/api/internal-list-export/pdf.php?' . http_build_query($exportQueryParams);

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($currentPage - 2, $totalPages - 4));
        $windowEnd = min($totalPages, $windowStart + 4);
        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $paginationButtons[] = $page;
        }
        if ($windowEnd < $totalPages) {
            $paginationEllipsis = true;
            $extraStart = max($windowEnd + 1, $totalPages - 1);
            for ($page = $extraStart; $page <= $totalPages; $page++) {
                $trailingPages[] = $page;
            }
        }
    }
}

$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$cartasExpedidasRowsCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cartas-expedidas-rows.css') ?: time();
$v2ListJsV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-expedidas/v2-list.js') ?: time();
$navBtnV = @filemtime($assetsRoot . '/js/shared/navigate-buttons.js') ?: time();
$searchListV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-expedidas/search-list.js') ?: time();
$cartorioTaskListV = @filemtime($assetsRoot . '/js/shared/cartorio-task-list.js') ?: time();
$registroMetaV = @filemtime($assetsRoot . '/js/shared/registro-metadata-list.js') ?: time();
$listSearchV = @filemtime($assetsRoot . '/js/shared/list-search.js') ?: time();
$pageHelpersV = @filemtime($assetsRoot . '/js/shared/page-helpers.js') ?: time();
$toastCatalogV = @filemtime($assetsRoot . '/js/shared/toast-catalog.js') ?: time();
$userContextV = @filemtime($assetsRoot . '/js/shared/user-context.js') ?: time();
if (!empty($cartasPiloto)) {
    require __DIR__ . '/partials/cartas-expedidas-piloto-list.php';
    return;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cartas-expedidas-rows.css?v=<?php echo (int) $cartasExpedidasRowsCssV; ?>">
    <?php include __DIR__ . '/_internal_head_css.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/operational-list-mobile.css?v=<?php echo (int) (@filemtime(__DIR__ . '/../../../public/assets/css/shared/operational-list-mobile.css') ?: time()); ?>">
</head>

<body class="page-internal page-operational-list">

    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Expedidas -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="card-title">
            <h1 class="title-page">Cartas Expedidas</h1>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <input type="text" id="search-input" class="height-md list-search-input" placeholder="Pesquisar" aria-label="Pesquisar" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>" autocomplete="off">
                <select class="width-300 height-md list-export-select white" id="list-export-v2" data-csv-href="<?php echo htmlspecialchars($exportCsvUrl, ENT_QUOTES); ?>" data-pdf-href="<?php echo htmlspecialchars($exportPdfUrl, ENT_QUOTES); ?>" aria-label="Exportar lista">
                    <option value="" selected disabled>Exportar lista</option>
                    <option value="pdf">PDF</option>
                    <option value="csv">CSV</option>
                    <option value="print">Imprimir</option>
                </select>
                <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=cartasexpedidasrules">Regra de envio</button>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <select class="width-500 height-md" id="filter-status" aria-label="Filtrar por status">
                    <option value="" <?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="aguardando_envio" <?php echo $statusFilter === 'aguardando_envio' ? ' selected' : ''; ?>>
                        Aguardando envio (<?php echo $statusCounts['aguardando_envio'] === null ? '—' : (int) $statusCounts['aguardando_envio']; ?>)
                    </option>
                    <option value="aguardando_cumprimento" <?php echo $statusFilter === 'aguardando_cumprimento' ? ' selected' : ''; ?>>
                        Aguardando cumprimento (<?php echo $statusCounts['aguardando_cumprimento'] === null ? '—' : (int) $statusCounts['aguardando_cumprimento']; ?>)
                    </option>
                    <option value="concluída" <?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)
                    </option>
                    <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)
                    </option>
                </select>
                <select class="height-md" id="filter-tipo" aria-label="Filtrar por finalidade" style="width: 350px;">
                    <option value="" <?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>" <?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo (int) $count; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php foreach ($rows as $row): ?>
            <?php include __DIR__ . '/partials/cartas-expedidas-v2-row.php'; ?>
        <?php endforeach; ?>

        <?php if ($totalPages > 0): ?>
            <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
                <div class="card-line left">
                    <span class="pagination-info">
                        Mostrando <?php echo htmlspecialchars((string) $startItem); ?> a <?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> <?php echo htmlspecialchars($documentsLabel); ?>
                        (<?php echo htmlspecialchars((string) $totalPages); ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo htmlspecialchars((string) $itemsPerPage); ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
                    </span>
                </div>
                <div class="card-line right">
                    <div class="pagination-controls">
                        <?php
                        $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                        $prevParams = $baseQueryParams;
                        $prevParams['page_number'] = $prevPage;
                        $prevHref = '?' . http_build_query($prevParams);
                        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                        $nextParams = $baseQueryParams;
                        $nextParams['page_number'] = $nextPage;
                        $nextHref = '?' . http_build_query($nextParams);
                        ?>
                        <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" aria-label="Página anterior" title="Página anterior"
                            <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                            <i class="fi fi-rr-angle-small-left" aria-hidden="true"></i>
                        </button>
                        <?php foreach ($paginationButtons as $page): ?>
                            <?php
                            $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page;
                            ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>"<?php if ($page !== $currentPage): ?> data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?> disabled<?php endif; ?>>
                                <?php echo (int) $page; ?>
                            </button>
                        <?php endforeach; ?>
                        <?php if ($paginationEllipsis && $trailingPages !== []): ?>
                            <span class="pagination-symbol pagination-ellipsis" aria-hidden="true">
                                <i class="fi fi-rr-menu-dots" aria-hidden="true"></i>
                            </span>
                            <?php foreach ($trailingPages as $page): ?>
                                <?php
                                $pageParams = $baseQueryParams;
                                $pageParams['page_number'] = $page;
                                ?>
                                <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>"<?php if ($page !== $currentPage): ?> data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?> disabled<?php endif; ?>>
                                    <?php echo (int) $page; ?>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" aria-label="Próxima página" title="Próxima página"
                            <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                            <i class="fi fi-rr-angle-small-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="/assets/js/shared/user-context.js?v=<?php echo (int) $userContextV; ?>"></script>
    <script src="/assets/js/shared/page-helpers.js?v=<?php echo (int) $pageHelpersV; ?>"></script>
    <script src="/assets/js/shared/toast-catalog.js?v=<?php echo (int) $toastCatalogV; ?>"></script>
    <script src="/assets/js/shared/list-search.js?v=<?php echo (int) $listSearchV; ?>"></script>
    <script src="/assets/js/shared/cartorio-task-list.js?v=<?php echo (int) $cartorioTaskListV; ?>"></script>
    <script src="/assets/js/shared/registro-metadata-list.js?v=<?php echo (int) $registroMetaV; ?>"></script>
    <script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $navBtnV; ?>"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/search-list.js?v=<?php echo (int) $searchListV; ?>"></script>
    <script src="/assets/js/shared/cartas-reiniciar.js?v=<?php echo (int) @filemtime(dirname(__DIR__,3).'/public/assets/js/shared/cartas-reiniciar.js'); ?>"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/v2-list.js?v=<?php echo (int) $v2ListJsV; ?>"></script>
    <script src="/assets/js/shared/copy-whatsapp-text.js?v=<?php echo (int) (@filemtime($assetsRoot . '/js/shared/copy-whatsapp-text.js') ?: time()); ?>"></script>
    <script>
        (function() {
            if (window.CartasExpedidasV2List && typeof window.CartasExpedidasV2List.initialize === 'function') {
                window.CartasExpedidasV2List.initialize({
                    initialProcessFilter: <?php echo json_encode($processoFilter, JSON_UNESCAPED_UNICODE); ?>
                });
            }
        })();
    </script>
</body>

</html>
