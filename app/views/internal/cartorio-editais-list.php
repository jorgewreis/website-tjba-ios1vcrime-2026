<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/EditaisService.php';
require_once __DIR__ . '/../../repositories/EditaisSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/EditaisUiCopy.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'editais'));
$statusFilter = trim($_GET['status'] ?? '');
if ($statusFilter === 'em_andamento') {
    $statusFilter = EditaisUiCopy::STATUS_AGUARDANDO_DECURSO;
}
$processoFilter = trim($_GET['processo'] ?? '');
$tipoEditalFilter = trim($_GET['tipo_edital'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
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
    'tipo_edital' => $tipoEditalFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new EditaisService($connection);
    $listResult = $service->listEditais($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
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

$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptions = [
    'pendente',
    EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO,
    EditaisUiCopy::STATUS_AGUARDANDO_DECURSO,
    'concluída',
    'cancelada',
];
$tipoEditalOptions = ['Citação', 'Intimação', 'Notificação', 'Outro'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$legacyEmAndamento = (int) ($statusCounts['em_andamento'] ?? 0);
$statusCounts[EditaisUiCopy::STATUS_AGUARDANDO_DECURSO] =
    (int) ($statusCounts[EditaisUiCopy::STATUS_AGUARDANDO_DECURSO] ?? 0) + $legacyEmAndamento;

$ordensSubtarefasAtivas = EditaisSubtarefasRepository::ORDENS_ATIVAS;
$ordemLabels = EditaisUiCopy::mapaLegendaEtapaPorOrdem();

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
if ($tipoEditalFilter !== '') {
    $baseQueryParams['tipo_edital'] = $tipoEditalFilter;
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
$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$editaisCssV = @filemtime($assetsRoot . '/css/modules/cartorio/editais-v2.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Editais - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
        <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <link rel="stylesheet" href="/assets/css/modules/cartorio/editais-v2.css?v=<?php echo (int) $editaisCssV; ?>">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal editais-v2-page editais-list-page">

    <div class="spacing-top-page"></div>

    <!-- HEADER PAGE -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page editais-v2-card">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page">Editais</h1>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">

                <div class="editais-filters-controls">
                    <input type="text" class="height-md list-search-input" id="search-input" placeholder="Pesquisar por processo" aria-label="Pesquisar por processo" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>" autocomplete="off">
                    <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                </div>
<button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="editais-filters">
            <span class="editais-filters-title">Filtros</span>
            <div class="editais-filters-controls">
                <select id="filter-status" aria-label="Filtrar por status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                    <option value="<?php echo htmlspecialchars(EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO, ENT_QUOTES); ?>"<?php echo $statusFilter === EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO ? ' selected' : ''; ?>>
                        Aguardando publicação (<?php echo (int) ($statusCounts[EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO] ?? 0); ?>)
                    </option>
                    <option value="<?php echo htmlspecialchars(EditaisUiCopy::STATUS_AGUARDANDO_DECURSO, ENT_QUOTES); ?>"<?php echo $statusFilter === EditaisUiCopy::STATUS_AGUARDANDO_DECURSO || $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>
                        Aguardando decurso (<?php echo (int) ($statusCounts[EditaisUiCopy::STATUS_AGUARDANDO_DECURSO] ?? 0); ?>)
                    </option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                </select>
                <select id="filter-tipo-edital" aria-label="Filtrar por tipo de edital">
                    <option value=""<?php echo $tipoEditalFilter === '' ? ' selected' : ''; ?>>Todos os tipos</option>
                    <?php foreach ($tipoEditalOptions as $tipoEditalOption): ?>
                        <option value="<?php echo htmlspecialchars($tipoEditalOption, ENT_QUOTES); ?>"<?php echo $tipoEditalFilter === $tipoEditalOption ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipoEditalOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>
        </div>

        <?php
        $activeFilters = [];
        if ($statusFilter !== '') {
            $statusFilterLabels = [
                'pendente' => 'Pendente',
                EditaisUiCopy::STATUS_AGUARDANDO_PUBLICACAO => 'Aguardando publicação',
                EditaisUiCopy::STATUS_AGUARDANDO_DECURSO => 'Aguardando decurso',
                'em_andamento' => 'Aguardando decurso',
                'concluída' => 'Finalizada',
                'cancelada' => 'Cancelada',
            ];
            $statusFilterDisplay = $statusFilterLabels[$statusFilter] ?? $statusFilter;
            $activeFilters[] = 'Status [' . htmlspecialchars($statusFilterDisplay, ENT_QUOTES) . ']';
        }
        if ($tipoEditalFilter !== '') {
            $activeFilters[] = 'Tipo de edital [' . htmlspecialchars($tipoEditalFilter, ENT_QUOTES) . ']';
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

        <!-- <div class="card-title" style="padding-top: 40px !important;">
            <div class="display-flex row row-full justify-start">
                <h2>Resultado</h2>
            </div>
        </div> -->

        <div class="display-flex col col-full register editais-v2-register">
            <div class="editais-list-count">
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
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idEdital = $row['IdEdital'];
                $decursoInfo = is_array($row['DecursoInfo'] ?? null) ? $row['DecursoInfo'] : [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $statusTarefaNorm = EditaisUiCopy::normalizarStatusPrincipal($statusTarefa);
                $tarefaConcluida = in_array($statusTarefaNorm, ['concluida', 'finalizada'], true);
                $allSt = $row['SubtarefasAll'] ?? [];
                $subtarefasExpansao = [];
                foreach ($allSt as $stRow) {
                    if (!is_array($stRow)) {
                        continue;
                    }
                    $tipoSt = (string) ($stRow['tipo'] ?? '');
                    if (!EditaisSubtarefasRepository::isTipoAtivo($tipoSt)) {
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
                $progressStatuses = [];
                foreach ($ordensSubtarefasAtivas as $ordemAtiva) {
                    $progressStatuses[$ordemAtiva] = 'pendente';
                }
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    $tipoS = (string) ($s['tipo'] ?? '');
                    if ($o === 3) {
                        $o = EditaisSubtarefasRepository::ORDEM_PUBLICACAO;
                    } elseif ($o === 4) {
                        $o = EditaisSubtarefasRepository::ORDEM_DECURSO;
                    }
                    if (EditaisSubtarefasRepository::isOrdemAtiva($o)) {
                        $statusProg = strtolower(trim((string) ($s['status'] ?? 'pendente')));
                        if ($statusProg === 'concluída') {
                            $statusProg = 'concluida';
                        }
                        $progressStatuses[$o] = $statusProg;
                    }
                }
                $currentSubtarefaTitle = EditaisUiCopy::resolverTituloTarefaListagem($statusTarefa, $allSt);
                $mostrarBarraStatus = !in_array($statusTarefaNorm, ['concluida', 'cancelada'], true);
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    $segmentosBarra[] = ['class' => 'concluida', 'title' => EditaisUiCopy::CADASTRO_ETAPA];
                    foreach ($ordensSubtarefasAtivas as $ordemAtiva) {
                        $statusSeg = $progressStatuses[$ordemAtiva] ?? 'pendente';
                        $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$ordemAtiva] ?? ''];
                    }
                }
            ?>
            <?php $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'editais', $registroListViewerContext, $connection); ?>
            <div class="register-line full register register-with-metadata editais-v2-row" data-edital-id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idEdital); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center editais-v2-row-main">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center editais-v2-row-primary">
                        <div class="col">
                            <?php if ($podeExpandirSubtarefas): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idEdital); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idEdital); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php else: ?>
                                <span class="button-table" style="visibility:hidden;" aria-hidden="true"><i class="fi fi-rr-angle-small-down"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process">
                            <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                        </div>
                        <div class="col width-600 col-tipo-edital" title="Clique para copiar">
                            <span class="minor-info">Tipo de edital</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['tipo_edital'] ?? $row['TipoEdital'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center editais-v2-row-secondary">
                        <div class="col<?php echo $mostrarBarraStatus ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($statusTarefaNorm === 'concluida') {
                                echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                            } elseif ($statusTarefaNorm === 'cancelada') {
                                echo '<div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = EditaisUiCopy::rotuloBadgeStatusTarefa($statusTarefa);
                                $classStatus = EditaisUiCopy::classeCssBadgeStatusTarefa($statusTarefa);
                                echo '<div class="width-300 status ' . htmlspecialchars($classStatus, ENT_QUOTES) . ' status-with-progress">';
                                echo '<span class="status-task-label width-300" style="justify-content: flex-start; font-size: 10px;">' . htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES) . '</span>';
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, static fn ($x) => $x === 'concluida'));
                                $maxBarra = 1 + count($ordensSubtarefasAtivas);
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="' . $maxBarra . '" aria-label="Progresso das subtarefas">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div></div>';
                            } else {
                                echo '<div class="width-300 padding-x-lg status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                        <?php include __DIR__ . '/_registro_metadata.php'; ?>
                    </div>
                </div>

                <?php if ($podeExpandirSubtarefas): ?>
                    <div class="display-flex col col-full subtask subtask-collapsed editais-v2-subtasks" id="subtask-<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" aria-hidden="true">
                        <?php foreach ($subtarefasExpansao as $st):
                            $stId = $st['id'] ?? '';
                            $stTipo = $st['tipo'] ?? '';
                            $stTipoNormalizado = strtolower(trim((string) $stTipo));
                            $isCertificacaoPublicacao = $stTipoNormalizado === EditaisSubtarefasRepository::TIPO_PUBLICACAO;
                            $isCertificacaoDecurso = $stTipoNormalizado === EditaisSubtarefasRepository::TIPO_DECURSO;
                            $stStatus = strtolower(trim((string) ($st['status'] ?? '')));
                            if ($stStatus === 'concluída') {
                                $stStatus = 'concluida';
                            }
                            $stLabel = EditaisUiCopy::textoSpanStatusSubtarefa($stTipo, $stStatus);
                            $statusClass = EditaisUiCopy::classeCssSpanStatusSubtarefa($stStatus);
                            $dadosEspecificosSt = [];
                            if (!empty($st['dados_especificos'])) {
                                $dadosDecode = json_decode((string) $st['dados_especificos'], true);
                                if (is_array($dadosDecode)) {
                                    $dadosEspecificosSt = $dadosDecode;
                                }
                            }
                            $dataPublicacaoValue = trim((string) ($row['DataPublicacao'] ?? $row['data_publicacao'] ?? ''));
                            if ($dataPublicacaoValue === '') {
                                $dataPublicacaoValue = trim((string) ($dadosEspecificosSt['data_publicacao'] ?? ''));
                            }
                            $liberaDecurso = (bool) ($decursoInfo['libera_conclusao'] ?? false);
                            $diasRestantesDecurso = (int) ($decursoInfo['dias_restantes'] ?? 0);
                            $venceHojeDecurso = (!$liberaDecurso && $diasRestantesDecurso === 0 && !empty($decursoInfo['data_limite_decurso']));
                        ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                            <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                                <div class="col task-status">
                                    <div class="padding-x-lg height-sm status <?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?></div>
                                </div>
                                <?php if ($isCertificacaoPublicacao): ?>
                                    <div class="col">
                                        <input type="date" class="width-300 height-sm subtask-data-publicacao" value="<?php echo htmlspecialchars($dataPublicacaoValue, ENT_QUOTES); ?>" title="Data da publicação no diário">
                                    </div>
                                <?php endif; ?>
                                <?php if ($isCertificacaoDecurso): ?>
                                    <div class="col">
                                        <span class="minor-info">
                                            <?php echo $venceHojeDecurso ? 'Vence hoje' : ((int) $diasRestantesDecurso . ' dias restantes'); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="display-flex row row-fit justify-end align-center">
                                <?php
                                $mostrarBotao = true;
                                if ($isCertificacaoDecurso && !$liberaDecurso) {
                                    $mostrarBotao = false;
                                }
                                if ($mostrarBotao):
                                    $btnTitle = EditaisUiCopy::tituloBotaoPorTipo($stTipo);
                                ?>
                                    <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?>">
                                        <span class="value-button"><?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?></span>
                                    </button>
                                <?php elseif ($isCertificacaoDecurso): ?>
                                    <span class="minor-info">Finalização automática após o decurso do prazo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="editais-list-pagination">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
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
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
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
        (function () {

            if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                App.RegistroMetadataList.bind({
                    storageKey: 'cartorio.postReloadToast',
                    cancelEndpoint: '/api/cartorio-editais/cancel.php',
                    cancelToastKey: 'cartorio.editais.cancel.success',
                    cancelFallback: { type: 'success', title: 'Sucesso', message: 'Edital cancelado.' }
                });
            }

            if (!window.App || !App.CartorioEditaisList || typeof App.CartorioEditaisList.initialize !== 'function') {
                return;
            }

            App.CartorioEditaisList.initialize({
                routePage: <?php echo json_encode($routePage, JSON_UNESCAPED_UNICODE); ?>
            });
        })();
    </script>
</body>
</html>
