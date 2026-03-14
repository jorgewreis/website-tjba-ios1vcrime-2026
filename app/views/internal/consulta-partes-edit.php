<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/PessoasService.php';
require_once __DIR__ . '/../../../config/security.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /frame.php?page=consultapartes');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$service = new PessoasService($connection);
$pessoa = $service->find($id);
if ($pessoa === null) {
    header('Location: /frame.php?page=consultapartes');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar pessoa - Consulta Partes</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script src="/assets/js/shared/cpf.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar pessoa</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($pessoa['nome_completo'] ?? 'Pessoa'); ?></h1>
        <p class="description-page">Dados básicos, telefones, endereços e vínculos com instituições.</p>
    </div>
    <div class="card-page">
        <input type="hidden" id="parte-id" value="<?php echo htmlspecialchars($pessoa['id'] ?? ''); ?>">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Dados básicos</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="cpf">CPF</label>
                <input type="text" class="width-md height-md white" id="cpf" value="<?php
                    $cpfVal = $pessoa['cpf'] ?? '';
                    echo htmlspecialchars(strlen($cpfVal) === 11 ? Security::formatCPF($cpfVal) : $cpfVal);
                ?>">
            </div>
            <div class="col">
                <label for="nome_completo">Nome completo</label>
                <input type="text" class="width-xl height-md white" id="nome_completo" value="<?php echo htmlspecialchars($pessoa['nome_completo'] ?? ''); ?>">
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="sexo">Sexo</label>
                <select class="width-md height-md white" id="sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino"<?php echo ($pessoa['sexo'] ?? '') === 'Feminino' ? ' selected' : ''; ?>>Feminino</option>
                    <option value="Masculino"<?php echo ($pessoa['sexo'] ?? '') === 'Masculino' ? ' selected' : ''; ?>>Masculino</option>
                    <option value="Outro"<?php echo ($pessoa['sexo'] ?? '') === 'Outro' ? ' selected' : ''; ?>>Outro</option>
                </select>
            </div>
            <div class="col">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" class="width-md height-md white" id="data_nascimento" value="<?php echo htmlspecialchars($pessoa['data_nascimento'] ?? ''); ?>">
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar dados</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Telefones</div>
        <div id="telefones-list">
            <?php foreach ($pessoa['telefones'] ?? [] as $t): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($t['tipo'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['numero'] ?? ''); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-tel" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <input type="text" class="width-sm height-md white" id="new-tel-tipo" placeholder="Tipo">
            <input type="text" class="width-md height-md white" id="new-tel-numero" placeholder="Número">
            <button type="button" class="btn btn-secondary-outline" id="add-tel-btn">Adicionar telefone</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Endereços</div>
        <div id="enderecos-list">
            <?php foreach ($pessoa['enderecos'] ?? [] as $e): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars(trim(($e['logradouro'] ?? '') . ' ' . ($e['numero'] ?? '') . ' ' . ($e['bairro'] ?? '') . ' ' . ($e['cidade'] ?? ''))); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-end" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Vínculos com processos</div>
        <div id="vinculos-processos">
            <?php foreach ($pessoa['vinculos_processos'] ?? [] as $vp): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
                <div class="col"><?php echo htmlspecialchars($vp['numero_processo'] ?? $vp['processo_id'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vp['tipo_parte'] ?? ''); ?> / <?php echo htmlspecialchars($vp['polo'] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pessoa['vinculos_processos'])): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line"><span class="minor-info">Nenhum vínculo com processo.</span></div>
            <?php endif; ?>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Vínculos com instituições</div>
        <div id="vinculos-inst-list">
            <?php foreach ($pessoa['vinculos_instituicoes'] ?? [] as $vi): ?>
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($vi['instituicao_nome'] ?? $vi['instituicao_sigla'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vi['cargo_funcao'] ?? '—'); ?></div>
                <div class="col">
                    <a href="/frame.php?page=partesinstituicoesedit&id=<?php echo urlencode($vi['id'] ?? ''); ?>" class="btn btn-secondary-outline">Editar</a>
                    <button type="button" class="btn btn-secondary-outline btn-del-vinculo" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">Excluir</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <a href="/frame.php?page=partesinstituicoescreate&parte_id=<?php echo urlencode($pessoa['id'] ?? ''); ?>" class="btn btn-primary">Vincular instituição</a>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
    (function() {
        var nomeEl = document.getElementById('nome_completo');
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
        var parteId = document.getElementById('parte-id').value;
        document.getElementById('salvar-btn').addEventListener('click', function() {
            var cpfRaw = (document.getElementById('cpf').value || '').replace(/\D/g, '');
            if (cpfRaw && cpfRaw.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(cpfRaw)) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'CPF inválido. Verifique os dígitos.' });
                return;
            }
            var nomeVal = (document.getElementById('nome_completo').value || '').trim();
            var payload = {
                id: parteId,
                cpf: cpfRaw || '',
                nome_completo: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nomeVal, 255) : nomeVal,
                sexo: (document.getElementById('sexo').value || '').trim(),
                data_nascimento: (document.getElementById('data_nascimento').value || '').trim()
            };
            this.disabled = true;
            fetch('/api/pessoas/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) { if (typeof showToast === 'function') showToast({ type: 'success', message: 'Dados salvos.' }); }
                else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
                document.getElementById('salvar-btn').disabled = false;
            })
            .catch(function() { document.getElementById('salvar-btn').disabled = false; });
        });
        document.getElementById('add-tel-btn').addEventListener('click', function() {
            var numero = (document.getElementById('new-tel-numero').value || '').trim();
            if (!numero) { if (typeof showToast === 'function') showToast({ type: 'error', message: 'Informe o número.' }); return; }
            fetch('/api/pessoas/telefones/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ parte_id: parteId, tipo: document.getElementById('new-tel-tipo').value.trim(), numero: numero })
            })
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
                fetch('/api/pessoas/telefones/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (d.deleted) window.location.reload(); });
            });
        });
        document.querySelectorAll('.btn-del-vinculo').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Desvincular esta instituição?')) return;
                var id = this.getAttribute('data-id');
                fetch('/api/pessoas/vinculos-instituicoes/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) { if (d.deleted) window.location.reload(); });
            });
        });
    })();
    </script>
</body>
</html>
