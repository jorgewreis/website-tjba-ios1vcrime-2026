<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasExpedidasService.php';
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

$routePage = trim((string) ($_GET['page'] ?? 'cartasexpedidas'));
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
    $service = new CartasExpedidasService($connection);
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
    'aguardando assinatura',
    'aguardando envio',
    'aguardando juntada do termo',
    'aguardando cumprimento',
    'concluída',
    'cancelada',
];

$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$statusCounts['em_andamento'] = (int) ($statusCounts['em_andamento'] ?? 0) + (int) ($statusCounts['aguardando assinatura'] ?? 0) + (int) ($statusCounts['aguardando envio'] ?? 0) + (int) ($statusCounts['aguardando juntada do termo'] ?? 0) + (int) ($statusCounts['aguardando juntada de termo'] ?? 0) + (int) ($statusCounts['aguardando cumprimento'] ?? 0);

$subtarefaLabels = [
    'aguardando_assinatura' => 'Assinar Carta expedida (Magistrada)',
    'aguardando_gerar_pdf' => 'Imprimir peças em PDF',
    'aguardando_envio' => 'Enviar Carta expedida',
    'aguardando_juntada_termo' => 'Juntar comprovante de envio',
    'aguardando_informacoes' => 'Informar detalhes',
    'aguardando_devolucao' => 'Juntar carta devolvida',
];
$taskTitleByTipo = [
    'aguardando_assinatura' => 'Aguardando assinatura',
    'aguardando_gerar_pdf' => 'Aguardando impressão em PDF',
    'aguardando_envio' => 'Aguardando envio',
    'aguardando_juntada_termo' => 'Ag. juntada de Termo',
    'aguardando_informacoes' => 'Aguardando cumprimento',
    'aguardando_devolucao' => 'Aguardando devolução',
];
$buttonTitleByTipo = [
    'aguardando_assinatura' => 'Informar assinatura',
    'aguardando_gerar_pdf' => 'Informar impressão em PDF',
    'aguardando_envio' => 'Informar envio',
    'aguardando_juntada_termo' => 'Informar juntada',
    'aguardando_informacoes' => 'Informar complementos',
    'aguardando_devolucao' => 'Informar juntada de carta devolvida',
];
$ordemLabels = [
    1 => $subtarefaLabels['aguardando_assinatura'],
    2 => $subtarefaLabels['aguardando_gerar_pdf'],
    3 => $subtarefaLabels['aguardando_envio'],
    4 => $subtarefaLabels['aguardando_juntada_termo'],
    5 => $subtarefaLabels['aguardando_informacoes'],
    6 => $subtarefaLabels['aguardando_devolucao'],
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
    <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Cartório - Cartas Expedidas -</span>
            <span class="specific">Lista</span>
        </div>
    </div>


    <!-- CARD PAGE -->
    <div class="container-full card-page">

        <!-- TITLE CARD -->
        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <!-- TÍTULO E DESCRIÇÃO DA PÁGINA -->
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Cartas Expedidas</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Lista de cartas expedidas pelo cartório para controle e envio.</p>
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
                    <option value="" <?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente" <?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                        Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                    </option>
                    <option value="em_andamento" <?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>
                        Em andamento (<?php echo htmlspecialchars((string) ($statusCounts['em_andamento'] ?? 0)); ?>)
                    </option>
                    <option value="concluída" <?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['concluída'] ?? 0)); ?>)
                    </option>
                    <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                    </option>
                </select>
                <!-- Filtro - Finalidade -->
                <select class="width-700 height-md" id="filter-tipo">
                    <option value="" <?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Todas as finalidades</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>" <?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
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
                    <option value="10" <?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="items">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <!-- TITLE CARD -->
        <!-- <div class="card-title" style="padding: 40px 0 10px 20px !important;">
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
                    $processo = $row['Processo'];
                    $idCarta = $row['IdCarta'];
                    $subtarefas = $row['Subtarefas'] ?? [];
                    $statusTarefa = (string) ($row['Status'] ?? '');
                    $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
                    $numeroProcessoDestinoAtual = trim((string) ($row['NumeroProcessoDestino'] ?? $row['numero_processo_destino'] ?? ''));
                    $varaDestinoAtual = trim((string) ($row['VaraDestino'] ?? $row['vara_destino_nome'] ?? $row['vara_destino'] ?? ''));
                    if (($numeroProcessoDestinoAtual === '' || $varaDestinoAtual === '') && is_array($subtarefas)) {
                        foreach ($subtarefas as $stInfoTmp) {
                            if (($stInfoTmp['tipo'] ?? '') !== 'aguardando_informacoes' || empty($stInfoTmp['dados_especificos'])) {
                                continue;
                            }
                            $dadosInfoTmp = json_decode((string) $stInfoTmp['dados_especificos'], true);
                            if (!is_array($dadosInfoTmp)) {
                                continue;
                            }
                            if ($numeroProcessoDestinoAtual === '') {
                                $numeroProcessoDestinoAtual = trim((string) ($dadosInfoTmp['numero_processo_destino'] ?? ''));
                            }
                            if ($varaDestinoAtual === '') {
                                $varaDestinoAtual = trim((string) ($dadosInfoTmp['vara_destino'] ?? ''));
                            }
                            if ($numeroProcessoDestinoAtual !== '' && $varaDestinoAtual !== '') {
                                break;
                            }
                        }
                    }
                ?>

                    <!-- LINHA COMPLETA DO REGISTRO -->
                    <div class="register-line" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCarta); ?>">

                        <!-- Lado esquerdo da linha -->
                        <div class="register-line-left">
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
                            <div class="col width-700 col-comarca">
                                <span class="minor-info text-ellipsis" style="font-weight: 600; color: var(--color-gray-900);"><?php echo htmlspecialchars('Comarca: ' . $row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                                <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['Finalidade']); ?> — <?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                            </div>
                        </div>

                        <!-- Lado direito da linha -->
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <button type="button" class="btn btn-editar" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" title="Editar">
                                <i class="fi fi-rr-pen-square"></i>
                            </button>
                            <div class="display-flex row row-fit justify-end gap-y-sm cartas-expedidas-allocate allocate-users">
                                <?php
                                $alocados = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($row['Alocados'] ?? []);
                                foreach ($alocados as $alocado) {
                                    $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                        'entity_id' => (string) ($idCarta ?? ''),
                                        'origem' => 'cartas_expedidas.list',
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
                                                data-endpoint="/api/cartorio-cartas-expedidas/desalocar.php"
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
                            $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente'];
                            foreach ($allSt as $s) {
                                $o = (int) ($s['ordem'] ?? 0);
                                if ($o >= 1 && $o <= 6) {
                                    $progressStatuses[$o - 1] = (string) ($s['status'] ?? 'pendente');
                                }
                            }
                            $currentSubtarefaTitle = '';
                            foreach ($allSt as $s) {
                                if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                                    $tipoAtual = $s['tipo'] ?? '';
                                    $currentSubtarefaTitle = $taskTitleByTipo[$tipoAtual] ?? $subtarefaLabels[$tipoAtual] ?? $tipoAtual;
                                    break;
                                }
                            }
                            if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                                $currentSubtarefaTitle = $taskTitleByTipo['aguardando_assinatura'] ?? 'Carta aguardando assinatura';
                            } elseif ($currentSubtarefaTitle === '' && ($st === 'concluída' || $st === 'finalizada')) {
                                $currentSubtarefaTitle = 'Todas as etapas concluídas';
                            } elseif ($currentSubtarefaTitle === '') {
                                $currentSubtarefaTitle = $row['Status'] ?? 'Em andamento';
                            }
                            $isEmAndamento = in_array($st, ['em_andamento', 'aguardando assinatura', 'aguardando envio', 'aguardando juntada do termo', 'aguardando juntada de termo', 'aguardando cumprimento'], true);
                            $mostrarBarraStatus = ($st !== 'concluída' && $st !== 'cancelada');
                            $segmentosBarra = [];
                            if ($mostrarBarraStatus) {
                                $segmentosBarra[] = ['class' => 'concluida', 'title' => 'Carta expedida e cadastrada'];
                                for ($i = 0; $i < 6; $i++) {
                                    $statusSeg = $progressStatuses[$i] ?? 'pendente';
                                    $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                                    $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$i + 1] ?? ''];
                                }
                            }
                            ?>
                            <div class="col<?php echo $mostrarBarraStatus && $st !== 'concluída' && $st !== 'cancelada' ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                                <?php
                                if ($st === 'concluída') {
                                    echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                                } elseif ($st === 'cancelada') {
                                    echo '<div class="padding-x-lg status status-cancelled">Cancelada</div>';
                                } elseif ($mostrarBarraStatus) {
                                    $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                    $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                    echo '<div class="status width-300 ' . $classStatus . ' status-with-progress">';
                                    echo '<span class="status-task-label" style="justify-content: flex-start; font-size: 10px;">' . htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES) . '</span>';
                                    $concluidosBarra = 1 + count(array_filter($progressStatuses, fn($x) => $x === 'concluida'));
                                    echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="7" aria-label="Progresso das subtarefas">';
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

                    <!-- SUBTAREFAS DO REGISTRO -->
                    <?php if (!$tarefaConcluida): ?>
                        <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-hidden="true">
                            <?php foreach ($subtarefas as $st):
                                $stId = $st['id'] ?? '';
                                $stTipo = $st['tipo'] ?? '';
                                $stTipoNormalizado = strtolower(trim((string) $stTipo));
                                $isGerarPdf = in_array($stTipoNormalizado, ['aguardando_gerar_pdf', 'gerar_pdf', 'geracao_pdf'], true);
                                $isEnvio = in_array($stTipoNormalizado, ['aguardando_envio', 'envio_processo', 'enviar_processo', 'envio'], true);
                                $isInformacoes = in_array($stTipoNormalizado, ['aguardando_informacoes', 'informar_dados', 'detalhamento_informacoes', 'Aguardando cumprimento'], true);
                                $isDevolucao = in_array($stTipoNormalizado, ['aguardando_devolucao', 'confirmar_juntada', 'juntada_devolucao', 'juntar_devolucao', 'Aguardando devolução'], true);
                                $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                            ?>
                                <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                                    <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                                        <div class="col task-status">
                                            <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                                        </div>
                                        <?php if ($isGerarPdf): ?>
                                            <div class="col">
                                                <input type="number" class="width-100 height-sm subtask-qtd-docs" value="2" min="2" max="10" placeholder="Qtd de docs (2 a 10)" title="Qtd de docs gerados" />
                                            </div>
                                        <?php elseif ($isEnvio): ?>
                                            <div class="col">
                                                <select class="width-400 height-sm subtask-metodo-envio" title="Método de envio">
                                                    <option value="">Método de envio</option>
                                                    <option value="Email">E-mail</option>
                                                    <option value="Malote Digital">Malote Digital</option>
                                                    <option value="PJe">PJe</option>
                                                    <option value="eSAJ">eSAJ</option>
                                                    <option value="ePROC">ePROC</option>
                                                </select>
                                            </div>
                                        <?php elseif ($isInformacoes): ?>
                                            <div class="col">
                                                <input type="text" class="width-400 height-sm subtask-numero-destino" placeholder="Nº processo" title="Número processo destino" value="<?php echo htmlspecialchars($numeroProcessoDestinoAtual, ENT_QUOTES); ?>" />
                                            </div>
                                            <div class="col">
                                                <input type="text" class="width-600 height-sm subtask-vara-destino" placeholder="Vara destino" title="Vara destino" value="<?php echo htmlspecialchars($varaDestinoAtual, ENT_QUOTES); ?>" />
                                            </div>
                                        <?php elseif ($isDevolucao): ?>
                                            <div class="col">
                                                <select class="width-500 height-sm subtask-resultado-devolucao" title="Resultado da devolução">
                                                    <option value="">Resultado</option>
                                                    <option value="devolvida_sem_cumprimento">Não cumprida</option>
                                                    <option value="cumprida_negativamente">Cumprida negativamente</option>
                                                    <option value="cumprida_parcialmente">Cumprida parcialmente</option>
                                                    <option value="cumprida_positivamente">Cumprida integralmente</option>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="display-flex row row-fit justify-end align-center">
                                        <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?>">
                                            <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
        </div>
    <?php endif; ?>

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
                    <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
                    <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                </button>
                <?php foreach ($paginationButtons as $page): ?>
                    <?php
                    $pageParams = $baseQueryParams;
                    $pageParams['page_number'] = $page;
                    ?>
                    <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>>
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
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>>
                            <?php echo $page; ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
                <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
                    <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
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

    <script src="/assets/js/modules/cartorio/cartas-expedidas/search-list.js"></script>
    <script>
        (function() {
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

                sections.forEach(function(section) {
                    section.style.display = 'none';
                });

                document.querySelectorAll('[data-list-section-trigger]').forEach(function(trigger) {
                    trigger.addEventListener('click', function(event) {
                        handleToggleClick(event, true);
                    });
                    trigger.addEventListener('keydown', function(event) {
                        handleToggleKeydown(event, true);
                    });
                });

                document.querySelectorAll('[data-list-section-hide]').forEach(function(trigger) {
                    trigger.addEventListener('click', function(event) {
                        handleToggleClick(event, false);
                    });
                    trigger.addEventListener('keydown', function(event) {
                        handleToggleKeydown(event, false);
                    });
                });
            }

            initializeListSectionToggles();

            if (window.CartasExpedidasSearch && typeof window.CartasExpedidasSearch.initializeProcessSearch === 'function') {
                window.CartasExpedidasSearch.initializeProcessSearch({
                    inputId: 'search-input',
                    itemsPerPageId: 'items-per-page',
                    filters: [{
                            id: 'filter-tipo',
                            param: 'finalidade'
                        },
                        {
                            id: 'filter-status',
                            param: 'status'
                        },
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
                newHref: '/frame.php?page=cartasexpedidascreate',
                editButtonSelector: '.btn-editar[data-carta-id]',
                editIdAttribute: 'cartaId',
                editHrefBuilder: function(cartaId) {
                    return '/frame.php?page=cartasexpedidasedit&id=' + encodeURIComponent(cartaId);
                },
                onReady: function(ctx) {
                    const notify = ctx.notify;
                    const queuePostReloadToast = ctx.queuePostReloadToast;

                    document.querySelectorAll('.btn-concluir-subtarefa').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const normalizeTipo = (value) => String(value || '').trim().toLowerCase();
                            const subtarefaId = this.getAttribute('data-subtarefa-id');
                            if (!subtarefaId) return;
                            const block = this.closest('[data-subtarefa-id][data-subtarefa-tipo]');
                            const tipo = normalizeTipo(block ? (block.getAttribute('data-subtarefa-tipo') || '') : '');
                            const qtdEl = block ? block.querySelector('.subtask-qtd-docs') : null;
                            const numeroDestino = block ? block.querySelector('.subtask-numero-destino') : null;
                            const resultadoDevolucao = block ? block.querySelector('.subtask-resultado-devolucao') : null;
                            const metodoEnvio = block ? block.querySelector('.subtask-metodo-envio') : null;

                            if (['aguardando_gerar_pdf', 'gerar_pdf'].includes(tipo)) {
                                var qtd = qtdEl ? (parseInt(qtdEl.value, 10) || 0) : 0;
                                if (qtd < 2 || qtd > 10) {
                                    notify('error', 'Atenção', 'Informe a quantidade de documentos gerados (2 a 10).');
                                    return;
                                }
                            } else if (['aguardando_envio', 'envio_processo', 'enviar_processo'].includes(tipo)) {
                                if (!metodoEnvio || !metodoEnvio.value || !metodoEnvio.value.trim()) {
                                    notify('error', 'Atenção', 'Selecione o método de envio.');
                                    return;
                                }
                            } else if (['aguardando_informacoes', 'informar_dados', 'detalhamento_informacoes'].includes(tipo)) {
                                if (!numeroDestino || !numeroDestino.value.trim()) {
                                    notify('error', 'Atenção', 'Informe o número do processo no Juízo Deprecante.');
                                    return;
                                }
                            } else if (['aguardando_devolucao', 'confirmar_juntada', 'juntada_devolucao'].includes(tipo)) {
                                if (!resultadoDevolucao || !resultadoDevolucao.value) {
                                    notify('error', 'Atenção', 'Selecione o resultado da devolução.');
                                    return;
                                }
                            }

                            let dadosEspecificos = null;
                            if (block) {
                                const varaDestino = block.querySelector('.subtask-vara-destino');
                                const obj = {};
                                if (qtdEl) obj.qtd_docs_gerados = parseInt(qtdEl.value, 10) || 2;
                                if (numeroDestino && numeroDestino.value.trim()) obj.numero_processo_destino = numeroDestino.value.trim();
                                if (varaDestino && varaDestino.value.trim()) obj.vara_destino = varaDestino.value.trim();
                                if (resultadoDevolucao && resultadoDevolucao.value) obj.resultado_devolucao = resultadoDevolucao.value;
                                if (metodoEnvio && metodoEnvio.value && metodoEnvio.value.trim()) obj.metodo_envio = metodoEnvio.value.trim();
                                if (Object.keys(obj).length > 0) dadosEspecificos = JSON.stringify(obj);
                            }

                            btn.disabled = true;
                            fetch('/api/cartorio-cartas-expedidas/concluir-subtarefa.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        subtarefa_id: subtarefaId,
                                        dados_especificos: dadosEspecificos
                                    })
                                })
                                .then(function(response) {
                                    return response.json();
                                })
                                .then(function(data) {
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
                                .catch(function() {
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