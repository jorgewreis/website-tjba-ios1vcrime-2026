<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';

$users = [];
$usersError = false;
$error = '';
$form = ['numero_processo' => '', 'tipo_tarefa' => '', 'tipo_documento' => '', 'especificacao_documento' => '', 'local' => '', 'usuario' => ''];
try {
    $repository = new CartorioTarefasRepository($connection);
    $users = $repository->listarUsuariosInternos();
} catch (Throwable $e) {
    error_log('Falha ao carregar usuários para cadastro de tarefa: ' . $e->getMessage());
    $usersError = true;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach ($form as $field => $unused) {
        $form[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }
    $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
    if (!Security::verifyCSRFToken($csrf)) {
        $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif ($usersError) {
        $error = 'Não foi possível carregar os usuários. Tente novamente.';
    } else {
        try {
            $repository->cadastrar($form['tipo_tarefa'], $form['tipo_documento'], $form['local'], $form['usuario'], $form['numero_processo'], $form['especificacao_documento']);
            $_SESSION['cartorio_tarefa_cadastrada'] = true;
            header('Location: /frame.php?page=tarefas', true, 303);
            exit;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Falha ao cadastrar tarefa do Cartório: ' . $e->getMessage());
            $error = 'Não foi possível cadastrar a tarefa. Tente novamente.';
        }
    }
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$taskTypes = CartorioTarefasRepository::TIPOS;
$documentTypes = CartorioTarefasRepository::DOCUMENTOS;
$specifications = CartorioTarefasRepository::ESPECIFICACOES;
$locations = CartorioTarefasRepository::LOCAIS;
$csrfToken = Security::generateCSRFToken();
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
        #tarefa-cadastro { width: 100%; box-sizing: border-box; }
        .tarefas-form-fields { display: flex; flex-wrap: wrap; gap: 15px; }
        .tarefas-form-field { display: flex; flex-direction: column; flex: 0 1 auto; min-width: 0; gap: 5px; }
        .tarefas-form-field select { width: 240px; max-width: calc(100vw - 60px); }
        .tarefas-form-field select#especificacao-documento { width: 360px; }
        .tarefas-form-field input { width: 280px; max-width: calc(100vw - 60px); }
        .cnj-feedback { font-size: 12px; }
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
    <?php if ($error !== ''): ?><div role="alert" class="padding-x-lg padding-y-md"><?= $escape($error) ?></div><?php endif; ?>
    <form id="tarefa-cadastro" method="post" action="/frame.php?page=tarefascreate">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <div class="tarefas-form-fields padding-x-lg padding-y-md border-bottom">
            <div class="tarefas-form-field">
                <label for="processo-tarefa">Número do processo</label>
                <input id="processo-tarefa" name="numero_processo" type="text" class="height-md" value="<?= $escape($form['numero_processo']) ?>" placeholder="0000000-00.0000.0.00.0000" inputmode="numeric" maxlength="25" autocomplete="off" aria-describedby="processo-tarefa-feedback" required>
                <span id="processo-tarefa-feedback" class="cnj-feedback" role="status" aria-live="polite"></span>
            </div>
        </div>
        <div class="tarefas-form-fields padding-x-lg padding-y-md border-bottom">
            <div class="tarefas-form-field">
                <label for="tipo-tarefa">Tipo de tarefa</label>
                <select id="tipo-tarefa" name="tipo_tarefa" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($taskTypes as $value => $type): ?><option value="<?= $escape($value) ?>" <?= $form['tipo_tarefa'] === $value ? 'selected' : '' ?>><?= $escape($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="tipo-documento">Tipo de documento</label>
                <select id="tipo-documento" name="tipo_documento" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($documentTypes as $type): ?><option value="<?= $escape($type) ?>" <?= $form['tipo_documento'] === $type ? 'selected' : '' ?>><?= $escape($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="especificacao-documento">Especificação do documento</label>
                <select id="especificacao-documento" name="especificacao_documento" class="height-md">
                    <option value="">Selecione</option>
                    <?php foreach ($specifications as $specification): ?><option value="<?= $escape($specification) ?>" <?= $form['especificacao_documento'] === $specification ? 'selected' : '' ?>><?= $escape($specification) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="local-tarefa">Local</label>
                <select id="local-tarefa" name="local" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($locations as $location): ?><option value="<?= $escape($location) ?>" <?= $form['local'] === $location ? 'selected' : '' ?>><?= $escape($location) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="tarefas-form-field">
                <label for="usuario-tarefa">Alocação de usuário</label>
                <select id="usuario-tarefa" name="usuario" class="height-md" required>
                    <option value="">Selecione</option>
                    <?php foreach ($users as $user): ?><option value="<?= $escape($user['id']) ?>" <?= $form['usuario'] === $user['id'] ? 'selected' : '' ?>><?= $escape($user['name']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($usersError): ?><span role="alert">Não foi possível carregar os usuários.</span><?php endif; ?>
            </div>
        </div>
        <div class="display-flex row row-full justify-start align-center gap-y-md padding-x-lg padding-y-md">
            <button type="button" class="btn btn-secondary" onclick="location.href='/frame.php?page=tarefas'">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="cadastrar-tarefa" disabled>Cadastrar</button>
        </div>
    </form>
</main>
<script src="/assets/js/modules/datajud/cnj.js?v=<?= $escape((string) (@hash_file('sha256', dirname(__DIR__, 3) . '/public/assets/js/modules/datajud/cnj.js') ?: '1')) ?>"></script>
<script>
(function () {
    const input = document.getElementById('processo-tarefa');
    const feedback = document.getElementById('processo-tarefa-feedback');
    const submit = document.getElementById('cadastrar-tarefa');
    const usersAvailable = <?= $usersError ? 'false' : 'true' ?>;
    function validate(showIncomplete) {
        const value = input.value.trim();
        const formatOk = /^\d{20}$/.test(value) || /^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/.test(value);
        const valid = formatOk && typeof window.validarCNJ === 'function' && window.validarCNJ(value);
        submit.disabled = !valid || !usersAvailable;
        input.setCustomValidity(valid ? '' : 'Informe um número de processo CNJ válido.');
        input.setAttribute('aria-invalid', value !== '' && !valid && (showIncomplete || value.replace(/\D/g, '').length >= 20) ? 'true' : 'false');
        if (valid) {
            input.value = window.formatarCNJ(value);
            feedback.textContent = 'Número CNJ válido.';
        } else if (value !== '' && (showIncomplete || value.replace(/\D/g, '').length >= 20)) {
            feedback.textContent = 'Número CNJ incompleto ou inválido.';
        } else {
            feedback.textContent = '';
        }
    }
    input.addEventListener('input', function () { validate(false); });
    input.addEventListener('blur', function () { validate(true); });
    document.getElementById('tarefa-cadastro').addEventListener('submit', function (event) {
        validate(true);
        if (submit.disabled) { event.preventDefault(); input.reportValidity(); }
    });
    validate(false);
}());
</script>
</body>
</html>
