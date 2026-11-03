<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';

$users = [];
$usersError = false;
try {
    $users = (new CartorioTarefasRepository($connection))->listarUsuariosInternos();
} catch (Throwable $e) {
    error_log('Falha ao carregar usuários para cadastro de tarefa: ' . $e->getMessage());
    $usersError = true;
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$taskTypes = ['Expedir', 'Assinar', 'Enviar', 'Juntar', 'Arquivar', 'Desentranhar'];
$documentTypes = ['Certidão', 'Ofício', 'Mandado', 'Carta Precatória', 'Guia', 'Edital', 'Ato Ordinatório', 'Petição', 'Resumo', 'Peças processuais', 'Laudo', 'Inquérito Policial', 'Medidas Protetivas'];
$locations = ['Cartório', 'Gabinete', 'Sala de Audiências'];
$assetRoot = dirname(__DIR__, 3) . '/public/assets/css/shared/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastrar tarefa - Primeira Vara Crime de Ilhéus</title>
    <?php foreach (['reset', 'color', 'layout', 'page', 'form', 'table', 'toast'] as $css): ?>
    <link rel="stylesheet" href="/assets/css/shared/<?= $css ?>.css?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . $css . '.css') ?: '1')) ?>">
    <?php endforeach; ?>
    <style>
        .tarefas-form-fields { display: flex; flex-wrap: wrap; gap: 15px; }
        .tarefas-form-field { display: flex; flex-direction: column; flex: 1 1 220px; min-width: 0; gap: 5px; }
        .tarefas-form-field select { width: 100%; }
    </style>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>
<div class="header-page">
    <div class="location"><span class="all">FokusLaw - Tarefas -</span><span class="specific">Cadastro</span></div>
    <div class="card-title"><div class="display-flex col col-full justify-start"><h1 class="title-page" style="color: var(--color-primary-active);">Cadastrar tarefa</h1></div></div>
</div>
<main class="container-full card-page">
    <div class="card-subtitle"><div class="display-flex row row-full justify-start"><h2>Informações da tarefa</h2></div></div>
    <form id="tarefa-cadastro" onsubmit="return false;">
        <div class="tarefas-form-fields padding-x-lg padding-y-md border-bottom">
            <div class="tarefas-form-field">
                <label for="tipo-tarefa">Tipo de tarefa</label>
                <select id="tipo-tarefa" name="tipo_tarefa" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($taskTypes as $type): ?><option value="<?= $escape($type) ?>"><?= $escape($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="tipo-documento">Tipo de documento</label>
                <select id="tipo-documento" name="tipo_documento" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($documentTypes as $type): ?><option value="<?= $escape($type) ?>"><?= $escape($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="local-tarefa">Local</label>
                <select id="local-tarefa" name="local" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($locations as $location): ?><option value="<?= $escape($location) ?>"><?= $escape($location) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="usuario-tarefa">Alocação de usuário</label>
                <select id="usuario-tarefa" name="usuario" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($users as $user): ?><option value="<?= $escape($user['id']) ?>"><?= $escape($user['name']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($usersError): ?><span role="alert">Não foi possível carregar os usuários.</span><?php endif; ?>
            </div>
        </div>
        <div class="display-flex row row-full justify-start align-center gap-y-md padding-x-lg padding-y-md">
            <button type="button" class="btn btn-secondary" onclick="location.href='/frame.php?page=tarefas'">Cancelar</button>
            <button type="button" class="btn btn-primary">Cadastrar</button>
        </div>
    </form>
</main>
</body>
</html>
