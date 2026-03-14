<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GuiasService.php';
require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'guias'));
$tipoFilter = trim($_GET['tipo'] ?? '');
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
    'tipo' => $tipoFilter,
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new GuiasService($connection);
    $listResult = $service->listGuias($filters, $itemsPerPage, $offset);
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

$documentsLabel = $totalDocuments === 1 ? 'guia' : 'guias';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptions = ['pendente', 'em_andamento', 'concluída', 'cancelada'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));

$subtarefaLabels = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Detalhamento: data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Cálculo da pena de multa (valor em R$)',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Expedição da Guia ao cartório',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Assinatura da Juíza na Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Juntada dos autos (automática ou manual)',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Geração dos documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Envio da Guia (canal e destinatário)',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Juntada do termo de envio aos autos',
];
$ordemLabels = [
    1 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_1],
    2 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_2],
    3 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_3],
    4 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_4],
    5 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_5],
    6 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_6],
    7 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_7],
    8 => $subtarefaLabels[GuiasSubtarefasRepository::TIPO_ORDEM_8],
];
$taskTitleByTipo = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Aguardando data do fato e dias-multa (detalhamento)',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Aguardando valor da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando expedição da Guia ao cartório',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando assinatura da Juíza na Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando confirmação da juntada dos autos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando geração dos documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando envio da Guia (canal e destinatário)',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Aguardando juntada do termo de envio aos autos',
];
$buttonTitleByTipo = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Informar data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Informar valor da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Informar data de expedição da Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Informar assinatura da Juíza',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Confirmar juntada (automática ou manual)',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Informar quantidade de documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Informar tipo de envio e destinatário',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Informar juntada do termo de envio',
];
$tipoJuntadaOptions = ['automática' => 'Automática', 'manual' => 'Manual'];
$tipoEnvioOptions = ['E-mail' => 'E-mail', 'Malote Digital' => 'Malote Digital', 'PJe' => 'PJe', 'eSAJ' => 'eSAJ', 'ePROC' => 'ePROC'];
$destinatarioOptions = ['VEP' => 'VEP', 'SEEU' => 'SEEU'];

