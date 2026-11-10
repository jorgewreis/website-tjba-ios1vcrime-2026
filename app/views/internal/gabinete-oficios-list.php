<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosGabineteService.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/OficioRegistroMetadataHelper.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'oficiosgabinete'));
$statusFilter = trim($_GET['status'] ?? '');
$oficioFilter = trim($_GET['oficio'] ?? '');
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

$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new OficiosGabineteService($connection);
    /** @var list<int> $anosComOficios */
    $anosComOficios = $service->listAnosComOficios();
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$anoAtual = (int) date('Y');
$anoFromGet = trim($_GET['ano'] ?? '');
if ($anoFromGet !== '' && ctype_digit($anoFromGet) && in_array((int) $anoFromGet, $anosComOficios, true)) {
    $anoFilter = $anoFromGet;
} elseif ($anosComOficios !== []) {
    $anoFilter = in_array($anoAtual, $anosComOficios, true) ? (string) $anoAtual : (string) $anosComOficios[0];
} else {
    $anoFilter = '';
}

$filters = [
    'status' => $statusFilter,
    'oficio' => $oficioFilter,
    'ano' => $anoFilter,
];

try {
    $listResult = $service->listOficios($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows);
$oficioListViewerContext = OficioRegistroMetadataHelper::viewerContextFromSession();
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
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $totalDocuments === 1 ? 'ofício' : 'ofícios';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptionsUi = ['pendente', 'finalizada', 'cancelada'];
$statusCountsRaw = $service->getStatusCounts($filters);
$statusCounts = array_merge(array_fill_keys($statusOptionsUi, 0), $statusCountsRaw);

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($oficioFilter !== '') {
    $baseQueryParams['oficio'] = $oficioFilter;
    if ($anoFilter !== '') {
        $baseQueryParams['ano'] = $anoFilter;
    }
} else {
    if ($statusFilter !== '') {
        $baseQueryParams['status'] = $statusFilter;
    }
    if ($anoFilter !== '') {
        $baseQueryParams['ano'] = $anoFilter;
    }
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

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
        <title>Ofícios - Gabinete</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Ofícios -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active);">Ofícios</h1>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">

            </div>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="filter-processo" class="display-flex col gap-y-sm" style="align-items: flex-start;">
                        <span>Número do processo <span id="gab-processo-hint" class="label-hint-obrigatorio">*obrigatório</span></span>
                    </label>
                    <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col">
                    <label for="filter-classe">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" data-datajud-silent-loading="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
                </div>
                <div class="col">
                    <label for="gab-oficio-tipo">Tipo de ofício <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="gab-oficio-tipo">
                        <option value="">Selecione…</option>
                        <optgroup label="Encaminhamento">
                            <option value="Encaminhando Informações">Encaminhando informações</option>
                            <option value="Encaminhando Mandado de Busca e Apreensao">Encaminhando Mandado de Busca e Apreensão </option>
                            <option value="Encaminhando Mandado de Prisao">Encaminhando Mandado de Prisão</option>
                            <option value="Encaminhando Pecas Processuais">Encaminhando Peças Processuais</option>
                        </optgroup>
                        <optgroup label="Solicitação">
                            <option value="Solicitando Informacoes">Solicitando informações</option>
                            <option value="Solicitando Pecas Processuais">Solicitando peças processuais</option>
                            <option value="Solicitando Providencias">Solicitando providências</option>
                        </optgroup>
                        <optgroup label="Administrativos">
                            <option value="Solicitando Afastamento" data-oficio-grupo="administrativo">Solicitando afastamento</option>
                            <option value="Solicitando Licença Prêmio" data-oficio-grupo="administrativo">Solicitando licença prêmio</option>
                            <option value="Solicitando Férias" data-oficio-grupo="administrativo">Solicitando férias</option>
                            <option value="Solicitando Reembolso" data-oficio-grupo="administrativo">Solicitando reembolso</option>
                            <option value="Outros" data-oficio-grupo="administrativo">Outros</option>
                        </optgroup>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-buttons padding-x-lg padding-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-md">

                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                    <input type="text" class="height-md list-search-input" id="search-input" placeholder="Nº do ofício (ex.: 42, 42/2026) ou processo CNJ" aria-label="Pesquisar por número do ofício (com ou sem ano) ou por número do processo CNJ" value="<?php echo htmlspecialchars($oficioFilter, ENT_QUOTES); ?>">
                    <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                </div>
<button type="button" class="btn btn-secondary-outline" id="btn-gab-oficio-limpar">Limpar</button>
            </div>
            <div class="display-flex row row-fit justify-start gap-y-md">
                <button type="button" class="btn btn-primary" id="btn-gab-oficio-registrar">Registrar</button>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom border-top">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-500 height-md" id="filter-status">
                    <option value="" <?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente" <?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                        Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                    </option>
                    <option value="finalizada" <?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['finalizada'] ?? 0)); ?>)
                    </option>
                    <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                    </option>
                </select>
                <select class="width-300 height-md" id="filter-ano" <?php echo $anosComOficios === [] ? ' disabled' : ''; ?>>
                    <?php if ($anosComOficios === []): ?>
                        <option value="">—</option>
                    <?php else: ?>
                        <?php foreach ($anosComOficios as $y): ?>
                            <option value="<?php echo (int) $y; ?>" <?php echo $anoFilter === (string) $y ? ' selected' : ''; ?>><?php echo (int) $y; ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

            </div>
        </div>

        <div class="display-flex col col-full register">
            <?php
            $activeFilters = [];
            if ($statusFilter !== '') {
                $statusFilterLabels = [
                    'pendente' => 'Pendente',
                    'finalizada' => 'Finalizada',
                    'cancelada' => 'Cancelada',
                ];
                $statusFilterDisplay = $statusFilterLabels[$statusFilter] ?? $statusFilter;
                $activeFilters[] = 'Status [' . htmlspecialchars($statusFilterDisplay, ENT_QUOTES) . ']';
            }
            if ($oficioFilter !== '') {
                $buscaDigits = preg_replace('/\D+/', '', $oficioFilter);
                $buscaRotulo = strlen($buscaDigits) === 20 ? 'Processo' : 'Ofício';
                $activeFilters[] = $buscaRotulo . ' [' . htmlspecialchars($oficioFilter, ENT_QUOTES) . ']';
            }
            if ($anoFilter !== '' && $anosComOficios !== []) {
                $activeFilters[] = 'Ano [' . htmlspecialchars($anoFilter, ENT_QUOTES) . ']';
            }

            if (!empty($activeFilters)) {
                echo '
                    <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);">
                        <div class="display-flex row row-fit justify-start align-center padding-x-sm padding-y-sm gap-y-sm">
                            <span class="text text-ellipsis" style="font-weight: 400; color: var(--color-primary-active); font-size: 12px;">
                                Lista com os seguintes filtros:
                            </span>
                            <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                                ' . implode(' + ', $activeFilters) . '
                            </span>
                        </div>
                    </div>';
            }
            ?>

            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom"
                style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $totalRegistros = (int) ($totalDocuments ?? 0);
                        if ($totalRegistros === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            $totalRegistrosText = $totalRegistros === 1 ? 'registro encontrado' : 'registros encontrados';
                            echo htmlspecialchars((string) $totalRegistros, ENT_QUOTES) . ' ' . htmlspecialchars($totalRegistrosText, ENT_QUOTES);
                        }
                        ?>
                    </span>
                </div>
            </div>

        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idOficio = (string) ($row['IdOficio'] ?? '');
                $numSeq = (int) ($row['NumeroSeq'] ?? 0);
                $ano = (int) ($row['Ano'] ?? 0);
                $nAno = $numSeq . '/' . $ano;
                $oficioMetadata = OficioRegistroMetadataHelper::fromListRow($row, 'gabinete', $oficioListViewerContext);
            ?>
            <div class="display-flex col col-full register register-oficio-list register-with-metadata border-bottom" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center register-oficio-list-row">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-1">
                        <div class="col width-200">
                            <span class="minor-info">Ofício</span>
                            <span class="major-info"><?php echo htmlspecialchars($nAno, ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-500 col-process">
                            <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                        </div>
                        <div class="col width-600">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TipoOficio'] ?? '')); ?></span>
                        </div>
                    </div>
                    <?php include __DIR__ . '/_oficio_registro_metadata.php'; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        </div>

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">
                Mostrando <?php echo htmlspecialchars((string) $startItem); ?>-<?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo htmlspecialchars((string) $totalPages); ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo htmlspecialchars((string) $itemsPerPage); ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
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
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams;
                        $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
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

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>
        (function() {

            if (window.App && App.ListSearch && typeof App.ListSearch.initializeProcessSearch === 'function') {
                App.ListSearch.initializeProcessSearch({
                    inputId: 'search-input',                    searchParamKey: 'oficio',
                    filters: [{
                            id: 'filter-status',
                            param: 'status'
                        },
                        {
                            id: 'filter-ano',
                            param: 'ano'
                        },
                    ],
                    minSearchDigits: 1,
                    debounceDelay: 1000,
                    initialProcessFilter: <?php echo json_encode($oficioFilter, JSON_UNESCAPED_UNICODE); ?>,
                });
            }
        })();
    </script>
    <script>
        (function () {
            if (!window.App || !App.OficiosList || typeof App.OficiosList.initialize !== 'function') {
                return;
            }

            App.OficiosList.initialize({
                storageKey: 'cartorio.postReloadToast',
                routePage: '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>',
                typeSelectId: 'gab-oficio-tipo',
                processHintId: 'gab-processo-hint',
                processLabelSelector: 'label[for="filter-processo"]',
                clearButtonId: 'btn-gab-oficio-limpar',
                createButtonId: 'btn-gab-oficio-registrar',
                createEndpoint: '/api/gabinete-oficios/create.php',
                enableDesalocar: false,
                cancelEndpoint: '/api/gabinete-oficios/cancel.php',
                buildRequest: function (ctx) {
                    const proc = document.getElementById('filter-processo')?.value?.trim() || '';
                    const tipo = document.getElementById('gab-oficio-tipo')?.value || '';
                    const cc = document.getElementById('hidden-classe-codigo')?.value || '';
                    const cd = document.getElementById('hidden-classe-descricao')?.value || '';
                    const admin = ctx.isAdministrative();

                    if (!tipo) {
                        ctx.notify('error', 'Validação', 'Selecione o tipo de ofício.');
                        return null;
                    }
                    if (!admin && !proc) {
                        ctx.notify('error', 'Validação', 'Preencha o número do processo.');
                        return null;
                    }
                    if (!admin) {
                        const classeVal = document.getElementById('filter-classe')?.value?.trim() || '';
                        if (classeVal === 'Aguarde...' || classeVal === 'Consultando DataJud…') {
                            ctx.notify('error', 'Validação', 'Aguarde a consulta ao DataJud terminar antes de cadastrar.');
                            return null;
                        }
                    }

                    return {
                        numero_processo: proc,
                        tipo_oficio: tipo,
                        classe_codigo: admin && !proc ? '' : cc,
                        classe_descricao: admin && !proc ? '' : cd
                    };
                },
                buildSuccessPayload: function () {
                    return { type: 'success', title: 'Sucesso', message: 'Ofício registrado com sucesso.' };
                }
            });
        })();
    </script>
</body>
</html>
