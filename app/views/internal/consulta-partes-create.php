<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Pessoa - Consulta Partes</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Nova pessoa</span></div>
        <h1 class="title-page">Cadastrar pessoa</h1>
        <p class="description-page">Dados básicos da parte (pessoa).</p>
    </div>
    <div class="card-page">
        <div class="row table header">Dados obrigatórios</div>
        <div class="row filter">
            <div class="col filter">
                <label for="cpf">CPF <span class="label-hint-obrigatorio">*</span></label>
                <input type="text" class="width-md height-md" id="cpf" name="cpf" placeholder="Somente números" required>
            </div>
            <div class="col filter">
                <label for="nome_completo">Nome completo <span class="label-hint-obrigatorio">*</span></label>
                <input type="text" class="width-xl height-md" id="nome_completo" name="nome_completo" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="sexo">Sexo <span class="label-hint-obrigatorio">*</span></label>
                <select class="width-md height-md" id="sexo" name="sexo" required>
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="data_nascimento">Data de nascimento <span class="label-hint-obrigatorio">*</span></label>
                <input type="date" class="width-md height-md" id="data_nascimento" name="data_nascimento" required>
            </div>
        </div>
        <div class="row buttons">
            <a href="/frame.php?page=consultapartes" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>
        <div id="feedback" class="row" aria-live="polite"></div>
    </div>
    <script>
    (function() {
        document.getElementById('cadastrar-btn').addEventListener('click', function() {
            var cpf = (document.getElementById('cpf').value || '').replace(/\D/g, '');
            var nome = (document.getElementById('nome_completo').value || '').trim();
            var sexo = (document.getElementById('sexo').value || '').trim();
            var data = (document.getElementById('data_nascimento').value || '').trim();
            if (!cpf || !nome || !sexo || !data) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'Preencha CPF, nome, sexo e data de nascimento.' });
                else alert('Preencha todos os campos obrigatórios.');
                return;
            }
            this.disabled = true;
            fetch('/api/pessoas/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cpf: cpf, nome_completo: nome, sexo: sexo, data_nascimento: data })
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                if (result.ok && result.data.id) {
                    if (typeof showToast === 'function') showToast({ type: 'success', message: 'Pessoa cadastrada.' });
                    window.location.href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(result.data.id);
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', message: result.data.error || 'Erro ao cadastrar.' });
                    else alert(result.data.error || 'Erro ao cadastrar.');
                    document.getElementById('cadastrar-btn').disabled = false;
                }
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'Erro de conexão.' });
                document.getElementById('cadastrar-btn').disabled = false;
            });
        });
    })();
    </script>
</body>
</html>