$tipoCounts = $service->getTipoCounts($filters);
$defaultTipos = [
    'Guia - Tipo não informado',
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva',
    'Guia (corrigir)',
    'Guia (reenviar)',
];
$tipoOrder = array_unique(array_merge($defaultTipos, array_keys($tipoCounts)));
$tipoCountsOrdered = [];
foreach ($tipoOrder as $t) {
    $tipoCountsOrdered[$t] = $tipoCounts[$t] ?? 0;
}

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($tipoFilter !== '') {
    $baseQueryParams['tipo'] = $tipoFilter;
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
        <title>Guias - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Lista de guias</span>
        </div>
        <h1 class="title-page">Guias</h1>
        <p class="description-page">Controle de guias de execução e recolhimento.</p>
    </div>

    <div class="card-page">

        <!-- ROW ACTIONS -->
        <div class="row table buttons right padding-lr-md padding-tb-md row-gap-10">
            <input type="text" class="width-md height-md white" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
            <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <!-- SUBTITLE TABLE -->
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Lista de Guias</div>

        <!-- FILTERS -->
        <div class="row table between padding-tb-lg padding-lr-md border-bottom">
            <div class="row left row-gap-10">
                <div class="col">
                    <label for="filter-tipo">Filtrar por tipo</label>
                    <select class="width-md height-md" id="filter-tipo">
                        <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <?php foreach ($tipoCountsOrdered as $tipo => $count): if ($count === 0 && $tipoFilter !== $tipo) continue; ?>
                            <option value="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"<?php echo $tipoFilter === $tipo ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo, ENT_QUOTES); ?> (<?php echo (int) $count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-status">Filtrar por status</label>
                    <select class="width-md height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                        <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                        <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)</option>
                        <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
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
                $idGuia = $row['IdGuia'];
                $subtarefas = $row['Subtarefas'] ?? [];
            ?>
            <!-- REGISTRO COMPLETO -->
            <div class="col full-line border-bottom" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idGuia); ?>">

                <!-- TAREFA -->
                <div class="row table between padding-lr-md">
                    <!-- DIV LEFT -->
                    <div class="row left">
                        <div class="col">
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <!-- DADOS DO PROCESSO -->
                        <div class="col process-info" title="clique para copiar">
                            <?php
                                $classeGuia = !empty($row['Classe']) ? $row['Classe'] : 'Classe não informada';
                                $classeStyleGuia = $classeGuia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeGuia, ENT_QUOTES); ?>" <?php echo $classeStyleGuia; ?>><?php echo htmlspecialchars($classeGuia); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo'] ?? ''); ?></span>
                        </div>
                        <div class="col comarca-info">
                            <span class="minor-info">Denunciado</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Denunciado'] ?? '—'); ?></span>
                        </div>
                        <div class="col detalhes-info">
                            <span class="minor-info">Denunciado</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Denunciado'] ?? '—'); ?></span>
                        </div>
                        <div class="col detalhes-info-tipo">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Tipo'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <!-- DIV RIGHT -->
                    <div class="row right">
                        <div class="col button-table button-edit" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" title="Editar">
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
                                    $title = 'Guia alocada para: ' . $userLabel;
                                    $classeTipo = in_array($tipo, ['rule', 'specific', 'all'], true) ? ' ' . $tipo : '';
                                    ?><span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"><?php echo htmlspecialchars($iniciais, ENT_QUOTES); ?></span><?php
                                }
                            }
                            ?>
                        </div>
                        <?php
                        $st = $row['Status'] ?? '';
                        $allSt = $row['SubtarefasAll'] ?? [];
                        $progressStatuses = array_fill(0, 8, 'pendente');
                        foreach ($allSt as $s) {
                            $o = (int) ($s['ordem'] ?? 0);
                            if ($o >= 1 && $o <= 8) {
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
                            $currentSubtarefaTitle = $taskTitleByTipo[GuiasSubtarefasRepository::TIPO_ORDEM_1] ?? $ordemLabels[1];
                        } elseif ($currentSubtarefaTitle === '' && $st === 'concluída') {
                            $currentSubtarefaTitle = 'Todas as etapas concluídas';
                        } elseif ($currentSubtarefaTitle === '') {
                            $currentSubtarefaTitle = $st === 'cancelada' ? 'Cancelada' : 'Em andamento';
                        }
                        $mostrarBarra = ($st !== 'concluída' && $st !== 'cancelada');
                        $segmentosBarra = [];
                        if ($mostrarBarra) {
                            $segmentosBarra[] = ['class' => 'concluida', 'title' => 'Guia cadastrada'];
                            for ($i = 0; $i < 8; $i++) {
                                $statusSeg = $progressStatuses[$i] ?? 'pendente';
                                $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : ($statusSeg === 'cancelada' || $statusSeg === 'pulada' ? $statusSeg : 'pendente'));
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
                            } elseif ($mostrarBarra) {
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, function ($x) { return $x === 'concluida' || $x === 'cancelada' || $x === 'pulada'; }));
                                echo '<div class="width-sm status status-current status-with-progress"><span class="status-task-label">Em andamento</span>';
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="9" aria-label="Progresso">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div></div>';
                            } else {
                                echo '<div class="width-sm status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- SUB-TAREFAS -->
                <div class="col subtask subtask-collapsed padding-lr-lg" id="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $btnTitle = $buttonTitleByTipo[$stTipo] ?? $stLabel;
                        $isOrdem1 = ($stTipo === GuiasSubtarefasRepository::TIPO_ORDEM_1);
                        $isOrdem2 = ($stTipo === GuiasSubtarefasRepository::TIPO_ORDEM_2);
                        $isOrdem5 = ($stTipo === GuiasSubtarefasRepository::TIPO_ORDEM_5);
                        $isOrdem6 = ($stTipo === GuiasSubtarefasRepository::TIPO_ORDEM_6);
                        $isOrdem7 = ($stTipo === GuiasSubtarefasRepository::TIPO_ORDEM_7);
                    ?>
                    <div class="row table between padding-tb-sm border-top full-line" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="col table left row-gap-10">
                            <div class="status status-pending padding-lr-lg"><?php echo htmlspecialchars($stLabel); ?></div>
                            <?php if ($isOrdem1): ?>
                                <?php $rowDataFato = $row['data_fato'] ?? ''; $rowDiasMulta = isset($row['dias_multa']) && $row['dias_multa'] !== null ? (int) $row['dias_multa'] : ''; ?>
                                <input type="date" class="subtask-data-fato width-sm height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" placeholder="Data do fato" title="Data do fato" value="<?php echo $rowDataFato !== '' ? htmlspecialchars($rowDataFato, ENT_QUOTES) : ''; ?>">
                                <input type="number" class="subtask-dias-multa width-sm height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" min="0" placeholder="Dias-multa" title="Quantidade de dias-multa" value="<?php echo $rowDiasMulta !== '' ? (int) $rowDiasMulta : ''; ?>">
                            <?php endif; ?>
                            <?php if ($isOrdem2): ?>
                                <?php $valorMultaRow = isset($row['valor_multa']) && $row['valor_multa'] !== null && $row['valor_multa'] !== '' ? 'R$ ' . number_format((float) $row['valor_multa'], 2, ',', '.') : ''; ?>
                                <input type="text" class="subtask-valor-multa width-sm height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" readonly placeholder="Valor (automático)" title="Valor total da pena de multa" value="<?php echo htmlspecialchars($valorMultaRow, ENT_QUOTES); ?>">
                            <?php endif; ?>
                            <?php if ($isOrdem5): ?>
                                <select class="subtask-tipo-juntada width-md height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de juntada">
                                    <option value="">Selecione</option>
                                    <?php foreach ($tipoJuntadaOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <?php if ($isOrdem6): ?>
                                <input type="number" class="subtask-qtd-docs width-sm height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" min="4" value="4" placeholder="Qtd (mín 4)" title="Qtd de documentos gerados">
                            <?php endif; ?>
                            <?php if ($isOrdem7): ?>
                                <select class="subtask-tipo-envio width-md height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de envio">
                                    <option value="">Selecione</option>
                                    <?php foreach ($tipoEnvioOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select class="subtask-destinatario width-sm height-md" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Destinatário">
                                    <option value="">Dest.</option>
                                    <?php foreach ($destinatarioOptions as $val => $label): ?>
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
                            <button type="button" class="btn btn-secondary btn-criar-subtarefa" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>">Criar primeira subtarefa</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col subtask subtask-collapsed padding-lr-md">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data">
                        <span class="major-info">Nenhuma guia cadastrada.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="row table between padding-tb-lg padding-lr-md">
            <span class="left pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
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
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
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
        const baseParams = <?php echo json_encode($baseQueryParams, JSON_UNESCAPED_UNICODE); ?>;

        function applyFilters() {
            const params = Object.assign({}, baseParams);
            const tipo = document.getElementById('filter-tipo');
            const status = document.getElementById('filter-status');
            const itemsPerPage = document.getElementById('items-per-page');
            const search = document.getElementById('search-input');
            if (tipo && tipo.value) params.tipo = tipo.value;
            if (status && status.value) params.status = status.value;
            if (itemsPerPage && itemsPerPage.value) params.items_per_page = itemsPerPage.value;
            if (search && search.value.trim()) params.processo = search.value.trim();
            params.page_number = 1;
            window.location.href = '?' + new URLSearchParams(params).toString();
        }

        const novoCadastroBtn = document.getElementById('novo-cadastro-btn');
        if (novoCadastroBtn) {
            novoCadastroBtn.addEventListener('click', function () {
                window.location.href = '/frame.php?page=guiascreate';
            });
        }

        ['filter-tipo', 'filter-status', 'items-per-page'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', applyFilters);
        });

        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilters, 800);
            });
        }

        document.querySelectorAll('.button-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const guiaId = this.dataset.guiaId || btn.closest('[data-guia-id]')?.dataset?.guiaId;
                if (guiaId) window.location.href = '/frame.php?page=guiasedit&id=' + encodeURIComponent(guiaId);
            });
        });

        document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const guiaId = this.value;
                const targetId = 'subtask-' + guiaId;
                document.querySelectorAll('.col.subtask').forEach(function (subtaskRow) {
                    const isTarget = subtaskRow.id === targetId;
                    subtaskRow.classList.toggle('subtask-collapsed', !isTarget);
                    subtaskRow.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                });
                document.querySelectorAll('input[name="open-subtask"]').forEach(function (r) {
                    r.setAttribute('aria-expanded', r.value === guiaId ? 'true' : 'false');
                });
            });
        });

        document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const subtarefaId = this.getAttribute('data-subtarefa-id');
                const subtarefaTipo = this.getAttribute('data-subtarefa-tipo') || '';
                if (!subtarefaId) return;
                const block = this.closest('.col.full-line[data-subtarefa-id]');
                const obj = {};
                if (block) {
                    const dataFato = block.querySelector('.subtask-data-fato');
                    const diasMulta = block.querySelector('.subtask-dias-multa');
                    const valorMulta = block.querySelector('.subtask-valor-multa');
                    const tipoJuntada = block.querySelector('.subtask-tipo-juntada');
                    const qtdDocs = block.querySelector('.subtask-qtd-docs');
                    const tipoEnvio = block.querySelector('.subtask-tipo-envio');
                    const destinatario = block.querySelector('.subtask-destinatario');
                    if (dataFato && dataFato.value && dataFato.value.trim()) obj.data_fato = dataFato.value.trim();
                    if (diasMulta && diasMulta.value !== '' && parseInt(diasMulta.value, 10) >= 0) obj.dias_multa = parseInt(diasMulta.value, 10);
                    if (valorMulta && valorMulta.value && valorMulta.value.trim()) obj.valor_multa = valorMulta.value.trim();
                    if (tipoJuntada && tipoJuntada.value && tipoJuntada.value.trim()) obj.tipo_juntada = tipoJuntada.value.trim();
                    if (qtdDocs && qtdDocs.value !== '') { var q = parseInt(qtdDocs.value, 10); if (q >= 4) obj.qtd_docs = q; }
                    if (tipoEnvio && tipoEnvio.value && tipoEnvio.value.trim()) obj.tipo_envio = tipoEnvio.value.trim();
                    if (destinatario && destinatario.value && destinatario.value.trim()) obj.destinatario = destinatario.value.trim();
                }
                if (subtarefaTipo === 'juntada_automatica' && !obj.tipo_juntada) {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'Selecione o tipo de juntada (automática ou manual).' });
                    else alert('Selecione o tipo de juntada.');
                    return;
                }
                if (subtarefaTipo === 'gerar_pdf') {
                    var q = block ? parseInt(block.querySelector('.subtask-qtd-docs')?.value || '0', 10) : 0;
                    if (q < 4) {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'Informe no mínimo 4 documentos.' });
                        else alert('Mínimo 4 documentos.');
                        return;
                    }
                    obj.qtd_docs = q;
                }
                if (subtarefaTipo === 'envio_guia' && (!obj.tipo_envio || !obj.destinatario)) {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'Selecione o tipo de envio e o destinatário.' });
                    else alert('Selecione tipo de envio e destinatário.');
                    return;
                }
                var dadosEspecificos = Object.keys(obj).length > 0 ? JSON.stringify(obj) : null;
                var payload = { id: subtarefaId, subtarefa_id: subtarefaId };
                if (dadosEspecificos) payload.dados_especificos = JSON.parse(dadosEspecificos);
                const btnEl = this;
                btnEl.disabled = true;
                fetch('/api/cartorio-guias/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && !result.data.error) {
                        if (typeof showToast === 'function') {
                            var msgConcluidaPorTipo = {
                                'detalhamento_informacoes': 'Detalhamento concluído: data do fato e dias-multa informados.',
                                'calculo_pena_multa': 'Cálculo da pena de multa registrado.',
                                'expedicao_guia': 'Expedição da Guia ao cartório registrada.',
                                'aguardando_assinatura': 'Assinatura da Juíza na Guia registrada.',
                                'juntada_automatica': 'Juntada dos autos registrada (automática ou manual).',
                                'gerar_pdf': 'Geração dos documentos em PDF registrada.',
                                'envio_guia': 'Envio da Guia registrado (canal e destinatário).',
                                'juntada_termo_envio': 'Juntada do termo de envio aos autos registrada.'
                            };
                            var msgConcluida = msgConcluidaPorTipo[subtarefaTipo] || 'Subtarefa concluída.';
                            showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: msgConcluida });
                            if (result.data.valor_multa_calculado) {
                                showToast({ type: 'success', variant: 'full', title: 'Cálculo automático', message: 'Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + result.data.valor_multa_calculado + '.' });
                            }
                        }
                        window.location.reload();
                    } else {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: result.data && result.data.error ? result.data.error : 'Erro ao concluir subtarefa.' });
                        else alert(result.data && result.data.error ? result.data.error : 'Erro ao concluir subtarefa.');
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao concluir subtarefa.' });
                    else alert('Erro ao concluir subtarefa.');
                    btnEl.disabled = false;
                });
            });
        });

        document.querySelectorAll('.btn-cancelar-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const subtarefaId = this.getAttribute('data-subtarefa-id');
                if (!subtarefaId) return;
                if (!confirm('Deseja cancelar a subtarefa "Detalhamento de informações"? A próxima etapa será disponibilizada.')) return;
                const btnEl = this;
                btnEl.disabled = true;
                fetch('/api/cartorio-guias/cancelar-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subtarefa_id: subtarefaId })
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && !result.data.error) {
                        if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Detalhamento cancelado: etapa de data do fato e dias-multa não será concluída.' });
                        window.location.reload();
                    } else {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: result.data && result.data.error ? result.data.error : 'Erro ao cancelar.' });
                        else alert(result.data && result.data.error ? result.data.error : 'Erro ao cancelar.');
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                    else alert('Erro de conexão.');
                    btnEl.disabled = false;
                });
            });
        });

        document.querySelectorAll('.btn-criar-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const guiaId = this.getAttribute('data-guia-id');
                if (!guiaId) return;
                const btnEl = this;
                btnEl.disabled = true;
                fetch('/api/cartorio-guias/criar-primeira-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: guiaId })
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && !result.data.error) {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Primeira subtarefa criada: detalhamento (data do fato e dias-multa) disponível para conclusão.' });
                        }
                        window.location.reload();
                    } else {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: result.data && result.data.error ? result.data.error : 'Erro ao criar subtarefa.' });
                        }
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') {
                        showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao criar subtarefa.' });
                    }
                    btnEl.disabled = false;
                });
            });
        });
    })();
    </script>
</body>
</html>
