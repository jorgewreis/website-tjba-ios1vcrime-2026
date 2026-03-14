<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/PessoasService.php';
require_once __DIR__ . '/../../../config/security.php';

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

$filtrou = ($nomeFilter !== '' || $cpfFilter !== '' || $instituicaoIdFilter !== '');

$rows = [];
$totalDocuments = 0;
$totalPages = 0;
$startItem = 0;
$endItem = 0;

if ($filtrou) {
    $filters = ['nome' => $nomeFilter, 'cpf' => $cpfFilter, 'instituicao_id' => $instituicaoIdFilter];
    $offset = ($currentPage - 1) * $itemsPerPage;

    try {
        $service = new PessoasService($connection);
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
if ($nomeFilter !== '') $baseQueryParams['nome'] = $nomeFilter;
if ($cpfFilter !== '') $baseQueryParams['cpf'] = $cpfFilter;
if ($instituicaoIdFilter !== '') $baseQueryParams['instituicao_id'] = $instituicaoIdFilter;

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
        <title>Consulta Partes - Primeira Vara Crime de Ilhéus</title>
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
        <script src="/assets/js/shared/cpf.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas -</span>
            <span class="specific">Consulta Partes</span>
        </div>
        <h1 class="title-page">Consulta Partes</h1>
        <p class="description-page">Pesquisa por pessoa: nome ou CPF. Informe ao menos um critério e clique em Consultar.</p>
    </div>

    <div class="card-page">

        <!-- FILTROS -->
        <form method="get" id="form-filters" style="width: 100%">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
            <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
                <div class="row left row-gap-10">
                    <div class="col">
                        <label for="filter-nome">Nome</label>
                        <input type="text" class="width-lg height-md white" id="filter-nome" name="nome" placeholder="Nome da parte" value="<?php echo htmlspecialchars($nomeFilter); ?>">
                    </div>
                    <div class="col">
                        <label for="filter-cpf">CPF</label>
                        <input type="text" class="width-md height-md white" id="filter-cpf" name="cpf" placeholder="CPF" value="<?php echo htmlspecialchars($cpfFilter); ?>">
                    </div>
                </div>
                <div class="row right">
                    <div class="col">
                        <label for="filter-cpf">-</label>
                        <button type="submit" class="btn btn-primary">Consultar</button>
                    </div>
                </div>
            </div>            
        </form>

        <!-- ROW ACTIONS -->
        <div class="row table buttons right padding-lr-md padding-tb-md row-gap-10">
            <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <!-- SUBTITLE TABLE -->
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Lista de Partes</div>
        
        <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
            <div class="row right">
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
        </div>

        <?php if (!$filtrou): ?>
        <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
            <div class="row table between padding-tb-md border-top">
                <div class="col table left no-data">
                    <span class="major-info">Informe ao menos um critério (nome ou CPF) e clique em Consultar para exibir os resultados.</span>
                </div>
            </div>
        </div>

        <?php elseif (!empty($rows)): ?>
            <div class="col full-line border-bottom">
                <?php foreach ($rows as $row): ?>
                <div class="row table between padding-lr-md padding-tb-md border-bottom">
                    <div class="row left">
                        <div class="col parte-info">
                            <span class="minor-info">Nome</span>
                            <span class="major-info"><?php $cpfCell = $row['cpf']; echo htmlspecialchars($row['nome_completo'] . " (" .  htmlspecialchars($cpfCell !== '' && strlen($cpfCell) === 11 ? Security::formatCPF($cpfCell) : ($cpfCell !== '' ? $cpfCell : '—'))) . ")" ?></span>
                        </div>
                        <div class="col parte-tel">
                            <span class="minor-info">Telefones</span>
                            <!-- TODO: listar telefones -->
                            <span class="major-info">
                                <?php foreach ($row['telefones'] ?? [] as $t): ?>
                                    <?php echo htmlspecialchars($t['tipo'] ?? '') . ': ' . htmlspecialchars($t['    numero'] ?? ''); ?>                                    
                                <?php endforeach; ?>
                            </span>
                        </div>
                        <div class="col parte-instituitoces">
                            <span class="minor-info">Instituições vinculadas</span>
                            <!-- TODO: listar instituições -->
                            <span class="major-info">
                                <?php foreach ($row['instituicoes'] ?? [] as $i): ?>
                                    <?php echo htmlspecialchars($i['nome'] ?? ''); ?>
                                    <?php echo htmlspecialchars($i['sigla'] ?? ''); ?>
                                    <?php echo htmlspecialchars($i['cargo_funcao'] ?? ''); ?>                                    
                                <?php endforeach; ?>
                            </span>
                        </div>
                    </div>
                    <div class="row right">
                        <div class="col part-edit">
                            <button type="button" class="" title="Editar" data-edit-id="<?php echo htmlspecialchars($row['id'] ?? ''); ?>"><i class="fi fi-rr-file-edit"></i></button>
                        </div>
                    </div>
                </div>
            </div>       
            <?php endforeach; ?>
            <?php else: ?>
                <div class="col subtask subtask-collapsed padding-lr-md full-line">
                    <div class="row table between padding-tb-md border-top">
                        <div class="col table left no-data">
                            <span class="major-info">Nenhuma parte encontrada para os critérios informados.</span>
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
        var filterNome = document.getElementById('filter-nome');
        if (filterNome && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            filterNome.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }
        if (document.getElementById('filter-cpf') && typeof App !== 'undefined' && App.CPF && App.CPF.init) {
            App.CPF.init('#filter-cpf');
        }
        document.getElementById('novo-cadastro-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=consultapartescreate';
        });
        document.querySelectorAll('button[data-edit-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-edit-id');
                if (id) window.location.href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(id);
            });
        });
    })();
    </script>
</body>
</html>
