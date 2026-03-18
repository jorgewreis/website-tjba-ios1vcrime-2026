<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../services/PessoasService.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';

$parteIdPrefill = trim((string) ($_GET['parte_id'] ?? ''));

$connection = Conection::getInstance()->getConnection();
$pessoasService = new PessoasService($connection);
$instituicoesService = new InstituicoesPublicasService($connection);
$listLimit = 500;
$pessoasList = $pessoasService->list([], $listLimit, 0);
$instituicoesList = $instituicoesService->list([], $listLimit, 0);
$pessoas = $pessoasList['rows'] ?? [];
$instituicoes = $instituicoesList['rows'] ?? [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo vínculo pessoa-instituição</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
                <label for="parte_id">Pessoa (parte) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md white" id="parte_id" required>
                    <option value="">— Selecione a pessoa —</option>
                    <?php foreach ($pessoas as $p): ?>
                    <?php
                        $cpfVal = $p['cpf'] ?? '';
                        $cpfDisplay = ($cpfVal !== '' && strlen($cpfVal) === 11) ? Security::formatCPF($cpfVal) : $cpfVal;
                        $label = htmlspecialchars($p['nome_completo'] ?? $p['id'] ?? '—');
                        if ($cpfDisplay !== '') { $label .= ' – ' . htmlspecialchars($cpfDisplay); }
                    ?>
                    <option value="<?php echo htmlspecialchars($p['id'] ?? ''); ?>"<?php echo ($parteIdPrefill !== '' && ($p['id'] ?? '') === $parteIdPrefill) ? ' selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-secondary-outline" style="margin-top:4px;" id="btn-buscar-pessoa">Abrir lista de pessoas</button>
            </div>
            <div class="col">
                <label for="instituicao_id">Instituição <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md white" id="instituicao_id" required>
                    <option value="">— Selecione a instituição —</option>
                    <?php foreach ($instituicoes as $i): ?>
                    <?php
                        $nomeInst = htmlspecialchars($i['nome'] ?? $i['id'] ?? '—');
                        $sigla = trim($i['nome_fantasia'] ?? '');
                        if ($sigla !== '') { $nomeInst .= ' (' . htmlspecialchars($sigla) . ')'; }
                    ?>
                    <option value="<?php echo htmlspecialchars($i['id'] ?? ''); ?>"><?php echo $nomeInst; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-secondary-outline" style="margin-top:4px;" id="btn-buscar-instituicao">Abrir lista de instituições</button>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="setor_id">Setor (opcional)</label>
                <select class="width-200 height-md white" id="setor_id">
                    <option value="">— Selecione a instituição primeiro —</option>
                </select>
            </div>
            <div class="col">
                <label for="profissao">Profissão</label>
                <input type="text" class="width-200 height-md white" id="profissao" placeholder="Ex: Policial">
            </div>
            <div class="col">
                <label for="cargo_funcao">Cargo / Função <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-200 height-md white" id="cargo_funcao" placeholder="Ex: Investigador">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="observacoes">Observações</label>
                <textarea class="width-600 height-md white" id="observacoes" rows="2"></textarea>
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
    document.getElementById('voltar-btn').addEventListener('click', function() { window.location.href = '/frame.php?page=partesinstituicoeslist'; });
    document.getElementById('btn-buscar-pessoa').addEventListener('click', function() { window.open('/frame.php?page=consultapartes', '_blank'); });
    document.getElementById('btn-buscar-instituicao').addEventListener('click', function() { window.open('/frame.php?page=instituicoespublicas', '_blank'); });
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
        var cargoFuncao = (document.getElementById('cargo_funcao').value || '').trim();
        if (!parteId || !instituicaoId) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'Selecione a pessoa e a instituição.' });
            return;
        }
        var payload = {
            parte_id: parteId,
            instituicao_id: instituicaoId,
            setor_id: (document.getElementById('setor_id').value || '').trim() || null,
            profissao: (document.getElementById('profissao').value || '').trim() || null,
            cargo_funcao: cargoFuncao || null,
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
