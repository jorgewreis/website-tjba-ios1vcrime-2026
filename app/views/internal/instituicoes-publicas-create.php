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
    document.getElementById('cadastrar-btn').addEventListener('click', function() {
        var nome = (document.getElementById('nome').value || '').trim();
        if (!nome) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Nome é obrigatório.' }); return; }
        this.disabled = true;
        fetch('/api/instituicoes-publicas/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nome: nome,
                nome_fantasia: (document.getElementById('nome_fantasia').value || '').trim() || null,
                cnpj: (document.getElementById('cnpj').value || '').replace(/\D/g, '') || null,
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
