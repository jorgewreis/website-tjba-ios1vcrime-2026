<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/EditaisService.php';
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

$routePage = trim((string) ($_GET['page'] ?? 'editais'));
$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$tipoEditalFilter = trim($_GET['tipo_edital'] ?? '');
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

$statusOptions = ['pendente', 'em_andamento', 'concluída', 'cancelada'];
$tipoEditalOptions = ['Citação', 'Intimação', 'Notificação', 'Outro'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));

$subtarefaLabels = [
    'expedicao_edital' => 'Expedição de Edital',
    'envio_djen' => 'Envio para publicação no DJEN',
    'certificacao_publicacao' => 'Data da publicação',
    'certificacao_decurso_prazo' => 'Aguardando decurso do prazo',
];
$ordemLabels = [
    1 => $subtarefaLabels['expedicao_edital'],
    2 => $subtarefaLabels['envio_djen'],
    3 => $subtarefaLabels['certificacao_publicacao'],
    4 => $subtarefaLabels['certificacao_decurso_prazo'],
];

$buttonTitleByTipo = [
    'expedicao_edital' => 'Informar expedição do edital',
    'envio_djen' => 'Informar envio para publicação no DJEN',
    'certificacao_publicacao' => 'Informar data da publicação',
    'certificacao_decurso_prazo' => 'Informar decurso do prazo',
];

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Editais - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <!-- HEADER PAGE -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Editais</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Acompanhamento da expedição, envio ao DJEN, certificação da publicação e decurso do prazo.</p>
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
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro de editais</span>
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
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                </select>
                <select class="width-700 height-md" id="filter-tipo-edital">
                    <option value=""<?php echo $tipoEditalFilter === '' ? ' selected' : ''; ?>>Todos os tipos</option>
                    <?php foreach ($tipoEditalOptions as $tipoEditalOption): ?>
                        <option value="<?php echo htmlspecialchars($tipoEditalOption, ENT_QUOTES); ?>"<?php echo $tipoEditalFilter === $tipoEditalOption ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($tipoEditalOption); ?>
                        </option>
                    <?php endforeach; ?>
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
                $idEdital = $row['IdEdital'];
                $subtarefas = $row['Subtarefas'] ?? [];
                $decursoInfo = is_array($row['DecursoInfo'] ?? null) ? $row['DecursoInfo'] : [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
                $allSt = $row['SubtarefasAll'] ?? [];
                $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente'];
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    if ($o >= 1 && $o <= 4) {
                        $progressStatuses[$o - 1] = (string) ($s['status'] ?? 'pendente');
                    }
                }
                $currentSubtarefaTitle = '';
                foreach ($allSt as $s) {
                    if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                        $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                        break;
                    }
                }
                $st = $row['Status'] ?? '';
                if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                    $currentSubtarefaTitle = $ordemLabels[1] ?? 'Expedição de Edital';
                } elseif ($currentSubtarefaTitle === '' && ($st === 'concluída')) {
                    $currentSubtarefaTitle = 'Todas as etapas concluídas';
                } elseif ($currentSubtarefaTitle === '') {
                    $currentSubtarefaTitle = $row['Status'] ?? 'Em andamento';
                }
                $isEmAndamento = ($st === 'em_andamento');
                $mostrarBarraStatus = ($st !== 'concluída' && $st !== 'cancelada');
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    for ($i = 0; $i < 4; $i++) {
                        $statusSeg = $progressStatuses[$i] ?? 'pendente';
                        $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$i + 1] ?? ''];
                    }
                }
            ?>
            <div class="display-flex col col-full register border-bottom" data-edital-id="<?php echo htmlspecialchars($idEdital, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idEdital); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idEdital); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idEdital); ?>" aria-expanded="false" />
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                            <?php endif; ?>
                        </div>
                        <div class="col width-500 col-process process-info" title="clique para copiar">
                            <?php
                                $classeEdital = trim((string) ($row['Classe'] ?? ''));
                                $classeEditalTemValor = $classeEdital !== '' && $classeEdital !== 'Classe não informada';
                                if (!$classeEditalTemValor) {
                                    $classeEdital = 'Classe não informada';
                                }
                                $classeEditalClass = 'minor-info text-ellipsis' . ($classeEditalTemValor ? ' classe-info' : '');
                            ?>
                            <span class="<?php echo $classeEditalClass; ?>" title="<?php echo htmlspecialchars($classeEdital, ENT_QUOTES); ?>"><?php echo htmlspecialchars($classeEdital); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-600 col-tipo-edital" title="clique para copiar">
                            <span class="minor-info">Tipo de edital</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['tipo_edital'] ?? $row['TipoEdital'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-edital-id="<?php echo htmlspecialchars($idEdital); ?>" title="Editar">
                            <i class="fi fi-rr-pen-square"></i>
                        </button>
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                        <?php
                            $alocados = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($row['Alocados'] ?? []);
                            foreach ($alocados as $alocado) {
                                $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                    'entity_id' => (string) ($idEdital ?? ''),
                                    'origem' => 'editais.list',
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
                                        data-endpoint="/api/cartorio-editais/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">×</button>
                                    <?php endif; ?>
                                </span>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="col<?php echo $mostrarBarraStatus && $st !== 'concluída' && $st !== 'cancelada' ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-300 padding-x-lg status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                echo '<div class="width-300 status ' . $classStatus . ' status-with-progress">';
                                echo '<span class="status-task-label width-300">' . htmlspecialchars($labelStatus) . '</span>';
                                $concluidosBarra = count(array_filter($progressStatuses, fn($x) => $x === 'concluida'));
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="4">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class']) . '" title="' . htmlspecialchars($seg['title']) . '"></div>';
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
                    <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idEdital); ?>" aria-hidden="true">
                        <?php foreach ($subtarefas as $st):
                            $stId = $st['id'] ?? '';
                            $stTipo = $st['tipo'] ?? '';
                            $stTipoNormalizado = strtolower(trim((string) $stTipo));
                            $isCertificacaoPublicacao = in_array($stTipoNormalizado, ['certificacao_publicacao', 'certificacao_diario', 'publicacao_certificada'], true);
                            $isCertificacaoDecurso = in_array($stTipoNormalizado, ['certificacao_decurso_prazo', 'decurso_prazo', 'certificar_decurso'], true);
                            $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
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
                                    <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
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
                                <?php if (!$isCertificacaoDecurso || $liberaDecurso): ?>
                                    <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?>">
                                        <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                                    </button>
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
