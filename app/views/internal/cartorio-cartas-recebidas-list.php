<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasRecebidasService.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/CartasRecebidasUiCopy.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';

$connection = Conection::getInstance()->getConnection();

$currentUserId   = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao        = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'cartasrecebidas'));
$finalidadeFilter = trim($_GET['finalidade'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} elseif (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int) $_GET['page']);
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
    $service = new CartasRecebidasService($connection);
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
    $statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO] =
        (int) ($statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO] ?? 0) + $legacyEmAndamento;
    unset($statusCounts['em_andamento']);
}

$ordensSubtarefasAtivas = CartasRecebidasSubtarefasRepository::ORDENS_ATIVAS;
$ordemLabels = CartasRecebidasUiCopy::mapaLegendaEtapaPorOrdem();

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

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
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
        <title>Cartas Recebidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">

                
        <!-- IMPORT - FLATICON -->
        <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css'>
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <!-- HEADER PAGE -->
    <div class="header-page">
        <!-- MAPA DE NAVEGAÇÃO DO SITE -->
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <!-- CARD PAGE -->
    <div class="container-full card-page">

        <!-- TITLE CARD -->
        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <!-- TÍTULO E DESCRIÇÃO DA PÁGINA -->
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Cartas Recebidas</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Controle de cartas precatórias recebidas de outros tribunais.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters">
                    <i class="fi fi-rr-filter"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <input type="text" class="height-md" id="search-input" placeholder="Pesquisar" style="width: 290px;" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
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

        <!-- SUBTITLE SECTION CARD - FILTERS -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <!-- Filtro - Status -->
                <select class="width-500 height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                        Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                    </option>
                    <option value="<?php echo htmlspecialchars(CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO, ENT_QUOTES); ?>"<?php echo $statusFilter === CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO ? ' selected' : ''; ?>>
                        Aguardando cumprimento (<?php echo htmlspecialchars((string) ($statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO] ?? 0)); ?>)
                    </option>
                    <option value="<?php echo htmlspecialchars(CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO, ENT_QUOTES); ?>"<?php echo $statusFilter === CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO ? ' selected' : ''; ?>>
                        Aguardando devolução (<?php echo htmlspecialchars((string) ($statusCounts[CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO] ?? 0)); ?>)
                    </option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['concluída'] ?? 0)); ?>)
                    </option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                    </option>
                </select>
                <!-- Filtro - Finalidade -->
                <select class="width-700 height-md" id="filter-tipo">
                    <option value=""<?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>"<?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo htmlspecialchars((string) $count); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - ITEMS PER PAGE -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="items" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Itens por página</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <!-- Filtro - Itens por página -->
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

        <!-- TITLE CARD -->
        <!-- <div class="card-title" style="padding: 40px 0 10px 0 !important;">
            <div class="display-flex row row-full justify-start">
                <h2>Lista</h2>
            </div>
        </div> -->
        <!-- END TITLE CARD -->

        <!-- REGISTERS LIST -->
        <div class="display-flex col col-full register">

            <!-- SUBTITLE SECTION CARD - FILTERS INFO -->       
            <?php
                $activeFilters = [];
                if ($statusFilter !== '') {
                    $statusFilterLabels = [
                        'pendente' => 'Pendente',
                        CartasRecebidasUiCopy::STATUS_AGUARDANDO_CUMPRIMENTO => 'Aguardando cumprimento',
                        CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO => 'Aguardando devolução',
                        'em_andamento' => 'Aguardando devolução',
                        'concluída' => 'Finalizada',
                        'cancelada' => 'Cancelada',
                    ];
                    $statusFilterDisplay = $statusFilterLabels[$statusFilter] ?? $statusFilter;
                    $activeFilters[] = 'Status [' . htmlspecialchars($statusFilterDisplay, ENT_QUOTES) . ']';
                }
                if ($finalidadeFilter !== '') {
                    $activeFilters[] = 'Finalidade [' . htmlspecialchars($finalidadeFilter, ENT_QUOTES) . ']';
                }
                if ($processoFilter !== '') {
                    $activeFilters[] = 'Processo [' . htmlspecialchars($processoFilter, ENT_QUOTES) . ']';
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

            <!-- Informa a quantidade de registros encontrados (x ou nenhum) -->
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
                $idCarta = $row['IdCarta'];
                $subtarefas = $row['Subtarefas'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $statusTarefaNorm = strtolower(trim($statusTarefa));
                if ($statusTarefaNorm === 'concluída') {
                    $statusTarefaNorm = 'concluida';
                }
                if ($statusTarefaNorm === 'em_andamento') {
                    $statusTarefaNorm = CartasRecebidasUiCopy::STATUS_AGUARDANDO_DEVOLUCAO;
                }
                $tarefaConcluida = in_array($statusTarefaNorm, ['concluida', 'finalizada'], true);
                $allStRow = $row['SubtarefasAll'] ?? [];
                $subtarefasExpansao = [];
                foreach ($allStRow as $stRow) {
                    if (!is_array($stRow)) {
                        continue;
                    }
                    if (!CartasRecebidasSubtarefasRepository::isTipoAtivo((string) ($stRow['tipo'] ?? ''))) {
                        continue;
                    }
                    $statusStRow = strtolower(trim((string) ($stRow['status'] ?? '')));
                    if ($statusStRow === 'concluída') {
                        $statusStRow = 'concluida';
                    }
                    if (in_array($statusStRow, ['pendente', 'em_andamento'], true)) {
                        $subtarefasExpansao[] = $stRow;
                    }
                }
                usort($subtarefasExpansao, static function (array $a, array $b): int {
                    return ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0));
                });
                $podeExpandirSubtarefas = !$tarefaConcluida && $subtarefasExpansao !== [];
            ?>
            <?php $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'cartas_recebidas', $registroListViewerContext, $connection); ?>
            <div class="display-flex col col-full register register-with-metadata border-bottom" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCarta); ?>">

                <div class="register-line" style="border-bottom: 0;">

                    <div class="register-line-left">
                        <div class="col">
                            <?php if ($podeExpandirSubtarefas): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php else: ?>
                                <span class="button-table" style="visibility:hidden;" aria-hidden="true"><i class="fi fi-rr-angle-small-down"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process">
                            <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                        </div>
                        <div class="col width-700 col-comarca" title="Clique para copiar">
                            <span class="minor-info text-ellipsis" style="font-weight: 600; color: var(--color-gray-900);"><?php echo htmlspecialchars('Comarca: ' . $row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['Finalidade']); ?> — <?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                        </div>
                    </div>

                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                            <?php
                            $alocados = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($row['Alocados'] ?? []);
                            foreach ($alocados as $alocado) {
                                $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                    'entity_id' => (string) ($idCarta ?? ''),
                                    'origem' => 'cartas_recebidas.list',
                                ]);
                                if ($chip === null) {
                                    continue;
                                }
                                $tipo         = $chip['tipo'];
                                $iniciais     = htmlspecialchars($chip['iniciais'], ENT_QUOTES);
                                $chipTitle    = $chip['title'];
                                $chipAria     = $chip['aria_label'];
                                $classeTipo   = $chip['classe_tipo'];
                                $alocadoIdStr = htmlspecialchars($chip['alocado_id'], ENT_QUOTES);
                                $usuarioIdStr = $chip['usuario_id'];
                                $podeRemover  = $isGestao || ($tipo === 'specific' && $usuarioIdStr === $currentUserId);
                                ?>
                                <span class="user-allocate-wrap">
                                    <span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($chipTitle, ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($chipAria, ENT_QUOTES); ?>"><?php echo $iniciais; ?></span>
                                    <?php if ($podeRemover): ?>
                                    <button type="button"
                                        class="user-allocate-remove js-desalocar"
                                        data-alocado-id="<?php echo $alocadoIdStr; ?>"
                                        data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"
                                        data-endpoint="/api/cartorio-cartas-recebidas/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">×</button>
                                    <?php endif; ?>
                                </span>
                                <?php
                            }
                            ?>
                        </div>
                        <?php
                        $st = $row['Status'] ?? '';
                        $allSt = $row['SubtarefasAll'] ?? [];
                        $progressStatuses = [];
                        foreach ($ordensSubtarefasAtivas as $ordemAtiva) {
                            $progressStatuses[$ordemAtiva] = 'pendente';
                        }
                        foreach ($allSt as $s) {
                            $o = (int) ($s['ordem'] ?? 0);
                            if (CartasRecebidasSubtarefasRepository::isOrdemAtiva($o)) {
                                $statusProg = strtolower(trim((string) ($s['status'] ?? 'pendente')));
                                if ($statusProg === 'concluída') {
                                    $statusProg = 'concluida';
                                }
                                $progressStatuses[$o] = $statusProg;
                            }
                        }
                        $currentSubtarefaTitle = CartasRecebidasUiCopy::resolverTituloTarefaListagem($statusTarefa, $allSt);
                        $mostrarBarraStatus = !in_array($statusTarefaNorm, ['concluida', 'cancelada'], true);
                        $segmentosBarra = [];
                        if ($mostrarBarraStatus) {
                            $segmentosBarra[] = ['class' => 'concluida', 'title' => CartasRecebidasUiCopy::CADASTRO_ETAPA];
                            foreach ($ordensSubtarefasAtivas as $ordemAtiva) {
                                $statusSeg = $progressStatuses[$ordemAtiva] ?? 'pendente';
                                $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : ($statusSeg === 'cancelada' ? 'cancelada' : 'pendente'));
                                $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$ordemAtiva] ?? ''];
                            }
                        }
                        ?>
                        <div class="col<?php echo $mostrarBarraStatus ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($statusTarefaNorm === 'concluida') {
                                echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                            } elseif ($statusTarefaNorm === 'cancelada') {
                                echo '<div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = CartasRecebidasUiCopy::rotuloBadgeStatusTarefa($statusTarefa);
                                $classStatus = CartasRecebidasUiCopy::classeCssBadgeStatusTarefa($statusTarefa);
                                echo '<div class="width-300 status ' . $classStatus . ' status-with-progress">';
                                echo '<span class="status-task-label width-300" style="justify-content: flex-start; font-size: 10px;">' . htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES) . '</span>';
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, function ($x) { return $x === 'concluida' || $x === 'cancelada'; }));
                                $maxBarra = 1 + count($ordensSubtarefasAtivas);
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="' . $maxBarra . '" aria-label="Progresso das subtarefas">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="width-300 padding-x-lg status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                        <?php include __DIR__ . '/_registro_metadata.php'; ?>
                    </div>

                </div>

                <?php if ($podeExpandirSubtarefas): ?>
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php foreach ($subtarefasExpansao as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = trim((string) ($st['tipo'] ?? ''));
                        $stStatus = strtolower(trim((string) ($st['status'] ?? '')));
                        if ($stStatus === 'concluída') {
                            $stStatus = 'concluida';
                        }
                        $stLabel = CartasRecebidasUiCopy::textoSpanStatusSubtarefa($stTipo, $stStatus);
                        $btnTitle = CartasRecebidasUiCopy::tituloBotaoPorTipo($stTipo);
                        $podeConcluir = $stStatus === 'em_andamento';
                        $statusClass = CartasRecebidasUiCopy::classeCssSpanStatusSubtarefa($stStatus);
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars((string) $stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status <?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?></div>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center gap-y-sm">
                            <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars((string) $stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?>"<?php echo $podeConcluir ? '' : ' disabled'; ?>>
                                <span class="value-button"><?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- PAGINAÇÃO -->  
        <div class="card-buttons">
            <span class="pagination-info">
                Mostrando <?php echo htmlspecialchars((string) $startItem); ?> a <?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo htmlspecialchars((string) $totalPages); ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo htmlspecialchars((string) $itemsPerPage); ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
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
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>"
                       <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php
                            $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page;
                        ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>>
                            <?php echo $page; ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true">
                            <img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas">
                        </span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php
                                $pageParams = $baseQueryParams;
                                $pageParams['page_number'] = $page;
                            ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>>
                                <?php echo $page; ?>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
                       <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
                </div>
            <?php endif; ?>
        </div> 
    </div>

    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/cartorio/cartas-recebidas/search-list.js"></script>
    <script>
        (function () {
            function initializeListSectionToggles() {
                const sections = document.querySelectorAll('[data-list-section]');

                function setSectionVisibility(sectionName, isVisible) {
                    const section = document.querySelector('[data-list-section="' + sectionName + '"]');
                    if (!section) {
                        return;
                    }

                    section.style.display = isVisible ? 'flex' : 'none';
                }

                function handleToggleClick(event, isVisible) {
                    const sectionName = event.currentTarget.getAttribute(isVisible ? 'data-list-section-trigger' : 'data-list-section-hide');
                    if (!sectionName) {
                        return;
                    }

                    setSectionVisibility(sectionName, isVisible);
                }

                function handleToggleKeydown(event, isVisible) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    handleToggleClick(event, isVisible);
                }

                sections.forEach(function (section) {
                    section.style.display = 'none';
                });

                document.querySelectorAll('[data-list-section-trigger]').forEach(function (trigger) {
                    trigger.addEventListener('click', function (event) {
                        handleToggleClick(event, true);
                    });
                    trigger.addEventListener('keydown', function (event) {
                        handleToggleKeydown(event, true);
                    });
                });

                document.querySelectorAll('[data-list-section-hide]').forEach(function (trigger) {
                    trigger.addEventListener('click', function (event) {
                        handleToggleClick(event, false);
                    });
                    trigger.addEventListener('keydown', function (event) {
                        handleToggleKeydown(event, false);
                    });
                });
            }

            initializeListSectionToggles();

            if (window.CartasRecebidasSearch && typeof window.CartasRecebidasSearch.initializeProcessSearch === 'function') {
                window.CartasRecebidasSearch.initializeProcessSearch({
                    inputId: 'search-input',
                    itemsPerPageId: 'items-per-page',
                    filters: [
                        { id: 'filter-tipo', param: 'finalidade' },
                        { id: 'filter-status', param: 'status' },
                    ],
                    minSearchDigits: 3,
                    debounceDelay: 1000,
                    initialProcessFilter: <?php echo json_encode($processoFilter, JSON_UNESCAPED_UNICODE); ?>,
                });
            }

            if (!window.App || !App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
                return;
            }

            if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                App.RegistroMetadataList.bind({
                    storageKey: 'cartorio.postReloadToast',
                    cancelEndpoint: '/api/cartorio-cartas-recebidas/cancel.php',
                    cancelToastKey: 'cartorio.cartas_recebidas.cancel.success',
                    cancelFallback: { type: 'success', title: 'Sucesso', message: 'Carta cancelada.' }
                });
            }

            App.CartorioTaskList.initialize({
                storageKey: 'cartorio.postReloadToast',
                newButtonId: 'novo-cadastro-btn',
                newHref: '/frame.php?page=cartasrecebidascreate',
                onReady: function (ctx) {
                    const notify = ctx.notify;
                    const queuePostReloadToast = ctx.queuePostReloadToast;

                    document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const subtarefaId = this.getAttribute('data-subtarefa-id');
                            const normalizeTipo = (value) => String(value || '').trim().toLowerCase();
                            if (!subtarefaId) return;
                            const block = this.closest('[data-subtarefa-id][data-subtarefa-tipo]');
                            const getFieldValue = function (className) {
                                const selector = '.' + className;
                                let el = block ? block.querySelector(selector) : null;
                                if (!el && subtarefaId) {
                                    el = document.querySelector(selector + '[data-subtarefa-id="' + subtarefaId + '"]');
                                }
                                return el && typeof el.value !== 'undefined' ? String(el.value || '').trim() : '';
                            };
                            const dadosEspecificos = null;
                            btn.disabled = true;
                            fetch('/api/cartorio-cartas-recebidas/concluir-subtarefa.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    subtarefa_id: subtarefaId,
                                    dados_especificos: dadosEspecificos
                                })
                            })
                                .then(function (response) { return response.json(); })
                                .then(function (data) {
                                    if (data.success) {
                                        queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                                        if (data.tarefa_principal_concluida && data.tarefa_principal_mensagem) {
                                            queuePostReloadToast('success', 'Sucesso', data.tarefa_principal_mensagem);
                                        }
                                        window.location.reload();
                                    } else {
                                        notify('error', 'Erro', data.error || 'Erro ao concluir subtarefa.');
                                        btn.disabled = false;
                                    }
                                })
                                .catch(function () {
                                    notify('error', 'Erro', 'Erro de conexão.');
                                    btn.disabled = false;
                                });
                        });
                    });
                }
            });
        })();
    </script>

</body>
</html>
