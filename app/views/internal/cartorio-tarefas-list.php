<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';

$canManageAutomationRules = ProfileNormalizer::isElevated(ProfileNormalizer::normalize($userPerfils ?? []));
$canAccessCartorioTasks = AccessEvaluator::canAccessPage('tarefascreate', $userPerfils ?? []);
$statusOptions = CartorioTarefasRepository::STATUS;
$statuses = ['todos_ativos' => 'Todos exceto finalizadas'] + $statusOptions;
$types = CartorioTarefasRepository::TIPOS;
$registrationSuccess = !empty($_SESSION['cartorio_tarefa_cadastrada']);
unset($_SESSION['cartorio_tarefa_cadastrada']);
$statusSuccess = !empty($_SESSION['cartorio_tarefa_status_atualizado']);
unset($_SESSION['cartorio_tarefa_status_atualizado']);
$allocationSuccess = !empty($_SESSION['cartorio_tarefa_alocacao_atualizada']);
unset($_SESSION['cartorio_tarefa_alocacao_atualizada']);
$statusError = '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'todos_ativos';
$status = $status === '' || isset($statuses[$status]) ? $status : 'todos_ativos';
$type = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
$type = isset($types[$type]) ? $type : '';
$server = is_string($_GET['servidor'] ?? null) ? trim($_GET['servidor']) : '';
$deadline = is_string($_GET['prazo'] ?? null) && in_array($_GET['prazo'], ['proximo', 'vencido'], true) ? $_GET['prazo'] : '';
$pageNumber = max(1, min(1000000, (int) ($_GET['pagina'] ?? 1)));
$perPage = 15;
$error = false;
$users = [];
$result = ['rows' => [], 'total' => 0, 'offset' => 0];
try {
    $repository = new CartorioTarefasRepository($connection);
    $users = $repository->listarUsuariosInternos();
    if (!in_array($server, array_column($users, 'id'), true)) $server = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
        $taskId = is_string($_POST['tarefa_id'] ?? null) ? trim($_POST['tarefa_id']) : '';
        $newStatus = is_string($_POST['novo_status'] ?? null) ? $_POST['novo_status'] : '';
        $action = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : 'status';
        if (!Security::verifyCSRFToken($csrf)) {
            $statusError = 'Não foi possível validar a sessão. Atualize a página e tente novamente.';
        } else {
            try {
                if ($action === 'alocar') {
                    if (!$canManageAutomationRules) throw new InvalidArgumentException('Apenas a chefia pode alterar a alocação.');
                    $usuario = is_string($_POST['usuario_id'] ?? null) ? trim($_POST['usuario_id']) : '';
                    require_once __DIR__ . '/../../services/CartorioTarefasAutomacaoService.php';
                    (new CartorioTarefasAutomacaoService($connection))->atribuirManual($taskId, $usuario);
                    $_SESSION['cartorio_tarefa_alocacao_atualizada'] = true;
                } elseif ($action === 'status') {
                    $repository->alterarStatus($taskId, $newStatus, ($canAccessCartorioTasks || $canManageAutomationRules) ? '' : $userId);
                    $_SESSION['cartorio_tarefa_status_atualizado'] = true;
                } else {
                    throw new InvalidArgumentException('Ação inválida.');
                }
                header('Location: /frame.php?' . http_build_query(['page' => 'tarefas', 'status' => $status, 'servidor' => $server, 'tipo' => $type, 'prazo' => $deadline, 'pagina' => $pageNumber]), true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $statusError = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Falha ao atualizar status da tarefa: ' . $e->getMessage());
                $statusError = 'Não foi possível atualizar o status. Tente novamente.';
            }
        }
    }
    $result = $repository->listar(['status' => $status, 'tipo' => $type, 'servidor' => $server, 'prazo' => $deadline, 'prazo_usuario' => $canManageAutomationRules ? '' : $userId, 'usuario_proprio' => ($canAccessCartorioTasks || $canManageAutomationRules) ? '' : $userId], $perPage, ($pageNumber - 1) * $perPage, $userId);
} catch (Throwable $e) {
    error_log('Falha ao carregar tarefas do Cartório: ' . $e->getMessage());
    $error = true;
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$total = $result['total'];
$pageNumber = (int) ($result['offset'] / $perPage) + 1;
$pages = max(1, (int) ceil($total / $perPage));
$url = static fn(int $page): string => '/frame.php?' . http_build_query(['page' => 'tarefas', 'status' => $status, 'servidor' => $server, 'tipo' => $type, 'prazo' => $deadline, 'pagina' => $page]);
$selectedFilters = [];
if ($status !== '') $selectedFilters[] = 'Status [' . $statuses[$status] . ']';
if ($server !== '') {
    foreach ($users as $user) if ($user['id'] === $server) $selectedFilters[] = 'Servidor [' . $user['name'] . ']';
}
if ($type !== '') $selectedFilters[] = 'Tarefa [' . $types[$type] . ']';
if ($deadline !== '') $selectedFilters[] = 'Prazo [' . ($deadline === 'proximo' ? 'próximo' : 'vencido') . ']';
$assetRoot = dirname(__DIR__, 3) . '/public/assets/css/shared/';
$csrfToken = Security::generateCSRFToken();
$elapsedSinceRegistration = static function (string $value, ?string $end = null): string {
    if (trim($value) === '' || ($end !== null && trim($end) === '')) return '—';
    try {
        $created = new DateTimeImmutable($value);
        $until = new DateTimeImmutable($end ?? 'now');
        if ($created > $until) return '0seg';
        $interval = $created->diff($until);
        if ($interval->y > 0) return $interval->y . 'a';
        if ($interval->m > 0) return $interval->m . 'm';
        $days = (int) $interval->days;
        if ($days >= 7) return intdiv($days, 7) . 's';
        if ($days > 0) return $days . 'd';
        if ($interval->h > 0) return $interval->h . 'h';
        if ($interval->i > 0) return $interval->i . 'min';
        return $interval->s . 'seg';
    } catch (Throwable $e) {
        return '—';
    }
};
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
        .register-line { display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; padding: 0; height: fit-content; border-bottom: 0; }
        .tarefas-table-scroll { width: 100%; overflow-x: auto; }
        .register-line-header, .register-line-content { width: 100%; min-width: 910px; display: grid; grid-template-columns: minmax(200px, 1.4fr) minmax(230px, 2fr) minmax(110px, 1fr) minmax(130px, 1fr) minmax(140px, 1fr) 55px; align-items: center; box-sizing: border-box; padding: 10px 15px; color: #202020; gap: 10px; font-size: 12px; }
        .register-line-header { background-color: #20202040; }
        .register-line-content { background-color: #20202010; }
        .register-line-content:not(:last-child) { border-bottom: 1px solid lightgray; }
        .register-col { display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; box-sizing: border-box; }
        .col-processo { display: flex; flex-direction: row; align-items: center; gap: 10px; }
        .col-content-icon { width: 50px; display: flex; flex-direction: column; flex-wrap: nowrap; justify-content: center; align-items: center; box-sizing: border-box; border-right: solid 1px #20202040; cursor: pointer; }
        .col-content-icon img { width: 20px; height: 20px; opacity: 0.3; }
        .col-content-icon:hover img { opacity: 1; }
        .pagination-symbol { display: flex; align-items: center; justify-content: center; padding: 0; }
        .pagination-symbol img { width: 14px; height: 14px; }
        .tarefa-status-form { width: 100%; }
        .tarefa-status-form select { width: 140px; max-width: 100%; }
        .tarefa-status-form select[data-status="em_andamento"], .tarefa-status-form option[value="em_andamento"] { background-color: gold; color: #202020; }
        .tarefa-status-form select[data-status="com_pendencias"], .tarefa-status-form option[value="com_pendencias"] { background-color: crimson; color: white; }
        .tarefa-status-form select[data-status="finalizada"], .tarefa-status-form option[value="finalizada"] { background-color: lime; color: #202020; }
        .tarefa-status-form option[value="a_fazer"] { background-color: white; color: #202020; }
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
    <?php if ($statusSuccess): ?><div class="card-line-info" role="status">Status atualizado com sucesso.</div><?php endif; ?>
    <?php if ($allocationSuccess): ?><div class="card-line-info" role="status">Alocação atualizada com sucesso.</div><?php endif; ?>
    <?php if ($statusError !== ''): ?><div class="card-line-info" role="alert"><?= $escape($statusError) ?></div><?php endif; ?>
    <div class="display-flex row row-full justify-end align-center gap-y-md padding-x-lg padding-y-md border-bottom">
        <button type="button" class="btn btn-secondary" <?= $canManageAutomationRules ? "onclick=\"location.href='/frame.php?page=tarefasrules'\"" : 'disabled' ?>>Regras de automação</button>
        <button type="button" class="btn btn-primary" <?= $canAccessCartorioTasks ? "onclick=\"location.href='/frame.php?page=tarefascreate'\"" : 'disabled' ?>>Cadastrar tarefa</button>
    </div>
    <form action="/frame.php" method="get" id="tarefas-filtros" class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
        <input type="hidden" name="page" value="tarefas">
        <?php if ($deadline !== ''): ?><input type="hidden" name="prazo" value="<?= $escape($deadline) ?>"><?php endif; ?>
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
        <div class="tarefas-table-scroll">
        <div class="register-line">
            <div class="register-line-header">
                <div class="col-processo">Processo</div>
                <div>Tarefa</div>
                <div>Local</div>
                <div>Usuário alocado</div>
                <div>Status</div>
                <div>Tempo</div>
            </div>
        </div>
        <div class="register-line">            
            <?php foreach ($result['rows'] as $row):
                $taskLabel = trim(implode(' ', array_filter([$types[$row['tipo']] ?? '', trim((string) ($row['tipo_documento'] ?? '')), lcfirst(trim((string) ($row['especificacao_documento'] ?? '')))], static fn(string $part): bool => $part !== '')));
                if (trim((string) ($row['tipo_documento'] ?? '')) === '') $taskLabel = trim((string) $row['titulo']);
            ?>
            <div class="register-line-content">
                <div class="register-col col-processo"><?= $escape(trim((string) ($row['numero_processo'] ?? '')) ?: '—') ?></div>
                <div class="register-col"><?= $escape($taskLabel ?: '—') ?></div>
                <div class="register-col"><?= $escape(trim((string) ($row['local_tarefa'] ?? '')) ?: '—') ?></div>
                <div class="register-col">
                    <?php if ($canManageAutomationRules): $alocadoId = $row['alocados_ids'][0] ?? ''; ?>
                    <form class="tarefa-alocacao-form" method="post" action="<?= $escape($url($pageNumber)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="acao" value="alocar">
                        <input type="hidden" name="tarefa_id" value="<?= $escape((string) $row['id']) ?>">
                        <select name="usuario_id" class="height-sm" aria-label="Alterar responsável pela tarefa <?= $escape($taskLabel) ?>" required>
                            <option value="">Sem responsável</option>
                            <?php if ($alocadoId !== '' && !in_array($alocadoId, array_column($users, 'id'), true)): ?><option value="" selected><?= $escape(implode(', ', $row['alocados']) . ' (inativo)') ?></option><?php endif; ?>
                            <?php foreach ($users as $user): ?><option value="<?= $escape($user['id']) ?>" <?= $alocadoId === $user['id'] ? 'selected' : '' ?>><?= $escape($user['name']) ?></option><?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-secondary">Atribuir</button></noscript>
                    </form>
                    <?php else: ?><?= $escape(implode(', ', $row['alocados'] ?? []) ?: 'Sem responsável') ?><?php endif; ?>
                </div>
                <div class="register-col">
                    <form class="tarefa-status-form" method="post" action="<?= $escape($url($pageNumber)) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="tarefa_id" value="<?= $escape((string) $row['id']) ?>">
                        <select name="novo_status" class="height-sm" data-status="<?= $escape((string) $row['status']) ?>" aria-label="Alterar status da tarefa <?= $escape($taskLabel) ?>">
                            <?php foreach ($statusOptions as $value => $label): ?><option value="<?= $escape($value) ?>" <?= $row['status'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-secondary">Alterar</button></noscript>
                    </form>
                </div>
                <div class="register-col" title="<?= $row['status'] === 'finalizada' ? 'Tempo até a finalização' : 'Tempo desde o cadastro' ?>"><?= $escape($elapsedSinceRegistration((string) ($row['criado_em'] ?? ''), $row['status'] === 'finalizada' ? (string) ($row['finalizado_em'] ?? '') : null)) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
    <?php if (!$error): ?>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">Mostrando <?= $total > 0 ? $result['offset'] + 1 : 0 ?>–<?= min($result['offset'] + $perPage, $total) ?> de <?= $total ?> tarefas</span>
            <div class="pagination-controls"><?php if ($pageNumber > 1): ?>
            <a class="pagination-symbol" href="<?= $escape($url($pageNumber - 1)) ?>" aria-label="Página anterior"><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt=""></a>
            <?php else: ?>
            <span class="pagination-symbol disabled" aria-disabled="true" aria-label="Página anterior"><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt=""></span>
            <?php endif; ?>
            <span class="pagination-number active" aria-current="page"><?= $pageNumber ?></span>
            <?php if ($pageNumber < $pages): ?>
                <a class="pagination-symbol" href="<?= $escape($url($pageNumber + 1)) ?>" aria-label="Próxima página"><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt=""></a>
                <?php else: ?>
                <span class="pagination-symbol disabled" aria-disabled="true" aria-label="Próxima página"><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt=""></span>
                <?php endif; ?>
            </div>
        </div><?php endif; ?>
</div>

<script>document.querySelectorAll('#tarefas-filtros select').forEach(function (select) { select.addEventListener('change', function () { document.getElementById('tarefas-filtros').requestSubmit(); }); });</script>
<script>document.querySelectorAll('.tarefa-status-form select').forEach(function (select) { select.addEventListener('change', function () { this.dataset.status = this.value; this.form.requestSubmit(); }); });</script>
<script>document.querySelectorAll('.tarefa-alocacao-form select').forEach(function (select) { select.addEventListener('change', function () { if (this.value) this.form.requestSubmit(); }); });</script>
</body>
</html>
