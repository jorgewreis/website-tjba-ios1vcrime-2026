<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();

$currentUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$podeVerEmailResumoReusPresos = false;
if ($currentUserId !== '') {
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $podeVerEmailResumoReusPresos = count(array_intersect($perfisUsuario, ['ADMIN', 'MAGISTRADA'])) > 0;
}

$routePage = 'reuspresos';
$qFilter = trim((string) ($_GET['q'] ?? ''));

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

$filters = $qFilter !== '' ? ['q' => $qFilter] : [];
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
if ($qFilter !== '') {
    $baseQueryParams['q'] = $qFilter;
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
    <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
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
        .grp-sub-line-primary .grp-sub-left .col-process { margin-right: 8px; }
    </style>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Réus presos -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Réus presos</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Acompanhamento de réus presos, processo principal e prazo de reavaliação.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
                <?php if ($podeVerEmailResumoReusPresos): ?>
                <button type="button" class="btn btn-secondary width-300" id="email-resumo-btn">Email resumo</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e exportação</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <input type="text" class="height-md" id="filter-q" placeholder="Nome ou número do processo" style="width: 290px;" value="<?php echo htmlspecialchars($qFilter, ENT_QUOTES); ?>">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
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

        <div class="display-flex col col-full register">

            <?php if ($qFilter !== ''): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm filter-summary wrap gap-y-sm border-bottom">
                <span class="filter-chip">Busca: <?php echo htmlspecialchars($qFilter, ENT_QUOTES); ?></span>
            </div>
            <?php endif; ?>

            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $trGrp = (int) ($totalDocuments ?? 0);
                        if ($trGrp === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            echo htmlspecialchars((string) $trGrp, ENT_QUOTES) . ' ' . htmlspecialchars($trGrp === 1 ? 'registro encontrado' : 'registros encontrados', ENT_QUOTES);
                        }
                        ?>
                    </span>
                </div>
            </div>

        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $rid = (string) ($row['id'] ?? '');
                $prox = grpFormatDate($row['proxima_revisao'] ?? null);
                $nivelRev = (string) ($row['RevisaoDestaque'] ?? 'sem');
                $revClass = grpRevisaoClassCss($nivelRev);
                $revTitle = trim((string) ($row['RevisaoDiasHint'] ?? ''));
                $subLinhas = $row['SubtarefaProcessos'] ?? [];
                $subLinhas = is_array($subLinhas) ? $subLinhas : [];
                $subLinhas = array_values(array_filter($subLinhas, static function ($sl) {
                    return is_array($sl) && !empty($sl['principal']);
                }));
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

                <div class="display-flex col col-full grp-subtask-panel" id="subtask-grp-<?php echo htmlspecialchars($rid, ENT_QUOTES); ?>">
                    <?php if (empty($subLinhas)): ?>
                    <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top">
                        <span class="minor-info">Nenhum processo vinculado.</span>
                    </div>
                    <?php else: ?>
                        <?php foreach ($subLinhas as $sl):
                            $isPrincipal = !empty($sl['principal']);
                            if ($isPrincipal): ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top align-start flex-wrap grp-sub-line grp-sub-line-primary">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap grp-sub-left align-start">
                            <div class="col width-500 col-process">
                                <?php $row = $sl; $processo_bloco_model = 'full'; include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-md grp-sub-right">
                            <div class="col">
                                <?php
                                    $obsUltMov = trim((string) ($sl['ultima_movimentacao_observacao'] ?? ''));
                                    $textoUltMov = (string) ($sl['ultima_movimentacao_data'] ?? '—') . ' — ' . (string) ($sl['ultima_movimentacao'] ?? '—');
                                    if ($obsUltMov !== '') {
                                        $textoUltMov .= ' (' . $obsUltMov . ')';
                                    }
                                ?>
                                <span class="width-700 minor-info text-ellipsis"><?php echo htmlspecialchars($textoUltMov, ENT_QUOTES); ?></span>
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

        </div>

        <div class="card-buttons">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls gap-y-sm">
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
        var notify = function (type, title, message) {
            if (window.App && App.Page && typeof App.Page.notify === 'function') {
                App.Page.notify(type, title, message);
                return;
            }
            if (typeof showToast === 'function') {
                showToast({ type: type, variant: 'full', title: title, message: message });
            }
        };

        var applyFilters = function () {
            var q = document.getElementById('filter-q')?.value?.trim() ?? '';
            var perPage = document.getElementById('items-per-page')?.value ?? '25';
            var params = new URLSearchParams(window.location.search);
            params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES, 'UTF-8'); ?>');
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (q) params.set('q', q); else params.delete('q');
            params.delete('nome');
            params.delete('processo');
            window.location.href = '?' + params.toString();
        };

        document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
        document.getElementById('filter-q')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyFilters(); }
        });

        document.getElementById('novo-cadastro-btn')?.addEventListener('click', function() {
            window.location.href = '/frame.php?page=reuspresoscreate';
        });
        document.getElementById('email-resumo-btn')?.addEventListener('click', function () {
            var btn = this;
            var defaultLabel = btn.textContent || 'Email resumo';
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            fetch('/api/gabinete-reus-presos/send-revisao-resumo-email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    return { ok: r.ok, body: j };
                });
            })
            .then(function (res) {
                if (res.ok && res.body && res.body.success) {
                    notify('success', 'Sucesso', res.body.message || 'Resumo enviado com sucesso.');
                    return;
                }
                notify('error', 'Erro', (res.body && res.body.message) ? res.body.message : 'Falha ao enviar resumo por e-mail.');
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão ao enviar o resumo.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = defaultLabel;
            });
        });
        document.querySelectorAll('.btn-editar').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (id) window.location.href = '/frame.php?page=reuspresosedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>
</body>
</html>
