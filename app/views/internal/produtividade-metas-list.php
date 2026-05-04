<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../helpers/ProdutividadeMetaAlvoOptions.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
$routePage = trim((string) ($_GET['page'] ?? 'produtividademetas'));

$isGestao = false;
if ($userId !== '') {
    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    foreach ($perfis as $perfil) {
        $p = strtoupper(trim((string) $perfil));
        if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
            $isGestao = true;
            break;
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$ativo = trim((string) ($_GET['ativo'] ?? ''));
$tipoAlvo = trim((string) ($_GET['tipo_alvo'] ?? ''));
$periodoTipo = trim((string) ($_GET['periodo_tipo'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$rows = [];
$totalDocuments = 0;
$errors = [];
$success = (string) ($_SESSION['produtividade_metas_flash_success'] ?? '');
unset($_SESSION['produtividade_metas_flash_success']);

if ($isGestao) {
    try {
        $result = $service->listMetas($userId, ['q' => $q, 'ativo' => $ativo, 'tipo_alvo' => $tipoAlvo, 'periodo_tipo' => $periodoTipo], $itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
        $totalDocuments = (int) ($result['total'] ?? 0);
    } catch (Throwable $e) {
        $errors[] = 'Erro ao carregar metas: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Acesso permitido apenas para perfis de gestão.';
}

$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($isGestao && $errors === []) {
    if ($totalDocuments === 0) {
        $currentPage = 1;
        $offset = 0;
    } elseif ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
        try {
            $result = $service->listMetas($userId, ['q' => $q, 'ativo' => $ativo, 'tipo_alvo' => $tipoAlvo, 'periodo_tipo' => $periodoTipo], $itemsPerPage, $offset);
            $rows = $result['rows'] ?? [];
        } catch (Throwable $e) {
            $errors[] = 'Erro ao carregar metas: ' . $e->getMessage();
        }
    }
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $totalDocuments === 1 ? 'meta' : 'metas';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

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

$baseQuery = [
    'page' => $routePage,
    'q' => $q,
    'ativo' => $ativo,
    'tipo_alvo' => $tipoAlvo,
    'periodo_tipo' => $periodoTipo,
    'items_per_page' => $itemsPerPage,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Produtividade - Metas</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade -</span>
            <span class="specific">Metas</span>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Metas de produtividade</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Gerencie metas por setor, perfil ou usuário para semana, mês e trimestre.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters">
                    <i class="fi fi-rr-filter"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividade">Voltar</button>
                <?php if ($isGestao): ?>
                <button type="button" class="btn btn-primary width-300" data-nav-href="/frame.php?page=produtividademetascreate">Nova meta</button>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" id="form-filters-metas" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>">

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                    <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa</span>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                    <input type="text" id="q" name="q" class="height-md" placeholder="Alvo, tipo ou período" style="width: 290px;" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">
                    <?php if ($isGestao): ?>
                    <?php
                    $listExportPage = $routePage;
                    $listExportQuery = $baseQuery;
                    include __DIR__ . '/_list_export_buttons.php';
                    ?>
                    <?php endif; ?>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
                        <i class="fi fi-rr-angle-small-up"></i>
                    </div>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                    <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                    <select id="ativo" name="ativo" class="width-200 height-md">
                        <option value=""<?php echo $ativo === '' ? ' selected' : ''; ?>>Todos os status</option>
                        <option value="1"<?php echo $ativo === '1' ? ' selected' : ''; ?>>Ativas</option>
                        <option value="0"<?php echo $ativo === '0' ? ' selected' : ''; ?>>Inativas</option>
                    </select>
                    <select id="tipo_alvo" name="tipo_alvo" class="width-200 height-md">
                        <option value=""<?php echo $tipoAlvo === '' ? ' selected' : ''; ?>>Todos os alvos</option>
                        <option value="setor"<?php echo $tipoAlvo === 'setor' ? ' selected' : ''; ?>>Setor</option>
                        <option value="perfil"<?php echo $tipoAlvo === 'perfil' ? ' selected' : ''; ?>>Perfil</option>
                        <option value="usuario"<?php echo $tipoAlvo === 'usuario' ? ' selected' : ''; ?>>Usuário</option>
                    </select>
                    <select id="periodo_tipo" name="periodo_tipo" class="width-200 height-md">
                        <option value=""<?php echo $periodoTipo === '' ? ' selected' : ''; ?>>Todos os períodos</option>
                        <option value="semana"<?php echo $periodoTipo === 'semana' ? ' selected' : ''; ?>>Semana</option>
                        <option value="mes"<?php echo $periodoTipo === 'mes' ? ' selected' : ''; ?>>Mês</option>
                        <option value="trimestre"<?php echo $periodoTipo === 'trimestre' ? ' selected' : ''; ?>>Trimestre</option>
                    </select>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
                        <i class="fi fi-rr-angle-small-up"></i>
                    </div>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="items" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                    <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Itens por página</span>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center">
                    <select id="items_per_page" name="items_per_page" class="width-300 height-md">
                        <?php foreach ($allowedPerPage as $size): ?>
                        <option value="<?php echo $size; ?>"<?php echo $itemsPerPage === $size ? ' selected' : ''; ?>><?php echo $size; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="items">
                        <i class="fi fi-rr-angle-small-up"></i>
                    </div>
                </div>
            </div>
        </form>

        <?php
        $tipoAlvoChipLabels = ['setor' => 'Setor', 'perfil' => 'Perfil', 'usuario' => 'Usuário'];
        $periodoChipLabels = ['semana' => 'Semana', 'mes' => 'Mês', 'trimestre' => 'Trimestre'];
        ?>

        <?php if ($errors === [] && $isGestao): ?>
        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <?php if ($q !== '' || $ativo !== '' || $tipoAlvo !== '' || $periodoTipo !== ''): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm filter-summary wrap gap-y-sm">
                <?php if ($q !== ''): ?>
                <span class="filter-chip">Busca: <?php echo htmlspecialchars($q); ?></span>
                <?php endif; ?>
                <?php if ($ativo !== ''): ?>
                <span class="filter-chip">Status: <?php echo $ativo === '1' ? 'Ativas' : 'Inativas'; ?></span>
                <?php endif; ?>
                <?php if ($tipoAlvo !== ''): ?>
                <span class="filter-chip">Alvo: <?php echo htmlspecialchars($tipoAlvoChipLabels[$tipoAlvo] ?? $tipoAlvo); ?></span>
                <?php endif; ?>
                <?php if ($periodoTipo !== ''): ?>
                <span class="filter-chip">Período: <?php echo htmlspecialchars($periodoChipLabels[$periodoTipo] ?? $periodoTipo); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($errors === []): ?>
            <?php if ($rows === []): ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="minor-info">Nenhuma meta encontrada.</span>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <div class="display-flex col col-full register border-bottom">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                            <div class="col width-200">
                                <span class="minor-info">Tipo de alvo</span>
                                <span class="major-info"><?php
                                    $__ta = (string) ($row['tipo_alvo'] ?? '');
                                    $__tipoAlvoLabels = ['setor' => 'Setor', 'perfil' => 'Perfil', 'usuario' => 'Usuário'];
                                    echo htmlspecialchars($__tipoAlvoLabels[$__ta] ?? $__ta, ENT_QUOTES, 'UTF-8');
                                ?></span>
                            </div>
                            <div class="col width-400">
                                <span class="minor-info">Alvo</span>
                                <span class="major-info" title="<?php echo htmlspecialchars((string) ($row['alvo_valor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php
                                    $__ta = (string) ($row['tipo_alvo'] ?? '');
                                    $__av = (string) ($row['alvo_valor'] ?? '');
                                    if ($__ta === 'setor') {
                                        $__setoresLab = ProdutividadeMetaAlvoOptions::setorChoices();
                                        $__u = strtoupper(trim($__av));
                                        $__alvoExib = $__setoresLab[$__u] ?? $__av;
                                    } elseif ($__ta === 'perfil') {
                                        $__alvoExib = ProdutividadeMetaAlvoOptions::perfilLabel($__av);
                                    } else {
                                        $__alvoExib = $__av;
                                    }
                                    echo htmlspecialchars($__alvoExib, ENT_QUOTES, 'UTF-8');
                                ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Período</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['periodo_tipo'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-300">
                                <span class="minor-info">Vigência</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['vigencia_inicio'] ?? ''), ENT_QUOTES); ?> até <?php echo htmlspecialchars((string) ($row['vigencia_fim'] ?? 'em aberto'), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Pontos meta</span>
                                <span class="major-info"><?php echo number_format((float) ($row['pontos_meta'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <span class="status <?php echo ((int) ($row['ativo'] ?? 0)) === 1 ? 'status-success' : 'status-cancelled'; ?> padding-x-lg">
                                <?php echo ((int) ($row['ativo'] ?? 0)) === 1 ? 'Ativa' : 'Inativa'; ?>
                            </span>
                            <button type="button" class="btn btn-editar" title="Editar" aria-label="Editar" data-nav-href="/frame.php?page=produtividademetasedit&id=<?php echo urlencode((string) ($row['id'] ?? '')); ?>">
                                <i class="fi fi-rr-pen-square"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($errors === []): ?>
        <div class="card-buttons">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel, ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel, ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel, ENT_QUOTES, 'UTF-8'); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls gap-y-sm">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = array_merge($baseQuery, ['page_number' => $prevPage]);
                    $prevHref = '/frame.php?' . http_build_query($prevParams);
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = array_merge($baseQuery, ['page_number' => $nextPage]);
                    $nextHref = '/frame.php?' . http_build_query($nextParams);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = array_merge($baseQuery, ['page_number' => $page]); ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="/frame.php?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && $trailingPages !== []): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = array_merge($baseQuery, ['page_number' => $page]); ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="/frame.php?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>

<?php if ($success !== ''): ?>
<script>
if (typeof showToast === 'function') {
    showToast({ type: 'success', message: <?php echo json_encode($success, JSON_UNESCAPED_UNICODE); ?> });
}
</script>
<?php endif; ?>
<?php if ($errors !== []): ?>
<script>
if (typeof showToast === 'function') {
    <?php foreach ($errors as $error): ?>
    showToast({ type: 'error', title: 'Erro', message: <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?> });
    <?php endforeach; ?>
}
</script>
<?php endif; ?>
</body>
</html>

