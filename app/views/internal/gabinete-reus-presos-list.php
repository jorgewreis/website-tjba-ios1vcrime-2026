<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = 'reuspresos';
$nomeFilter = trim($_GET['nome'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');

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

$filters = [
    'nome' => $nomeFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new GabineteReusPresosService($connection);
    $listResult = $service->listAtivos($filters, $itemsPerPage, $offset);
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
        $listResult = $service->listAtivos($filters, $itemsPerPage, $offset);
        $rows = $listResult['rows'] ?? [];
    }
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $totalDocuments === 1 ? 'réu' : 'réus';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($nomeFilter !== '') {
    $baseQueryParams['nome'] = $nomeFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

function grpFormatDate(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '—';
    }
    $t = strtotime($ymd);

    return $t !== false ? date('d/m/Y', $t) : '—';
}

/**
 * @param 'sem'|'normal'|'atencao'|'urgente'|'atrasada' $nivel
 */
function grpRevisaoClassCss(string $nivel): string
{
    $map = [
        'sem' => 'grp-revisao-sem',
        'normal' => 'grp-revisao-normal',
        'atencao' => 'grp-revisao-atencao',
        'urgente' => 'grp-revisao-urgente',
        'atrasada' => 'grp-revisao-atrasada',
    ];

    return $map[$nivel] ?? 'grp-revisao-sem';
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
    <title>Réus Presos - Gabinete</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <style>
        /* Destaque da próxima reavaliação (lista Gabinete — réus presos) */
        .grp-revisao-wrap { display: inline-block; max-width: 100%; }
        .grp-revisao-sem { color: var(--color-gray-600); font-weight: 600; }
        .grp-revisao-normal { 
            width: fit-content !important;
            color: var(--color-gray-800);
            font-size: 11px;
            font-weight: 500;
            background: var(--color-gray-200);
            padding: 2px 5px 1px 5px;
            border-radius: 3px;
        }
        .grp-revisao-atencao {
            width: fit-content !important;
            color: var(--color-gray-800);
            font-size: 11px;
            font-weight: 700;
            background: var(--color-warning);
            padding: 2px 5px 1px 5px;
            border-radius: 3px;
        }
        .grp-revisao-urgente {
            width: fit-content !important;
            color: var(--color-gray-000);
            font-size: 11px;
            font-weight: 700;
            background: var(--color-red-500);
            padding: 2px 5px 1px 5px;
            border-radius: 3px;
        }
        .grp-revisao-atrasada {
            width: fit-content !important;
            color: var(--color-gray-000);
            font-size: 11px;
            font-weight: 700;
            background: var(--color-gray-800);
            padding: 2px 5px 1px 5px;
            border-radius: 3px;
        }
        .grp-sub-line-primary .grp-sub-left { flex: 1 1 auto; min-width: 0; max-width: 55%; }
        .grp-sub-line-primary .grp-sub-right { flex: 1 1 auto; min-width: 0; max-width: 45%; justify-content: flex-end; text-align: right; }
        .grp-sub-line-compact { padding-top: 4px !important; padding-bottom: 4px !important; }
        .grp-sub-line-compact .major-info { font-size: 0.95em; line-height: 1.3; }
    </style>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete -</span>
            <span class="specific">Réus Presos</span>
        </div>
        <h1 class="title-page">Réus presos</h1>
        <p class="description-page">Acompanhamento de custódia, processo principal e prazo de reavaliação. Use <strong>expandir</strong> para ver cada processo vinculado (principal com última movimentação e data; demais em linha compacta).</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2 style="margin:0;">Lista</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <button type="button" class="btn btn-primary height-md" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                <div class="col">
                    <label for="filter-nome">Nome do réu</label>
                    <input type="text" class="width-600 height-md" id="filter-nome" placeholder="Parte do nome" value="<?php echo htmlspecialchars($nomeFilter, ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="filter-processo">Processo (CNJ)</label>
                    <input type="text" class="width-400 height-md" id="filter-processo" placeholder="Número ou trecho" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-end">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Nenhum réu ativo encontrado com os filtros informados.</span>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($rows as $row):
                $rid = (string) ($row['id'] ?? '');
                $prox = grpFormatDate($row['proxima_revisao'] ?? null);
                $nivelRev = (string) ($row['RevisaoDestaque'] ?? 'sem');
                $revClass = grpRevisaoClassCss($nivelRev);
                $revTitle = trim((string) ($row['RevisaoDiasHint'] ?? ''));
                $subLinhas = $row['SubtarefaProcessos'] ?? [];
                $subLinhas = is_array($subLinhas) ? $subLinhas : [];
                ?>
            <div class="display-flex col col-full register border-bottom" data-reu-preso-id="<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                        <div class="col width-600">
                            <span class="minor-info">Réu</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-400">
                            <span class="minor-info">Preso desde <?php echo htmlspecialchars((string) ($row['DataPrisao'] ?? '—'), ENT_QUOTES); ?></span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TempoCustodiaLabel'] ?? '—'), ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-400">
                            <span class="minor-info">Próxima reavaliação</span>
                            <span class="major-info text-ellipsis grp-revisao-wrap <?php echo htmlspecialchars($revClass, ENT_QUOTES); ?>" <?php echo $revTitle !== '' ? ' title="' . htmlspecialchars($revTitle, ENT_QUOTES) . '"' : ''; ?>><?php echo htmlspecialchars($prox, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="col">
                            <button type="button" class="btn btn-editar" title="Editar" aria-label="Editar" data-id="<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>"><i class="fi fi-rr-pen-square"></i></button>
                        </div>
                    </div>
                </div>

                <div class="display-flex col col-full grp-subtask-panel" id="subtask-grp-<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php if (empty($subLinhas)): ?>
                    <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top">
                        <span class="minor-info">Nenhum processo vinculado.</span>
                    </div>
                    <?php else: ?>
                        <?php foreach ($subLinhas as $sl):
                            $isPrincipal = !empty($sl['principal']);
                            if ($isPrincipal): ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top align-start flex-wrap grp-sub-line grp-sub-line-primary" style="font-family: monospace; font-size: 11px;">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap grp-sub-left">
                            <div class="col">
                                <span class="width-700 minor-info text-ellipsis"><?php echo htmlspecialchars((string) ($sl['numero_processo'] ?? '—') . ' (' . ($sl['classe_nome'] ?? '—') . ')', ENT_QUOTES); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-md">
                            <div class="col">
                                <span class="width-700 minor-info text-ellipsis"><?php echo htmlspecialchars((string) ($sl['ultima_movimentacao_data'] ?? '—') . ' — ' . ($sl['ultima_movimentacao'] ?? '—'), ENT_QUOTES); ?></span>
                            </div>
                        </div>
                    </div>
                            <?php else: ?>
                    <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top">
                        <span class="width-700 minor-info text-ellipsis" title="<?php echo htmlspecialchars((string) ($sl['texto_compacto'] ?? ''), ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) ($sl['texto_compacto'] ?? '—'), ENT_QUOTES); ?></span>
                    </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
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

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
    (function() {
        var applyFilters = function () {
            var nome = document.getElementById('filter-nome')?.value?.trim() ?? '';
            var proc = document.getElementById('filter-processo')?.value?.trim() ?? '';
            var perPage = document.getElementById('items-per-page')?.value ?? '25';
            var params = new URLSearchParams(window.location.search);
            params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES, 'UTF-8'); ?>');
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (nome) params.set('nome', nome); else params.delete('nome');
            if (proc) params.set('processo', proc); else params.delete('processo');
            window.location.href = '?' + params.toString();
        };

        document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
        document.getElementById('filter-nome')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });
        document.getElementById('filter-processo')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        document.getElementById('novo-cadastro-btn')?.addEventListener('click', function() {
            window.location.href = '/frame.php?page=reuspresoscreate';
        });
        document.querySelectorAll('.btn-editar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (id) window.location.href = '/frame.php?page=reuspresosedit&id=' + encodeURIComponent(id);
            });
        });

        document.querySelectorAll('input[name="open-subtask-grp"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var mid = this.value;
                var targetId = 'subtask-grp-' + mid;
                document.querySelectorAll('.grp-subtask-panel').forEach(function (row) {
                    var isTarget = row.id === targetId;
                    row.classList.toggle('subtask-collapsed', !isTarget);
                    row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                });
                document.querySelectorAll('input[name="open-subtask-grp"]').forEach(function (r) {
                    var p = document.getElementById('subtask-grp-' + r.value);
                    if (p) {
                        r.setAttribute('aria-expanded', r.checked && r.value === mid ? 'true' : 'false');
                    }
                });
            });
        });
    })();
    </script>
</body>
</html>
