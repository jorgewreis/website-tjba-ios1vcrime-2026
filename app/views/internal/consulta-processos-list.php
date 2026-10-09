<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'consultaprocessos'));
$numeroFilter = trim($_GET['numero'] ?? '');
$parteNomeFilter = trim($_GET['parte_nome'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;

$filtrou = ($numeroFilter !== '' || $parteNomeFilter !== '');

$rows = [];
$totalDocuments = 0;
$totalPages = 1;
$startItem = 0;
$endItem = 0;

if ($filtrou) {
    $filters = ['numero' => $numeroFilter, 'parte_nome' => $parteNomeFilter];
    $offset = ($currentPage - 1) * $itemsPerPage;

    try {
        $service = new ConsultaProcessosService($connection);
        $listResult = $service->list($filters, $itemsPerPage, $offset);
        $rows = $listResult['rows'] ?? [];
        $totalDocuments = (int) ($listResult['total'] ?? 0);
    } catch (Throwable $e) {
        die('Erro ao carregar os dados: ' . $e->getMessage());
    }

    $totalPages = max(1, (int) ceil($totalDocuments / $itemsPerPage));
    if ($currentPage > $totalPages && $totalPages > 0) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $itemsPerPage;
    $displayedCount = count($rows);
    $startItem = $displayedCount > 0 ? $offset + 1 : 0;
    $endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
}

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($numeroFilter !== '') $baseQueryParams['numero'] = $numeroFilter;
if ($parteNomeFilter !== '') $baseQueryParams['parte_nome'] = $parteNomeFilter;

$paginationButtons = [];
if ($totalPages > 0) {
    $paginationButtons = $totalPages <= 5 ? range(1, $totalPages) : array_slice(range(1, $totalPages), max(0, $currentPage - 3), 5);
    if ($totalPages > 5 && $currentPage <= 3) {
        $paginationButtons = array_merge(range(1, 4), [$totalPages]);
    }
}

$documentsLabel = $totalDocuments === 1 ? 'registro' : 'registros';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta Processos - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
        <?php /* Apenas formatação CNJ no filtro; não inclui consulta de classe ao DataJud (lista local). */ ?>
        <script src="/assets/js/modules/datajud/cnj.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/datajud/cnj.js') ?: time(); ?>"></script>
        <script>
        (function () {
            function boot() {
                if (typeof window.bindAllCNJInputs === 'function') {
                    window.bindAllCNJInputs(document);
                }
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas -</span>
            <span class="specific">Consulta Processos</span>
        </div>
        <h1 class="title-page">Consulta Processos</h1>
        <p class="description-page">Pesquisa por número do processo ou nome da parte. Informe ao menos um critério e clique em Consultar.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Lista de processos</h2>
            </div>
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="filter-numero">Número do processo</label>
                        <input type="text" class="width-500 height-md" id="filter-numero" name="numero" placeholder="0000000-00.0000.0.00.0000" value="<?php echo htmlspecialchars($numeroFilter); ?>">
                    </div>
                    <div class="col">
                        <label for="filter-parte">Nome da parte</label>
                        <input type="text" class="width-600 height-md" id="filter-parte" name="parte_nome" placeholder="Digite o nome completo" value="<?php echo htmlspecialchars($parteNomeFilter); ?>">
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-end">
                    <?php
                    $listExportPage = $routePage;
                    $listExportQuery = $baseQueryParams;
                    include __DIR__ . '/_list_export_buttons.php';
                    ?>

                </div>
            </div>
        </form>

        <?php if (!$filtrou): ?>
                <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="padding-y-md major-info">Informe o número do processo ou o nome da parte para pesquisar</span>
                </div>
            </div>
        <?php elseif (!empty($rows)): ?>
            <?php foreach ($rows as $row): ?>
            <div class="display-flex col col-full register border-bottom" data-processo-id="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES); ?>">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col width-500 col-process">
                        <?php $processo_bloco_model = 'full'; $processo_bloco_variant = 'consulta-processos'; include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum processo encontrado para os critérios informados.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($filtrou): ?>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                <?php if ($totalPages > 0): ?>(<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams; $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams; $nextParams['page_number'] = min($totalPages, $currentPage + 1);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>‹</button>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" <?php if ($p !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($qp), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $p; ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>›</button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
</body>
</html>
