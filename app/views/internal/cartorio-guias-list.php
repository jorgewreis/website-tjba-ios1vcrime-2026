<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/GuiasService.php';

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
    'calcular_pena_multa' => 'Cálculo da pena de multa',
    'expedir_guia' => 'Expedir Guia',
    'assinar_guia' => 'Assinar Guia',
    'gerar_pdf' => 'Gerar PDF da documentação',
    'enviar_documentos' => 'Enviar documentos',
    'juntar_termo_envio' => 'Juntar termo de envio',
    'expedir_certidao' => 'Expedir certidão',
];
$ordemLabels = [
    1 => $subtarefaLabels['calcular_pena_multa'],
    2 => $subtarefaLabels['expedir_guia'],
    3 => $subtarefaLabels['assinar_guia'],
    4 => $subtarefaLabels['gerar_pdf'],
    5 => $subtarefaLabels['enviar_documentos'],
    6 => $subtarefaLabels['juntar_termo_envio'],
    7 => $subtarefaLabels['expedir_certidao'],
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
        <div class="row filter">
            <div class="col filter">
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
            <div class="col filter">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-md height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>Pendente (<?php echo (int) ($statusCounts['pendente'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['concluída'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
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

        <div class="row table header">Lista de Guias</div>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $idGuia = $row['IdGuia'];
                $subtarefas = $row['Subtarefas'] ?? [];
            ?>
            <div class="col full-line register" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>">
                <div class="row table" id="<?php echo htmlspecialchars($idGuia); ?>">
                    <div class="row">
                        <div class="col">
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <div class="col filter process-info" title="clique para copiar">
                            <span class="minor-info">Processo</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo'] ?? ''); ?></span>
                        </div>
                    </div>
                    <div class="col filter">
                        <span class="minor-info">Denunciado</span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Denunciado'] ?? '—'); ?></span>
                    </div>
                    <div class="col filter">
                        <span class="minor-info">Tipo</span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Tipo'] ?? '—'); ?></span>
                    </div>
                    <div class="row">
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
                        $currentSubtarefaTitle = '';
                        foreach ($allSt as $s) {
                            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                                $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                                break;
                            }
                        }
                        if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                            $currentSubtarefaTitle = $ordemLabels[1] ?? 'Cálculo da pena de multa';
                        } elseif ($currentSubtarefaTitle === '' && $st === 'concluída') {
                            $currentSubtarefaTitle = 'Todas as etapas concluídas';
                        } elseif ($currentSubtarefaTitle === '') {
                            $currentSubtarefaTitle = $st === 'cancelada' ? 'Cancelada' : 'Em andamento';
                        }
                        ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-sm status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-sm status status-cancelled">Cancelada</div>';
                            } elseif ($st === 'em_andamento') {
                                echo '<div class="width-sm status status-current">Em andamento</div>';
                            } else {
                                echo '<div class="width-sm status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="row subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idGuia); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st):
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                    ?>
                    <div class="col full-line" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" style="border-top: 1px solid var(--color-gray-400);">
                        <div class="row subtask-info">
                            <div class="col task-info">
                                <span class="minor-info">Subtarefa</span>
                                <span class="major-info"><?php echo htmlspecialchars($stLabel); ?></span>
                            </div>
                        </div>
                        <div class="row subtask-buttons">
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Concluir</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                    <div class="col full-line">
                        <span class="minor-info">Nenhuma subtarefa pendente.</span>
                        <button type="button" class="btn btn-secondary btn-criar-subtarefa" data-guia-id="<?php echo htmlspecialchars($idGuia, ENT_QUOTES); ?>">Criar primeira subtarefa</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Nenhuma guia cadastrada.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row pagination">
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
                document.querySelectorAll('.row.subtask').forEach(function (subtaskRow) {
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
                if (!subtarefaId) return;
                const btnEl = this;
                btnEl.disabled = true;
                fetch('/api/cartorio-guias/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: subtarefaId })
                })
                .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && !result.data.error) {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Subtarefa concluída.' });
                        }
                        window.location.reload();
                    } else {
                        if (typeof showToast === 'function') {
                            showToast({ type: 'error', variant: 'full', title: 'Erro', message: result.data && result.data.error ? result.data.error : 'Erro ao concluir subtarefa.' });
                        }
                        btnEl.disabled = false;
                    }
                })
                .catch(function() {
                    if (typeof showToast === 'function') {
                        showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao concluir subtarefa.' });
                    }
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
                            showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Subtarefa criada.' });
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
