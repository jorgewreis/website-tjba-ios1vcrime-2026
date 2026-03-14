<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasRecebidasService.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

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
    'em_andamento',
    'concluída',
    'cancelada',
];

$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));

$subtarefaLabels = [
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Ato ordinatório - Cumprimento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de documento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Juntada de certidão do oficial de justiça',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Ato ordinatório - Devolução',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Devolução da Carta',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Juntada de termo de envio',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Arquivamento definitivo',
];
$ordemLabels = [
    1 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1],
    2 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2],
    3 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3],
    4 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4],
    5 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5],
    6 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6],
    7 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7],
];
$taskTitleByTipo = [
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando ato ordinatório de cumprimento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando expedição de documento para cumprimento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando cumprimento pelo oficial de justiça',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando ato ordinatório de devolução',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando envio do processo ao juízo de origem',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando juntada de termo de envio',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando arquivamento definitivo',
];
$buttonTitleByTipo = [
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Informar ato ordinatório de cumprimento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Informar expedição de documento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Informar juntada de certidão',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Informar ato ordinatório de devolução',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Informar envio do processo',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Informar juntada',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Informar arquivamento definitivo',
];
$resultadoCumprimentoOptions = [
    'cumprido_integralmente' => 'Cumprido integralmente',
    'cumprido_parcialmente' => 'Cumprido parcialmente',
    'cumprido_negativamente' => 'Cumprido negativamente',
    'nao_cumprido' => 'Não cumprido',
];
$tipoEnvioOptions = [
    'E-mail' => 'E-mail',
    'Malote Digital' => 'Malote Digital',
    'PJe' => 'PJe',
    'eSAJ' => 'eSAJ',
    'ePROC' => 'ePROC',
];

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
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Recebidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
                
        <!-- IMPORT - FLATICON -->
        <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css'>
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <!-- Mapa de navegação do site -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Lista de documentos</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Cartas Recebidas</h1>
        <p class="description-page">Controle de cartas precatórias recebidas de outros tribunais.</p>
    </div>

    <div class="card-page">

        <!-- ROW ACTIONS -->
        <div class="row table buttons right padding-lr-md padding-tb-md row-gap-10">
            <input type="text" class="width-md height-md white" id="search-input" placeholder="Pesquisar na lista..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
            <select class="width-sm height-md white" id="export-type" disabled>
                <option value="" selected>Exportar lista</option>
                <option value="pdf">Gerar PDF</option>
                <option value="csv">Gerar CSV</option>
                <option value="print">Imprimir</option>
            </select>
            <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <!-- SUBTITLE TABLE -->
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Lista de Documentos</div>

        <!-- FILTERS -->
        <div class="row table between padding-tb-lg padding-lr-md border-bottom">
            <div class="row left row-gap-10">
                <div class="col">
                    <label for="filter-tipo">Filtrar por finalidade</label>
                    <select class="width-md height-md" id="filter-tipo">
                        <option value=""<?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Mostrar todas</option>
                        <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                            <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>"<?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo htmlspecialchars((string) $count); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-status">Filtrar por status</label>
                    <select class="width-md height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                            Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                        </option>
                        <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>
                            Em andamento (<?php echo htmlspecialchars((string) ($statusCounts['em_andamento'] ?? 0)); ?>)
                        </option>
                        <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                            Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['concluída'] ?? 0)); ?>)
                        </option>
                        <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                            Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                        </option>
                    </select>
                </div>
            </div>
            <div class="col right">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-sm height-md" id="items-per-page">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $processo = $row['Processo'];
                $idCarta = $row['IdCarta'];
                $subtarefas = $row['Subtarefas'] ?? [];
            ?>
            <!-- REGISTRO COMPLETO -->
            <div class="col full-line border-bottom" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCarta); ?>">

                <!-- TAREFA -->
                <div class="row table between padding-lr-md">
                    <!-- DIV LEFT -->
                    <div class="row left">
                        <div class="col">
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <div class="col process-info" title="clique para copiar">
                            <?php
                                $classeNome = $row['ClasseNome'] ?? 'Classe não informada';
                                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo']); ?></span>
                        </div>
                        <div class="col comarca-info">
                            <span class="minor-info">Comarca de origem</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                        </div>
                        <div class="col detalhes-info">
                            <span class="minor-info"><?php echo htmlspecialchars($row['Finalidade']); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                        </div>
                    </div>
                    <!-- DIV RIGHT -->
                    <div class="row right">
                        <div class="col button-table button-edit" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <div class="row allocate-users">
                            <?php
                            $alocados = $row['Alocados'] ?? [];
                            if (count($alocados) === 0) {
                                echo '—';
                            } else {
                                foreach ($alocados as $alocado) {
                                    $tipo = $alocado['tipo'] ?? 'specific';
                                    $iniciais = $alocado['iniciais'] ?? '—';
                                    $nomeCompleto = $alocado['nome_completo'] ?? '';
                                    $userLabel = $nomeCompleto !== '' ? $nomeCompleto : $iniciais;
                                    if ($tipo === 'rule') {
                                        $title = 'Carta recebida alocada para: ' . $userLabel;
                                    } elseif ($tipo === 'all') {
                                        $title = 'Usuário alocado para a tarefa: ' . $userLabel;
                                    } else {
                                        $title = 'Alocação voluntária: ' . $userLabel;
                                    }
                                    $classeTipo = in_array($tipo, ['rule', 'specific', 'all'], true) ? ' ' . $tipo : '';
                                    ?><span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"><?php echo htmlspecialchars($iniciais, ENT_QUOTES); ?></span><?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                        $st = $row['Status'] ?? '';
                        $allSt = $row['SubtarefasAll'] ?? [];
                        $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente'];
                        foreach ($allSt as $s) {
                            $o = (int) ($s['ordem'] ?? 0);
                            if ($o >= 1 && $o <= 7) {
                                $progressStatuses[$o - 1] = (string) ($s['status'] ?? 'pendente');
                            }
                        }
                        $currentSubtarefaTitle = '';
                        foreach ($allSt as $s) {
                            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                                $currentSubtarefaTitle = $taskTitleByTipo[$s['tipo'] ?? ''] ?? $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                                break;
                            }
                        }
                        if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                            $currentSubtarefaTitle = $taskTitleByTipo[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1] ?? $ordemLabels[1];
                        } elseif ($currentSubtarefaTitle === '' && ($st === 'concluída' || $st === 'finalizada')) {
                            $currentSubtarefaTitle = 'Todas as etapas concluídas';
                        } elseif ($currentSubtarefaTitle === '') {
                            $currentSubtarefaTitle = $row['Status'] ?? 'Em andamento';
                        }
                        $isEmAndamento = ($st === 'em_andamento');
                        $mostrarBarraStatus = ($st !== 'concluída' && $st !== 'cancelada');
                        $segmentosBarra = [];
                        if ($mostrarBarraStatus) {
                            $segmentosBarra[] = ['class' => 'concluida', 'title' => 'Carta recebida e cadastrada'];
                            for ($i = 0; $i < 7; $i++) {
                                $statusSeg = $progressStatuses[$i] ?? 'pendente';
                                $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : ($statusSeg === 'cancelada' ? 'cancelada' : 'pendente'));
                                $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$i + 1] ?? ''];
                            }
                        }
                        ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-sm status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-sm status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                echo '<div class="width-sm status ' . $classStatus . ' status-with-progress">';
                                echo '<span class="status-task-label">' . htmlspecialchars($labelStatus, ENT_QUOTES) . '</span>';
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, function ($x) { return $x === 'concluida' || $x === 'cancelada'; }));
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="8" aria-label="Progresso das subtarefas">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="width-sm status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAREFAS -->
                <div class="col subtask subtask-collapsed padding-lr-lg full-line" id="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $btnTitle = $buttonTitleByTipo[$stTipo] ?? $stLabel;
                        $isOrdem1 = ($stTipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1);
                        $isOrdem3 = ($stTipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3);
                        $isOrdem5 = ($stTipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5);
                    ?>
                    <div class="row table between padding-tb-sm border-top full-line" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="col table left row-gap-10">
                            <div class="status status-pending padding-lr-lg"><?php echo htmlspecialchars($stLabel); ?></div>
                            <?php if ($isOrdem3): ?>
                                <select class="subtask-resultado-cumprimento width-md height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Resultado do cumprimento">
                                    <option value="">Selecione o resultado</option>
                                    <?php foreach ($resultadoCumprimentoOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <?php if ($isOrdem5): ?>
                                <select class="subtask-tipo-envio width-md height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de envio">
                                    <option value="">Selecione o tipo de envio</option>
                                    <?php foreach ($tipoEnvioOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col table right">
                            <?php if ($isOrdem1): ?>
                                <button type="button" class="btn btn-secondary-outline btn-cancelar-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Cancelar esta subtarefa">Cancelar</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary padding-lr-lg row-gap-10 btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?>"><span class="value-button">Concluir</span></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="row table between padding-tb-md border-top">
                            <span class="minor-info">Nenhuma sub-tarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: // Caso não haja dados para exibir ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data">
                        <span class="major-info">Nenhum documento cadastrado.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="row table between padding-tb-lg padding-lr-md">
            <span class="left pagination-info">
                Mostrando <?php echo htmlspecialchars((string) $startItem); ?>-<?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo htmlspecialchars((string) $totalPages); ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo htmlspecialchars((string) $itemsPerPage); ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="right pagination-controls">
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
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>"
                       <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php
                            $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page;
                        ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true">
                            <img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas">
                        </a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php
                                $pageParams = $baseQueryParams;
                                $pageParams['page_number'] = $page;
                            ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                                <?php echo $page; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
                       <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
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

            const novoCadastroBtn = document.getElementById('novo-cadastro-btn');
            if (novoCadastroBtn) {
                novoCadastroBtn.addEventListener('click', function () {
                    window.location.href = '/frame.php?page=cartasrecebidascreate';
                });
            }

            document.querySelectorAll('.button-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    const cartaId = button.dataset.cartaId;
                    if (!cartaId) return;
                    window.location.href = '/frame.php?page=cartasrecebidasedit&id=' + encodeURIComponent(cartaId);
                });
            });

            // Expandir/recolher subtarefa ao selecionar o radio
            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const cartaId = this.value;
                    const targetId = 'subtask-' + cartaId;
                    document.querySelectorAll('.col.subtask').forEach(function (subtaskRow) {
                        const isTarget = subtaskRow.id === targetId;
                        subtaskRow.classList.toggle('subtask-collapsed', !isTarget);
                        subtaskRow.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                    document.querySelectorAll('input[name="open-subtask"]').forEach(function (r) {
                        r.setAttribute('aria-expanded', r.value === cartaId ? 'true' : 'false');
                    });
                });
            });

            // Concluir subtarefa
            document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    const subtarefaTipo = this.getAttribute('data-subtarefa-tipo') || '';
                    if (!subtarefaId) return;
                    const block = this.closest('.col.full-line[data-subtarefa-id]');
                    const obj = {};
                    if (block) {
                        const resultadoCumprimento = block.querySelector('.subtask-resultado-cumprimento');
                        const tipoEnvio = block.querySelector('.subtask-tipo-envio');
                        if (resultadoCumprimento && resultadoCumprimento.value && resultadoCumprimento.value.trim()) {
                            obj.resultado_cumprimento = resultadoCumprimento.value.trim();
                        }
                        if (tipoEnvio && tipoEnvio.value && tipoEnvio.value.trim()) {
                            obj.tipo_envio = tipoEnvio.value.trim();
                        }
                    }
                    if (subtarefaTipo === 'juntada_certidao_oficial' && !(obj.resultado_cumprimento)) {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'Selecione o resultado do cumprimento.' });
                        else alert('Selecione o resultado do cumprimento.');
                        return;
                    }
                    if (subtarefaTipo === 'envio_processo' && !(obj.tipo_envio)) {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'Selecione o tipo de envio.' });
                        else alert('Selecione o tipo de envio.');
                        return;
                    }
                    const dadosEspecificos = Object.keys(obj).length > 0 ? JSON.stringify(obj) : null;
                    btn.disabled = true;
                    fetch('/api/cartorio-cartas-recebidas/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            subtarefa_id: subtarefaId,
                            dados_especificos: dadosEspecificos
                        })
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao concluir subtarefa.' });
                            else alert(data.error || 'Erro ao concluir subtarefa.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        else alert('Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });

            // Cancelar subtarefa (apenas ordem 1)
            document.querySelectorAll('.btn-cancelar-subtarefa').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    if (!confirm('Deseja cancelar a subtarefa "Ato ordinatório - Cumprimento"? A próxima etapa será disponibilizada.')) return;
                    btn.disabled = true;
                    fetch('/api/cartorio-cartas-recebidas/cancelar-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ subtarefa_id: subtarefaId })
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao cancelar subtarefa.' });
                            else alert(data.error || 'Erro ao cancelar subtarefa.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        else alert('Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>

</body>
</html>
