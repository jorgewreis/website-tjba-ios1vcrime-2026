<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/PessoasService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'consultapartes'));
$nomeFilter = trim($_GET['nome'] ?? '');
$cpfFilter = trim($_GET['cpf'] ?? '');
$instituicaoIdFilter = trim($_GET['instituicao_id'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : (isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1);

$filters = ['nome' => $nomeFilter, 'cpf' => $cpfFilter, 'instituicao_id' => $instituicaoIdFilter];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new PessoasService($connection);
    $listResult = $service->list($filters, $itemsPerPage, $offset);
} catch (Throwable $e) {
    die('Erro ao carregar os dados: ' . $e->getMessage());
}

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);
$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($nomeFilter !== '') $baseQueryParams['nome'] = $nomeFilter;
if ($cpfFilter !== '') $baseQueryParams['cpf'] = $cpfFilter;
if ($instituicaoIdFilter !== '') $baseQueryParams['instituicao_id'] = $instituicaoIdFilter;

$paginationButtons = $totalPages > 0 ? ( $totalPages <= 5 ? range(1, $totalPages) : array_slice(range(1, $totalPages), max(0, $currentPage - 3), 5) ) : [];
if ($totalPages > 5 && $currentPage <= 3) {
    $paginationButtons = array_merge(range(1, 4), [$totalPages]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consulta Partes - Primeira Vara Crime de Ilhéus</title>
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
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Consulta Partes</span></div>
        <h1 class="title-page">Consulta Partes</h1>
        <p class="description-page">Pesquisa por pessoa: nome, CPF ou instituição vinculada.</p>
    </div>
    <div class="card-page">
        <form method="get" class="row filter" id="form-filters">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
            <div class="col filter">
                <label for="filter-nome">Nome</label>
                <input type="text" class="width-md height-md" id="filter-nome" name="nome" placeholder="Nome da parte" value="<?php echo htmlspecialchars($nomeFilter); ?>">
            </div>
            <div class="col filter">
                <label for="filter-cpf">CPF</label>
                <input type="text" class="width-md height-md" id="filter-cpf" name="cpf" placeholder="CPF" value="<?php echo htmlspecialchars($cpfFilter); ?>">
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
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
        <div class="row buttons">
            <a href="/frame.php?page=consultapartescreate" class="btn btn-primary">Nova pessoa</a>
        </div>
        <div class="row table header">Lista de Partes</div>
        <?php if (!empty($rows)): ?>
            <table class="table-list">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Processos</th>
                        <th>Instituições</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nome_completo'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($row['cpf'] ?? '—'); ?></td>
                        <td><?php echo (int) ($row['total_processos'] ?? 0); ?></td>
                        <td><?php echo (int) ($row['total_instituicoes'] ?? 0); ?></td>
                        <td>
                            <a href="/frame.php?page=consultapartesedit&id=<?php echo urlencode($row['id'] ?? ''); ?>" class="btn btn-secondary-outline" title="Editar"><i class="fi fi-rr-file-edit"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="row table"><div class="col"><span class="major-info">Nenhuma parte encontrada.</span></div></div>
        <?php endif; ?>
        <div class="row pagination">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> registros</span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams; $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams; $nextParams['page_number'] = min($totalPages, $currentPage + 1);
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>href="?<?php echo http_build_query($prevParams); ?>"<?php endif; ?>>‹</a>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <a class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" href="?<?php echo http_build_query($qp); ?>"><?php echo $p; ?></a>
                    <?php endforeach; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>href="?<?php echo http_build_query($nextParams); ?>"<?php endif; ?>>›</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
