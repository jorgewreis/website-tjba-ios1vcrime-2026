<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';
require_once __DIR__ . '/../../../config/security.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=instituicoespublicas'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new InstituicoesPublicasService($connection);
$inst = $service->find($id);
if ($inst === null) { header('Location: /frame.php?page=instituicoespublicas'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script src="/assets/js/shared/cnpj.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Instituições -</span> <span class="specific">Editar</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($inst['nome'] ?? 'Instituição'); ?></h1>
    </div>
    <div class="card-page">
        <input type="hidden" id="inst-id" value="<?php echo htmlspecialchars($inst['id']); ?>">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Dados</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-xl height-md white" id="nome" value="<?php echo htmlspecialchars($inst['nome'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="nome_fantasia">Nome fantasia / Sigla</label>
                <input type="text" class="width-md height-md white" id="nome_fantasia" value="<?php echo htmlspecialchars($inst['nome_fantasia'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="width-md height-md white" id="cnpj" value="<?php
                    $cnpjVal = preg_replace('/\D+/', '', (string) ($inst['cnpj'] ?? ''));
                    echo htmlspecialchars($cnpjVal !== '' && strlen($cnpjVal) === 14 ? Security::formatCNPJ($cnpjVal) : ($inst['cnpj'] ?? ''));
                ?>">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="observacoes">Observações</label>
                <textarea class="width-xl height-md white" id="observacoes" rows="2"><?php echo htmlspecialchars($inst['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <a href="/frame.php?page=instituicoespublicas" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Endereços</div>
        <div id="enderecos-list">
            <?php foreach ($inst['enderecos'] ?? [] as $e): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars(trim(($e['logradouro'] ?? '') . ($e['numero'] ? ' ' . $e['numero'] : '') . ($e['bairro'] ? ' - ' . $e['bairro'] : '') . ($e['cidade'] ? ' - ' . $e['cidade'] : '') . ($e['uf'] ? '/' . $e['uf'] : ''))); ?></div>
                <div class="col"><?php echo !empty($e['principal']) ? 'Principal' : '—'; ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-end" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="new-end-cep">CEP</label>
                <input type="text" class="width-sm height-md white" id="new-end-cep" placeholder="00000-000" maxlength="9">
                <button type="button" class="btn btn-secondary-outline" id="buscar-cep-btn">Buscar CEP</button>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="new-end-logradouro">Logradouro</label>
                <input type="text" class="width-xl height-md white" id="new-end-logradouro" placeholder="Rua, avenida...">
            </div>
            <div class="col">
                <label for="new-end-numero">Número</label>
                <input type="text" class="width-sm height-md white" id="new-end-numero" placeholder="Nº">
            </div>
            <div class="col">
                <label for="new-end-complemento">Complemento</label>
                <input type="text" class="width-md height-md white" id="new-end-complemento" placeholder="Sala, andar...">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="new-end-bairro">Bairro</label>
                <input type="text" class="width-md height-md white" id="new-end-bairro" placeholder="Bairro">
            </div>
            <div class="col">
                <label for="new-end-cidade">Cidade</label>
                <input type="text" class="width-md height-md white" id="new-end-cidade" placeholder="Cidade">
            </div>
            <div class="col">
                <label for="new-end-uf">UF</label>
                <input type="text" class="width-xs height-md white" id="new-end-uf" placeholder="UF" maxlength="2">
            </div>
            <label class="inline"><input type="checkbox" id="new-end-principal"> Principal</label>
            <button type="button" class="btn btn-secondary-outline" id="add-end-btn">Adicionar endereço</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Telefones</div>
        <div id="telefones-list">
            <?php foreach ($inst['telefones'] ?? [] as $t): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($t['tipo'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['numero'] ?? ''); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['ramal'] ?? '—'); ?></div>
                <div class="col"><?php echo !empty($t['principal']) ? 'Principal' : '—'; ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-tel" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <input type="text" class="width-sm height-md white" id="new-tel-tipo" placeholder="Tipo">
            <input type="text" class="width-md height-md white" id="new-tel-numero" placeholder="Número *">
            <input type="text" class="width-sm height-md white" id="new-tel-ramal" placeholder="Ramal">
            <label class="inline"><input type="checkbox" id="new-tel-principal"> Principal</label>
            <button type="button" class="btn btn-secondary-outline" id="add-tel-btn">Adicionar telefone</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Setores</div>
        <div id="setores-list">
            <?php foreach ($inst['setores'] ?? [] as $s): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($s['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($s['nome_setor'] ?? ''); ?></div>
                <div class="col"><?php echo htmlspecialchars($s['sigla'] ?? '—'); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-setor" data-id="<?php echo htmlspecialchars($s['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <input type="text" class="width-xl height-md white" id="new-setor-nome" placeholder="Nome do setor *">
            <input type="text" class="width-sm height-md white" id="new-setor-sigla" placeholder="Sigla">
            <button type="button" class="btn btn-secondary-outline" id="add-setor-btn">Adicionar setor</button>
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
    document.getElementById('salvar-btn').addEventListener('click', function() {
        var id = document.getElementById('inst-id').value;
        var cnpjRaw = (document.getElementById('cnpj').value || '').replace(/\D/g, '');
        if (cnpjRaw && cnpjRaw.length === 14 && typeof App !== 'undefined' && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'CNPJ inválido. Verifique os dígitos.' });
            return;
        }
        var nomeVal = (document.getElementById('nome').value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
        var nomeFantasiaVal = (document.getElementById('nome_fantasia').value || '').trim().toUpperCase().slice(0, 120);
        var payload = {
            id: id,
            nome: nomeVal,
            nome_fantasia: nomeFantasiaVal === '' ? null : nomeFantasiaVal,
            cnpj: cnpjRaw === '' ? null : cnpjRaw,
            observacoes: (document.getElementById('observacoes').value || '').trim() || null
        };
        this.disabled = true;
        fetch('/api/instituicoes-publicas/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { if (typeof showToast === 'function') showToast({ type: 'success', message: 'Salvo.' }); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
            document.getElementById('salvar-btn').disabled = false;
        })
        .catch(function() { document.getElementById('salvar-btn').disabled = false; });
    });

    var instId = document.getElementById('inst-id').value;
    document.getElementById('add-tel-btn').addEventListener('click', function() {
        var numero = (document.getElementById('new-tel-numero').value || '').trim();
        if (!numero) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Informe o número.' }); return; }
        var payload = {
            instituicao_id: instId,
            tipo: (document.getElementById('new-tel-tipo').value || '').trim() || null,
            numero: numero,
            ramal: (document.getElementById('new-tel-ramal').value || '').trim() || null,
            principal: document.getElementById('new-tel-principal').checked ? 1 : 0
        };
        fetch('/api/instituicoes-publicas/telefones/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) { window.location.reload(); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
        });
    });
    document.querySelectorAll('.btn-del-tel').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Excluir este telefone?')) return;
            var id = this.getAttribute('data-id');
            fetch('/api/instituicoes-publicas/telefones/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.deleted) window.location.reload(); });
        });
    });

    document.getElementById('add-setor-btn').addEventListener('click', function() {
        var nomeSetor = (document.getElementById('new-setor-nome').value || '').trim();
        if (!nomeSetor) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Informe o nome do setor.' }); return; }
        var payload = {
            instituicao_id: instId,
            nome_setor: nomeSetor,
            sigla: (document.getElementById('new-setor-sigla').value || '').trim() || null
        };
        fetch('/api/instituicoes-publicas/setores/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) { window.location.reload(); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
        });
    });
    document.querySelectorAll('.btn-del-setor').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Excluir este setor?')) return;
            var id = this.getAttribute('data-id');
            fetch('/api/instituicoes-publicas/setores/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.deleted) window.location.reload(); });
        });
    });

    document.getElementById('buscar-cep-btn').addEventListener('click', function() {
        var cep = (document.getElementById('new-end-cep').value || '').replace(/\D/g, '');
        if (cep.length !== 8) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Informe um CEP com 8 dígitos.' }); return; }
        this.disabled = true;
        fetch('/api/cep/consulta.php?q=' + encodeURIComponent(cep), { method: 'GET', credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.mode === 'cep' && d.data) {
                document.getElementById('new-end-logradouro').value = d.data.logradouro || '';
                document.getElementById('new-end-bairro').value = d.data.bairro || '';
                document.getElementById('new-end-cidade').value = d.data.cidade || '';
                document.getElementById('new-end-uf').value = (d.data.uf || '').toUpperCase().slice(0, 2);
                if (typeof showToast === 'function') showToast({ type: 'success', message: 'CEP encontrado.' });
            } else {
                if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'CEP não encontrado.' });
            }
        })
        .catch(function() { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Erro ao consultar CEP.' }); })
        .finally(function() { document.getElementById('buscar-cep-btn').disabled = false; });
    });
    document.getElementById('add-end-btn').addEventListener('click', function() {
        var logradouro = (document.getElementById('new-end-logradouro').value || '').trim();
        var cidade = (document.getElementById('new-end-cidade').value || '').trim();
        if (!logradouro && !cidade) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Informe ao menos logradouro ou cidade.' }); return; }
        var cepRaw = (document.getElementById('new-end-cep').value || '').replace(/\D/g, '');
        var payload = {
            instituicao_id: instId,
            cep: cepRaw.length === 8 ? cepRaw : null,
            logradouro: logradouro || null,
            numero: (document.getElementById('new-end-numero').value || '').trim() || null,
            complemento: (document.getElementById('new-end-complemento').value || '').trim() || null,
            bairro: (document.getElementById('new-end-bairro').value || '').trim() || null,
            cidade: cidade || null,
            uf: (document.getElementById('new-end-uf').value || '').trim().toUpperCase().slice(0, 2) || null,
            principal: document.getElementById('new-end-principal').checked ? 1 : 0
        };
        fetch('/api/instituicoes-publicas/enderecos/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) { window.location.reload(); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
        });
    });
    document.querySelectorAll('.btn-del-end').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Excluir este endereço?')) return;
            var id = this.getAttribute('data-id');
            fetch('/api/instituicoes-publicas/enderecos/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.deleted) window.location.reload(); });
        });
    });
    </script>
</body>
</html>
