<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';

$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter === 'agendada') {
    $statusFilter = 'designada';
}
if ($statusFilter === 'adiada') {
    $statusFilter = 'redesignada';
}
$periodoFilter = trim((string) ($_GET['periodo'] ?? ''));
$tipoFilter = trim((string) ($_GET['tipo'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$offset = ($pageNumber - 1) * $itemsPerPage;

$listFilters = [
    'status' => $statusFilter,
    'periodo' => $periodoFilter,
    'tipo' => $tipoFilter,
    'search' => $search,
];
$result = $service->list($listFilters, $itemsPerPage, $offset);
$tipos = $service->listTiposAtivos();
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$statusCounts = $result['statusCounts'] ?? ['designada' => 0, 'realizada' => 0, 'redesignada' => 0, 'cancelada' => 0, 'em_andamento' => 0, 'finalizada' => 0];

if ($total === 0) {
    $totalPages = 0;
    $pageNumber = 1;
    $offset = 0;
} else {
    $totalPages = (int) ceil($total / $itemsPerPage);
    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
        $offset = ($pageNumber - 1) * $itemsPerPage;
        $result = $service->list($listFilters, $itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
        $statusCounts = $result['statusCounts'] ?? $statusCounts;
    }
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $total === 1 ? 'audiência' : 'audiências';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$baseQueryParams = [
    'page' => 'estudapauta',
    'items_per_page' => $itemsPerPage,
];
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($periodoFilter !== '') {
    $baseQueryParams['periodo'] = $periodoFilter;
}
if ($tipoFilter !== '') {
    $baseQueryParams['tipo'] = $tipoFilter;
}
if ($search !== '') {
    $baseQueryParams['search'] = $search;
}

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($pageNumber - 2, $totalPages - 4));
        $windowEnd = min($totalPages, $windowStart + 4);
        for ($p = $windowStart; $p <= $windowEnd; $p++) {
            $paginationButtons[] = $p;
        }
        if ($windowEnd < $totalPages) {
            $paginationEllipsis = true;
            $extraStart = max($windowEnd + 1, $totalPages - 1);
            for ($p = $extraStart; $p <= $totalPages; $p++) {
                $trailingPages[] = $p;
            }
        }
    }
}

$statusOptionLabels = [
    'designada' => 'Designada',
    'em_andamento' => 'Em andamento',
    'finalizada' => 'Finalizada',
    'realizada' => 'Realizada',
    'redesignada' => 'Redesignada',
    'cancelada' => 'Cancelada',
];
$periodoChipLabels = [
    'hoje' => 'Hoje',
    'semana' => 'Próximos 7 dias',
    'mes' => 'Este mês',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Estudantes</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Estudantes - Audiências -</span>
            <span class="specific">Pauta de audiências</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Pauta de Audiências</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Lista de audiências (somente leitura). Use os filtros para localizar por data, tipo ou situação.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters">
                    <i class="fi fi-rr-filter"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <input type="text" class="height-md" id="search-input" placeholder="Processo, parte ou sala..." autocomplete="off" style="width: 290px;" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <select class="width-400 height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="designada"<?php echo $statusFilter === 'designada' ? ' selected' : ''; ?>>Designada (<?php echo (int) ($statusCounts['designada'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                    <option value="realizada"<?php echo $statusFilter === 'realizada' ? ' selected' : ''; ?>>Realizada (<?php echo (int) ($statusCounts['realizada'] ?? 0); ?>)</option>
                    <option value="redesignada"<?php echo $statusFilter === 'redesignada' ? ' selected' : ''; ?>>Redesignada (<?php echo (int) ($statusCounts['redesignada'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                </select>
                <select class="width-500 height-md" id="filter-periodo">
                    <option value=""<?php echo $periodoFilter === '' ? ' selected' : ''; ?>>Todo o período</option>
                    <option value="hoje"<?php echo $periodoFilter === 'hoje' ? ' selected' : ''; ?>>Hoje</option>
                    <option value="semana"<?php echo $periodoFilter === 'semana' ? ' selected' : ''; ?>>Próximos 7 dias</option>
                    <option value="mes"<?php echo $periodoFilter === 'mes' ? ' selected' : ''; ?>>Este mês</option>
                </select>
                <select class="width-500 height-md" id="filter-tipo">
                    <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <?php foreach ($tipos as $tp): $tpNome = (string) ($tp['nome'] ?? ''); ?>
                        <option value="<?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?>"<?php echo $tipoFilter === $tpNome ? ' selected' : ''; ?>><?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="items" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Itens por página</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-300 height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="items">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <?php if ($statusFilter !== '' || $periodoFilter !== '' || $tipoFilter !== '' || $search !== ''): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm filter-summary wrap gap-y-sm">
                <?php if ($search !== ''): ?>
                <span class="filter-chip">Pesquisa: <?php echo htmlspecialchars($search); ?></span>
                <?php endif; ?>
                <?php if ($statusFilter !== ''): ?>
                <span class="filter-chip">Status: <?php echo htmlspecialchars($statusOptionLabels[$statusFilter] ?? $statusFilter); ?></span>
                <?php endif; ?>
                <?php if ($periodoFilter !== ''): ?>
                <span class="filter-chip">Período: <?php echo htmlspecialchars($periodoChipLabels[$periodoFilter] ?? $periodoFilter); ?></span>
                <?php endif; ?>
                <?php if ($tipoFilter !== ''): ?>
                <span class="filter-chip">Tipo: <?php echo htmlspecialchars($tipoFilter); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $total; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>

        <?php foreach ($rows as $row): ?>
        <div class="display-flex col col-full register border-bottom" data-audiencia-id="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . substr((string) $row['hora_audiencia'], 0, 5), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400 col-process process-info" title="clique para copiar">
                        <?php
                            $classeAudiencia = !empty($row['classe_nome']) ? $row['classe_nome'] : 'Classe não informada';
                            $classeStyleAudiencia = $classeAudiencia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info text-ellipsis" <?php echo $classeStyleAudiencia; ?>><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Tipo de audiência</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Intimações</span>
                        <span class="major-info">
                            <?php
                                $cumprimentos = is_array($row['partes_cumprimento'] ?? null) ? $row['partes_cumprimento'] : [];
                                foreach ($cumprimentos as $cumprimentoParte):
                                    $cp = is_array($cumprimentoParte)
                                        ? $cumprimentoParte
                                        : ['nome_parte' => '', 'cumprimento' => (string) $cumprimentoParte];
                                    $statusCumpr = (string) ($cp['cumprimento'] ?? '');
                                    $miniClass = $statusCumpr === 'cumprido'
                                        ? 'status-finished'
                                        : ($statusCumpr === 'nao_cumprido' ? 'status-cancelled' : 'status-pending');
                            ?>
                                <div class="mini-status <?php echo $miniClass; ?>"></div>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $total; ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $pageNumber > 1 ? $pageNumber - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $prevHref = '?' . http_build_query($prevParams);
                    $nextPage = $pageNumber < $totalPages ? $pageNumber + 1 : $pageNumber;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
                    $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $pageNumber <= 1 ? ' disabled' : ''; ?>" <?php if ($pageNumber > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                    <?php foreach ($paginationButtons as $pBtn): ?>
                        <?php $pageParams = $baseQueryParams;
                        $pageParams['page_number'] = $pBtn; ?>
                        <button type="button" class="pagination-number<?php echo $pBtn === $pageNumber ? ' active' : ''; ?>" <?php if ($pBtn !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $pBtn): ?>
                            <?php $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $pBtn; ?>
                            <button type="button" class="pagination-number<?php echo $pBtn === $pageNumber ? ' active' : ''; ?>" <?php if ($pBtn !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $pageNumber >= $totalPages ? ' disabled' : ''; ?>" <?php if ($pageNumber < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/audiencias/pauta-list-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list-estudantes.js') ?: time(); ?>"></script>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>
</body>
</html>
