<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script src="/assets/js/shared/cnpj.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Instituições -</span> <span class="specific">Nova</span></div>
        <h1 class="title-page">Nova instituição pública</h1>
    </div>
    <div class="card-page">
        <div class="row table header">Dados</div>
        <div class="row filter">
            <div class="col filter">
                <label for="nome">Nome *</label>
                <input type="text" class="width-xl height-md" id="nome" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="nome_fantasia">Nome fantasia / Sigla</label>
                <input type="text" class="width-md height-md" id="nome_fantasia">
            </div>
            <div class="col filter">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="width-md height-md" id="cnpj" placeholder="Somente números">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="observacoes">Observações</label>
                <textarea class="width-xl height-md" id="observacoes" rows="2"></textarea>
            </div>
        </div>
        <div class="row buttons">
            <a href="/frame.php?page=instituicoespublicas" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>
    </div>
    <script>
    (function() {
        var nomeEl = document.getElementById('nome');
        var nomeFantasiaEl = document.getElementById('nome_fantasia');
        if (nomeEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nomeEl.addEventListener('blur', function() { this.value = TextFormatter.formatProperName(this.value, 255); });
        }
        if (nomeFantasiaEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nomeFantasiaEl.addEventListener('blur', function() { this.value = TextFormatter.formatProperName(this.value, 120); });
        }
        if (typeof App !== 'undefined' && App.CNPJ && App.CNPJ.init) {
            App.CNPJ.init('#cnpj');
        }
    })();
    document.getElementById('cadastrar-btn').addEventListener('click', function() {
        var nome = (document.getElementById('nome').value || '').trim();
        if (!nome) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Nome é obrigatório.' }); return; }
        var cnpjRaw = (document.getElementById('cnpj').value || '').replace(/\D/g, '');
        if (cnpjRaw && cnpjRaw.length === 14 && typeof App !== 'undefined' && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'CNPJ inválido. Verifique os dígitos.' });
            return;
        }
        this.disabled = true;
        fetch('/api/instituicoes-publicas/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nome, 255) : nome,
                nome_fantasia: (function() {
                    var v = (document.getElementById('nome_fantasia').value || '').trim();
                    return v === '' ? null : (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName ? TextFormatter.formatProperName(v, 120) : v);
                })(),
                cnpj: cnpjRaw === '' ? null : cnpjRaw,
                observacoes: (document.getElementById('observacoes').value || '').trim() || null
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) { if (typeof showToast === 'function') showToast({ type: 'success', message: 'Cadastrada.' }); window.location.href = '/frame.php?page=instituicoespublicasedit&id=' + encodeURIComponent(d.id); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); document.getElementById('cadastrar-btn').disabled = false; }
        })
        .catch(function() { document.getElementById('cadastrar-btn').disabled = false; });
    });
    </script>
</body>
</html>
