<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';

$statuses = ['todos_ativos' => 'Todos exceto finalizadas', 'a_fazer' => 'A fazer', 'em_andamento' => 'Em andamento', 'com_pendencias' => 'Com pendências', 'finalizada' => 'Finalizada'];
$types = ['assinatura' => 'Assinatura', 'expedicao' => 'Expedição', 'envio' => 'Envio', 'juntada' => 'Juntada'];
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'todos_ativos';
$status = $status === '' || isset($statuses[$status]) ? $status : 'todos_ativos';
$type = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$type = isset($types[$type]) ? $type : '';
$server = is_string($_GET['servidor'] ?? null) ? trim($_GET['servidor']) : '';
$pageNumber = max(1, min(1000000, (int) ($_GET['pagina'] ?? 1)));
$perPage = 15;
$error = false;
$users = [];
$result = ['rows' => [], 'total' => 0, 'offset' => 0];
try {
    $repository = new CartorioTarefasRepository($connection);
    $users = $repository->listarUsuariosInternos();
    if (!in_array($server, array_column($users, 'id'), true)) $server = '';
    $result = $repository->listar(['status' => $status, 'tipo' => $type, 'servidor' => $server], $perPage, ($pageNumber - 1) * $perPage);
} catch (Throwable $e) {
    error_log('Falha ao carregar tarefas do Cartório: ' . $e->getMessage());
    $error = true;
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$total = $result['total'];
$pageNumber = (int) ($result['offset'] / $perPage) + 1;
$pages = max(1, (int) ceil($total / $perPage));
$url = static fn(int $page): string => '/frame.php?' . http_build_query(['page' => 'tarefas', 'status' => $status, 'servidor' => $server, 'tipo' => $type, 'pagina' => $page]);
$selectedFilters = [];
if ($status !== '') $selectedFilters[] = 'Status [' . $statuses[$status] . ']';
if ($server !== '') {
    foreach ($users as $user) if ($user['id'] === $server) $selectedFilters[] = 'Servidor [' . $user['name'] . ']';
}
if ($type !== '') $selectedFilters[] = 'Tarefa [' . $types[$type] . ']';
$assetRoot = dirname(__DIR__, 3) . '/public/assets/css/shared/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarefas - Primeira Vara Crime de Ilhéus</title>
    <?php foreach (['reset', 'color', 'layout', 'page', 'form', 'table', 'toast'] as $css): ?>
    <link rel="stylesheet" href="/assets/css/shared/<?= $css ?>.css?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . $css . '.css') ?: '1')) ?>">
    <?php endforeach; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>
<div class="header-page"><div class="location"><span class="all">Interno - Cartório - Tarefas -</span><span class="specific">Lista</span></div></div>
<div class="container-full card-page">
    <div class="card-title"><div class="display-flex col col-full justify-start"><h1 class="title-page" style="color: var(--color-primary-active);">Tarefas</h1></div></div>
    <form action="/frame.php" method="get" id="tarefas-filtros" class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
        <input type="hidden" name="page" value="tarefas">
        <span class="text" style="font-weight: 600;">Filtros</span>
        <div class="display-flex row row-fit justify-end gap-y-md align-center">
            <select class="width-400 height-md" name="status" aria-label="Filtrar por status">
                <option value="">Todos os status</option>
                <?php foreach ($statuses as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
            </select>
            <select class="width-400 height-md" name="servidor" aria-label="Filtrar por servidor">
                <option value="">Todos os servidores</option>
                <?php foreach ($users as $user): ?><option value="<?= $escape($user['id']) ?>" <?= $server === $user['id'] ? 'selected' : '' ?>><?= $escape($user['name']) ?></option><?php endforeach; ?>
            </select>
            <select class="width-600 height-md" name="tipo" aria-label="Filtrar por tarefa">
                <option value="">Todas as tarefas</option>
                <?php foreach ($types as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-primary">Filtrar</button></noscript>
        </div>
    </form>
    <?php if ($selectedFilters): ?><div class="display-flex row row-full padding-x-lg padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);"><span class="text" style="color: var(--color-primary-active); font-size: 12px;">Lista com os seguintes filtros: <?= $escape(implode(' + ', $selectedFilters)) ?></span></div><?php endif; ?>
    <div class="card-title" style="padding-top: 40px;"><h2>Lista de Tarefas</h2></div>
    <div class="display-flex col col-full register">
        <div class="display-flex row row-full padding-x-lg padding-y-md border-bottom" role="status" style="background-color: var(--color-primary-bg-light);">
            <?= $error ? 'Não foi possível carregar as tarefas. Tente novamente.' : ($total === 0 ? 'Nenhuma tarefa encontrada.' : $total . ($total === 1 ? ' registro encontrado.' : ' registros encontrados.')) ?>
        </div>
        <?php foreach ($result['rows'] as $row): ?>
        <div class="register-line"><div class="display-flex col col-full padding-x-lg padding-y-md"><strong><?= $escape((string) $row['titulo']) ?></strong><span><?= $escape($types[$row['tipo']] ?? (string) $row['tipo']) ?> · <?= $escape($statuses[$row['status']] ?? (string) $row['status']) ?></span></div></div>
        <?php endforeach; ?>
    </div>
    <?php if (!$error && $total > 0): ?><div class="display-flex row row-full justify-between padding-x-md padding-y-md"><span class="pagination-info">Mostrando <?= $result['offset'] + 1 ?>–<?= min($result['offset'] + $perPage, $total) ?> de <?= $total ?> tarefas</span><div class="pagination-controls"><?php if ($pageNumber > 1): ?><a class="pagination-symbol" href="<?= $escape($url($pageNumber - 1)) ?>" aria-label="Página anterior">‹</a><?php endif; ?><span class="pagination-number active" aria-current="page"><?= $pageNumber ?></span><?php if ($pageNumber < $pages): ?><a class="pagination-symbol" href="<?= $escape($url($pageNumber + 1)) ?>" aria-label="Próxima página">›</a><?php endif; ?></div></div><?php endif; ?>
</div>
<script>document.querySelectorAll('#tarefas-filtros select').forEach(function (select) { select.addEventListener('change', function () { document.getElementById('tarefas-filtros').requestSubmit(); }); });</script>
</body>
</html>
