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
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../access/AccessEvaluator.php';

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
$canReconcileEditais = AccessEvaluator::canAccessPage(
    'editaisedit',
    UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) ($_SESSION['id_usuario'] ?? '')))
);
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
$exportQueryParams = $baseQueryParams;
$exportQueryParams['page'] = $routePage;
unset($exportQueryParams['page_number'], $exportQueryParams['items_per_page']);
$exportCsvUrl = '/api/internal-list-export/csv.php?' . http_build_query($exportQueryParams);
$exportPdfUrl = '/api/internal-list-export/pdf.php?' . http_build_query($exportQueryParams);

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
$cartasRowsCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cartas-expedidas-rows.css') ?: time();
$editaisRowsCssV = @filemtime($assetsRoot . '/css/modules/cartorio/editais-rows.css') ?: time();
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
        <link rel="stylesheet" href="/assets/css/modules/cartorio/cartas-expedidas-rows.css?v=<?php echo (int) $cartasRowsCssV; ?>">
        <link rel="stylesheet" href="/assets/css/modules/cartorio/editais-rows.css?v=<?php echo (int) $editaisRowsCssV; ?>">
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
            <h1 class="title-page">Editais</h1>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <input type="text" id="search-input" class="height-md list-search-input" placeholder="Pesquisar" aria-label="Pesquisar por processo" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>" autocomplete="off">
                <select class="width-300 height-md list-export-select white" id="list-export-v2" data-csv-href="<?php echo htmlspecialchars($exportCsvUrl, ENT_QUOTES); ?>" data-pdf-href="<?php echo htmlspecialchars($exportPdfUrl, ENT_QUOTES); ?>" aria-label="Exportar lista">
                    <option value="" selected disabled>Exportar lista</option>
                    <option value="pdf">Exportar PDF</option>
                    <option value="csv">Exportar CSV</option>
                </select>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
                <?php if ($canReconcileEditais && $rows !== []): ?>
                    <button type="button" class="btn btn-secondary" id="reconciliar-editais-btn" aria-describedby="reconciliar-editais-help">Atualizar etapas desta página</button>
                    <span id="reconciliar-editais-help" class="sr-only">Executa a manutenção de etapas somente após sua confirmação.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom editais-filters">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis editais-filters-title">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap editais-filters-controls">
                <select class="width-500 height-md" id="filter-status" aria-label="Filtrar por status">
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
                <select class="height-md" id="filter-tipo-edital" aria-label="Filtrar por tipo de edital">
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

        <div class="editais-list-results" role="list" aria-label="Resultados dos editais">
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
            <?php
            $historicoLinhas = $registroMetadata['historico_linhas'] ?? [];
            $historicoRecente = $historicoLinhas !== [] ? $historicoLinhas[count($historicoLinhas) - 1] : null;
            $historicoTexto = $historicoRecente !== null ? trim((string) ($historicoRecente['data_hora'] ?? '')) : '';
            $historicoAutor = $historicoRecente !== null ? trim((string) ($historicoRecente['autor'] ?? '')) : '';
            if ($historicoAutor !== '') {
                $historicoTexto .= ($historicoTexto !== '' ? ' | ' : '') . $historicoAutor;
            }
            $editalBadgeClass = $tarefaConcluida ? 'status-finished' : ($statusTarefaNorm === 'cancelada' ? 'status-cancelled' : ($statusTarefaNorm === 'pendente' ? 'status-current' : 'status-pending'));
            $editalBadgeLabel = $tarefaConcluida ? 'Finalizada' : ($statusTarefaNorm === 'cancelada' ? 'Cancelada' : $currentSubtarefaTitle);
            ?>
            <div class="edital-record" role="listitem" data-edital-id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>">
            <div class="register-line full carta-expedida-v2-row edital-row" data-registro-id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>">
                <div class="left carta-expedida-left">
                    <?php include __DIR__ . '/partials/editais-v2-process.php'; ?>
                    <div class="register-block carta-info-column">
                        <div class="register-block-content">
                            <div class="register-block-content-item"><strong class="register-block-content-item-value"><?php echo htmlspecialchars((string) ($row['tipo_edital'] ?? $row['TipoEdital'] ?? '—')); ?></strong></div>
                            <div class="register-block-content-item"><span class="register-block-content-item-text">Tipo de edital</span></div>
                        </div>
                    </div>
                </div>
                <div class="right carta-expedida-right">
                    <div class="carta-expedida-status-group">
                        <div class="register-block-status">
                            <div class="badge-status-primary <?php echo htmlspecialchars($editalBadgeClass, ENT_QUOTES); ?>"><span class="badge-status-text"><?php echo htmlspecialchars($editalBadgeLabel, ENT_QUOTES); ?></span></div>
                            <?php if (!empty($registroMetadata['show_edit']) && !empty($registroMetadata['edit_href'])): ?>
                            <button type="button" class="carta-row-action" data-action="editar-registro" data-edit-href="<?php echo htmlspecialchars($registroMetadata['edit_href'], ENT_QUOTES); ?>" title="Editar edital" aria-label="Editar edital"><img src="/assets/icons/Eject-Button--Streamline-Ultimate.png" alt=""></button>
                            <?php endif; ?>
                            <?php if ($podeExpandirSubtarefas): ?>
                            <label class="carta-row-action edital-row-expand" title="Expandir etapas do edital">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" aria-expanded="false" aria-label="Expandir etapas do edital" />
                                <img src="/assets/icons/Button-Next--Streamline-Ultimate.png" alt="">
                            </label>
                            <?php endif; ?>
                        </div>
                        <div class="container-historical container-sm">
                            <div class="container-line"><div class="line-content double-line">
                                <span class="content-classe">Última movimentação</span>
                                <span class="content-assuntos<?php echo $historicoTexto === '' ? ' over' : ''; ?>"><?php echo htmlspecialchars($historicoTexto !== '' ? $historicoTexto : 'Nenhuma movimentação registrada', ENT_QUOTES); ?></span>
                            </div></div>
                        </div>
                    </div>
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
            <?php else: ?>
                <div class="editais-list-empty" role="status">
                    <strong>Nenhum edital encontrado</strong>
                    <span>Ajuste os filtros ou a pesquisa para ver outros resultados.</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-line full pagination editais-list-pagination">
            <div class="card-line left">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?> a <?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
            </span>
            </div>
            <?php if ($totalPages > 0): ?>
                <div class="card-line right"><div class="pagination-controls">
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
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" aria-label="Página anterior" title="Página anterior" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <i class="fi fi-rr-angle-small-left" aria-hidden="true"></i>
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page === $currentPage): ?>aria-current="page" disabled<?php else: ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><i class="fi fi-rr-menu-dots"></i></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page === $currentPage): ?>aria-current="page" disabled<?php else: ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" aria-label="Próxima página" title="Próxima página" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <i class="fi fi-rr-angle-small-right" aria-hidden="true"></i>
                    </button>
                </div></div>
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
                routePage: <?php echo json_encode($routePage, JSON_UNESCAPED_UNICODE); ?>,
                editalIds: <?php echo json_encode(array_values(array_filter(array_map(static fn($row) => (string) ($row['IdEdital'] ?? ''), $rows))), JSON_UNESCAPED_UNICODE); ?>
            });
        })();
    </script>
</body>
</html>
