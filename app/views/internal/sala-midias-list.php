<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaMidiasService.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';

$connection = Conection::getInstance()->getConnection();
$service = new SalaMidiasService($connection);
$currentUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$isGestao = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'])) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'salamidias'));
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$dataAudiencia = trim((string) ($_GET['data_audiencia'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$list = $service->list([
    'search' => $search,
    'status' => $status,
    'data_audiencia' => $dataAudiencia,
], $itemsPerPage, $offset);
$rows = $list['rows'] ?? [];
$total = (int) ($list['total'] ?? 0);
$statusCounts = array_merge(
    array_fill_keys(['pendente', 'em_andamento', 'concluida', 'cancelada'], 0),
    $service->getStatusCounts(['search' => $search, 'data_audiencia' => $dataAudiencia])
);

if ($total === 0) {
    $totalPages = 0;
    $currentPage = 1;
} else {
    $totalPages = (int) ceil($total / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
}
$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($search !== '') {
    $baseQueryParams['search'] = $search;
}
if ($status !== '') {
    $baseQueryParams['status'] = $status;
}
if ($dataAudiencia !== '') {
    $baseQueryParams['data_audiencia'] = $dataAudiencia;
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

$subtarefaLabels = [
    'download_midia' => 'Download de Mídia no Lifesize',
    'sincronizacao_midia' => 'Sincronização de Mídia no PJe',
    'upload_midia' => 'Upload de Mídia na Pasta Pública',
    'certidao_upload_midia' => 'Certidão de Upload de Mídia no PJe',
];
$buttonTitleByTipo = [
    'download_midia' => 'Informar download',
    'sincronizacao_midia' => 'Informar sincronização',
    'upload_midia' => 'Informar upload',
    'certidao_upload_midia' => 'Informar expedição de certidão',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sala de Audiências - Mídias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Sala de Audiências -</span>
        <span class="specific">Mídias</span>
    </div>
    <h1 class="title-page">Mídias de audiências</h1>
    <p class="description-page">Controle de tarefas de download, sincronização, upload e certidão de upload de mídia.</p>
</div>

<div class="container-full card-page">
    <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
        <div class="display-flex row row-fit justify-start">
            <h2>Listagem de tarefas</h2>
        </div>
        <div class="display-flex row row-fit justify-end gap-y-md">
            <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo, classe ou tipo..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="btn btn-secondary-outline" id="regras-btn">Regras</button>
            <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Nova tarefa</button>
        </div>
    </div>

    <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
        <div class="display-flex row row-fit justify-start gap-y-md">
            <div class="col">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-500 height-md" id="filter-status">
                    <option value=""<?php echo $status === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="pendente"<?php echo $status === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $status === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="concluida"<?php echo $status === 'concluida' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluida'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $status === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                </select>
            </div>
            <div class="col">
                <label for="filter-data-audiencia">Filtrar por data da audiência</label>
                <input type="date" class="width-300 height-md" id="filter-data-audiencia" value="<?php echo htmlspecialchars($dataAudiencia, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="display-flex row row-fit justify-end gap-y-md">
            <div class="col">
                <label for="items-per-page">Itens por página</label>
                <select class="width-300 height-md" id="items-per-page">
                    <?php foreach ($allowedPerPage as $opt): ?>
                        <option value="<?php echo $opt; ?>"<?php echo $itemsPerPage === $opt ? ' selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="display-flex col col-full">
        <?php if ($rows === []): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhuma tarefa de mídia encontrada.</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $id = trim((string) ($row['id'] ?? ''));
                $statusRow = trim((string) ($row['status'] ?? ''));
                $subtarefas = is_array($row['Subtarefas'] ?? null) ? $row['Subtarefas'] : [];
                $alocados = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($row['Alocados'] ?? []);
                $tarefaConcluida = in_array($statusRow, ['concluida', 'cancelada'], true);
                $qtdMidiasRow = max(1, (int) ($row['qtd_midias'] ?? 1));
                $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente'];
                $orderMap = ['download_midia' => 1, 'sincronizacao_midia' => 2, 'upload_midia' => 3, 'certidao_upload_midia' => 4];
                foreach ($subtarefas as $st) {
                    $code = trim((string) ($st['codigo'] ?? ''));
                    $ord = $orderMap[$code] ?? 0;
                    if ($ord >= 1 && $ord <= 4) {
                        $progressStatuses[$ord - 1] = (string) ($st['status'] ?? 'pendente');
                    }
                }
                $currentSubtarefaTitle = '';
                foreach ($subtarefas as $st) {
                    if (trim((string) ($st['status'] ?? '')) === 'em_andamento') {
                        $currentSubtarefaTitle = $subtarefaLabels[(string) ($st['codigo'] ?? '')] ?? (string) ($st['titulo'] ?? '');
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $statusRow === 'pendente') {
                    $currentSubtarefaTitle = $subtarefaLabels['download_midia'];
                } elseif ($currentSubtarefaTitle === '' && $statusRow === 'concluida') {
                    $currentSubtarefaTitle = 'Todas as etapas concluídas';
                } elseif ($currentSubtarefaTitle === '') {
                    $currentSubtarefaTitle = $statusRow === 'cancelada' ? 'Cancelada' : 'Em andamento';
                }
                $mostrarBarraStatus = ($statusRow !== 'concluida' && $statusRow !== 'cancelada');
                $subtarefaAtiva = null;
                foreach ($subtarefas as $sub) {
                    if (trim((string) ($sub['status'] ?? '')) === 'em_andamento') {
                        $subtarefaAtiva = $sub;
                        break;
                    }
                }
                if ($subtarefaAtiva === null) {
                    foreach ($subtarefas as $sub) {
                        if (trim((string) ($sub['status'] ?? '')) === 'pendente') {
                            $subtarefaAtiva = $sub;
                            break;
                        }
                    }
                }
                ?>
                <div class="display-flex col col-full register border-bottom" data-midia-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col">
                                <?php if (!$tarefaConcluida): ?>
                                <label class="button-table" title="Expandir detalhes">
                                    <input type="radio" name="open-subtask-midias" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" aria-controls="subtask-midias-<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false">
                                    <i class="fi fi-rr-angle-small-down"></i>
                                </label>
                                <?php endif; ?>
                            </div>
                            <div class="col width-500 col-process process-info" title="Clique para copiar">
                                <span class="minor-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['classe_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="col width-600" title="Clique para copiar">
                                <span class="minor-info">Audiência</span>
                                <span class="major-info text-ellipsis"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($row['data_audiencia'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($row['tipo_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <button type="button" class="btn btn-editar" title="Editar tarefa de mídia" aria-label="Editar tarefa de mídia" data-nav-href="/frame.php?page=salamidiasedit&id=<?php echo urlencode($id); ?>">
                                <i class="fi fi-rr-pen-square"></i>
                            </button>
                            <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                                <?php foreach ($alocados as $alocado): ?>
                                    <?php
                                    $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                        'entity_id' => $id,
                                        'origem' => 'salamidias.list',
                                    ]);
                                    if ($chip === null) {
                                        continue;
                                    }
                                    $tipoChip = $chip['tipo'];
                                    $podeRemover = $isGestao || ($tipoChip === 'specific' && (string) ($chip['usuario_id'] ?? '') === $currentUserId);
                                    ?>
                                    <span class="user-allocate-wrap">
                                        <span class="user-allocate<?php echo $chip['classe_tipo']; ?>" title="<?php echo htmlspecialchars($chip['title'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($chip['aria_label'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($chip['iniciais'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <?php if ($podeRemover): ?>
                                        <button type="button"
                                            class="user-allocate-remove js-desalocar"
                                            data-alocado-id="<?php echo htmlspecialchars($chip['alocado_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-endpoint="/api/sala-midias/desalocar.php"
                                            title="Remover alocação"
                                            aria-label="Remover alocação">×</button>
                                        <?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <div class="col<?php echo $mostrarBarraStatus ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($statusRow === 'concluida'): ?>
                                    <div class="width-300 padding-x-lg status status-finished">Finalizada</div>
                                <?php elseif ($statusRow === 'cancelada'): ?>
                                    <div class="width-300 padding-x-lg status status-cancelled">Cancelada</div>
                                <?php else: ?>
                                    <?php
                                    $isEmAndamento = $statusRow === 'em_andamento';
                                    $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                    $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                    $concluidosBarra = count(array_filter($progressStatuses, static function ($x): bool {
                                        return $x === 'concluida';
                                    }));
                                    ?>
                                    <div class="width-300 status <?php echo $classStatus; ?> status-with-progress">
                                        <span class="status-task-label width-300"><?php echo htmlspecialchars($labelStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarra; ?>" aria-valuemin="0" aria-valuemax="4">
                                            <?php
                                            $titles = [$subtarefaLabels['download_midia'], $subtarefaLabels['sincronizacao_midia'], $subtarefaLabels['upload_midia'], $subtarefaLabels['certidao_upload_midia']];
                                            for ($i = 0; $i < 4; $i++):
                                                $segStatus = $progressStatuses[$i] ?? 'pendente';
                                                $segClass = $segStatus === 'concluida' ? 'concluida' : ($segStatus === 'em_andamento' ? 'em_andamento' : 'pendente');
                                            ?>
                                                <div class="status-task-progress-segment <?php echo htmlspecialchars($segClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($titles[$i], ENT_QUOTES, 'UTF-8'); ?>"></div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!$tarefaConcluida): ?>
                    <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-midias-<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                        <?php if ($subtarefaAtiva !== null): ?>
                            <?php
                            $subId = trim((string) ($subtarefaAtiva['id'] ?? ''));
                            $subCode = trim((string) ($subtarefaAtiva['codigo'] ?? ''));
                            $subLabel = $subtarefaLabels[$subCode] ?? trim((string) ($subtarefaAtiva['titulo'] ?? 'Subtarefa'));
                            $subStatus = trim((string) ($subtarefaAtiva['status'] ?? 'pendente'));
                            $concluivel = in_array($subStatus, ['pendente', 'em_andamento'], true) && $statusRow !== 'cancelada';
                            $isDownload = $subCode === 'download_midia';
                            $isSyncOrUpload = in_array($subCode, ['sincronizacao_midia', 'upload_midia'], true);
                            ?>
                            <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($subId, ENT_QUOTES, 'UTF-8'); ?>" data-subtarefa-codigo="<?php echo htmlspecialchars($subCode, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields">
                                    <div class="col task-status">
                                        <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($subLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <?php if ($isDownload): ?>
                                        <div class="col">
                                            <input
                                                type="number"
                                                class="width-300 height-sm js-midias-qtd-midias"
                                                min="1"
                                                step="1"
                                                value="<?php echo (int) $qtdMidiasRow; ?>"
                                                placeholder="Qtd. de mídias para download"
                                                title="Informe a quantidade de mídias para download">
                                        </div>
                                    <?php elseif ($isSyncOrUpload): ?>
                                        <div class="col">
                                            <input
                                                type="number"
                                                class="width-300 height-sm readonly js-midias-qtd-midias"
                                                value="<?php echo (int) $qtdMidiasRow; ?>"
                                                readonly
                                                title="Quantidade de mídias definida no download">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="display-flex row row-fit justify-end align-center">
                                    <?php if ($concluivel): ?>
                                    <button type="button"
                                        class="btn btn-primary height-sm js-midias-concluir-subtarefa"
                                        data-subtarefa-id="<?php echo htmlspecialchars($subId, ENT_QUOTES, 'UTF-8'); ?>"
                                        title="<?php echo htmlspecialchars($buttonTitleByTipo[$subCode] ?? 'Informar resolução da subtarefa', ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$subCode] ?? 'Informar resolução da subtarefa', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
        <span class="pagination-info">
            Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> tarefa(s)
            (<?php echo $totalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)
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
            <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
            </button>
            <?php foreach ($paginationButtons as $page): ?>
                <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
            <?php endforeach; ?>
            <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                <?php foreach ($trailingPages as $page): ?>
                    <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                    <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                <?php endforeach; ?>
            <?php endif; ?>
            <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.SALA_MIDIAS_LIST = {
    routePage: <?php echo json_encode($routePage, JSON_UNESCAPED_UNICODE); ?>,
    filters: {
        search: <?php echo json_encode($search, JSON_UNESCAPED_UNICODE); ?>,
        status: <?php echo json_encode($status, JSON_UNESCAPED_UNICODE); ?>,
        data_audiencia: <?php echo json_encode($dataAudiencia, JSON_UNESCAPED_UNICODE); ?>,
        items_per_page: <?php echo (int) $itemsPerPage; ?>
    }
};
</script>
<script src="/assets/js/modules/audiencias/midias-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/midias-list.js') ?: time(); ?>"></script>
</body>
</html>
