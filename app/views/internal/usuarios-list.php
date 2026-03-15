<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/UsuariosService.php';
require_once __DIR__ . '/../../../config/security.php';

$connection = Conection::getInstance()->getConnection();

/** Barra "Row action" (Novo cadastro) apenas para admin, magistrada e diretora */
$perfisComAcessoRowAction = ['ADMIN', 'MAGISTRADA', 'DIRETORA'];
$userPerfis = [];
$userId = Security::getUserId();
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$showRowAction = count(array_intersect($userPerfis, $perfisComAcessoRowAction)) > 0;

$routePage = trim((string) ($_GET['page'] ?? 'usuarioslist'));
$tipoFilter = trim((string) ($_GET['tipo'] ?? ''));
$perfilFilter = trim((string) ($_GET['perfil'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;

$filters = [];
if ($tipoFilter !== '') {
    $filters['tipo'] = $tipoFilter;
}
if ($perfilFilter !== '') {
    $filters['perfil'] = $perfilFilter;
}

$rows = [];
$totalDocuments = 0;
$totalPages = 0;
$startItem = 0;
$endItem = 0;

$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new UsuariosService($connection);
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
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$baseQueryParams = ['page' => $routePage, 'items_per_page' => $itemsPerPage];
if ($tipoFilter !== '') {
    $baseQueryParams['tipo'] = $tipoFilter;
}
if ($perfilFilter !== '') {
    $baseQueryParams['perfil'] = $perfilFilter;
}

$paginationButtons = [];
if ($totalPages > 0) {
    $paginationButtons = $totalPages <= 5 ? range(1, $totalPages) : array_slice(range(1, $totalPages), max(0, $currentPage - 3), 5);
    if ($totalPages > 5 && $currentPage <= 3) {
        $paginationButtons = array_merge(range(1, 4), [$totalPages]);
    }
}

$documentsLabel = $totalDocuments === 1 ? 'usuário' : 'usuários';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusLabels = [
    'ativo'    => 'Ativo',
    'pendente' => 'Pendente',
    'bloqueado' => 'Bloqueado',
    'inativo'  => 'Inativo',
];
$statusClasses = [
    'ativo'    => 'status-success',
    'pendente' => 'status-pending',
    'bloqueado' => 'status-cancelled',
    'inativo'  => 'status-cancelled',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Usuários - Primeira Vara Crime de Ilhéus</title>
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
            <span class="all">Interno -</span>
            <span class="specific">Usuários</span>
        </div>
        <h1 class="title-page">Usuários</h1>
        <p class="description-page">Listagem de servidores internos (estagiários, servidores, diretora, admin, magistrada) e externos (estudantes). Use os filtros e clique em Consultar.</p>
    </div>

    <div class="card-page">
        <?php
        $flashSuccess = $_SESSION['usuarios_create_success'] ?? '';
        if ($flashSuccess !== '') {
            unset($_SESSION['usuarios_create_success']);
        }
        ?>
        <?php if ($flashSuccess !== ''): ?>
        <div class="row filter padding-lr-lg padding-tb-md">
            <div class="col">
                <p class="status status-current"><?php echo htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- FILTROS -->
        <form method="get" id="form-filters" style="width: 100%">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">
            <div class="row table between padding-tb-lg padding-lr-md border-bottom full-line">
                <div class="row left row-gap-10 fit">
                    <div class="col">
                        <label for="filter-tipo">Tipo</label>
                        <select class="width-md height-md white" id="filter-tipo" name="tipo">
                            <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Todos</option>
                            <option value="interno"<?php echo $tipoFilter === 'interno' ? ' selected' : ''; ?>>Interno</option>
                            <option value="externo"<?php echo $tipoFilter === 'externo' ? ' selected' : ''; ?>>Externo</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="filter-perfil">Perfil</label>
                        <select class="width-md height-md white" id="filter-perfil" name="perfil">
                            <option value=""<?php echo $perfilFilter === '' ? ' selected' : ''; ?>>Todos</option>
                            <option value="estagiario"<?php echo $perfilFilter === 'estagiario' ? ' selected' : ''; ?>>Estagiário</option>
                            <option value="servidor"<?php echo $perfilFilter === 'servidor' ? ' selected' : ''; ?>>Servidor</option>
                            <option value="diretora"<?php echo $perfilFilter === 'diretora' ? ' selected' : ''; ?>>Diretora</option>
                            <option value="magistrada"<?php echo $perfilFilter === 'magistrada' ? ' selected' : ''; ?>>Magistrada</option>
                            <option value="admin"<?php echo $perfilFilter === 'admin' ? ' selected' : ''; ?>>Admin</option>
                            <option value="estudante"<?php echo $perfilFilter === 'estudante' ? ' selected' : ''; ?>>Estudante</option>
                        </select>
                    </div>
                    <div class="col">
                        <label for="items-per-page">Itens por página</label>
                        <select class="width-sm height-md white" id="items-per-page" name="items_per_page">
                            <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                            <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                            <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                            <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <div class="row right fit">
                    <div class="col">
                        <label for="filter-perfil">-</label>
                        <button type="submit" class="btn btn-primary">Consultar</button>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($showRowAction): ?>
        <!-- ROW ACTIONS (apenas admin, magistrada, diretora) -->
        <div class="row table buttons right padding-lr-md padding-tb-md row-gap-10">
            <button type="button" class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>
        <?php endif; ?>

        <!-- SUBTITLE TABLE -->
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Lista de usuários</div>

        <?php if (!empty($rows)): ?>
            <div class="col full-line border-bottom">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status = $row['status'] ?? 'ativo';
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $statusClass = $statusClasses[$status] ?? 'status-current';
                    $tipoLabel = ($row['tipo'] ?? 'interno') === 'externo' ? 'Externo' : 'Interno';
                    $setores = trim($row['setores'] ?? '');
                    // Exibir "Sala de Audiências" para o usuário; internamente o setor permanece AUDIENCIA
                    $setoresDisplay = str_replace('AUDIENCIA', 'Sala de Audiências', $setores);
                    $cargo = trim($row['cargo'] ?? '');
                    $editId = $row['id_usuario'] ?? $row['id'] ?? '';
                    ?>
                    <div class="row table between padding-lr-sm padding-tb-sm border-bottom">
                        <div class="row table left fit">
                            <div class="col parte-nome text-ellipsis">
                                <span class="minor-info">Nome</span>
                                <span class="major-info"><?php echo htmlspecialchars($row['nome_completo'] ?? '—'); ?></span>
                            </div>
                            <div class="col parte-tipo">
                                <span class="minor-info">Tipo</span>
                                <span class="major-info"><?php echo htmlspecialchars($tipoLabel); ?></span>
                            </div>
                            <div class="col parte-setor">
                                <span class="minor-info">Setor</span>
                                <span class="major-info"><?php echo $setoresDisplay !== '' ? htmlspecialchars($setoresDisplay) : '—'; ?></span>
                            </div>
                            <div class="col parte-cargo">
                                <span class="minor-info">Cargo</span>
                                <span class="major-info"><?php echo $cargo !== '' ? htmlspecialchars($cargo) : '—'; ?></span>
                            </div>
                        </div>
                        <div class="row table right fit">
                            <div class="col part-edit">
                                <button type="button" class="" title="Editar" data-edit-id="<?php echo htmlspecialchars($editId); ?>"><i class="fi fi-rr-file-edit"></i></button>
                            </div>
                            <div class="col">
                                <span class="major-info"><span class="width-sm status <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="row table between padding-tb-md border-top">
                    <div class="col table left no-data">
                        <span class="major-info">Nenhum usuário encontrado para os critérios informados.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="row table between padding-tb-lg padding-lr-md">
            <span class="left pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                <?php if ($totalPages > 0): ?>(<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="right pagination-controls">
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
        var form = document.getElementById('form-filters');
        var itemsPerPage = document.getElementById('items-per-page');
        var filterTipo = document.getElementById('filter-tipo');
        var filterPerfil = document.getElementById('filter-perfil');

        if (itemsPerPage && form) {
            itemsPerPage.addEventListener('change', function () {
                form.submit();
            });
        }
        if (filterTipo && form) {
            filterTipo.addEventListener('change', function () {
                form.submit();
            });
        }
        if (filterPerfil && form) {
            filterPerfil.addEventListener('change', function () {
                form.submit();
            });
        }

        var novoBtn = document.getElementById('novo-cadastro-btn');
        if (novoBtn) {
            novoBtn.addEventListener('click', function () {
                window.location.href = '/frame.php?page=usuarioscreate';
            });
        }

        document.querySelectorAll('button[data-edit-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-edit-id');
                if (id) {
                    window.location.href = '/frame.php?page=usuariosedit&id=' + encodeURIComponent(id);
                }
            });
        });
    })();
    </script>
</body>
</html>
