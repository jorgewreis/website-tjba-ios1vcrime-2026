<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GuiasService.php';
require_once __DIR__ . '/../../repositories/GuiasSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$currentUserId   = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao        = false;
if ($currentUserId !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$currentUserId]);
    $perfisUsuario = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'guias'));
$tipoFilter = trim($_GET['tipo'] ?? '');
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
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Detalhar data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Calcular pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Expedir a Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Assinar a Guia no BNMP',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Juntar a Guia aos autos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Gerar documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Enviar a Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Juntar o termo de envio',
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
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Aguardando expedição da Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Aguardando assinatura da Juíza',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Aguardando confirmação da juntada dos autos',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Aguardando geração dos documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Aguardando envio da Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Aguardando juntada do termo de envio',
];
$buttonTitleByTipo = [
    GuiasSubtarefasRepository::TIPO_ORDEM_1 => 'Informar data do fato e dias-multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_2 => 'Informar valor da pena de multa',
    GuiasSubtarefasRepository::TIPO_ORDEM_3 => 'Informar expedição da Guia',
    GuiasSubtarefasRepository::TIPO_ORDEM_4 => 'Informar assinatura da Juíza',
    GuiasSubtarefasRepository::TIPO_ORDEM_5 => 'Confirmar juntada (automática ou manual)',
    GuiasSubtarefasRepository::TIPO_ORDEM_6 => 'Informar quantidade de documentos em PDF',
    GuiasSubtarefasRepository::TIPO_ORDEM_7 => 'Informar tipo de envio e destinatário',
    GuiasSubtarefasRepository::TIPO_ORDEM_8 => 'Informar juntada do termo de envio',
];
$tipoJuntadaOptions = ['automática' => 'Automática', 'manual' => 'Manual'];
$tipoEnvioOptions = ['E-mail' => 'E-mail', 'Malote Digital' => 'Malote Digital', 'PJe' => 'PJe', 'eSAJ' => 'eSAJ', 'ePROC' => 'ePROC'];
$destinatarioOptions = ['VEP' => 'VEP', 'SEEU' => 'SEEU'];
$tiposGuiaExpedicaoOptions = [
    'Guia de Execução Definitiva',
    'Guia de Recolhimento Provisória',
    'Guia de Recolhimento Definitiva',
];

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
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Guias - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
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
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Lista de guias</span>
        </div>
        <h1 class="title-page">Guias</h1>
        <p class="description-page">Controle de guias de execução e recolhimento.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de guias</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <select class="width-300 height-md" id="export-type" disabled>
                    <option value="" selected>Exportar lista</option>
                    <option value="pdf">Gerar PDF</option>
                    <option value="csv">Gerar CSV</option>
                    <option value="print">Imprimir</option>
                </select>
                <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md">
                <div class="col">
                    <label for="filter-status">Filtrar por status</label>
                    <select class="width-500 height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                        <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                        <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)</option>
                        <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-tipo">Filtrar por tipo</label>
                    <select class="width-500 height-md" id="filter-tipo">
                        <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <?php foreach ($tipoCountsOrdered as $tipo => $count): if ($count === 0 && $tipoFilter !== $tipo) continue; ?>
                            <option value="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"<?php echo $tipoFilter === $tipo ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo, ENT_QUOTES); ?> (<?php echo (int) $count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>                
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page">
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
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
            ?>
            <div class="display-flex col col-full register border-bottom" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idGuia); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process process-info" title="clique para copiar">
                            <?php
                                $classeGuia = !empty($row['Classe']) ? $row['Classe'] : 'Classe não informada';
                                $classeStyleGuia = $classeGuia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeGuia, ENT_QUOTES); ?>" <?php echo $classeStyleGuia; ?>><?php echo htmlspecialchars($classeGuia); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo'] ?? ''); ?></span>
                        </div>
                        <div class="col width-500 col-tipo-guia" title="clique para copiar">
                            <span class="minor-info">Tipo</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['Tipo'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-500 col-reu" title="clique para copiar">
                            <span class="minor-info">Denunciado</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['Denunciado'] ?? '—'); ?></span>
                        </div>                        
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-edit-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-pen-square"></i>
                        </button>
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                            <?php
                            $alocados = $row['Alocados'] ?? [];
                            foreach ($alocados as $alocado) {
                                $tipo         = $alocado['tipo'] ?? 'specific';
                                $iniciais     = htmlspecialchars($alocado['iniciais'] ?? '—', ENT_QUOTES);
                                $nomeCompleto = $alocado['nome_completo'] ?? '';
                                $nomeLabel    = $nomeCompleto !== '' ? $nomeCompleto : ($alocado['iniciais'] ?? '—');
                                if ($tipo === 'rule') {
                                    $chipTitle = 'Servidor responsável (regra): ' . $nomeLabel;
                                } elseif ($tipo === 'all') {
                                    $chipTitle = 'Servidor alocado: ' . $nomeLabel;
                                } else {
                                    $chipTitle = 'Autoalocado: ' . $nomeLabel;
                                }
                                $classeTipo   = in_array($tipo, ['rule', 'specific', 'all'], true) ? ' ' . $tipo : '';
                                $alocadoIdStr = htmlspecialchars($alocado['alocado_id'] ?? '', ENT_QUOTES);
                                $usuarioIdStr = $alocado['usuario_id'] ?? '';
                                $podeRemover  = $isGestao || ($tipo === 'specific' && $usuarioIdStr === $currentUserId);
                                ?>
                                <span class="user-allocate-wrap">
                                    <span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($chipTitle, ENT_QUOTES); ?>"><?php echo $iniciais; ?></span>
                                    <?php if ($podeRemover): ?>
                                    <button type="button"
                                        class="user-allocate-remove js-desalocar"
                                        data-alocado-id="<?php echo $alocadoIdStr; ?>"
                                        data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"
                                        data-endpoint="/api/cartorio-guias/desalocar.php"
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
                        <div class="col<?php echo $mostrarBarra ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarra) {
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, function ($x) { return $x === 'concluida' || $x === 'cancelada' || $x === 'pulada'; }));
                                echo '<div class="width-300 status status-current status-with-progress"><span class="status-task-label width-300">Em andamento</span>';
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="9" aria-label="Progresso">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div></div>';
                            } else {
                                echo '<div class="width-300 padding-x-lg status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if (!$tarefaConcluida): ?>
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stTipoNormalizado = strtolower(trim((string) $stTipo));
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $btnTitle = $buttonTitleByTipo[$stTipo] ?? $stLabel;
                        $isOrdem1 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_1), 'informar_detalhes', 'detalhamento'], true);
                        $isOrdem2 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_2), 'calcular_multa', 'calculo_multa'], true);
                        $isOrdem3 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_3), 'expedir_guia', 'expedicao'], true);
                        $isOrdem5 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_5), 'confirmar_juntada', 'juntada_autos', 'juntada_guia_autos', 'juntada_nos_autos'], true);
                        $isOrdem6 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_6), 'geracao_pdf', 'pdf'], true);
                        $isOrdem7 = in_array($stTipoNormalizado, [strtolower(GuiasSubtarefasRepository::TIPO_ORDEM_7), 'enviar_guia', 'envio'], true);
                        $dadosEspecificosSt = [];
                        if (!empty($st['dados_especificos'])) {
                            $dadosDecode = json_decode((string) $st['dados_especificos'], true);
                            if (is_array($dadosDecode)) {
                                $dadosEspecificosSt = $dadosDecode;
                            }
                        }
                        $tipoGuiaAtual = trim((string) ($dadosEspecificosSt['tipo_guia'] ?? ''));
                        if ($tipoGuiaAtual === '') {
                            $tipoGuiaAtual = trim((string) ($row['Tipo'] ?? ''));
                        }
                        $tipoJuntadaAtual = trim((string) ($dadosEspecificosSt['tipo_juntada'] ?? ''));
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                            <?php if ($isOrdem1): ?>
                                <?php $rowDataFato = $row['data_fato'] ?? ''; $rowDiasMulta = isset($row['dias_multa']) && $row['dias_multa'] !== null ? (int) $row['dias_multa'] : ''; ?>
                                <div class="col"><input type="date" class="subtask-data-fato width-300 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" placeholder="Data do fato" title="Data do fato" value="<?php echo $rowDataFato !== '' ? htmlspecialchars($rowDataFato, ENT_QUOTES) : ''; ?>"></div>
                                <div class="col"><input type="number" class="subtask-dias-multa width-300 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" min="0" placeholder="Dias-multa" title="Quantidade de dias-multa" value="<?php echo $rowDiasMulta !== '' ? (int) $rowDiasMulta : ''; ?>"></div>
                            <?php endif; ?>
                            <?php if ($isOrdem2): ?>
                                <?php $valorMultaRow = isset($row['valor_multa']) && $row['valor_multa'] !== null && $row['valor_multa'] !== '' ? 'R$ ' . number_format((float) $row['valor_multa'], 2, ',', '.') : ''; ?>
                                <div class="col"><input type="text" class="subtask-valor-multa width-100 height-sm readonly" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" readonly placeholder="Valor (automático)" title="Valor total da pena de multa" value="<?php echo htmlspecialchars($valorMultaRow, ENT_QUOTES); ?>"></div>
                            <?php endif; ?>
                            <?php if ($isOrdem3): ?>
                                <div class="col"><select class="subtask-tipo-guia width-400 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo da guia expedida">
                                    <option value="">Selecione o tipo da guia</option>
                                    <?php foreach ($tiposGuiaExpedicaoOptions as $tipoGuiaOption): ?>
                                        <option value="<?php echo htmlspecialchars($tipoGuiaOption, ENT_QUOTES); ?>"<?php echo $tipoGuiaAtual === $tipoGuiaOption ? ' selected' : ''; ?>><?php echo htmlspecialchars($tipoGuiaOption, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <?php endif; ?>
                            <?php if ($isOrdem5): ?>
                                <div class="col"><select class="subtask-tipo-juntada width-300 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de juntada">
                                    <option value="">Selecione</option>
                                    <?php foreach ($tipoJuntadaOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"<?php echo $tipoJuntadaAtual === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <?php endif; ?>
                            <?php if ($isOrdem6): ?>
                                <div class="col"><input type="number" class="subtask-qtd-docs width-200 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" min="4" value="4" placeholder="Qtd (mín 4)" title="Qtd de documentos gerados"></div>
                            <?php endif; ?>
                            <?php if ($isOrdem7): ?>
                                <div class="col"><select class="subtask-tipo-envio width-300 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Tipo de envio">
                                    <option value="">Selecione</option>
                                    <?php foreach ($tipoEnvioOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                                <div class="col"><select class="subtask-destinatario width-200 height-sm" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="Destinatário">
                                    <option value="">Dest.</option>
                                    <?php foreach ($destinatarioOptions as $val => $label): ?>
                                        <option value="<?php echo htmlspecialchars($val, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
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
                    <?php if (empty($subtarefas)): ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top align-center gap-y-sm">
                            <span class="minor-info">Nenhuma sub-tarefa pendente.</span>
                            <button type="button" class="btn btn-secondary height-sm btn-criar-subtarefa" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>">Criar primeira subtarefa</button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhuma guia cadastrada.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
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
        const notify = (type, title, message) => {
            if (typeof showToast === 'function') {
                showToast({ type, variant: 'full', title, message });
            }
        };
        const POST_RELOAD_TOAST_KEY = 'cartorio.postReloadToast';
        const queuePostReloadToast = (type, title, message) => {
            try {
                const raw = sessionStorage.getItem(POST_RELOAD_TOAST_KEY);
                const fila = raw ? JSON.parse(raw) : [];
                const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                lista.push({ type, title, message });
                sessionStorage.setItem(POST_RELOAD_TOAST_KEY, JSON.stringify(lista));
            } catch (e) {}
        };
        const flushPostReloadToast = () => {
            try {
                const raw = sessionStorage.getItem(POST_RELOAD_TOAST_KEY);
                if (!raw) return;
                sessionStorage.removeItem(POST_RELOAD_TOAST_KEY);
                const data = JSON.parse(raw);
                const lista = Array.isArray(data) ? data : (data && data.message ? [data] : []);
                lista.forEach(function (item) {
                    if (item && item.message) notify(item.type || 'info', item.title || 'Informação', item.message);
                });
            } catch (e) {}
        };
        flushPostReloadToast();
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

        document.querySelectorAll('.btn-editar[data-edit-id]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                const guiaId = (event.currentTarget?.getAttribute('data-edit-id') || '').trim();
                if (!guiaId) {
                    notify('error', 'Erro', 'Identificador da guia não encontrado para abrir edição.');
                    return;
                }
                window.location.href = '/frame.php?page=guiasedit&id=' + encodeURIComponent(guiaId);
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
                const normalizeTipo = (value) => String(value || '').trim().toLowerCase();
                const hasTipo = (tipo, aliases) => aliases.includes(tipo);
                if (!subtarefaId) return;
                const block = this.closest('div.subtask[data-subtarefa-id]');
                const subtarefaTipo = normalizeTipo(
                    this.getAttribute('data-subtarefa-tipo') ||
                    (block ? block.getAttribute('data-subtarefa-tipo') : '')
                );
                const obj = {};
                if (block) {
                    const dataFato = block.querySelector('.subtask-data-fato');
                    const diasMulta = block.querySelector('.subtask-dias-multa');
                    const valorMulta = block.querySelector('.subtask-valor-multa');
                    const tipoGuia = block.querySelector('.subtask-tipo-guia');
                    const tipoJuntada = block.querySelector('.subtask-tipo-juntada');
                    const qtdDocs = block.querySelector('.subtask-qtd-docs');
                    const tipoEnvio = block.querySelector('.subtask-tipo-envio');
                    const destinatario = block.querySelector('.subtask-destinatario');
                    if (dataFato && dataFato.value && dataFato.value.trim()) obj.data_fato = dataFato.value.trim();
                    if (diasMulta && diasMulta.value !== '' && parseInt(diasMulta.value, 10) >= 0) obj.dias_multa = parseInt(diasMulta.value, 10);
                    if (valorMulta && valorMulta.value && valorMulta.value.trim()) obj.valor_multa = valorMulta.value.trim();
                    if (tipoGuia && tipoGuia.value && tipoGuia.value.trim()) obj.tipo_guia = tipoGuia.value.trim();
                    if (tipoJuntada && tipoJuntada.value && tipoJuntada.value.trim()) obj.tipo_juntada = tipoJuntada.value.trim();
                    if (qtdDocs && qtdDocs.value !== '') { var q = parseInt(qtdDocs.value, 10); if (q >= 4) obj.qtd_docs = q; }
                    if (tipoEnvio && tipoEnvio.value && tipoEnvio.value.trim()) obj.tipo_envio = tipoEnvio.value.trim();
                    if (destinatario && destinatario.value && destinatario.value.trim()) obj.destinatario = destinatario.value.trim();
                }
                if (hasTipo(subtarefaTipo, ['detalhamento_informacoes', 'informar_detalhes', 'detalhamento'])) {
                    var dataFatoValor = (obj.data_fato || '').trim();
                    if (dataFatoValor !== '') {
                        var hoje = new Date();
                        hoje.setHours(0, 0, 0, 0);
                        var limiteMinimo = new Date(hoje);
                        limiteMinimo.setDate(limiteMinimo.getDate() - 10);
                        var dataFatoDate = new Date(dataFatoValor + 'T00:00:00');
                        if (isNaN(dataFatoDate.getTime()) || dataFatoDate > hoje || dataFatoDate < limiteMinimo) {
                            notify('error', 'Atenção', 'A data do fato deve estar dentro dos últimos 10 dias e não pode ser futura.');
                            return;
                        }
                    }
                }
                if (['juntada_automatica', 'confirmar_juntada', 'juntada_autos', 'juntada_guia_autos', 'juntada_nos_autos'].includes(subtarefaTipo) && !obj.tipo_juntada) {
                    notify('error', 'Atenção', 'Selecione o tipo de juntada (automática ou manual).');
                    return;
                }
                if (hasTipo(subtarefaTipo, ['expedicao_guia', 'expedir_guia', 'expedicao']) && !obj.tipo_guia) {
                    notify('error', 'Atenção', 'Selecione o tipo da guia expedida.');
                    return;
                }
                if (hasTipo(subtarefaTipo, ['gerar_pdf', 'geracao_pdf', 'pdf'])) {
                    var q = block ? parseInt(block.querySelector('.subtask-qtd-docs')?.value || '0', 10) : 0;
                    if (q < 4) {
                        notify('error', 'Atenção', 'Informe no mínimo 4 documentos.');
                        return;
                    }
                    obj.qtd_docs = q;
                }
                if (hasTipo(subtarefaTipo, ['envio_guia', 'enviar_guia', 'envio']) && (!obj.tipo_envio || !obj.destinatario)) {
                    notify('error', 'Atenção', 'Selecione o tipo de envio e o destinatário.');
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
                        queuePostReloadToast('success', 'Sucesso', msgConcluida);
                        if (result.data.tarefa_principal_concluida && result.data.tarefa_principal_mensagem) {
                            queuePostReloadToast('success', 'Sucesso', result.data.tarefa_principal_mensagem);
                        }
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao concluir subtarefa.');
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    notify('error', 'Erro', 'Erro ao concluir subtarefa.');
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
                        queuePostReloadToast('success', 'Sucesso', 'Detalhamento cancelado: etapa de data do fato e dias-multa não será concluída.');
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Erro ao cancelar.');
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    notify('error', 'Erro', 'Erro de conexão.');
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
                        queuePostReloadToast('success', 'Sucesso', 'Primeira subtarefa criada: detalhamento (data do fato e dias-multa) disponível para conclusão.');
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

        (function () {
            const pending = {};
            function requireSecondClick(key, msg) {
                const now = Date.now();
                if ((pending[key] || 0) > now) { delete pending[key]; return true; }
                pending[key] = now + 5000;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: msg });
                return false;
            }
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-desalocar');
                if (!btn) return;
                const alocadoId = btn.getAttribute('data-alocado-id') || '';
                const tipo      = btn.getAttribute('data-tipo') || '';
                const endpoint  = btn.getAttribute('data-endpoint') || '';
                if (!alocadoId || !endpoint) return;
                if (!requireSecondClick('desalocar-' + alocadoId, 'Clique novamente para confirmar a remoção da alocação.')) return;
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alocado_id: alocadoId, tipo: tipo }),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.deleted) {
                        if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Alocação removida.' });
                        setTimeout(function () { window.location.reload(); }, 800);
                    } else {
                        const msg = (res.j && res.j.error) ? res.j.error : 'Falha ao remover alocação.';
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: msg });
                    }
                }).catch(function () {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                });
            });
        })();
    </script>
</body>
</html>
