<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/EditaisService.php';

$connection = Conection::getInstance()->getConnection();

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
    'certificacao_publicacao' => 'Certificação da publicação',
    'certificacao_decurso_prazo' => 'Certificação de decurso do prazo',
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
    'certificacao_publicacao' => 'Informar certificação da publicação',
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

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Lista</span>
        </div>
        <h1 class="title-page">Editais</h1>
        <p class="description-page">Expedição, envio para publicação no DJEN, certificação da publicação e certificação de decurso do prazo.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de editais</h2>
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
                    <label for="filter-tipo-edital">Filtrar por tipo de edital</label>
                    <select class="width-700 height-md" id="filter-tipo-edital">
                        <option value=""<?php echo $tipoEditalFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <?php foreach ($tipoEditalOptions as $tipoEditalOption): ?>
                            <option value="<?php echo htmlspecialchars($tipoEditalOption, ENT_QUOTES); ?>"<?php echo $tipoEditalFilter === $tipoEditalOption ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipoEditalOption); ?>
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
                $idEdital = $row['IdEdital'];
                $subtarefas = $row['Subtarefas'] ?? [];
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
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idEdital); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idEdital); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <div class="col width-500 col-process" title="clique para copiar">
                            <?php
                                $classeEdital = $row['Classe'] ?? 'Classe não informada';
                                if ($classeEdital === '') {
                                    $classeEdital = 'Classe não informada';
                                }
                                $classeStyleEdital = $classeEdital !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeEdital, ENT_QUOTES); ?>" <?php echo $classeStyleEdital; ?>><?php echo htmlspecialchars($classeEdital); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo'] ?? '—'); ?></span>
                        </div>
                        <div class="col width-600 col-tipo-edital" title="clique para copiar">
                            <span class="minor-info">Tipo de edital</span>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['tipo_edital'] ?? $row['TipoEdital'] ?? '—')); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="col button-table button-edit" data-edital-id="<?php echo htmlspecialchars($idEdital); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                        <?php
                            $alocados = $row['Alocados'] ?? [];
                            if (count($alocados) > 0) {
                                foreach ($alocados as $alocado) {
                                    $tipo = $alocado['tipo'] ?? 'specific';
                                    $iniciais = $alocado['iniciais'] ?? '—';
                                    $nomeCompleto = $alocado['nome_completo'] ?? '';
                                    $userLabel = $nomeCompleto !== '' ? $nomeCompleto : $iniciais;
                                    if ($tipo === 'rule') {
                                        $title = 'Edital alocado para ' . ($nomeCompleto !== '' ? $nomeCompleto : $iniciais);
                                    } elseif ($tipo === 'all') {
                                        $title = 'Usuário alocado para a tarefa: ' . ($nomeCompleto !== '' ? $nomeCompleto : $iniciais);
                                    } else {
                                        $title = 'Alocação voluntária: ' . ($nomeCompleto !== '' ? $nomeCompleto : $iniciais);
                                    }
                                    $classeTipo = in_array($tipo, ['rule', 'specific', 'all'], true) ? ' ' . $tipo : '';
                                    ?><span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>"><?php echo htmlspecialchars($iniciais, ENT_QUOTES); ?></span><?php
                                }
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

                <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idEdital); ?>" aria-hidden="true">
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
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum edital cadastrado.</span>
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
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
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
            const applyFilters = function () {
                const status = document.getElementById('filter-status')?.value ?? '';
                const tipoEdital = document.getElementById('filter-tipo-edital')?.value ?? '';
                const processo = document.getElementById('search-input')?.value?.trim() ?? '';
                const perPage = document.getElementById('items-per-page')?.value ?? '10';
                const params = new URLSearchParams(window.location.search);
                params.set('page', '<?php echo $routePage; ?>');
                params.set('page_number', '1');
                params.set('items_per_page', perPage);
                if (status) params.set('status', status); else params.delete('status');
                if (tipoEdital) params.set('tipo_edital', tipoEdital); else params.delete('tipo_edital');
                if (processo) params.set('processo', processo); else params.delete('processo');
                window.location.href = '?' + params.toString();
            };

            document.getElementById('filter-status')?.addEventListener('change', applyFilters);
            document.getElementById('filter-tipo-edital')?.addEventListener('change', applyFilters);
            document.getElementById('items-per-page')?.addEventListener('change', applyFilters);

            document.getElementById('search-input')?.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') applyFilters();
            });

            document.getElementById('novo-cadastro-btn')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=editaiscreate';
            });

            document.querySelectorAll('.button-edit').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.dataset.editalId;
                    if (id) window.location.href = '/frame.php?page=editaisedit&id=' + encodeURIComponent(id);
                });
            });

            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const editalId = this.value;
                    const targetId = 'subtask-' + editalId;
                    document.querySelectorAll('.col.subtask').forEach(function (row) {
                        const isTarget = row.id === targetId;
                        row.classList.toggle('subtask-collapsed', !isTarget);
                        row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                });
            });

            document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    this.disabled = true;
                    const payload = { subtarefa_id: subtarefaId };
                    const tipo = this.closest('[data-subtarefa-tipo]')?.getAttribute('data-subtarefa-tipo');
                    if (tipo === 'certificacao_publicacao') {
                        const dataPub = prompt('Informe a data da publicação (AAAA-MM-DD):');
                        if (dataPub) payload.dados_especificos = { data_publicacao: dataPub };
                    }
                    fetch('/api/cartorio-editais/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) window.location.reload();
                        else {
                            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: data.error || 'Erro ao concluir.' });
                            else alert(data.error || 'Erro ao concluir.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                        else alert('Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>
</body>
</html>
