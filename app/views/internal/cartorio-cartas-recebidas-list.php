<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasRecebidasService.php';
require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';

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
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Expedir Ato Ordinatório - Cumprimento',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Expedição de mandado/ofício',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Juntar certidão do oficial de justiça',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Expedir Ato Ordinatório - Devolução',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Devolver Carta ao Juízo Deprecante',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Juntar comprovante de envio',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Arquivar definitivamente com baixa',
    'devolucao_origem' => 'Aguardando cumprimento e devolução',	
    'devolução_origem' => 'Aguardando cumprimento e devolução',
];
$ordemLabels = [
    1 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1],
    2 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2],
    3 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3],
    4 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4],
    5 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5],
    6 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6],
    7 => $subtarefaLabels[CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7],
    'devolucao_origem' => 'Aguardando cumprimento e devolução',	
    'devolução_origem' => 'Aguardando cumprimento e devolução',
];
$taskTitleByTipo = [
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Ag. Ato Ordinatório',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Ag. expedição de mandado',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Ag. juntada de certidão',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Ag. Ato Ordinatório',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Ag. devolução da Carta',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Ag. juntada do Termo',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Ag. arquivamento definitivo',
    'devolucao_origem' => 'Aguardando cumprimento',	
    'devolução_origem' => 'Aguardando cumprimento',   
];
$buttonTitleByTipo = [
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1 => 'Informar expedição',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_2 => 'Informar expedição',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3 => 'Informar juntada de certidão',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_4 => 'Informar expedição',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5 => 'Informar devolução',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_6 => 'Informar juntada',
    CartasRecebidasSubtarefasRepository::TIPO_ORDEM_7 => 'Informar arquivamento definitivo',
    'devolucao_origem' => 'Devolver carta',
    'devolução_origem' => 'Devolver carta',
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
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Recebidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

                
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
            <div class="display-flex col col-full justify-start">
                <!-- TÍTULO E DESCRIÇÃO DA PÁGINA -->
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Cartas Recebidas</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Controle de cartas precatórias recebidas de outros tribunais.</p>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 600; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro de cartas recebidas</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar" value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - FILTERS -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 600; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-500 height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
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
                <select class="width-700 height-md" id="filter-tipo">
                    <option value=""<?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>"<?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo htmlspecialchars((string) $count); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - FILTERS INFO -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom"
        style="background-color: var(--color-primary-bg-light);">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px; padding: 5px 0;">
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
                    if ($finalidadeFilter !== '') {
                        $activeFilters[] = 'Finalidade [' . htmlspecialchars($finalidadeFilter, ENT_QUOTES) . ']';
                    }
                    if ($processoFilter !== '') {
                        $activeFilters[] = 'Processo [' . htmlspecialchars($processoFilter, ENT_QUOTES) . ']';
                    }

                    if (!empty($activeFilters)) {
                        echo 'Lista com os seguintes filtros: ' . implode(' + ', $activeFilters);
                    } else {
                        echo 'Lista sem filtros';
                    }
                ?>
                </span>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - ITEMS PER PAGE -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 600; color: var(--color-gray-900); font-size: 13px;">Itens por página</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-300 height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <!-- TITLE CARD -->
        <div class="card-title" style="padding-top: 40px !important;">
            <div class="display-flex row row-full justify-start">
                <h2>Resultado</h2>
            </div>
        </div>

        <!-- REGISTERS LIST -->
        <div class="display-flex col col-full register">
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
                $processo = $row['Processo'];
                $idCarta = $row['IdCarta'];
                $subtarefas = $row['Subtarefas'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
            ?>
            <div class="display-flex col col-full register border-bottom" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCarta); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process process-info" title="clique para copiar">
                            <?php
                                $classeNome = $row['ClasseNome'] ?? 'Classe não informada';
                                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo']); ?></span>
                        </div>
                        <div class="col width-700 col-comarca" title="clique para copiar">
                            <span class="minor-info text-ellipsis" style="font-weight: 600; color: var(--color-gray-900);"><?php echo htmlspecialchars('Comarca: ' . $row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['Finalidade']); ?> — <?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-pen-square"></i>
                        </button>
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
                        <div class="col<?php echo $mostrarBarraStatus && $st !== 'concluída' && $st !== 'cancelada' ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                echo '<div class="width-300 status ' . $classStatus . ' status-with-progress">';
                                echo '<span class="status-task-label width-300" style="justify-content: flex-start; font-size: 10px;">' . htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES) . '</span>';
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, function ($x) { return $x === 'concluida' || $x === 'cancelada'; }));
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="8" aria-label="Progresso das subtarefas">';
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
                    </div>
                </div>

                <?php if (!$tarefaConcluida): ?>
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stTipoNormalizado = strtolower(trim((string) $stTipo));
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $btnTitle = $buttonTitleByTipo[$stTipo] ?? $stLabel;
                        $isOrdem1 = ($stTipo === CartasRecebidasSubtarefasRepository::TIPO_ORDEM_1);
                        $isOrdem3 = in_array(
                            $stTipoNormalizado,
                            [
                                strtolower(CartasRecebidasSubtarefasRepository::TIPO_ORDEM_3),
                                'certidao_oficial_justica',
                                'juntada_certidao',
                                'confirmar_juntada',
                            ],
                            true
                        );
                        $isOrdem5 = in_array(
                            $stTipoNormalizado,
                            [
                                strtolower(CartasRecebidasSubtarefasRepository::TIPO_ORDEM_5),
                                'envio_processo',
                                'enviar_processo',
                                'envio',
                                'devolucao_origem',
                                'devolução_origem',
                            ],
                            true
                        );
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                            <?php if ($isOrdem3): ?>
                                <div class="col">
                                <select class="subtask-resultado-cumprimento width-500 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Resultado do cumprimento">
                                    <option value="">Selecione o resultado</option>
                                    <?php foreach ($resultadoCumprimentoOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                </div>
                            <?php endif; ?>
                            <?php if ($isOrdem5): ?>
                                <div class="col">
                                <select class="subtask-tipo-envio width-400 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de envio">
                                    <option value="">Selecione o tipo de envio</option>
                                    <?php foreach ($tipoEnvioOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center gap-y-sm">
                            <?php if ($isOrdem1): ?>
                                <button type="button" class="btn btn-secondary-outline height-sm btn-cancelar-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Cancelar esta subtarefa">Cancelar</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?>">
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

            App.CartorioTaskList.initialize({
                storageKey: 'cartorio.postReloadToast',
                newButtonId: 'novo-cadastro-btn',
                newHref: '/frame.php?page=cartasrecebidascreate',
                editButtonSelector: '.btn-editar[data-carta-id]',
                editIdAttribute: 'cartaId',
                editHrefBuilder: function (cartaId) {
                    return '/frame.php?page=cartasrecebidasedit&id=' + encodeURIComponent(cartaId);
                },
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
                            const subtarefaTipo = normalizeTipo(
                                this.getAttribute('data-subtarefa-tipo') ||
                                (block ? block.getAttribute('data-subtarefa-tipo') : '')
                            );
                            const obj = {};
                            const resultadoCumprimentoValue = getFieldValue('subtask-resultado-cumprimento');
                            const tipoEnvioValue = getFieldValue('subtask-tipo-envio');
                            if (resultadoCumprimentoValue) {
                                obj.resultado_cumprimento = resultadoCumprimentoValue;
                            }
                            if (tipoEnvioValue) {
                                obj.tipo_envio = tipoEnvioValue;
                            }
                            if (['juntada_certidao_oficial', 'confirmar_juntada', 'juntada_certidao', 'certidao_oficial_justica', 'juntada_certidao_oficial_justica'].includes(subtarefaTipo) && !(obj.resultado_cumprimento)) {
                                notify('error', 'Atenção', 'Selecione o resultado do cumprimento.');
                                return;
                            }
                            if (['envio_processo', 'enviar_processo', 'envio', 'devolucao_origem', 'devolução_origem'].includes(subtarefaTipo) && !(obj.tipo_envio)) {
                                notify('error', 'Atenção', 'Selecione o tipo de envio.');
                                return;
                            }
                            const dadosEspecificos = Object.keys(obj).length > 0 ? JSON.stringify(obj) : null;
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
                                .then(function (response) { return response.json(); })
                                .then(function (data) {
                                    if (data.success) {
                                        queuePostReloadToast('info', 'Informação', 'Subtarefa cancelada com sucesso.');
                                        window.location.reload();
                                    } else {
                                        notify('error', 'Erro', data.error || 'Erro ao cancelar subtarefa.');
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
