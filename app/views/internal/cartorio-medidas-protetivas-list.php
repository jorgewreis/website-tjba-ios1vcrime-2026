<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/MedidasProtetivasService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'medidasprotetivas'));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
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
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new MedidasProtetivasService($connection);
    $listResult = $service->listMedidasProtetivas($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);
$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($totalDocuments === 0) {
    $currentPage = 1;
} elseif ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$statusOptions = ['pendente', 'em_andamento', 'concluída', 'cancelada'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$subtarefaLabels = [
    'cadastro_partes_pje' => 'Cadastro de partes no PJe',
    'expedicao_mandados_intimacao' => 'Expedição de mandados de intimação',
];
$ordemLabels = [
    1 => $subtarefaLabels['cadastro_partes_pje'],
    2 => $subtarefaLabels['expedicao_mandados_intimacao'],
];

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
}

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
        <title>Medidas Protetivas - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Lista</span>
        </div>
        <h1 class="title-page">Medidas Protetivas</h1>
        <p class="description-page">Cadastro inicial, cadastro de partes no PJe, expedição de mandados e integração automática com o Cejusc.</p>
    </div>

    <div class="card-page">
        <div class="row filter">
            <div class="col filter">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-md height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) $statusCounts['pendente']; ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) $statusCounts['em_andamento']; ?>)</option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) $statusCounts['concluída']; ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) $statusCounts['cancelada']; ?>)</option>
                </select>
            </div>
            <div class="col filter">
                <label for="items-per-page">Itens por página</label>
                <select class="width-sm height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <div class="row buttons">
            <input type="text" class="width-md height-md" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
            <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <div class="row table header">Lista de Medidas Protetivas</div>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idMedida = (string) ($row['IdMedida'] ?? '');
                $subtarefas = $row['Subtarefas'] ?? [];
                $allSt = $row['SubtarefasAll'] ?? [];
                $progressStatuses = ['pendente', 'pendente'];
                foreach ($allSt as $subtarefa) {
                    $ordem = (int) ($subtarefa['ordem'] ?? 0);
                    if ($ordem >= 1 && $ordem <= 2) {
                        $progressStatuses[$ordem - 1] = (string) ($subtarefa['status'] ?? 'pendente');
                    }
                }
                $st = (string) ($row['Status'] ?? '');
                $currentSubtarefaTitle = '';
                foreach ($allSt as $subtarefa) {
                    if (trim((string) ($subtarefa['status'] ?? '')) === 'em_andamento') {
                        $currentSubtarefaTitle = $subtarefaLabels[$subtarefa['tipo'] ?? ''] ?? '';
                        break;
                    }
                }
                if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                    $currentSubtarefaTitle = $ordemLabels[1];
                } elseif ($currentSubtarefaTitle === '' && $st === 'concluída') {
                    $currentSubtarefaTitle = 'Todas as etapas concluídas';
                }
                $mostrarBarraStatus = !in_array($st, ['concluída', 'cancelada'], true);
            ?>
            <div class="col full-line register" data-medida-id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>">
                <div class="row table" id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>">
                    <div class="row">
                        <div class="col">
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <div class="col filter process-info" title="clique para copiar">
                            <span class="minor-info">Processo</span>
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="col filter comarca-info">
                        <span class="minor-info">Classe</span>
                        <span class="major-info text-ellipsis" title="<?php echo htmlspecialchars((string) ($row['Classe'] ?? '—'), ENT_QUOTES); ?>"><?php echo htmlspecialchars((string) ($row['Classe'] ?? '—')); ?></span>
                    </div>
                    <div class="col filter comarca-info">
                        <span class="minor-info">Réu</span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['ReuNome'] ?? '—')); ?></span>
                    </div>
                    <?php if (!empty($row['CejuscTarefaId'])): ?>
                        <div class="col filter comarca-info">
                            <span class="minor-info">Cejusc</span>
                            <a class="major-info" href="/frame.php?page=cejuscedit&id=<?php echo urlencode((string) $row['CejuscTarefaId']); ?>">Abrir tarefa</a>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col button-table button-edit" data-medida-id="<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <div class="row allocate-users">
                            <?php
                            $alocados = $row['Alocados'] ?? [];
                            if (count($alocados) === 0) {
                                echo '—';
                            } else {
                                foreach ($alocados as $alocado) {
                                    $iniciais = $alocado['iniciais'] ?? '—';
                                    $nomeCompleto = $alocado['nome_completo'] ?? '';
                                    $title = 'Alocado: ' . ($nomeCompleto !== '' ? $nomeCompleto : $iniciais);
                                    echo '<span class="user-allocate" title="' . htmlspecialchars($title) . '">' . htmlspecialchars($iniciais) . '</span>';
                                }
                            }
                            ?>
                        </div>
                        <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php if ($st === 'concluída'): ?>
                                <div class="width-sm status status-finished">Finalizada</div>
                            <?php elseif ($st === 'cancelada'): ?>
                                <div class="width-sm status status-cancelled">Cancelada</div>
                            <?php elseif ($mostrarBarraStatus): ?>
                                <div class="width-sm status <?php echo $st === 'em_andamento' ? 'status-current' : 'status-pending'; ?> status-with-progress">
                                    <span class="status-task-label"><?php echo $st === 'em_andamento' ? 'Em andamento' : 'Pendente'; ?></span>
                                    <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo count(array_filter($progressStatuses, static fn ($value) => $value === 'concluida')); ?>" aria-valuemin="0" aria-valuemax="2">
                                        <?php foreach ($progressStatuses as $index => $statusSeg): ?>
                                            <div class="status-task-progress-segment <?php echo htmlspecialchars($statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente')); ?>" title="<?php echo htmlspecialchars($ordemLabels[$index + 1]); ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="width-sm status status-pending">Pendente</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idMedida, ENT_QUOTES); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $subtarefa): ?>
                        <div class="col full-line" data-subtarefa-id="<?php echo htmlspecialchars((string) ($subtarefa['id'] ?? ''), ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars((string) ($subtarefa['tipo'] ?? ''), ENT_QUOTES); ?>" style="border-top: 1px solid var(--color-gray-400);">
                            <div class="row subtask-item">
                                <div class="left-content">
                                    <div class="col task-status">
                                        <div class="status status-pending"><?php echo htmlspecialchars($subtarefaLabels[$subtarefa['tipo'] ?? ''] ?? 'Subtarefa'); ?></div>
                                    </div>
                                </div>
                                <div class="right-content">
                                    <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars((string) ($subtarefa['id'] ?? ''), ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($subtarefaLabels[$subtarefa['tipo'] ?? ''] ?? 'Concluir', ENT_QUOTES); ?>"><i class="fi fi-rr-play"></i></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                        <div class="col full-line">
                            <span class="minor-info">Nenhuma subtarefa pendente.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Nenhuma medida protetiva cadastrada.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row pagination">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> registros</span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
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
            function applyFilters() {
                var status = document.getElementById('filter-status')?.value || '';
                var processo = document.getElementById('search-input')?.value?.trim() || '';
                var perPage = document.getElementById('items-per-page')?.value || '10';
                var params = new URLSearchParams(window.location.search);
                params.set('page', '<?php echo $routePage; ?>');
                params.set('page_number', '1');
                params.set('items_per_page', perPage);
                if (status) params.set('status', status); else params.delete('status');
                if (processo) params.set('processo', processo); else params.delete('processo');
                window.location.href = '?' + params.toString();
            }

            document.getElementById('filter-status')?.addEventListener('change', applyFilters);
            document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
            document.getElementById('search-input')?.addEventListener('keypress', function (event) {
                if (event.key === 'Enter') {
                    applyFilters();
                }
            });
            document.getElementById('novo-cadastro-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=medidasprotetivascreate';
            });
            document.querySelectorAll('.button-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    var id = this.dataset.medidaId;
                    if (id) {
                        window.location.href = '/frame.php?page=medidasprotetivasedit&id=' + encodeURIComponent(id);
                    }
                });
            });
            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    var targetId = 'subtask-' + this.value;
                    document.querySelectorAll('.row.subtask').forEach(function (row) {
                        var isTarget = row.id === targetId;
                        row.classList.toggle('subtask-collapsed', !isTarget);
                        row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                });
            });
            document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    var qtd = window.prompt('Informe a quantidade realizada nesta subtarefa:');
                    var qtdInt = parseInt(qtd || '0', 10);
                    if (!qtd || isNaN(qtdInt) || qtdInt < 1) {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Campo obrigatório', message: 'Informe uma quantidade maior que zero.' });
                        }
                        return;
                    }
                    btn.disabled = true;
                    fetch('/api/cartorio-medidas-protetivas/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            subtarefa_id: subtarefaId,
                            dados_especificos: { qtd: qtdInt }
                        })
                    })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                            return;
                        }
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao concluir.' });
                        }
                        btn.disabled = false;
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        }
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>
</body>
</html>
