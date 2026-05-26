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

$connection = Conection::getInstance()->getConnection();

$currentUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'cartasexpedidas'));
if ($routePage === '') {
    $routePage = 'cartasexpedidas';
}
$finalidadeFilter = trim((string) ($_GET['finalidade'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
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

try {
    $service = new CartasExpedidasService($connection);
    $listResult = $service->listCartas($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 0;
    $currentPage = 1;
} else {
    $totalPages = (int) ceil($totalDocuments / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$rawStatusCounts = $service->getStatusCounts($filters);
$statusCounts = [
    'aguardando_envio' => (int) ($rawStatusCounts['em_andamento'] ?? 0) + (int) ($rawStatusCounts['aguardando envio'] ?? 0),
    'aguardando_cumprimento' => (int) ($rawStatusCounts['aguardando cumprimento'] ?? 0),
    'concluída' => (int) ($rawStatusCounts['concluída'] ?? 0),
    'cancelada' => (int) ($rawStatusCounts['cancelada'] ?? 0),
];

// Contadores da barra de results: sempre globais (ignoram filtros aplicados).
$globalStatusCounts = $service->getStatusCounts([]);
$globalAtivosTotal = (int) ($globalStatusCounts['em_andamento'] ?? 0)
    + (int) ($globalStatusCounts['aguardando envio'] ?? 0)
    + (int) ($globalStatusCounts['aguardando cumprimento'] ?? 0);
$globalAguardandoEnvio = (int) ($globalStatusCounts['em_andamento'] ?? 0)
    + (int) ($globalStatusCounts['aguardando envio'] ?? 0);

// Contadores "para você" só fazem sentido para perfis não-gestão. Para gestão, ocultamos o bloco.
$userAtivosTotal = 0;
$userAguardandoEnvio = 0;
if (!$isGestao && $currentUserId !== '') {
    $userStatusCounts = $service->getStatusCountsByUser($currentUserId);
    $userAtivosTotal = (int) ($userStatusCounts['em_andamento'] ?? 0)
        + (int) ($userStatusCounts['aguardando envio'] ?? 0)
        + (int) ($userStatusCounts['aguardando cumprimento'] ?? 0);
    $userAguardandoEnvio = (int) ($userStatusCounts['em_andamento'] ?? 0)
        + (int) ($userStatusCounts['aguardando envio'] ?? 0);
}

$mostraParaVoce = !$isGestao && $currentUserId !== '';
$alertAguardandoEnvio = $mostraParaVoce ? ($userAguardandoEnvio >= 1) : ($globalAguardandoEnvio >= 1);

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
$v2ListJsV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-expedidas/v2-list.js') ?: time();
$navBtnV = @filemtime($assetsRoot . '/js/shared/navigate-buttons.js') ?: time();
$searchListV = @filemtime($assetsRoot . '/js/modules/cartorio/cartas-expedidas/search-list.js') ?: time();
$cartorioTaskListV = @filemtime($assetsRoot . '/js/shared/cartorio-task-list.js') ?: time();
$registroMetaV = @filemtime($assetsRoot . '/js/shared/registro-metadata-list.js') ?: time();
$listSearchV = @filemtime($assetsRoot . '/js/shared/list-search.js') ?: time();
$pageHelpersV = @filemtime($assetsRoot . '/js/shared/page-helpers.js') ?: time();
$toastCatalogV = @filemtime($assetsRoot . '/js/shared/toast-catalog.js') ?: time();
$userContextV = @filemtime($assetsRoot . '/js/shared/user-context.js') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>

<body data-theme="light">

    <div class="page-card-list">
        <div class="page-card-list-title">
            <div class="page-card-list-title-line">
                <div class="line-left title">
                    <span class="page-title-text">Cartas Expedidas</span>
                </div>
            </div>
            <div class="page-card-list-title-line">
                <div class="line-left description">
                    <span class="page-description-text">Documentos expedidos pelo Cartório para cumprimento por outras Comarcas</span>
                </div>
                <div class="line-right">
                    <div class="button-icon-left" role="button" tabindex="0" aria-label="Pesquisar" title="Pesquisar" data-v2-panel-trigger="search">
                        <i class="fi fi-rr-search" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left media-1024" role="button" tabindex="0" aria-label="Filtrar" title="Filtrar" data-v2-panel-trigger="filters">
                        <i class="fi fi-rr-filter" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left" role="button" tabindex="0" aria-label="Exportar" title="Exportar" data-v2-panel-trigger="export">
                        <i class="fi fi-rr-file-export" aria-hidden="true"></i>
                    </div>
                    <div class="button-icon-left" role="button" tabindex="0" aria-label="Itens por página" title="Itens por página" data-v2-panel-trigger="items">
                        <i class="fi fi-rr-list" aria-hidden="true"></i>
                    </div>
                    <div class="buttons">
                        <button type="button" class="button-btn btn-primary" id="novo-cadastro-btn">
                            <span class="btn-icon-text">Novo cadastro</span>
                            <div class="btn-icon hidden">
                                <i class="fi fi-rr-spinner" aria-hidden="true"></i>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-line">
            <div class="card-line left">
                <a href="./../../documents/pdf/Guia_de_Expedição_Carta_Precatória.png" target="_blank">
                    <div class="page-card-list-document">
                        <i class="fi fi-rr-file-pdf" aria-hidden="true"></i>
                        <span class="page-card-list-document-text">Infográfico - Expedição de Cartas Precatórias</span>
                    </div>
                </a>
                <a href="./../../documents/pdf/Fluxo_de_Expedição_Carta_Precatória.png" target="_blank">
                    <div class="page-card-list-document">                        
                        <i class="fi fi-rr-file-pdf" aria-hidden="true"></i>
                        <span class="page-card-list-document-text">Infográfico - Fluxo de Cumprimento</span>
                    </div>
                </a>
                <a href="./../../documents/pdf/Guia_Prático_de_Cartas_Judiciárias.png" target="_blank">
                    <div class="page-card-list-document">
                        <i class="fi fi-rr-file-pdf" aria-hidden="true"></i>
                        <span class="page-card-list-document-text">Infográfico - Guia Prático de Tipos de Cartas</span>
                    </div>
                </a>
            </div>
        </div>

        <div class="card-line full" data-v2-panel="search" hidden>
            <div class="card-line left">
                <div class="line-description">Pesquisar processo</div>
            </div>
            <div class="card-line right">
                <input type="text" id="search-input" class="width-300 height-md" placeholder="Pesquisar" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>" autocomplete="off">
            </div>
        </div>

        <div class="card-line full media-768" data-v2-panel="items" hidden>
            <div class="card-line left">
                <div class="line-description">Quantidade de itens por página</div>
            </div>
            <div class="card-line right">
                <select class="width-100 height-md" id="items-per-page">
                    <option value="10" <?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <div class="card-line full media-768" data-v2-panel="export" hidden>
            <div class="card-line left">
                <div class="line-description">Exportar lista</div>
            </div>
            <div class="card-line right">
                <select class="width-200 height-md list-export-select" id="list-export-v2" data-csv-href="<?php echo htmlspecialchars($exportCsvUrl, ENT_QUOTES); ?>" data-pdf-href="<?php echo htmlspecialchars($exportPdfUrl, ENT_QUOTES); ?>" aria-label="Exportar lista">
                    <option value="" selected disabled>Exportar</option>
                    <option value="pdf">PDF</option>
                    <option value="csv">CSV</option>
                    <option value="print">Imprimir</option>
                </select>
            </div>
        </div>

        <div class="card-line full media-1024" data-v2-panel="filters" hidden>
            <div class="card-line left">
                <div class="line-description">Filtrar por</div>
            </div>
            <div class="card-line right">
                <select class="width-300 height-md" id="filter-status">
                    <option value="" <?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="aguardando_envio" <?php echo $statusFilter === 'aguardando_envio' ? ' selected' : ''; ?>>
                        Aguardando envio (<?php echo (int) ($statusCounts['aguardando_envio'] ?? 0); ?>)
                    </option>
                    <option value="aguardando_cumprimento" <?php echo $statusFilter === 'aguardando_cumprimento' ? ' selected' : ''; ?>>
                        Aguardando cumprimento (<?php echo (int) ($statusCounts['aguardando_cumprimento'] ?? 0); ?>)
                    </option>
                    <option value="concluída" <?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)
                    </option>
                    <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)
                    </option>
                </select>
                <select class="width-300 height-md" id="filter-tipo">
                    <option value="" <?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>" <?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo (int) $count; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card-line full results">
            <div class="card-line left results">
                <div class="line-results">
                    <div class="number left"><?php echo (int) $globalAtivosTotal; ?></div>
                    <div class="text">Documentos cadastrados</div>
                    <?php if ($mostraParaVoce && $userAtivosTotal > 0): ?>
                        <div class="text right">para você</div>
                        <div class="number right"><?php echo (int) $userAtivosTotal; ?></div>
                    <?php endif; ?>
                </div>
                <div class="line-results<?php echo $alertAguardandoEnvio ? ' alert' : ''; ?>">
                    <div class="number left"><?php echo (int) $globalAguardandoEnvio; ?></div>
                    <div class="text">Aguardando envio</div>
                    <?php if ($mostraParaVoce && $userAguardandoEnvio > 0): ?>
                        <div class="text right">para você</div>
                        <div class="number right"><?php echo (int) $userAguardandoEnvio; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php foreach ($rows as $row): ?>
            <?php include __DIR__ . '/partials/cartas-expedidas-v2-row.php'; ?>
        <?php endforeach; ?>

        <?php if ($totalPages > 0): ?>
            <div class="card-line pagination">
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
    <script src="/assets/js/modules/cartorio/cartas-expedidas/v2-list.js?v=<?php echo (int) $v2ListJsV; ?>"></script>
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
