<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CejuscService.php';

$connection = Conection::getInstance()->getConnection();

$currentUserId   = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao        = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'cejusc'));
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
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new CejuscService($connection);
    $listResult = $service->listTarefas($filters, $itemsPerPage, $offset);
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
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));

$subtarefaLabels = [
    'gerar_pdf_decisao' => 'Gerar PDF da decisão',
    'enviar_decisao' => 'Envio da decisão por e-mail',
    'juntar_termo_envio' => 'Juntada de termo de envio',
];
$ordemLabels = [
    1 => $subtarefaLabels['gerar_pdf_decisao'],
    2 => $subtarefaLabels['enviar_decisao'],
    3 => $subtarefaLabels['juntar_termo_envio'],
];

$buttonTitleByTipo = [
    'gerar_pdf_decisao' => 'Informar impressão em PDF',
    'enviar_decisao' => 'Informar envio por e-mail',
    'juntar_termo_envio' => 'Informar juntada de termo de envio',
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
        <title>Cejusc - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Cejusc -</span>
            <span class="specific">Lista de tarefas</span>
        </div>
        <h1 class="title-page">Cejusc</h1>
        <p class="description-page">Geração da decisão em PDF, envio por e-mail ao Cejusc e juntada do comprovante de envio.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de tarefas</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
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
                $idCarta = $row['IdCarta'];
                $subtarefas = $row['Subtarefas'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaConcluida = in_array($statusTarefa, ['concluída', 'concluida', 'finalizada'], true);
                $allSt = $row['SubtarefasAll'] ?? [];
                $progressStatuses = ['pendente', 'pendente', 'pendente'];
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    if ($o >= 1 && $o <= 3) {
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
                    $currentSubtarefaTitle = $ordemLabels[1] ?? 'Gerar PDF da decisão';
                } elseif ($currentSubtarefaTitle === '' && ($st === 'concluída')) {
                    $currentSubtarefaTitle = 'Todas as etapas concluídas';
                } elseif ($currentSubtarefaTitle === '') {
                    $currentSubtarefaTitle = $row['Status'] ?? 'Em andamento';
                }
                $isEmAndamento = ($st === 'em_andamento');
                $mostrarBarraStatus = ($st !== 'concluída' && $st !== 'cancelada');
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    for ($i = 0; $i < 3; $i++) {
                        $statusSeg = $progressStatuses[$i] ?? 'pendente';
                        $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$i + 1] ?? ''];
                    }
                }
            ?>
            <div class="display-flex col col-full register border-bottom" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCarta); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col">
                            <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-expanded="false" />
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
                        <div class="col width-600 col-reu" title="clique para copiar">
                            <span class="minor-info">Réu</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($row['ReuNome'] ?? '—'); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-carta-id="<?php echo htmlspecialchars($idCarta); ?>" title="Editar">
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
                                        data-endpoint="/api/cartorio-cejusc/desalocar.php"
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
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="3">';
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
                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center">
                            <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?>">
                                <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                            <span class="minor-info">Nenhuma sub-tarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhuma tarefa cadastrada.</span>
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
            if (!window.App || !App.CartorioCejuscList || typeof App.CartorioCejuscList.initialize !== 'function') {
                return;
            }

            App.CartorioCejuscList.initialize({
                routePage: <?php echo json_encode($routePage, JSON_UNESCAPED_UNICODE); ?>
            });
        })();
    </script>
</body>
</html>
