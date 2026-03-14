<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';

$parteIdPrefill = trim((string) ($_GET['parte_id'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo vínculo pessoa-instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Novo vínculo pessoa-instituição</span></div>
        <h1 class="title-page">Novo vínculo pessoa-instituição</h1>
        <p class="description-page">Vincule uma pessoa (parte) a uma instituição pública.</p>
    </div>
    <div class="card-page">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Dados do vínculo</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="parte_id">ID da pessoa (parte) *</label>
                <input type="text" class="width-md height-md white" id="parte_id" placeholder="Ex: PRT-..." value="<?php echo htmlspecialchars($parteIdPrefill); ?>" required>
                <a href="/frame.php?page=consultapartes" target="_blank" class="btn btn-secondary-outline" style="margin-top:4px;">Buscar pessoa</a>
            </div>
            <div class="col">
                <label for="instituicao_id">ID da instituição *</label>
                <input type="text" class="width-md height-md white" id="instituicao_id" placeholder="Ex: INS-..." required>
                <a href="/frame.php?page=instituicoespublicas" target="_blank" class="btn btn-secondary-outline" style="margin-top:4px;">Buscar instituição</a>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="setor_id">Setor (opcional)</label>
                <select class="width-md height-md white" id="setor_id">
                    <option value="">— Selecione a instituição primeiro —</option>
                </select>
            </div>
            <div class="col">
                <label for="profissao">Profissão</label>
                <input type="text" class="width-md height-md white" id="profissao" placeholder="Ex: Policial">
            </div>
            <div class="col">
                <label for="cargo_funcao">Cargo / Função *</label>
                <input type="text" class="width-md height-md white" id="cargo_funcao" placeholder="Ex: Investigador">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="data_inclusao">Data de inclusão *</label>
                <input type="date" class="width-md height-md white" id="data_inclusao" required>
            </div>
            <div class="col">
                <label for="data_inicio">Data início</label>
                <input type="date" class="width-md height-md white" id="data_inicio">
            </div>
            <div class="col">
                <label for="data_fim">Data fim</label>
                <input type="date" class="width-md height-md white" id="data_fim">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label><input type="checkbox" id="ativo" checked> Ativo</label>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="observacoes">Observações</label>
                <textarea class="width-xl height-md white" id="observacoes" rows="2"></textarea>
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <a href="/frame.php?page=partesinstituicoeslist" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
    (function() {
        var hoje = new Date().toISOString().slice(0, 10);
        var dataInclusao = document.getElementById('data_inclusao');
        if (dataInclusao && !dataInclusao.value) dataInclusao.value = hoje;
    })();
    document.getElementById('instituicao_id').addEventListener('change', function() {
        var instId = (this.value || '').trim();
        var sel = document.getElementById('setor_id');
        sel.innerHTML = '<option value="">— Carregando —</option>';
        if (!instId) { sel.innerHTML = '<option value="">— Selecione a instituição primeiro —</option>'; return; }
        fetch('/api/instituicoes-publicas/setores.php?instituicao_id=' + encodeURIComponent(instId))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var opts = '<option value="">— Nenhum setor —</option>';
                (d.setores || []).forEach(function(s) {
                    opts += '<option value="' + (s.id || '') + '">' + (s.nome_setor || s.sigla || s.id) + '</option>';
                });
                sel.innerHTML = opts;
            })
            .catch(function() { sel.innerHTML = '<option value="">— Erro —</option>'; });
    });
    document.getElementById('salvar-btn').addEventListener('click', function() {
        var parteId = (document.getElementById('parte_id').value || '').trim();
        var instituicaoId = (document.getElementById('instituicao_id').value || '').trim();
        var dataInclusao = (document.getElementById('data_inclusao').value || '').trim();
        var cargoFuncao = (document.getElementById('cargo_funcao').value || '').trim();
        if (!parteId || !instituicaoId || !dataInclusao) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'Preencha pessoa, instituição e data de inclusão.' });
            return;
        }
        var payload = {
            parte_id: parteId,
            instituicao_id: instituicaoId,
            setor_id: (document.getElementById('setor_id').value || '').trim() || null,
            profissao: (document.getElementById('profissao').value || '').trim() || null,
            cargo_funcao: cargoFuncao || null,
            data_inclusao: dataInclusao,
            data_inicio: (document.getElementById('data_inicio').value || '').trim() || null,
            data_fim: (document.getElementById('data_fim').value || '').trim() || null,
            ativo: document.getElementById('ativo').checked,
            observacoes: (document.getElementById('observacoes').value || '').trim() || null
        };
        this.disabled = true;
        fetch('/api/pessoas/vinculos-instituicoes/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.id) {
                    if (typeof showToast === 'function') showToast({ type: 'success', message: 'Vínculo criado.' });
                    window.location.href = '/frame.php?page=partesinstituicoeslist';
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' });
                    document.getElementById('salvar-btn').disabled = false;
                }
            })
            .catch(function() { document.getElementById('salvar-btn').disabled = false; });
    });
    </script>
</body>
</html>
