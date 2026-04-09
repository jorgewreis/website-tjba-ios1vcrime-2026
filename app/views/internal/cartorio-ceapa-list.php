<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CeapaService.php';

$connection = Conection::getInstance()->getConnection();

$currentUserId   = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao        = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'ceapa'));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$processoFilter = trim((string) ($_GET['processo'] ?? ''));
$tipoSuspensaoFilter = trim((string) ($_GET['tipo_suspensao'] ?? ''));
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
    'tipo_suspensao' => $tipoSuspensaoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new CeapaService($connection);
    $listResult = $service->listTarefas($filters, $itemsPerPage, $offset);
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

$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptions = ['pendente', 'em_andamento', 'finalizada'];
$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$tipoSuspensaoOptions = [
    'Acordo de não Persecução Penal',
    'Suspensão Condicional do Processo',
];
$tipoSuspensaoCounts = array_merge(
    array_fill_keys($tipoSuspensaoOptions, 0),
    $service->getTipoSuspensaoCounts($filters)
);
$tipoSuspensaoTotal = array_sum($tipoSuspensaoCounts);

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
if ($tipoSuspensaoFilter !== '') {
    $baseQueryParams['tipo_suspensao'] = $tipoSuspensaoFilter;
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
        <title>CEAPA - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno - Cartório - Ceapa -</span>
            <span class="specific">Lista de registros</span>
        </div>
        <h1 class="title-page">Ceapa</h1>
        <p class="description-page">Acompanhamento de cumprimento de decisão com controle de status e observações.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md">
                <h2>Lista de registros</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
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
                        <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-tipo-suspensao">Filtrar por tipo de suspensão</label>
                    <select class="width-500 height-md" id="filter-tipo-suspensao">
                        <option value=""<?php echo $tipoSuspensaoFilter === '' ? ' selected' : ''; ?>>Mostrar todos (<?php echo (int) $tipoSuspensaoTotal; ?>)</option>
                        <option value="Acordo de não Persecução Penal"<?php echo $tipoSuspensaoFilter === 'Acordo de não Persecução Penal' ? ' selected' : ''; ?>>Acordo de não Persecução Penal (<?php echo (int) ($tipoSuspensaoCounts['Acordo de não Persecução Penal'] ?? 0); ?>)</option>
                        <option value="Suspensão Condicional do Processo"<?php echo $tipoSuspensaoFilter === 'Suspensão Condicional do Processo' ? ' selected' : ''; ?>>Suspensão Condicional do Processo (<?php echo (int) ($tipoSuspensaoCounts['Suspensão Condicional do Processo'] ?? 0); ?>)</option>
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
            <?php foreach ($rows as $row): ?>
                <?php
                $idCeapa = (string) ($row['IdCeapa'] ?? '');
                $status = trim((string) ($row['StatusCumprimento'] ?? 'pendente'));
                $statusLabel = 'Pendente';
                $statusClass = 'status-pending';
                if ($status === 'em_andamento') {
                    $statusLabel = 'Em andamento';
                    $statusClass = 'status-current';
                } elseif ($status === 'finalizada') {
                    $statusLabel = 'Finalizada';
                    $statusClass = 'status-finished';
                }
                $classeNome = trim((string) ($row['ClasseNome'] ?? ''));
                if ($classeNome === '') {
                    $classeNome = 'Classe não informada';
                }
                ?>
                <div class="display-flex col col-full register border-bottom" data-ceapa-id="<?php echo htmlspecialchars($idCeapa, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idCeapa, ENT_QUOTES); ?>">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                            <div class="col width-500 col-process process-info" title="clique para copiar">
                                <span class="minor-info text-ellipsis" title="<?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?>"><?php echo htmlspecialchars($classeNome, ENT_QUOTES); ?></span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['Processo'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-600 col-reu" title="clique para copiar">
                                <span class="minor-info">Réu</span>
                                <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['ReuNome'] ?? '—'), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-600">
                                <span class="minor-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TipoSuspensao'] ?? '—'), ENT_QUOTES); ?></span>
                                <span class="major-info"><?php echo "Decisão exarada em " . date('d/m/Y', strtotime((string) ($row['DataDecisao'] ?? '—'))); ?></span>
                            </div>
                            <div class="col width-700">
                                <span class="minor-info">Decisão a cumprir</span>
                                <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['DecisaoCumprir'] ?? '—'), ENT_QUOTES); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
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
                                } elseif ($tipo === 'specific') {
                                    $chipTitle = 'Alocação direta: ' . $nomeLabel;
                                } else {
                                    $chipTitle = 'Alocação: ' . $nomeLabel;
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
                                        data-endpoint="/api/cartorio-ceapa/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">×</button>
                                    <?php endif; ?>
                                </span>
                                <?php
                            }
                            ?>
                            </div>
                            <button type="button" class="btn btn-editar" data-ceapa-id="<?php echo htmlspecialchars($idCeapa, ENT_QUOTES); ?>" title="Editar" aria-label="Editar" >
                                <i class="fi fi-rr-pen-square"></i>
                            </button>
                            <div class="col">
                                <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?></div>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum registro cadastrado.</span>
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
            if (!window.App || !App.CeapaList || typeof App.CeapaList.initialize !== 'function') {
                return;
            }
            App.CeapaList.initialize();
        })();
    </script>
</body>
</html>
