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
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta Partes - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas - Consulta Partes -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastrar pessoa</h1>
        <p class="description-page">Dados básicos da parte (pessoa). Apenas o nome completo é obrigatório; CPF, sexo e data de nascimento podem ser informados quando disponíveis.</p>
    </div>

    <div class="card-page">
        <div class="row table header">Informações da parte</div>
        <div class="row filter">
            <div class="col filter">
                <label for="nome_completo">Nome completo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-xl height-md" id="nome_completo" name="nome_completo" placeholder="Nome completo da pessoa" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="cpf">CPF</label>
                <input type="text" class="width-md height-md" id="cpf" name="cpf" placeholder="Somente números">
            </div>
            <div class="col filter">
                <label for="sexo">Sexo</label>
                <select class="width-md height-md" id="sexo" name="sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" class="width-md height-md" id="data_nascimento" name="data_nascimento">
            </div>
        </div>

        <div class="row buttons">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>

        <div class="row pagination">
            <div class="col">
                <label id="cadastro-feedback" class="status-placeholder" aria-live="polite"></label>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/shared/text-formatter.js"></script>
    <script src="/assets/js/shared/cpf.js"></script>
    <script>
    (function() {
        var nomeEl = document.getElementById('nome_completo');
        var cpfEl = document.getElementById('cpf');
        if (nomeEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nomeEl.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }
        if (typeof App !== 'undefined' && App.CPF && App.CPF.init) {
            App.CPF.init('#cpf');
        }

        document.getElementById('voltar-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=consultapartes';
        });

        document.getElementById('cadastrar-btn').addEventListener('click', function() {
            var nome = (document.getElementById('nome_completo').value || '').trim();
            if (!nome) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'Nome completo é obrigatório.' });
                else alert('Informe o nome completo.');
                return;
            }
            var cpfRaw = (document.getElementById('cpf').value || '').replace(/\D/g, '');
            if (cpfRaw && cpfRaw.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(cpfRaw)) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'CPF inválido. Verifique os dígitos.' });
                else alert('CPF inválido. Verifique os dígitos.');
                return;
            }
            var cpf = cpfRaw || null;
            var sexo = (document.getElementById('sexo').value || '').trim();
            var data = (document.getElementById('data_nascimento').value || '').trim();

            this.disabled = true;
            fetch('/api/pessoas/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nome_completo: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nome, 255) : nome,
                    cpf: cpf,
                    sexo: sexo || null,
                    data_nascimento: data || null
                })
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
