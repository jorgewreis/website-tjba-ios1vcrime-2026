<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GabineteMonitoramentoEletronicoService.php';
require_once __DIR__ . '/../../repositories/GabineteMonitoramentoEletronicoSubtarefasRepository.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'monitoramentogabinete'));
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
} else {
    $currentPage = 1;
}

$filters = [
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new GabineteMonitoramentoEletronicoService($connection);
    $listResult = $service->listMonitoramentos($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
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

$documentsLabel = $totalDocuments === 1 ? 'monitoramento' : 'monitoramentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptionsUi = ['pendente', 'em_andamento', 'finalizada', 'cancelada'];
$statusCountsRaw = $service->getStatusCounts($filters);
$statusCounts = array_merge(array_fill_keys($statusOptionsUi, 0), $statusCountsRaw);

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

$subtarefaLabels = [
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR => 'Iniciar monitoramento',
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR => 'Encerrar monitoramento',
];
$buttonTitleByTipo = [
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR => 'Confirmar início',
    GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR => 'Confirmar encerramento',
];
$buttonEncerrarPendenteLabel = 'Encerrar monitoramento';

$statusLabelUi = static function (string $s): string {
    $map = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];

    return $map[$s] ?? $s;
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Monitoramento Eletrônico - Gabinete</title>
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
            <span class="all">Interno - Gabinete - Monitoramento Eletrônico -</span>
            <span class="specific">Lista</span>
        </div>
        <h1 class="title-page">Monitoramento Eletrônico</h1>
        <p class="description-page">Consulta aos registros de monitoramento eletrônico. Para novo cadastro, use a tela de cadastro.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-between align-center gap-y-md" style="gap:12px;">
                <h2 style="margin:0;">Lista de monitoramentos</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo ou réu..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <select class="width-300 height-md" id="export-type" disabled>
                    <option value="" selected>Exportar lista</option>
                    <option value="pdf">Gerar PDF</option>
                    <option value="csv">Gerar CSV</option>
                    <option value="print">Imprimir</option>
                </select>
                <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                <div class="col">
                    <label for="filter-status">Filtrar por status</label>
                    <select class="width-500 height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                        <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                        <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
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
                $idMon = (string) ($row['IdMon'] ?? '');
                $numSeq = (int) ($row['NumeroSeq'] ?? 0);
                $ano = (int) ($row['Ano'] ?? 0);
                $nAno = $numSeq . '/' . $ano;
                $subtarefas = $row['Subtarefas'] ?? [];
                $allSt = $row['SubtarefasAll'] ?? [];
                $statusTarefa = (string) ($row['Status'] ?? '');
                $tarefaCancelada = ($statusTarefa === 'cancelada');
                $tarefaFinalizada = ($statusTarefa === 'finalizada');
                $duracaoLabel = (string) ($row['DuracaoLabel'] ?? '—');
                $classeNome = (string) ($row['ClasseNome'] ?? 'Classe não informada');
                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';

                $progressStatuses = ['pendente', 'pendente'];
                foreach ($allSt as $s) {
                    $o = (int) ($s['ordem'] ?? 0);
                    if ($o >= 1 && $o <= 2) {
                        $stSeg = (string) ($s['status'] ?? 'pendente');
                        $progressStatuses[$o - 1] = $stSeg === 'concluida' ? 'concluida' : ($stSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                    }
                }
                $mostrarBarraStatus = !$tarefaCancelada && !$tarefaFinalizada;
                $segmentosBarra = [];
                if ($mostrarBarraStatus) {
                    for ($i = 0; $i < 2; $i++) {
                        $statusSeg = $progressStatuses[$i] ?? 'pendente';
                        if ($statusSeg === 'concluida') {
                            $segClass = 'concluida';
                        } elseif ($statusSeg === 'em_andamento') {
                            $segClass = 'em_andamento';
                        } else {
                            $segClass = 'pendente';
                        }
                        $ordemSeg = $i + 1;
                        $tipoSeg = $ordemSeg === 1 ? GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR : GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR;
                        $titleSeg = $subtarefaLabels[$tipoSeg] ?? '';
                        $segmentosBarra[] = ['class' => $segClass, 'title' => $titleSeg];
                    }
                }
                $barLabel = $statusTarefa === 'em_andamento' ? 'Em andamento' : 'Pendente';
                $currentSubtarefaTitle = '';
                foreach ($allSt as $s) {
                    if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                        $tipoS = (string) ($s['tipo'] ?? '');
                        $currentSubtarefaTitle = $subtarefaLabels[$tipoS] ?? $tipoS;
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $statusTarefa === 'pendente') {
                    $currentSubtarefaTitle = $subtarefaLabels[GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR] ?? '';
                }
                $expandir = !empty($subtarefas);
                $dataInicioRow = $row['DataInicio'] ?? null;
                $dataInicioBr = '';
                if ($dataInicioRow !== null && $dataInicioRow !== '') {
                    $tsDi = strtotime((string) $dataInicioRow);
                    if ($tsDi !== false) {
                        $dataInicioBr = date('d/m/Y', $tsDi);
                    }
                }
                $barTitleAttr = ($statusTarefa === 'pendente' && $mostrarBarraStatus)
                    ? 'Iniciar e encerrar monitoramento'
                    : $currentSubtarefaTitle;
            ?>
            <div class="display-flex col col-full register border-bottom" data-monitoramento-id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>">

                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <?php if ($expandir): ?>
                        <div class="col">
                            <label class="button-table" title="Expandir subtarefas">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <?php endif; ?>
                        <div class="col width-200">
                            <span class="minor-info">Monitoramento</span>
                            <span class="major-info"><?php echo htmlspecialchars($nAno, ENT_QUOTES); ?></span>
                        </div>
                        <div class="col width-420 col-process process-info" title="clique para copiar">
                            <span class="minor-info text-ellipsis" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? '')); ?></span>
                        </div>
                        <div class="col width-280">
                            <span class="minor-info">Réu</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['ReuNome'] ?? '')); ?></span>
                        </div>
                        <div class="col width-160">
                            <span class="minor-info">Duração</span>
                            <span class="major-info"><?php echo htmlspecialchars($duracaoLabel); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="col button-table button-edit" data-monitoramento-id="<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <?php if ($tarefaCancelada): ?>
                        <div class="col">
                            <div class="width-300 padding-x-lg status status-cancelled"><?php echo htmlspecialchars($statusLabelUi('cancelada')); ?></div>
                        </div>
                        <?php elseif ($tarefaFinalizada): ?>
                        <?php elseif ($mostrarBarraStatus): ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($barTitleAttr); ?>">
                            <div class="width-300 status status-pending status-with-progress">
                                <span class="status-task-label width-300"><?php echo htmlspecialchars($barLabel); ?></span>
                                <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo (int) count(array_filter($progressStatuses, static function ($x) { return $x === 'concluida'; })); ?>" aria-valuemin="0" aria-valuemax="2">
                                    <?php foreach ($segmentosBarra as $seg): ?>
                                    <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class']); ?>" title="<?php echo htmlspecialchars($seg['title']); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($expandir): ?>
                <div class="display-flex col col-full subtask subtask-collapsed gme-subtask-panel" id="subtask-<?php echo htmlspecialchars($idMon, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php if ($statusTarefa === 'em_andamento' && $dataInicioBr !== ''): ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-finished"><?php echo htmlspecialchars($subtarefaLabels[GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR] ?? ''); ?></div>
                            </div>
                            <span class="minor-info">Início em <?php echo htmlspecialchars($dataInicioBr, ENT_QUOTES); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($subtarefas as $st):
                        $stId = (string) ($st['id'] ?? '');
                        $stTipo = (string) ($st['tipo'] ?? '');
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $stStatusSt = (string) ($st['status'] ?? '');
                        $precisaData = ($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_INICIAR);
                        $encerrarPendente = ($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR && $stStatusSt === 'pendente');
                    ?>
                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                            <div class="col task-status">
                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                            </div>
                            <?php if ($precisaData): ?>
                            <div class="col">
                                <label for="data-inicio-<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Data de início</label>
                                <input type="date" class="width-280 height-md gme-data-inicio" id="data-inicio-<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="display-flex row row-fit justify-end align-center">
                            <?php if ($encerrarPendente): ?>
                            <button type="button" class="btn btn-secondary-outline height-sm" disabled title="Disponível após iniciar o monitoramento">
                                <span class="value-button"><?php echo htmlspecialchars($buttonEncerrarPendenteLabel, ENT_QUOTES); ?></span>
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-primary height-sm btn-gme-concluir" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-precisa-data="<?php echo $precisaData ? '1' : '0'; ?>" title="<?php echo htmlspecialchars($stTipo === GabineteMonitoramentoEletronicoSubtarefasRepository::TIPO_ENCERRAR ? 'Confirma encerramento e altera status para Finalizada' : 'Confirma início e altera status para Em andamento', ENT_QUOTES); ?>">
                                <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                            <span class="minor-info">Nenhuma subtarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum monitoramento cadastrado.</span>
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
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $currentPage > 1 ? $currentPage - 1 : 1;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="?<?php echo htmlspecialchars(http_build_query($prevParams)); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>"><?php echo $page; ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="?<?php echo htmlspecialchars(http_build_query($nextParams)); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
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
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };
            const queuePostReloadToast = (type, title, message) => {
                try {
                    const key = 'gme.postReloadToast';
                    const raw = sessionStorage.getItem(key);
                    const fila = raw ? JSON.parse(raw) : [];
                    const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
                    lista.push({ type, title, message });
                    sessionStorage.setItem(key, JSON.stringify(lista));
                } catch (e) {}
            };
            const flushPostReloadToast = () => {
                try {
                    const raw = sessionStorage.getItem('gme.postReloadToast');
                    if (!raw) return;
                    sessionStorage.removeItem('gme.postReloadToast');
                    const data = JSON.parse(raw);
                    const lista = Array.isArray(data) ? data : (data && data.message ? [data] : []);
                    lista.forEach(function (item) {
                        if (item && item.message) notify(item.type || 'info', item.title || 'Informação', item.message);
                    });
                } catch (e) {}
            };
            flushPostReloadToast();

            document.getElementById('novo-cadastro-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=monitoramentogabinetecreate';
            });

            const applyFilters = function () {
                const status = document.getElementById('filter-status')?.value ?? '';
                const processo = document.getElementById('search-input')?.value?.trim() ?? '';
                const perPage = document.getElementById('items-per-page')?.value ?? '25';
                const params = new URLSearchParams(window.location.search);
                params.set('page', '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>');
                params.set('page_number', '1');
                params.set('items_per_page', perPage);
                if (status) params.set('status', status); else params.delete('status');
                if (processo) params.set('processo', processo); else params.delete('processo');
                window.location.href = '?' + params.toString();
            };

            document.getElementById('filter-status')?.addEventListener('change', applyFilters);
            document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
            document.getElementById('search-input')?.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') applyFilters();
            });

            document.querySelectorAll('.button-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.monitoramentoId;
                    if (id) window.location.href = '/frame.php?page=monitoramentogabineteedit&id=' + encodeURIComponent(id);
                });
            });

            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const mid = this.value;
                    const targetId = 'subtask-' + mid;
                    document.querySelectorAll('.gme-subtask-panel').forEach(function (row) {
                        const isTarget = row.id === targetId;
                        row.classList.toggle('subtask-collapsed', !isTarget);
                        row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                });
            });

            document.querySelectorAll('.btn-gme-concluir').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const sid = this.getAttribute('data-subtarefa-id');
                    const precisa = this.getAttribute('data-precisa-data') === '1';
                    if (!sid) return;
                    let dataInicio = null;
                    if (precisa) {
                        const inp = document.getElementById('data-inicio-' + sid);
                        dataInicio = inp && inp.value ? inp.value : '';
                        if (!dataInicio) {
                            notify('error', 'Validação', 'Informe a data de início.');
                            return;
                        }
                    }
                    this.disabled = true;
                    const body = { subtarefa_id: sid };
                    if (dataInicio) body.data_inicio = dataInicio;
                    fetch('/api/gabinete-monitoramento-eletronico/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body),
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (res.ok && res.j && res.j.success) {
                            queuePostReloadToast('success', 'Sucesso', 'Operação concluída.');
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao concluir.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>
</body>
</html>
