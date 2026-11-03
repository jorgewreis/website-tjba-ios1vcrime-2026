<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';

$statuses = ['todos_ativos' => 'Todos exceto finalizadas', 'a_fazer' => 'A fazer', 'em_andamento' => 'Em andamento', 'com_pendencias' => 'Com pendências', 'finalizada' => 'Finalizada'];
$types = CartorioTarefasRepository::TIPOS;
$registrationSuccess = !empty($_SESSION['cartorio_tarefa_cadastrada']);
unset($_SESSION['cartorio_tarefa_cadastrada']);
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'todos_ativos';
$status = $status === '' || isset($statuses[$status]) ? $status : 'todos_ativos';
$type = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$type = isset($types[$type]) ? $type : '';
$server = is_string($_GET['servidor'] ?? null) ? trim($_GET['servidor']) : '';
$ordem = ($_GET['ordem'] ?? '') === 'asc' ? 'asc' : 'desc';
$pageNumber = max(1, min(1000000, (int) ($_GET['pagina'] ?? 1)));
$perPage = 15;
$error = false;
$users = [];
$result = ['rows' => [], 'total' => 0, 'offset' => 0];
try {
    $repository = new CartorioTarefasRepository($connection);
    $users = $repository->listarUsuariosInternos();
    if (!in_array($server, array_column($users, 'id'), true)) $server = '';
    $result = $repository->listar(['status' => $status, 'tipo' => $type, 'servidor' => $server], $perPage, ($pageNumber - 1) * $perPage, $ordem);
} catch (Throwable $e) {
    error_log('Falha ao carregar tarefas do Cartório: ' . $e->getMessage());
    $error = true;
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$total = $result['total'];
$pageNumber = (int) ($result['offset'] / $perPage) + 1;
$pages = max(1, (int) ceil($total / $perPage));
$url = static fn(int $page, string $sort): string => '/frame.php?' . http_build_query(['page' => 'tarefas', 'status' => $status, 'servidor' => $server, 'tipo' => $type, 'pagina' => $page, 'ordem' => $sort]);
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
    <style>
        .card-line-info { width: 100%; display: flex; flex-direction: row; flex-wrap: nowrap; justify-content: flex-start; align-items: center; box-sizing: border-box; padding: 5px 15px; background-color: #47196f; color: #ffffffbb; font-size: 12px; }
        .register-line { display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; padding: 0; height: fit-content;}
        .register-line-header { width: 100%; display: flex; flex-direction: row; flex-wrap: nowrap; justify-content: flex-start; align-items: center; box-sizing: border-box; padding: 10px 15px; background-color: #20202040; color: #202020; gap: 10px; font-size: 12px;}
        .register-line-content { width: 100%; display: flex; flex-direction: row; flex-wrap: nowrap; justify-content: flex-start; align-items: center; box-sizing: border-box; padding: 10px 15px; background-color: #20202010; color: #202020; gap: 10px; font-size: 12px;}
        .register-col { display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; box-sizing: border-box; }
        .col-processo { width: 200px; }
        .col-header-icons { width: 50px; display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: center; align-items: center; box-sizing: border-box; gap: 2px; border-right: solid 1px #20202040; cursor: pointer; }
        .col-content-icon { width: 50px; display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: center; align-items: center; box-sizing: border-box; border-right: solid 1px #20202040; cursor: pointer; }
        .col-header-icons img { width: 10px; height: 10px; }
        .col-content-icon img { width: 20px; height: 20px; opacity: 0.3; }
        .col-content-icon:hover img { opacity: 1; }
        .pagination-symbol { display: flex; align-items: center; justify-content: center; padding: 0; }
        .pagination-symbol img { width: 14px; height: 14px; }
    </style>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body class="page-internal">

<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">FokusLaw - Tarefas -</span>
        <span class="specific">Lista</span>
    </div>
    <div class="card-title">
        <div class="display-flex col col-full justify-start">
            <h1 class="title-page" style="color: var(--color-primary-active);">Tarefas</h1>
        </div>
    </div>
</div>

<div class="container-full card-page">
    <?php if ($registrationSuccess): ?><div class="card-line-info" role="status">Tarefa cadastrada com sucesso.</div><?php endif; ?>
    <div class="display-flex row row-full justify-end align-center gap-y-md padding-x-lg padding-y-md border-bottom">
        <button type="button" class="btn btn-secondary">Regras de automação</button>
        <button type="button" class="btn btn-primary" onclick="location.href='/frame.php?page=tarefascreate'">Cadastrar tarefa</button>
    </div>
    <form action="/frame.php" method="get" id="tarefas-filtros" class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
        <input type="hidden" name="page" value="tarefas">
        <input type="hidden" name="ordem" value="<?= $escape($ordem) ?>">
        <div class="display-flex row row-fit justify-start align-center padding-x-sm"><span class="text text-ellipsis" style="font-weight: 600; color: var(--color-gray-900); font-size: 13px;">Filtros</span></div>
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

    <?php if ($selectedFilters): ?>
        <div class="card-line-info">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis">Lista com os seguintes filtros: <?= $escape(implode(' + ', $selectedFilters)) ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card-subtitle">
        <div class="display-flex row row-full justify-start">
            <h2>Lista de Tarefas</h2>
        </div>
    </div>
    
    <div class="display-flex col col-full register">
        <div class="card-line-info" role="status">
            <div class="display-flex col col-fit align-center justify-start padding-x-sm">
                <span class="text text-ellipsis">
                    <?= $error ? 'Não foi possível carregar as tarefas. Tente novamente.' : ($total === 0 ? 'Nenhuma tarefa encontrada.' : $total . ($total === 1 ? ' registro encontrado.' : ' registros encontrados.')) ?>
                </span>
            </div>
        </div>
        <div class="register-line">
            <div class="register-line-header">
                <div class="register-col col-processo">Processo</div>
                <a class="col-header-icons" href="<?= $escape($url(1, $ordem === 'asc' ? 'desc' : 'asc')) ?>" aria-label="Ordenar processos em ordem <?= $ordem === 'asc' ? 'decrescente' : 'crescente' ?>" title="Ordenar processos em ordem <?= $ordem === 'asc' ? 'decrescente' : 'crescente' ?>">
                    <img src="/assets/icons/Arrow-Button-<?= $ordem === 'asc' ? 'Up' : 'Down' ?>-2--Streamline-Ultimate.png" alt="">
                </a>
            </div>
        </div>
        <div class="register-line">            
            <?php foreach ($result['rows'] as $row): ?>
            <div class="register-line-content">
                <div class="register-col col-processo"><?= $escape(trim((string) ($row['numero_processo'] ?? '')) ?: '—') ?></div>
                <div class="col-content-icon" aria-hidden="true">
                    <img src="/assets/icons/Folder-File--Streamline-Ultimate.png" alt="">
                </div>
                <div class="register-col">
                    <strong><?= $escape((string) $row['titulo']) ?></strong>
                    <span><?= $escape($types[$row['tipo']] ?? (string) $row['tipo']) ?> · <?= $escape($statuses[$row['status']] ?? (string) $row['status']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if (!$error): ?>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">Mostrando <?= $total > 0 ? $result['offset'] + 1 : 0 ?>–<?= min($result['offset'] + $perPage, $total) ?> de <?= $total ?> tarefas</span>
            <div class="pagination-controls"><?php if ($pageNumber > 1): ?>
            <a class="pagination-symbol" href="<?= $escape($url($pageNumber - 1, $ordem)) ?>" aria-label="Página anterior"><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt=""></a>
            <?php else: ?>
            <span class="pagination-symbol disabled" aria-disabled="true" aria-label="Página anterior"><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt=""></span>
            <?php endif; ?>
            <span class="pagination-number active" aria-current="page"><?= $pageNumber ?></span>
            <?php if ($pageNumber < $pages): ?>
                <a class="pagination-symbol" href="<?= $escape($url($pageNumber + 1, $ordem)) ?>" aria-label="Próxima página"><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt=""></a>
                <?php else: ?>
                <span class="pagination-symbol disabled" aria-disabled="true" aria-label="Próxima página"><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt=""></span>
                <?php endif; ?>
            </div>
        </div><?php endif; ?>
</div>

<script>document.querySelectorAll('#tarefas-filtros select').forEach(function (select) { select.addEventListener('change', function () { document.getElementById('tarefas-filtros').requestSubmit(); }); });</script>
</body>
</html>
