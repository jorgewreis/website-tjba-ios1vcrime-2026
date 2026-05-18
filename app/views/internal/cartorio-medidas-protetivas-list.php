<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/MedidasProtetivasService.php';
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

$routePage = trim((string) ($_GET['page'] ?? 'medidasprotetivas'));
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
} elseif (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int) $_GET['page']);
} else {
    $currentPage = 1;
}

$filters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new MedidasProtetivasService($connection);
    $listResult = $service->listMedidasProtetivas($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$totalDocuments = (int) ($listResult['total'] ?? 0);
$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($totalDocuments === 0) {
    $currentPage = 1;
} elseif ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$statusOptions = ['pendente', 'em_andamento', 'concluída', 'cancelada'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$subtarefaLabels = [
    'cadastro_partes_pje' => 'Cadastro de partes no PJe',
    'expedicao_mandados_intimacao' => 'Expedição de mandados de intimação',
    'cadastro_bnmp' => 'Cadastro no BNMP',
    'certidao_cumprimento' => 'Certidão de Cumprimento',
];
$ordemLabels = [
    1 => $subtarefaLabels['cadastro_partes_pje'],
    2 => $subtarefaLabels['expedicao_mandados_intimacao'],
    3 => $subtarefaLabels['cadastro_bnmp'],
    4 => $subtarefaLabels['certidao_cumprimento'],
];
$buttonTitleByTipo = [
    'cadastro_partes_pje' => 'Informar cadastro de partes no PJe',
    'expedicao_mandados_intimacao' => 'Informar expedição de mandados',
    'cadastro_bnmp' => 'Informar cadastro no BNMP',
    'certidao_cumprimento' => 'Expedir certidão',
];

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
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
        <title>Medidas Protetivas - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <!-- HEADER PAGE -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Medidas Protetivas</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Acompanhamento de Medidas Protetivas de Urgência para ações criminais.</p>
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
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro de medidas protetivas</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <input type="text" class="height-md" id="search-input" placeholder="Pesquisar" style="width: 295px;" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
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
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) $statusCounts['pendente']; ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) $statusCounts['em_andamento']; ?>)</option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) $statusCounts['concluída']; ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) $statusCounts['cancelada']; ?>)</option>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <?php
        $activeFilters = [];
        if ($statusFilter !== '') {
            $statusFilterLabels = [
                'pendente' => 'Pendente',
                'em_andamento' => 'Em andamento',
                'concluída' => 'Finalizada',
                'cancelada' => 'Cancelada',
            ];
            $statusFilterDisplay = $statusFilterLabels[$statusFilter] ?? $statusFilter;
            $activeFilters[] = 'Status [' . htmlspecialchars($statusFilterDisplay, ENT_QUOTES) . ']';
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

        <!-- <div class="card-title" style="padding-top: 40px !important;">
            <div class="display-flex row row-full justify-start">
                <h2>Resultado</h2>
            </div>
        </div> -->

        <div class="display-flex col col-full register">
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
                $idMedida = (string) ($row['IdMedida'] ?? '');
                $subtarefas = $row['Subtarefas'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
                $allSt = $row['SubtarefasAll'] ?? [];
                $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente'];
                foreach ($allSt as $subtarefa) {
                    $ordem = (int) ($subtarefa['ordem'] ?? 0);
                    if ($ordem >= 1 && $ordem <= 4) {
                        $progressStatuses[$ordem - 1] = (string) ($subtarefa['status'] ?? 'pendente');
                    }
                }
                $st = (string) ($row['Status'] ?? '');
                $currentSubtarefaTitle = '';
                foreach ($allSt as $subtarefa) {
                    if (trim((string) ($subtarefa['status'] ?? '')) === 'em_andamento') {
                        $currentSubtarefaTitle = $subtarefaLabels[$subtarefa['tipo'] ?? ''] ?? '';
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                    $currentSubtarefaTitle = $ordemLabels[1];
                } elseif ($currentSubtarefaTitle === '' && $st === 'concluída') {
                    $currentSubtarefaTitle = 'Todas as etapas concluídas';
                }
                $mostrarBarraStatus = !in_array($st, ['concluída', 'cancelada'], true);
            ?>
            <?php $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'medidas_protetivas', $registroListViewerContext); ?>
            <div class="display-flex col col-full register register-with-metadata border-bottom" data-medida-id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process">
                            <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                        </div>
                        <div class="col width-600 col-reu" title="Clique para copiar">
                            <span class="minor-info">Réu</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['ReuNome'] ?? '—')); ?></span>
                        </div>
                    </div>
                
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                            <?php
                            $alocados = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($row['Alocados'] ?? []);
                            foreach ($alocados as $alocado) {
                                $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                    'entity_id' => (string) ($idMedida ?? ''),
                                    'origem' => 'medidas.list',
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
                                        data-endpoint="/api/cartorio-medidas-protetivas/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">×</button>
                                    <?php endif; ?>
                                </span>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="col<?php echo $mostrarBarraStatus && $st !== 'concluída' && $st !== 'cancelada' ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php if ($st === 'concluída'): ?>
                                <div class="width-300 padding-x-lg status status-finished">Finalizada</div>
                            <?php elseif ($st === 'cancelada'): ?>
                                <div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>
                            <?php elseif ($mostrarBarraStatus): ?>
                                <div class="width-300 status <?php echo $st === 'em_andamento' ? 'status-current' : 'status-pending'; ?> status-with-progress">
                                    <span class="status-task-label width-300"><?php echo $st === 'em_andamento' ? 'Em andamento' : 'Pendente'; ?></span>
                                    <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo count(array_filter($progressStatuses, static fn ($value) => $value === 'concluida')); ?>" aria-valuemin="0" aria-valuemax="4">
                                        <?php foreach ($progressStatuses as $index => $statusSeg): ?>
                                            <div class="status-task-progress-segment <?php echo htmlspecialchars($statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente')); ?>" title="<?php echo htmlspecialchars($ordemLabels[$index + 1]); ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="width-300 padding-x-lg status status-pending">Pendente</div>
                            <?php endif; ?>
                        </div>
                        <?php include __DIR__ . '/_registro_metadata.php'; ?>
                    </div>
                </div>

                <?php if (!$tarefaConcluida): ?>
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $subtarefa):
                        $stId = (string) ($subtarefa['id'] ?? '');
                        $stTipo = (string) ($subtarefa['tipo'] ?? '');
                        $stLabel = $subtarefaLabels[$stTipo] ?? 'Subtarefa';
                    ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                            <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                                <div class="col task-status">
                                    <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                                </div>
                                <div class="col">
                                    <?php if ($stTipo === 'cadastro_partes_pje'): ?>
                                    <input
                                        type="number"
                                        class="subtask-qtd width-300 height-sm"
                                        data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>"
                                        data-qtd-min="0"
                                        data-qtd-max="10"
                                        title="Quantidade de partes cadastradas no PJe (0 a 10)"
                                        min="0"
                                        max="10"
                                        value="2"
                                        step="1"
                                        inputmode="numeric"
                                    >
                                    <?php elseif ($stTipo === 'expedicao_mandados_intimacao'): ?>
                                    <input
                                        type="number"
                                        class="subtask-qtd width-300 height-sm"
                                        data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>"
                                        data-qtd-min="1"
                                        data-qtd-max="10"
                                        title="Quantidade de mandados de intimação expedidos (1 a 10)"
                                        min="1"
                                        max="10"
                                        step="1"
                                        inputmode="numeric"
                                    >
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="display-flex row row-fit justify-end align-center">
                                <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" title="<?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?>">
                                    <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
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

        <div class="card-buttons">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> registros</span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
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

            if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                App.RegistroMetadataList.bind({
                    storageKey: 'cartorio.postReloadToast',
                    cancelEndpoint: '/api/cartorio-medidas-protetivas/cancel.php',
                    cancelToastKey: 'cartorio.medidas_protetivas.cancel.success',
                    cancelFallback: { type: 'success', title: 'Sucesso', message: 'Medida cancelada.' }
                });
            }

            if (!window.App || !App.CartorioMedidasProtetivasList || typeof App.CartorioMedidasProtetivasList.initialize !== 'function') {
                return;
            }

            App.CartorioMedidasProtetivasList.initialize({
                routePage: <?php echo json_encode($routePage, JSON_UNESCAPED_UNICODE); ?>
            });
        })();
    </script>
</body>
</html>
