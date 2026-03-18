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
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;

$filtrou = ($numeroFilter !== '' || $parteNomeFilter !== '');

$rows = [];
$totalDocuments = 0;
$totalPages = 0;
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

    $totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
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

        <?php include __DIR__ . '/_toast_script.php'; ?>
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

    <div class="card-page">

        <!-- FILTROS -->
        <form method="get" id="form-filters" style="width: 100%">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
            <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
                <div class="row left row-gap-10 fit">
                    <div class="col">
                        <label for="filter-numero">Número do processo</label>
                        <input type="text" class="width-md height-md white" id="filter-numero" name="numero" placeholder="Número do processo" value="<?php echo htmlspecialchars($numeroFilter); ?>">
                    </div>
                    <div class="col">
                        <label for="filter-parte">Nome da parte</label>
                        <input type="text" class="width-lg height-md white" id="filter-parte" name="parte_nome" placeholder="Nome da parte" value="<?php echo htmlspecialchars($parteNomeFilter); ?>">
                    </div>
                </div>
                <div class="row right fit">
                    <div class="col">
                        <label style="color: var(--color-gray-000);">-</label>
                        <button type="submit" class="btn btn-primary">Consultar</button>
                    </div>
                </div>                
            </div>
        </form>

        <!-- SUBTITLE TABLE -->
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Lista de Processos</div>

        <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
            <div class="col">
                <label for="items-per-page">Itens por página</label>
                <select class="width-sm height-md white" name="items_per_page" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <?php if (!$filtrou): ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data">
                        <span class="major-info">Informe o número do processo ou o nome da parte e clique em Consultar para exibir os resultados.</span>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($rows)): ?>
            <div class="col full-line border-bottom">
                <?php foreach ($rows as $row): ?>
                <div class="row table between padding-lr-md padding-tb-md border-bottom">
                    <div class="row left">
                        <div class="col process-info">
                            <span class="minor-info"><?php echo htmlspecialchars($row['classe_nome'] ?? '—'); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['numero_processo'] ?? '—'); ?></span>
                        </div>
                        <div class="col process-partes">
                            <span class="minor-info">Partes</span>
                            <span class="major-info"><?php echo htmlspecialchars($row['total_partes'] ?? '—'); ?></span>
                        </div>                        
                    </div>
                    <div class="row right">
                        <div class="col process-edit">
                            <button type="button" class="" title="Abrir" data-edit-id="<?php echo htmlspecialchars($row['id'] ?? ''); ?>"><i class="fi fi-rr-file-edit"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="col subtask subtask-collapsed padding-lr-md full-line">
                    <div class="row table between padding-tb-md border-top">
                        <div class="col table left no-data">
                            <span class="major-info">Nenhum processo encontrado para os critérios informados.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php if ($filtrou): ?>
        <!-- Paginação -->
        <div class="row table between padding-tb-lg padding-lr-md">
            <span class="left pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                <?php if ($totalPages > 0): ?>(<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="right pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams; $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams; $nextParams['page_number'] = min($totalPages, $currentPage + 1);
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="?<?php echo http_build_query($prevParams); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>‹</a>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <a class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" href="?<?php echo http_build_query($qp); ?>"><?php echo $p; ?></a>
                    <?php endforeach; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="?<?php echo http_build_query($nextParams); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>›</a>
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

    <script>
    (function() {
        document.querySelectorAll('button[data-edit-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-edit-id');
                if (id) window.location.href = '/frame.php?page=consultaprocessosedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
</body>
</html>
