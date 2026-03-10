<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';

$connection = Conection::getInstance()->getConnection();
$routePage = trim((string) ($_GET['page'] ?? 'instituicoespublicas'));
$nomeFilter = trim($_GET['nome'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) $itemsPerPage = 25;
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;

$filters = ['nome' => $nomeFilter];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new InstituicoesPublicasService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
} catch (Throwable $e) { die('Erro: ' . $e->getMessage()); }

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);
$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($currentPage > $totalPages && $totalPages > 0) $currentPage = $totalPages;
$startItem = count($rows) > 0 ? $offset + 1 : 0;
$endItem = $offset + count($rows);

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($nomeFilter !== '') $baseQueryParams['nome'] = $nomeFilter;
$paginationButtons = $totalPages <= 5 ? range(1, max(0, $totalPages)) : array_slice(range(1, $totalPages), 0, 5);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instituições Públicas</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Instituições Públicas</span></div>
        <h1 class="title-page">Instituições Públicas</h1>
        <p class="description-page">Cadastro de instituições públicas.</p>
    </div>
    <div class="card-page">
        <form method="get" class="row filter">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <input type="hidden" name="items_per_page" value="<?php echo $itemsPerPage; ?>">
            <div class="col filter">
                <label for="filter-nome">Nome</label>
                <input type="text" class="width-md height-md" id="filter-nome" name="nome" value="<?php echo htmlspecialchars($nomeFilter); ?>">
            </div>
            <div class="col filter"><button type="submit" class="btn btn-primary">Filtrar</button></div>
        </form>
        <div class="row buttons">
            <a href="/frame.php?page=instituicoespublicascreate" class="btn btn-primary">Nova instituição</a>
        </div>
        <div class="row table header">Lista</div>
        <?php if (!empty($rows)): ?>
            <table class="table-list">
                <thead><tr><th>Nome</th><th>Sigla/Nome fantasia</th><th>CNPJ</th><th>Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nome'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['nome_fantasia'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['cnpj'] ?? '—'); ?></td>
                        <td><a href="/frame.php?page=instituicoespublicasedit&id=<?php echo urlencode($row['id'] ?? ''); ?>" class="btn btn-secondary-outline">Editar</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="row table"><div class="col"><span class="major-info">Nenhuma instituição encontrada.</span></div></div>
        <?php endif; ?>
        <div class="row pagination">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?></span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php $prev = $baseQueryParams; $prev['page_number'] = max(1, $currentPage - 1); ?>
                    <a class="pagination-symbol" href="?<?php echo http_build_query($prev); ?>">‹</a>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <a class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" href="?<?php echo http_build_query($qp); ?>"><?php echo $p; ?></a>
                    <?php endforeach; ?>
                    <?php $next = $baseQueryParams; $next['page_number'] = min($totalPages, $currentPage + 1); ?>
                    <a class="pagination-symbol" href="?<?php echo http_build_query($next); ?>">›</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
