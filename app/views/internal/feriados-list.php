<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/FeriadosService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = 'feriadoslist';
$tipoFilter = trim($_GET['tipo_feriado'] ?? '');
$qFilter = trim($_GET['q'] ?? '');

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

$filters = ['q' => $qFilter];
if ($tipoFilter !== '') {
    $filters['tipo_feriado'] = $tipoFilter;
}

$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new FeriadosService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 1;
    $currentPage = 1;
    $offset = 0;
} else {
    $totalPages = (int) ceil($totalDocuments / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
        $listResult = $service->list($filters, $itemsPerPage, $offset);
        $rows = $listResult['rows'] ?? [];
    }
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $totalDocuments === 1 ? 'feriado' : 'feriados';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$labelsTipo = [
    'estadual' => 'Estadual',
    'municipal' => 'Municipal',
    'nacional' => 'Nacional',
    'religioso' => 'Religioso',
    'compensacao' => 'Compensação',
    'recesso' => 'Recesso',
];
$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($tipoFilter !== '') {
    $baseQueryParams['tipo_feriado'] = $tipoFilter;
}
if ($qFilter !== '') {
    $baseQueryParams['q'] = $qFilter;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

function feriadosFormatDate(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $t = strtotime($ymd);

    return $t !== false ? date('d/m/Y', $t) : htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8');
}

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Feriados - Primeira Vara Crime de Ilhéus</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Admin -</span>
            <span class="specific">Feriados</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active);">Feriados e recessos</h1>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">

                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                    <input type="text" class="height-md list-search-input" id="filter-q" placeholder="Parte da descrição" value="<?php echo htmlspecialchars($qFilter); ?>">
                    <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                </div>
<button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-300 height-md" name="tipo_feriado" id="filter-tipo">
                    <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Todos os tipos</option>
                    <?php foreach ($labelsTipo as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"<?php echo $tipoFilter === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>

            </div>
        </div>

        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <?php if ($tipoFilter !== '' || $qFilter !== ''): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm filter-summary wrap gap-y-sm">
                <?php if ($tipoFilter !== ''): ?>
                <span class="filter-chip">Tipo: <?php echo htmlspecialchars($labelsTipo[$tipoFilter] ?? $tipoFilter); ?></span>
                <?php endif; ?>
                <?php if ($qFilter !== ''): ?>
                <span class="filter-chip">Descrição: <?php echo htmlspecialchars($qFilter); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>

        <?php if (empty($rows)): ?>
        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Nenhum registro encontrado com os filtros informados.</span>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($rows as $row):
                $fid = (string) ($row['id'] ?? '');
                $tipo = (string) ($row['tipo_feriado'] ?? '');
                ?>
            <div class="display-flex col col-full register border-bottom" data-feriado-id="<?php echo htmlspecialchars($fid, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                        <div class="col width-500">
                            <span class="minor-info">Período</span>
                            <span class="major-info"><?php echo feriadosFormatDate($row['data_inicio'] ?? null); ?> — <?php echo feriadosFormatDate($row['data_fim'] ?? null); ?></span>
                        </div>
                        <div class="col width-300">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info"><?php echo htmlspecialchars($labelsTipo[$tipo] ?? $tipo); ?></span>
                        </div>
                        <div class="col width-700">
                            <span class="minor-info">Descrição</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['descricao'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="col part-edit">
                            <button type="button" class="btn btn-editar" title="Editar" aria-label="Editar" data-id="<?php echo htmlspecialchars($fid, ENT_QUOTES); ?>"><i class="fi fi-rr-pen-square"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
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
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var applyFilters = function () {
            var tipo = document.getElementById('filter-tipo')?.value ?? '';
            var q = document.getElementById('filter-q')?.value?.trim() ?? '';
            var perPage = '15';
            var params = new URLSearchParams(window.location.search);
            params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES, 'UTF-8'); ?>');
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (tipo) params.set('tipo_feriado', tipo); else params.delete('tipo_feriado');
            params.delete('ativo');
            if (q) params.set('q', q); else params.delete('q');
            window.location.href = '?' + params.toString();
        };

        document.getElementById('filter-tipo')?.addEventListener('change', applyFilters);
        document.getElementById('filter-q')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilters();
            }
        });

        document.getElementById('novo-cadastro-btn')?.addEventListener('click', function() {
            window.location.href = '/frame.php?page=feriadoscreate';
        });
        document.querySelectorAll('.btn-editar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (id) window.location.href = '/frame.php?page=feriadosedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
</body>
</html>
