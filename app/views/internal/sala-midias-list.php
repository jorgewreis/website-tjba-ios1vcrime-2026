<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaMidiasService.php';

$connection = Conection::getInstance()->getConnection();
$service = new SalaMidiasService($connection);

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$dataAudiencia = trim((string) ($_GET['data_audiencia'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$list = $service->list([
    'search' => $search,
    'status' => $status,
    'data_audiencia' => $dataAudiencia,
], $itemsPerPage, $offset);
$rows = $list['rows'] ?? [];
$total = (int) ($list['total'] ?? 0);
$totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 1;
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sala de Audiências - Mídias</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Sala de Audiências -</span>
        <span class="specific">Mídias</span>
    </div>
    <h1 class="title-page">Mídias de audiências</h1>
    <p class="description-page">Controle de tarefas de download, sincronização, upload e certidão de upload de mídia.</p>
</div>

<div class="container-full card-page">
    <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
        <h2>Listagem de tarefas</h2>
        <div class="display-flex row row-fit gap-y-sm">
            <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=salamidiasrules">Regras</button>
            <button type="button" class="btn btn-primary" data-nav-href="/frame.php?page=salamidiascreate">Nova tarefa</button>
        </div>
    </div>

    <form method="get" class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
        <input type="hidden" name="page" value="salamidias">
        <div class="col filter">
            <label for="midias-search">Processo, classe ou tipo</label>
            <input type="text" class="width-500 height-md" id="midias-search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col filter">
            <label for="midias-data">Data da audiência</label>
            <input type="date" class="width-300 height-md" id="midias-data" name="data_audiencia" value="<?php echo htmlspecialchars($dataAudiencia, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col filter">
            <label for="midias-status">Status</label>
            <select class="width-300 height-md" id="midias-status" name="status">
                <option value="">Todos</option>
                <option value="pendente"<?php echo $status === 'pendente' ? ' selected' : ''; ?>>Pendente</option>
                <option value="em_andamento"<?php echo $status === 'em_andamento' ? ' selected' : ''; ?>>Em andamento</option>
                <option value="concluida"<?php echo $status === 'concluida' ? ' selected' : ''; ?>>Concluída</option>
                <option value="cancelada"<?php echo $status === 'cancelada' ? ' selected' : ''; ?>>Cancelada</option>
            </select>
        </div>
        <div class="col filter">
            <label for="items-per-page">Itens por página</label>
            <select class="width-200 height-md" id="items-per-page" name="items_per_page">
                <?php foreach ($allowedPerPage as $opt): ?>
                    <option value="<?php echo $opt; ?>"<?php echo $itemsPerPage === $opt ? ' selected' : ''; ?>><?php echo $opt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col filter">
            <button type="submit" class="btn btn-secondary height-md">Aplicar filtros</button>
        </div>
    </form>

    <div class="display-flex col col-full">
        <?php if ($rows === []): ?>
            <div class="padding-x-md padding-y-md border-bottom">
                <span class="minor-info">Nenhuma tarefa de mídia encontrada.</span>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <?php
                $id = trim((string) ($row['id'] ?? ''));
                $statusRow = trim((string) ($row['status'] ?? ''));
                $subtarefas = is_array($row['Subtarefas'] ?? null) ? $row['Subtarefas'] : [];
                ?>
                <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-sm">
                    <div class="display-flex row row-full justify-between align-center gap-y-md">
                        <div class="display-flex col col-fit">
                            <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="minor-info"><?php echo htmlspecialchars((string) ($row['classe_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="minor-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($row['data_audiencia'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($row['tipo_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="display-flex row row-fit gap-y-sm align-center">
                            <span class="status status-primary-light"><?php echo htmlspecialchars(str_replace('_', ' ', $statusRow), ENT_QUOTES, 'UTF-8'); ?></span>
                            <button type="button" class="btn btn-editar" title="Editar tarefa de mídia" aria-label="Editar tarefa de mídia" data-nav-href="/frame.php?page=salamidiasedit&id=<?php echo urlencode($id); ?>">
                                <i class="fi fi-rr-pencil"></i>
                            </button>
                        </div>
                    </div>
                    <div class="display-flex row row-full flex-wrap gap-y-sm">
                        <?php foreach ($subtarefas as $sub): ?>
                            <?php
                            $subId = trim((string) ($sub['id'] ?? ''));
                            $subStatus = trim((string) ($sub['status'] ?? ''));
                            $concluivel = $subStatus !== 'concluida' && $statusRow !== 'cancelada';
                            ?>
                            <div class="display-flex row row-full align-center justify-between" style="border:1px solid var(--line);border-radius:8px;padding:8px 10px;margin-bottom:6px;">
                                <div class="display-flex col col-fit">
                                    <span class="minor-info"><?php echo htmlspecialchars((string) ($sub['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($sub['sistema'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="minor-info">Status: <?php echo htmlspecialchars(str_replace('_', ' ', $subStatus), ENT_QUOTES, 'UTF-8'); ?> | Pontos: <?php echo htmlspecialchars((string) ($sub['pontos_calculados'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div>
                                    <?php if ($concluivel): ?>
                                        <button
                                            type="button"
                                            class="btn btn-secondary-outline height-sm js-midias-concluir-subtarefa"
                                            data-subtarefa-id="<?php echo htmlspecialchars($subId, ENT_QUOTES, 'UTF-8'); ?>"
                                        >Concluir</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="display-flex row row-full justify-between align-center padding-x-md padding-y-md border-top">
        <span class="pagination-info">Total de <?php echo $total; ?> registros.</span>
        <div class="display-flex row row-fit gap-y-sm">
            <?php if ($currentPage > 1): ?>
                <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=salamidias&page_number=<?php echo $currentPage - 1; ?>&items_per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&data_audiencia=<?php echo urlencode($dataAudiencia); ?>">Anterior</button>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <button type="button" class="btn btn-secondary-outline height-sm" data-nav-href="/frame.php?page=salamidias&page_number=<?php echo $currentPage + 1; ?>&items_per_page=<?php echo $itemsPerPage; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&data_audiencia=<?php echo urlencode($dataAudiencia); ?>">Próxima</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="/assets/js/modules/audiencias/midias-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/midias-list.js') ?: time(); ?>"></script>
</body>
</html>
