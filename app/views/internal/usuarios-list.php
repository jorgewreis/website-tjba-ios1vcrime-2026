<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/UsuariosService.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';

$connection = Conection::getInstance()->getConnection();

/** Barra "Row action" (Novo cadastro) apenas para admin, magistrada e diretora */
$perfisComAcessoRowAction = ['ADMIN', 'MAGISTRADA', 'DIRETORA'];
$userPerfis = [];
$userId = Security::getUserId();
if ($userId !== null && trim((string) $userId) !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userId));
}
$showRowAction = count(array_intersect($userPerfis, $perfisComAcessoRowAction)) > 0;

$routePage = trim((string) ($_GET['page'] ?? 'usuarioslist'));
$tipoFilter = trim((string) ($_GET['tipo'] ?? 'interno'));
$perfilFilter = trim((string) ($_GET['perfil'] ?? ''));
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
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
$totalPages = 1;
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

$totalPages = max(1, (int) ceil($totalDocuments / $itemsPerPage));
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

$documentsLabel = $totalDocuments === 1 ? 'usuário' : 'usuários';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$tipoCounts = $service->getTipoCounts($filters);
$perfilCounts = $service->getPerfilCounts($filters);

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

$formatUltimoAcesso = static function (?string $raw): string {
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '—';
    }

    return date('d/m/Y H:i', $ts);
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Usuários - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
    </div>

    <div class="container-full card-page">
        <?php
        $flashSuccess = $_SESSION['usuarios_create_success'] ?? '';
        $flashEdit = $_SESSION['usuarios_edit_message'] ?? '';
        if ($flashSuccess !== '') {
            unset($_SESSION['usuarios_create_success']);
        }
        if ($flashEdit !== '') {
            unset($_SESSION['usuarios_edit_message']);
        }
        $flashMessage = $flashSuccess !== '' ? $flashSuccess : $flashEdit;
        ?>
        <?php if ($flashMessage !== ''): ?>
        
        <?php endif; ?>

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Usuários</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Listagem de servidores internos (estagiários, servidores, diretora, admin, magistrada) e externos (estudantes). Os filtros são aplicados automaticamente.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">

                <?php if ($showRowAction): ?>
                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                    <?php
                    $listExportPage = $routePage;
                    $listExportQuery = $baseQueryParams;
                    include __DIR__ . '/_list_export_buttons.php';
                    ?>
                </div>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" id="form-filters" class="display-flex col col-full" style="width:100%" data-auto-submit-filters>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage); ?>">

            <div class="display-          </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center">
                    <?php
                    $listExportPage = $routePage;
                    $listExportQuery = $baseQueryParams;
                    include __DIR__ . '/_list_export_buttons.php';
                    ?>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                    <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                    <select class="width-300 height-md" id="filter-tipo" name="tipo">
                        <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="interno"<?php echo $tipoFilter === 'interno' ? ' selected' : ''; ?>>
                            Interno (<?php echo (int) ($tipoCounts['interno'] ?? 0); ?>)
                        </option>
                        <option value="externo"<?php echo $tipoFilter === 'externo' ? ' selected' : ''; ?>>
                            Externo (<?php echo (int) ($tipoCounts['externo'] ?? 0); ?>)
                        </option>
                    </select>
                    <select class="width-500 height-md" id="filter-perfil" name="perfil">
                        <option value=""<?php echo $perfilFilter === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="estagiario"<?php echo $perfilFilter === 'estagiario' ? ' selected' : ''; ?>>
                            Estagiário (<?php echo (int) ($perfilCounts['estagiario'] ?? 0); ?>)
                        </option>
                        <option value="servidor"<?php echo $perfilFilter === 'servidor' ? ' selected' : ''; ?>>
                            Servidor (<?php echo (int) ($perfilCounts['servidor'] ?? 0); ?>)
                        </option>
                        <option value="diretora"<?php echo $perfilFilter === 'diretora' ? ' selected' : ''; ?>>
                            Diretora (<?php echo (int) ($perfilCounts['diretora'] ?? 0); ?>)
                        </option>
                        <option value="magistrada"<?php echo $perfilFilter === 'magistrada' ? ' selected' : ''; ?>>
                            Magistrada (<?php echo (int) ($perfilCounts['magistrada'] ?? 0); ?>)
                        </option>
                        <option value="admin"<?php echo $perfilFilter === 'admin' ? ' selected' : ''; ?>>
                            Admin (<?php echo (int) ($perfilCounts['admin'] ?? 0); ?>)
                        </option>
                        <option value="estudante"<?php echo $perfilFilter === 'estudante' ? ' selected' : ''; ?>>
                            Estudante (<?php echo (int) ($perfilCounts['estudante'] ?? 0); ?>)
                        </option>
                    </select>
                </div>
            </div>


        </form>

        <?php
        $tipoLabelUi = ['interno' => 'Interno', 'externo' => 'Externo'];
        $perfilLabelUi = [
            'estagiario' => 'Estagiário',
            'servidor' => 'Servidor',
            'diretora' => 'Diretora',
            'magistrada' => 'Magistrada',
            'admin' => 'Admin',
            'estudante' => 'Estudante',
        ];
        ?>
        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <?php if ($tipoFilter !== '' || $perfilFilter !== ''): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm filter-summary wrap gap-y-sm">
                <?php if ($tipoFilter !== ''): ?>
                <span class="filter-chip">Tipo: <?php echo htmlspecialchars($tipoLabelUi[$tipoFilter] ?? $tipoFilter); ?></span>
                <?php endif; ?>
                <?php if ($perfilFilter !== ''): ?>
                <span class="filter-chip">Perfil: <?php echo htmlspecialchars($perfilLabelUi[$perfilFilter] ?? $perfilFilter); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>

        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row): ?>
                    <?php
                    $status = $row['status'] ?? 'ativo';
                    $statusLabel = $statusLabels[$status] ?? $status;
                    $statusClass = $statusClasses[$status] ?? 'status-current';
                    $tipoLabel = ($row['tipo'] ?? 'interno') === 'externo' ? 'Externo' : 'Interno';
                    $setores = trim($row['setores'] ?? '');
                    $setoresDisplay = str_replace('AUDIENCIA', 'Sala de Audiências', $setores);
                    $cargo = trim($row['cargo'] ?? '');
                    $editId = $row['id_usuario'] ?? $row['id'] ?? '';
                    ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm wrap">
                            <div class="col width-700 parte-nome text-ellipsis">
                                <span class="minor-info">Nome</span>
                                <span class="major-info"><?php echo htmlspecialchars($row['nome_completo'] ?? '—'); ?></span>
                            </div>
                            <div class="col width-300 parte-tipo">
                                <span class="minor-info">Tipo</span>
                                <span class="major-info"><?php echo htmlspecialchars($tipoLabel); ?></span>
                            </div>
                            <div class="col width-600 parte-setor">
                                <span class="minor-info">Setor</span>
                                <span class="major-info"><?php echo $setoresDisplay !== '' ? htmlspecialchars($setoresDisplay) : '—'; ?></span>
                            </div>
                            <div class="col width-500 parte-cargo">
                                <span class="minor-info">Cargo</span>
                                <span class="major-info"><?php echo $cargo !== '' ? htmlspecialchars($cargo) : '—'; ?></span>
                            </div>
                            <div class="col width-280 parte-ultimo-acesso">
                                <span class="minor-info">Último acesso</span>
                                <span class="major-info"><?php echo htmlspecialchars($formatUltimoAcesso(isset($row['ultimo_acesso']) ? trim((string) $row['ultimo_acesso']) : null), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <div class="col part-edit">
                                <button type="button" class="btn btn-editar" title="Editar" aria-label="Editar" data-edit-id="<?php echo htmlspecialchars($editId); ?>"><i class="fi fi-rr-pen-square"></i></button>
                            </div>
                            <div class="col">
                                <span class="width-300 status padding-x-lg <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                            </div>
                    </div>
                </div>
            </div>
                <?php endforeach; ?>
        <?php else: ?>
            <div class="display-flex col col-full register border-bottom">
                <div class="display-flex row row-full padding-x-sm padding-y-sm">
                    <span class="major-info">Nenhum usuário encontrado para os critérios informados.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel); ?>
                <?php if ($totalPages > 0): ?>(<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)<?php endif; ?>
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = max(1, $currentPage - 1);
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = min($totalPages, $currentPage + 1);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($prevParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                        <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" <?php if ($p !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($qp), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $p; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $p): $qp = $baseQueryParams; $qp['page_number'] = $p; ?>
                            <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" <?php if ($p !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($qp), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $p; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($nextParams), ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
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
