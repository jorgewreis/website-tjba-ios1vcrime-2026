<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Instituição</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Dados</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="nome" required>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="nome_fantasia">Nome fantasia / Sigla</label>
                <input type="text" class="width-200 height-md white" id="nome_fantasia">
            </div>
            <div class="col">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="width-200 height-md white" id="cnpj" placeholder="Somente números">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="observacoes">Observações</label>
                <textarea class="width-600 height-md white" id="observacoes" rows="2"></textarea>
            </div>
        </div>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <a href="/frame.php?page=instituicoespublicas" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>
    </div>
    <script>
    (function() {
        var nomeEl = document.getElementById('nome');
        var nomeFantasiaEl = document.getElementById('nome_fantasia');
        if (nomeEl) {
            nomeEl.addEventListener('blur', function() {
                this.value = (this.value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
            });
        }
        if (nomeFantasiaEl) {
            nomeFantasiaEl.addEventListener('blur', function() {
                this.value = (this.value || '').trim().toUpperCase().slice(0, 120);
            });
        }
        if (typeof App !== 'undefined' && App.CNPJ && App.CNPJ.init) {
            App.CNPJ.init('#cnpj');
        }
    })();
    document.getElementById('cadastrar-btn').addEventListener('click', function() {
        var nome = (document.getElementById('nome').value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
        if (!nome) { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Nome é obrigatório.' }); return; }
        var cnpjRaw = (document.getElementById('cnpj').value || '').replace(/\D/g, '');
        if (cnpjRaw && cnpjRaw.length === 14 && typeof App !== 'undefined' && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'CNPJ inválido. Verifique os dígitos.' });
            return;
        }
        var nomeFantasiaVal = (document.getElementById('nome_fantasia').value || '').trim().toUpperCase().slice(0, 120);
        this.disabled = true;
        fetch('/api/instituicoes-publicas/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: nome,
                nome_fantasia: nomeFantasiaVal === '' ? null : nomeFantasiaVal,
                cnpj: cnpjRaw === '' ? null : cnpjRaw,
                observacoes: (document.getElementById('observacoes').value || '').trim() || null
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) { if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Cadastrada.' }); window.location.href = '/frame.php?page=instituicoespublicasedit&id=' + encodeURIComponent(d.id); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: d.error || 'Erro.' }); document.getElementById('cadastrar-btn').disabled = false; }
        })
        .catch(function() { document.getElementById('cadastrar-btn').disabled = false; });
    });
    </script>
</body>
</html>
