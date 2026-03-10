<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';
require_once __DIR__ . '/../../../config/security.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'instituicoespublicas'));
$nomeFilter = trim($_GET['nome'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : (isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1);

$filtrou = ($nomeFilter !== '');

$rows = [];
$totalDocuments = 0;
$totalPages = 0;
$startItem = 0;
$endItem = 0;

if ($filtrou) {
    $filters = ['nome' => $nomeFilter];
    $offset = ($currentPage - 1) * $itemsPerPage;

    try {
        $service = new InstituicoesPublicasService($connection);
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
if ($nomeFilter !== '') {
    $baseQueryParams['nome'] = $nomeFilter;
}

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
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Instituições Públicas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <script src="/assets/js/shared/text-formatter.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas -</span>
            <span class="specific">Instituições Públicas</span>
        </div>
        <h1 class="title-page">Instituições Públicas</h1>
        <p class="description-page">Cadastro de instituições públicas. Informe o nome (ou parte dele) e clique em Consultar para exibir os resultados.</p>
    </div>

    <div class="card-page">
        <form method="get" class="row filter" id="form-filters">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
            <div class="col filter">
                <label for="filter-nome">Nome</label>
                <input type="text" class="width-md height-md" id="filter-nome" name="nome" placeholder="Nome da instituição" value="<?php echo htmlspecialchars($nomeFilter); ?>">
            </div>
            <div class="col filter">
                <label for="items-per-page">Itens por página</label>
                <select class="width-sm height-md" name="items_per_page" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="col filter">
                <button type="submit" class="btn btn-primary">Consultar</button>
            </div>
        </form>

        <div class="row buttons">
            <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Nova instituição</button>
        </div>

        <div class="row table header">Lista de Instituições</div>
        <?php if (!$filtrou): ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Informe o nome (ou parte do nome) acima e clique em Consultar para exibir os resultados.</span>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($rows)): ?>
            <table class="table-list">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Sigla / Nome fantasia</th>
                        <th>CNPJ</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nome'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['nome_fantasia'] ?? '—'); ?></td>
                        <td><?php
                        $cnpjCell = $row['cnpj'] ?? '';
                        $cnpjCell = preg_replace('/\D+/', '', $cnpjCell);
                        echo htmlspecialchars($cnpjCell !== '' && strlen($cnpjCell) === 14 ? Security::formatCNPJ($cnpjCell) : ($row['cnpj'] ?? '') !== '' ? ($row['cnpj'] ?? '') : '—');
                    ?></td>
                        <td>
                            <button type="button" class="btn btn-secondary-outline" title="Editar" data-edit-id="<?php echo htmlspecialchars($row['id'] ?? ''); ?>"><i class="fi fi-rr-file-edit"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Nenhuma instituição encontrada para os critérios informados.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($filtrou): ?>
        <div class="row pagination">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                <?php if ($totalPages > 0): ?>(<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = min($totalPages, $currentPage + 1);
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
        var filterNome = document.getElementById('filter-nome');
        if (filterNome && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            filterNome.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }
        document.getElementById('novo-cadastro-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=instituicoespublicascreate';
        });
        document.querySelectorAll('button[data-edit-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-edit-id');
                if (id) window.location.href = '/frame.php?page=instituicoespublicasedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
</body>
</html>
