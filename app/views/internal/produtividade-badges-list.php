<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
$routePage = trim((string) ($_GET['page'] ?? 'produtividadebadges'));

$isGestao = false;
if ($userId !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userId]);
    $perfis = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
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
$tipo = trim((string) ($_GET['tipo'] ?? ''));
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
$success = (string) ($_SESSION['produtividade_badges_flash_success'] ?? '');
unset($_SESSION['produtividade_badges_flash_success']);

if ($isGestao) {
    try {
        $result = $service->listBadges($userId, ['q' => $q, 'ativo' => $ativo, 'tipo' => $tipo], $itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
        $totalDocuments = (int) ($result['total'] ?? 0);
    } catch (Throwable $e) {
        $errors[] = 'Erro ao carregar badges: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Acesso permitido apenas para perfis de gestão.';
}

$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $totalDocuments === 1 ? 'badge' : 'badges';

$baseQuery = [
    'page' => $routePage,
    'q' => $q,
    'ativo' => $ativo,
    'tipo' => $tipo,
    'items_per_page' => $itemsPerPage,
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Produtividade - Badges</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade -</span>
            <span class="specific">Badges</span>
        </div>
        <h1 class="title-page">Badges de produtividade</h1>
        <p class="description-page">Gerencie conquistas por pontuação cumulativa ou ato específico.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Lista de badges</h2>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <?php if ($isGestao): ?>
                <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQuery;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividade">Voltar</button>
                <?php if ($isGestao): ?>
                <button type="button" class="btn btn-primary height-md" data-nav-href="/frame.php?page=produtividadebadgescreate">Novo badge</button>
                <?php endif; ?>
            </div>
        </div>

        <form method="get" class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>">
            <div class="display-flex row row-fit justify-start gap-y-md">
                <div class="col">
                    <label for="q">Buscar</label>
                    <input type="text" id="q" name="q" class="width-400 height-md" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="width-300 height-md">
                        <option value=""<?php echo $tipo === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="pontuacao_cumulativa"<?php echo $tipo === 'pontuacao_cumulativa' ? ' selected' : ''; ?>>Pontuação cumulativa</option>
                        <option value="ato_especifico"<?php echo $tipo === 'ato_especifico' ? ' selected' : ''; ?>>Ato específico</option>
                    </select>
                </div>
                <div class="col">
                    <label for="ativo">Status</label>
                    <select id="ativo" name="ativo" class="width-200 height-md">
                        <option value=""<?php echo $ativo === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="1"<?php echo $ativo === '1' ? ' selected' : ''; ?>>Ativos</option>
                        <option value="0"<?php echo $ativo === '0' ? ' selected' : ''; ?>>Inativos</option>
                    </select>
                </div>
                <div class="col">
                    <label for="items_per_page">Itens por página</label>
                    <select id="items_per_page" name="items_per_page" class="width-200 height-md">
                        <?php foreach ($allowedPerPage as $size): ?>
                        <option value="<?php echo $size; ?>"<?php echo $itemsPerPage === $size ? ' selected' : ''; ?>><?php echo $size; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-end">
                <button type="submit" class="btn btn-primary height-md">Consultar</button>
            </div>
        </form>

        <?php if ($errors === []): ?>
            <?php if ($rows === []): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                <span class="minor-info">Nenhum badge encontrado.</span>
            </div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <div class="display-flex col col-full register border-bottom">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                            <div class="col width-400">
                                <span class="minor-info">Nome</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-300">
                                <span class="minor-info">Tipo</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['tipo'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Threshold</span>
                                <span class="major-info"><?php echo number_format((float) ($row['valor_threshold'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Período</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['periodo'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <span class="status <?php echo ((int) ($row['ativo'] ?? 0)) === 1 ? 'status-success' : 'status-cancelled'; ?> padding-x-lg">
                                <?php echo ((int) ($row['ativo'] ?? 0)) === 1 ? 'Ativo' : 'Inativo'; ?>
                            </span>
                            <button type="button" class="btn btn-editar" title="Editar" aria-label="Editar" data-nav-href="/frame.php?page=produtividadebadgesedit&id=<?php echo urlencode((string) ($row['id'] ?? '')); ?>">
                                <i class="fi fi-rr-pen-square"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-top">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel, ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo (int) $totalPages; ?> páginas)
            </span>
            <?php if ($totalPages > 1): ?>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $params = array_merge($baseQuery, ['page_number' => $p]); ?>
                    <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" <?php if ($p !== $currentPage): ?>data-nav-href="/frame.php?<?php echo htmlspecialchars(http_build_query($params), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $p; ?></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

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
