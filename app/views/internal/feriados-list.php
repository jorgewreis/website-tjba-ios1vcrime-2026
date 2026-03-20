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
$ativoFilter = isset($_GET['ativo']) ? trim((string) $_GET['ativo']) : '';

$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
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
if ($ativoFilter === '0' || $ativoFilter === '1') {
    $filters['ativo'] = (int) $ativoFilter;
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
    $totalPages = 0;
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
    'federal' => 'Federal',
    'religioso' => 'Religioso',
    'compensacao' => 'Compensação',
    'recesso' => 'Recesso',
];
$labelsRegra = [
    'fixa' => 'Fixa',
    'unica' => 'Única',
    'movel' => 'Móvel',
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
if ($ativoFilter === '0' || $ativoFilter === '1') {
    $baseQueryParams['ativo'] = $ativoFilter;
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
        <h1 class="title-page">Feriados e recessos</h1>
        <p class="description-page">Cadastro de períodos em que não há expediente forense, para apoio ao cálculo de decurso de prazo e indisponibilidades.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <button type="button" class="btn btn-primary height-md" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex col col-full" style="width:100%">
            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                    <div class="col">
                        <label for="filter-tipo">Tipo</label>
                        <select class="width-300 height-md" name="tipo_feriado" id="filter-tipo">
                            <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Todos</option>
                            <?php foreach ($labelsTipo as $val => $lab): ?>
                                <option value="<?php echo htmlspecialchars($val); ?>"<?php echo $tipoFilter === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label for="filter-ativo">Situação</label>
                        <select class="width-200 height-md" name="ativo" id="filter-ativo">
                            <option value=""<?php echo $ativoFilter === '' ? ' selected' : ''; ?>>Todos</option>
                            <option value="1"<?php echo $ativoFilter === '1' ? ' selected' : ''; ?>>Ativo</option>
                            <option value="0"<?php echo $ativoFilter === '0' ? ' selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="filter-q">Descrição</label>
                        <input type="text" class="width-400 height-md" name="q" id="filter-q" placeholder="Parte da descrição" value="<?php echo htmlspecialchars($qFilter); ?>">
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-end">
                    <div class="col">
                        <label for="items-per-page">Itens por página</label>
                        <select class="width-300 height-md" name="items_per_page" id="items-per-page">
                            <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                            <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                            <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                            <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Nenhum registro encontrado com os filtros informados.</span>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($rows as $row):
                $fid = (string) ($row['id'] ?? '');
                $tipo = (string) ($row['tipo_feriado'] ?? '');
                $regra = (string) ($row['regra_data'] ?? '');
                $ativoRow = !empty($row['ativo']);
                ?>
            <div class="display-flex col col-full register border-bottom" data-feriado-id="<?php echo htmlspecialchars($fid, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                        <div class="col width-400">
                            <span class="minor-info">Período</span>
                            <span class="major-info"><?php echo feriadosFormatDate($row['data_inicio'] ?? null); ?> — <?php echo feriadosFormatDate($row['data_fim'] ?? null); ?></span>
                        </div>
                        <div class="col width-300">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info"><?php echo htmlspecialchars($labelsTipo[$tipo] ?? $tipo); ?></span>
                        </div>
                        <div class="col width-300">
                            <span class="minor-info">Regra</span>
                            <span class="major-info"><?php echo htmlspecialchars($labelsRegra[$regra] ?? $regra); ?></span>
                        </div>
                        <div class="col width-500">
                            <span class="minor-info">Descrição</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['descricao'] ?? '—')); ?></span>
                        </div>
                        <div class="col width-200">
                            <span class="minor-info">Situação</span>
                            <span class="major-info"><?php echo $ativoRow ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-secondary-outline height-md btn-editar" data-id="<?php echo htmlspecialchars($fid, ENT_QUOTES); ?>">Editar</button>
                        <button type="button" class="btn btn-secondary-outline height-md btn-excluir" data-id="<?php echo htmlspecialchars($fid, ENT_QUOTES); ?>">Excluir</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
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
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo (int) $page; ?></a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo (int) $page; ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var applyFilters = function () {
            var tipo = document.getElementById('filter-tipo')?.value ?? '';
            var ativo = document.getElementById('filter-ativo')?.value ?? '';
            var q = document.getElementById('filter-q')?.value?.trim() ?? '';
            var perPage = document.getElementById('items-per-page')?.value ?? '25';
            var params = new URLSearchParams(window.location.search);
            params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES, 'UTF-8'); ?>');
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (tipo) params.set('tipo_feriado', tipo); else params.delete('tipo_feriado');
            if (ativo !== '') params.set('ativo', ativo); else params.delete('ativo');
            if (q) params.set('q', q); else params.delete('q');
            window.location.href = '?' + params.toString();
        };

        document.getElementById('filter-tipo')?.addEventListener('change', applyFilters);
        document.getElementById('filter-ativo')?.addEventListener('change', applyFilters);
        document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
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
        document.querySelectorAll('.btn-excluir').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (!id) return;
                if (!confirm('Excluir este feriado permanentemente?')) return;
                var el = this;
                el.disabled = true;
                fetch('/api/admin/feriados/delete.php?id=' + encodeURIComponent(id), { method: 'POST', credentials: 'same-origin' })
                    .then(function(r) { return r.json().then(function(j) {	return { ok: r.ok, j: j }; }); })
                    .then(function(o) {
                        if (o.ok) {
                            var msgEx = 'Excluído.';
                            var nEd = parseInt(o.j.editais_decurso_recalculados, 10);
                            if (!isNaN(nEd) && nEd > 0) {
                                msgEx += ' Decurso de prazo recalculado em ' + nEd + ' ' + (nEd === 1 ? 'edital' : 'editais') + '.';
                            }
                            if (typeof showToast === 'function') showToast({ type: 'success', message: msgEx });
                            window.location.reload();
                        } else {
                            if (typeof showToast === 'function') showToast({ type: 'error', message: (o.j && o.j.error) ? o.j.error : 'Erro ao excluir.' });
                            el.disabled = false;
                        }
                    })
                    .catch(function() { el.disabled = false; });
            });
        });
    })();
    </script>
</body>
</html>
