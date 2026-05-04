<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/../../repositories/GabineteMonitoramentoEletronicoSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'monitoramentogabinete'));
$statusFilter = trim($_GET['status'] ?? '');
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
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);
    $listResult = $service->listMonitoramentos($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
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

$documentsLabel = $totalDocuments === 1 ? 'monitoramento' : 'monitoramentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptionsUi = ['pendente', 'em_andamento', 'finalizada', 'cancelada'];
$statusCountsRaw = $service->getStatusCounts($filters);
$statusCounts = array_merge(array_fill_keys($statusOptionsUi, 0), $statusCountsRaw);

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
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

$subtarefaLabels = [
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR => 'Iniciar monitoramento',
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR => 'Encerrar monitoramento',
];
$buttonTitleByTipo = [
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR => 'Confirmar início',
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR => 'Confirmar encerramento',
];
$buttonEncerrarPendenteLabel = 'Encerrar monitoramento';

$statusLabelUi = static function (string $s): string {
    $map = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];

    return $map[$s] ?? $s;
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Monitoramento Eletrônico - Gabinete</title>
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
            <span class="all">Interno - Gabinete - Monitoramento Eletrônico -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Monitoramento Eletrônico</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Consulta aos registros de monitoramento eletrônico. Para novo cadastro, use a tela de cadastro.</p>
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

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <input type="text" class="height-md" id="search-input" placeholder="Pesquisar por processo ou réu..." style="width: 290px;" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
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

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-500 height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
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

        <div class="display-flex col col-full register">

            <?php
            $activeFiltersGme = [];
            if ($statusFilter !== '') {
                $activeFiltersGme[] = 'Status [' . htmlspecialchars($statusLabelUi($statusFilter), ENT_QUOTES) . ']';
            }
            if ($processoFilter !== '') {
                $activeFiltersGme[] = 'Processo [' . htmlspecialchars($processoFilter, ENT_QUOTES) . ']';
            }
            if (!empty($activeFiltersGme)) {
                echo '
                    <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);">
                        <div class="display-flex row row-fit justify-start align-center padding-x-sm padding-y-sm gap-y-sm">
                            <span class="text text-ellipsis" style="font-weight: 400; color: var(--color-primary-active); font-size: 12px;">
                                Lista com os seguintes filtros:
                            </span>
                            <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                                ' . implode(' + ', $activeFiltersGme) . '
                            </span>
                        </div>
                    </div>';
            }
            ?>

            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $trGme = (int) ($totalDocuments ?? 0);
                        if ($trGme === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            echo htmlspecialchars((string) $trGme, ENT_QUOTES) . ' ' . htmlspecialchars($trGme === 1 ? 'registro encontrado' : 'registros encontrados', ENT_QUOTES);
                        }
                        ?>
                    </span>
                </div>
            </div>

        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idMon = (string) ($row['IdMon'] ?? '');
                $numSeq = (int) ($row['NumeroSeq'] ?? 0);
                $ano = (int) ($row['Ano'] ?? 0);
                $nAno = $numSeq . '/' . $ano;
                $subtarefas = $row['Subtarefas'] ?? [];
                $allSt = $row['SubtarefasAll'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaCancelada = ($statusTarefa === 'cancelada');
                $tarefaFinalizada = ($statusTarefa === 'finalizada');
                $duracaoLabel = (string) ($row['DuracaoLabel'] ?? '—');
                $classeNome = (string) ($row['ClasseNome'] ?? 'Classe não informada');
                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';

                $progressStatuses = ['pendente', 'pendente'];
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    if ($o >= 1 && $o <= 2) {
                        $stSeg = (string) ($s['status'] ?? 'pendente');
                        $progressStatuses[$o - 1] = $stSeg === 'concluida' ? 'concluida' : ($stSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                    }
                }
                $mostrarBarraStatus = !$tarefaCancelada && !$tarefaFinalizada;
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    for ($i = 0; $i < 2; $i++) {
                        $statusSeg = $progressStatuses[$i] ?? 'pendente';
                        if ($statusSeg === 'concluida') {
                            $segClass = 'concluida';
                        } elseif ($statusSeg === 'em_andamento') {
                            $segClass = 'em_andamento';
                        } else {
                            $segClass = 'pendente';
                        }
                        $ordemSeg = $i + 1;
                        $tipoSeg = $ordemSeg === 1 ? GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR : GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR;
                        $titleSeg = $subtarefaLabels[$tipoSeg] ?? '';
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $titleSeg];
                    }
                }
                $barLabel = $statusTarefa === 'em_andamento' ? 'Em andamento' : 'Pendente';
                $currentSubtarefaTitle = '';
                foreach ($allSt as $s) {
                    if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                        $tipoS = (string) ($s['tipo'] ?? '');
                        $currentSubtarefaTitle = $subtarefaLabels[$tipoS] ?? $tipoS;
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $statusTarefa === 'pendente') {
                    $currentSubtarefaTitle = $subtarefaLabels[GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR] ?? '';
                }
                $expandir = !empty($subtarefas);
                $dataInicioRow = $row['DataInicio'] ?? null;
                $dataInicioBr = '';
                if ($dataInicioRow !== null && $dataInicioRow !== '') {
                    $tsDi = strtotime((string) $dataInicioRow);
                    if ($tsDi !== false) {
                        $dataInicioBr = date('d/m/Y', $tsDi);
                    }
                }
                $barTitleAttr = ($statusTarefa === 'pendente' && $mostrarBarraStatus)
                    ? 'Iniciar e encerrar monitoramento'
                    : $currentSubtarefaTitle;
            ?>
            <div class="display-flex col col-full register border-bottom" data-monitoramento-id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <?php if ($expandir): ?>
                        <div class="col">
                            <label class="button-table" title="Expandir subtarefas">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <?php endif; ?>                        
                        <div class="col width-600 col-process process-info" title="clique para copiar">
                            <span class="minor-info text-ellipsis" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? '')); ?></span>
                        </div>
                        <div class="col width-600 col-reu" title="clique para copiar">
                            <span class="minor-info">Réu</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['ReuNome'] ?? '')); ?></span>
                        </div>
                        <div class="col width-300 col-duracao">
                            <span class="minor-info">Duração</span>
                            <span class="major-info"><?php echo htmlspecialchars($duracaoLabel); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-monitoramento-id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-pen-square"></i>
                        </button>
                        <?php if ($tarefaCancelada): ?>
                        <div class="col">
                            <div class="width-300 padding-x-lg status status-cancelled"><?php echo htmlspecialchars($statusLabelUi('cancelada')); ?></div>
                        </div>
                        <?php elseif ($tarefaFinalizada): ?>
                        <?php elseif ($mostrarBarraStatus): ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($barTitleAttr); ?>">
                            <div class="width-300 status status-pending status-with-progress">
                                <span class="status-task-label width-300"><?php echo htmlspecialchars($barLabel); ?></span>
                                <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo (int) count(array_filter($progressStatuses, static function ($x) { return $x === 'concluida'; })); ?>" aria-valuemin="0" aria-valuemax="2">
                                    <?php foreach ($segmentosBarra as $seg): ?>
                                    <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class']); ?>" title="<?php echo htmlspecialchars($seg['title']); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($expandir): ?>
                <div class="display-flex col col-full subtask subtask-collapsed gme-subtask-panel" id="subtask-<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php if ($statusTarefa === 'em_andamento' && $dataInicioBr !== ''): ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-finished"><?php echo htmlspecialchars($subtarefaLabels[GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR] ?? ''); ?></div>
                            </div>
                            <span class="minor-info">Início em <?php echo htmlspecialchars($dataInicioBr, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($subtarefas as $st):
                        $stId = (string) ($st['id'] ?? '');
                        $stTipo = (string) ($st['tipo'] ?? '');
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $stStatusSt = (string) ($st['status'] ?? '');
                        $precisaData = ($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR);
                        $encerrarPendente = ($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR && $stStatusSt === 'pendente');
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                            <?php if ($precisaData): ?>
                            <div class="col">
                                <input type="date" class="width-300 height-md gme-data-inicio" id="data-inicio-<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center">
                            <?php if ($encerrarPendente): ?>
                            <button type="button" class="btn btn-secondary-outline height-sm" disabled title="Disponível após iniciar o monitoramento">
                                <span class="value-button"><?php echo htmlspecialchars($buttonEncerrarPendenteLabel, ENT_QUOTES); ?></span>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-primary height-sm btn-gme-concluir" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-precisa-data="<?php echo $precisaData ? '1' : '0'; ?>" title="<?php echo htmlspecialchars($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR ? 'Confirma encerramento e altera status para Finalizada' : 'Confirma início e altera status para Em andamento', ENT_QUOTES); ?>">
                                <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                            <span class="minor-info">Nenhuma subtarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        </div>

        <div class="card-buttons">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls gap-y-sm">
                    <?php
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $currentPage > 1 ? $currentPage - 1 : 1;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
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
        (function () {
            if (!window.App || !App.GmeList || typeof App.GmeList.initialize !== 'function') {
                return;
            }

            App.GmeList.initialize({
                storageKey: 'gme.postReloadToast',
                routePage: '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>',
                newHref: '/frame.php?page=monitoramentogabinetecreate',
                editHrefBuilder: function (id) {
                    return '/frame.php?page=monitoramentogabineteedit&id=' + encodeURIComponent(id);
                },
                concludeEndpoint: '/api/gabinete-monitoramento-eletronico/concluir-subtarefa.php',
                concludeSuccessMessage: 'Operação concluída.'
            });
        })();
    </script>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>
</body>
</html>
