<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
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

$partesRepoProf = new PartesRepository($connection);
$profissoesLista = $partesRepoProf->listProfissoesDistintas();
$profissaoAtual = trim((string) ($pessoa['profissao'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar pessoa - Consulta Partes</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
    <div class="container-full card-page">
        <input type="hidden" id="parte-id" value="<?php echo htmlspecialchars(trim((string) ($pessoa['id'] ?? $pessoa['ID'] ?? ''))); ?>">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados básicos</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="cpf">CPF</label>
                <input type="text" class="width-300 height-md white readyonl" id="cpf" value="<?php
                    $cpfVal = $pessoa['cpf'] ?? '';
                    echo htmlspecialchars(strlen($cpfVal) === 11 ? Security::formatCPF($cpfVal) : $cpfVal);
                ?>" readonly>
            </div>
            <div class="col">
                <label for="nome_completo">Nome completo</label>
                <input type="text" class="width-600 height-md white" id="nome_completo" placeholder="Digite o nome completo" value="<?php echo htmlspecialchars($pessoa['nome_completo'] ?? ''); ?>">
            </div>
            <div class="col">
                <label for="sexo">Sexo</label>
                <select class="width-200 height-md white" id="sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino"<?php echo ($pessoa['sexo'] ?? '') === 'Feminino' ? ' selected' : ''; ?>>Feminino</option>
                    <option value="Masculino"<?php echo ($pessoa['sexo'] ?? '') === 'Masculino' ? ' selected' : ''; ?>>Masculino</option>
                    <option value="Outro"<?php echo ($pessoa['sexo'] ?? '') === 'Outro' ? ' selected' : ''; ?>>Outro</option>
                </select>
            </div>
            <div class="col">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" class="width-300 height-md white" id="data_nascimento" value="<?php echo htmlspecialchars($pessoa['data_nascimento'] ?? ''); ?>">
            </div>
        </div>

        <?php include __DIR__ . '/_partes_profissao_fields.php'; ?>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Telefones</h2></div>
        <div id="telefones-list">
            <?php foreach ($pessoa['telefones'] ?? [] as $t): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($t['tipo'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($t['numero'] ?? ''); ?></div>
                <div class="col"><button type="button" class="btn btn-secondary-outline btn-del-tel" data-id="<?php echo htmlspecialchars($t['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-tel-tipo">Tipo</label>
                <select class="width-300 height-md white" id="new-tel-tipo">
                    <option value="">Selecione</option>
                    <option value="Telefone fixo">Telefone fixo</option>
                    <option value="Celular">Celular</option>
                </select>
            </div>
            <div class="col">
                <label for="new-tel-numero">Número</label>
                <input type="text" class="width-300 height-md white" id="new-tel-numero" placeholder="(00) 00000-0000">
            </div>
            <div class="col">
                <label for="add-tel-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-secondary-outline" id="add-tel-btn">Adicionar telefone</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Endereços</h2></div>
        <div id="enderecos-list">
            <?php foreach ($pessoa['enderecos'] ?? [] as $e): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars(trim(($e['logradouro'] ?? '') . ' ' . ($e['numero'] ?? '') . ' ' . ($e['bairro'] ?? '') . ' ' . ($e['cidade'] ?? ''))); ?></div>
                <div class="col"><button type="button" class="btn btn-primary-outline btn-del-end" data-id="<?php echo htmlspecialchars($e['id'] ?? ''); ?>">Excluir</button></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-cep">CEP</label>
                <input type="text" class="width-200 height-md white" id="new-end-cep" placeholder="00000-000" maxlength="9">
            </div>
            <div class="col">
                <label for="buscar-cep-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-primary-outline" id="buscar-cep-btn">Buscar CEP</button>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-logradouro">Logradouro</label>
                <input type="text" class="width-600 height-md white" id="new-end-logradouro" placeholder="Rua, avenida...">
            </div>
            <div class="col">
                <label for="new-end-numero">Número</label>
                <input type="text" class="width-200 height-md white" id="new-end-numero" placeholder="Nº">
            </div>
            <div class="col">
                <label for="new-end-complemento">Complemento</label>
                <input type="text" class="width-400 height-md white" id="new-end-complemento" placeholder="Sala, andar...">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="new-end-bairro">Bairro</label>
                <input type="text" class="width-400 height-md white" id="new-end-bairro" placeholder="Bairro">
            </div>
            <div class="col">
                <label for="new-end-cidade">Cidade</label>
                <input type="text" class="width-300 height-md white" id="new-end-cidade" placeholder="Cidade">
            </div>
            <div class="col">
                <label for="new-end-uf">UF</label>
                <input type="text" class="width-200 height-md white" id="new-end-uf" placeholder="UF" maxlength="2">
            </div>
            <div class="col">
                <label class="inline"><input type="checkbox" id="new-end-principal"> Principal</label>
            </div>
            <div class="col">
                <label for="add-end-btn" style="color: transparent;">-</label>
                <button type="button" class="btn btn-primary-outline" id="add-end-btn">Adicionar endereço</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Vínculos com processos</h2></div>
        <div class="display-flex col col-full" id="vinculos-processos">
            <?php foreach ($pessoa['vinculos_processos'] ?? [] as $vp): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col"><?php echo htmlspecialchars($vp['numero_processo'] ?? $vp['processo_id'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vp['tipo_parte'] ?? ''); ?> / <?php echo htmlspecialchars($vp['polo'] ?? ''); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($pessoa['vinculos_processos'])): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md"><span class="minor-info">Nenhum vínculo com processo.</span></div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-top"><h2>Vínculos com instituições</h2></div>
        <div class="display-flex col col-full" id="vinculos-inst-list">
            <?php foreach ($pessoa['vinculos_instituicoes'] ?? [] as $vi): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">
                <div class="col"><?php echo htmlspecialchars($vi['instituicao_nome'] ?? $vi['instituicao_sigla'] ?? '—'); ?></div>
                <div class="col"><?php echo htmlspecialchars($vi['cargo_funcao'] ?? '—'); ?></div>
                <div class="col">
                    <button type="button" class="btn btn-secondary-outline" data-nav-href="/frame.php?page=partesinstituicoesedit&id=<?php echo urlencode($vi['id'] ?? ''); ?>">Editar</button>
                    <button type="button" class="btn btn-secondary-outline btn-del-vinculo" data-id="<?php echo htmlspecialchars($vi['id'] ?? ''); ?>">Excluir</button>
                </div>
            </div>
            <?php endforeach; ?>        
            <div class="display-flex row row-fit justify-start padding-x-md padding-y-md">
                <button type="button" id="vincular-instituicao-btn" class="btn btn-primary-outline">Vincular instituição</button>
            </div>
        </div>
        <div class="display-flex row row-full justify-end padding-x-md padding-y-md border-top">
            <div class="display-flex row row-fit justify-end gap-y-md">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-primary" id="salvar-btn">Salvar dados</button>
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

    <script>
    (function() {
        function queuePostReloadToast(type, title, message) {
            try {
                var key = 'cartorio.postReloadToast';
                var raw = sessionStorage.getItem(key);
                var fila = raw ? JSON.parse(raw) : [];
                var lista = Array.isArray(fila) ? fila : ((fila && fila.message) ? [fila] : []);
                lista.push({ type: type, title: title, message: message });
                sessionStorage.setItem(key, JSON.stringify(lista));
            } catch (e) {}
        }
        var pendingConfirmations = {};
        function requireSecondClick(key, message) {
            var now = Date.now();
            var validUntil = Number(pendingConfirmations[key] || 0);
            if (validUntil > now) {
                delete pendingConfirmations[key];
                return true;
            }
            pendingConfirmations[key] = now + 5000;
            if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
            return false;
        }
        var nomeEl = document.getElementById('nome_completo');
        if (nomeEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nomeEl.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }
        if (typeof App !== 'undefined' && App.CPF && App.CPF.init) {
            App.CPF.init('#cpf');
        }

        function syncProfissaoOutra() {
            var sel = document.getElementById('profissao_selecao');
            var wrap = document.getElementById('profissao-outra-wrap');
            var outraEl = document.getElementById('profissao_outra');
            if (!sel || !wrap) return;
            var outraToken = window.PROFISSAO_SELECT_OUTRA || '__OUTRA__';
            var show = sel.value === outraToken;
            wrap.style.display = show ? 'block' : 'none';
            if (!show && outraEl) outraEl.value = '';
        }
        var profSelEdit = document.getElementById('profissao_selecao');
        if (profSelEdit) {
            profSelEdit.addEventListener('change', syncProfissaoOutra);
            syncProfissaoOutra();
        }
        var profOutraEdit = document.getElementById('profissao_outra');
        if (profOutraEdit && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            profOutraEdit.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 120);
            });
        }

        document.getElementById('voltar-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=consultapartes';
        });
        var vincularInstBtn = document.getElementById('vincular-instituicao-btn');
        if (vincularInstBtn) {
            vincularInstBtn.addEventListener('click', function() {
                var parteId = getParteId();
                if (!parteId) return;
                window.location.href = '/frame.php?page=partesinstituicoescreate&parte_id=' + encodeURIComponent(parteId);
            });
        }
        function getParteId() {
            var el = document.getElementById('parte-id');
            return (el && el.value) ? String(el.value).trim() : '';
        }
        var parteId = getParteId();
        document.getElementById('salvar-btn').addEventListener('click', function() {
            var cpfRaw = (document.getElementById('cpf').value || '').replace(/\D/g, '');
            if (cpfRaw && cpfRaw.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(cpfRaw)) {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'CPF inválido. Verifique os dígitos.' });
                return;
            }
            var nomeVal = (document.getElementById('nome_completo').value || '').trim();
            var outraToken = window.PROFISSAO_SELECT_OUTRA || '__OUTRA__';
            var ps = document.getElementById('profissao_selecao');
            var po = document.getElementById('profissao_outra');
            function formatProfissaoDigitada(v) {
                if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                    return TextFormatter.formatProperName(v || '', 120);
                }
                return (v || '').trim();
            }
            var profissaoOutraFmt = '';
            if (ps && ps.value === outraToken && po) {
                profissaoOutraFmt = formatProfissaoDigitada(po.value);
                po.value = profissaoOutraFmt;
            }
            var payload = {
                id: parteId,
                cpf: cpfRaw || '',
                nome_completo: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nomeVal, 255) : nomeVal,
                sexo: (document.getElementById('sexo').value || '').trim(),
                data_nascimento: (document.getElementById('data_nascimento').value || '').trim(),
                profissao_selecao: ps ? (ps.value || '').trim() : '',
                profissao_outra: profissaoOutraFmt
            };
            this.disabled = true;
            fetch('/api/pessoas/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                var d = result.data;
                if (result.ok && d.success) {
                    if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Dados salvos.' });
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: (d && d.error) ? d.error : 'Erro.' });
                }
                document.getElementById('salvar-btn').disabled = false;
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                document.getElementById('salvar-btn').disabled = false;
            });
        });
        document.getElementById('add-tel-btn').addEventListener('click', function() {
            var parteIdAtual = getParteId();
            if (!parteIdAtual) {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'ID da pessoa não encontrado. Recarregue a página.' });
                return;
            }
            var tipoEl = document.getElementById('new-tel-tipo');
            var numeroEl = document.getElementById('new-tel-numero');
            var numero = (numeroEl && numeroEl.value ? numeroEl.value : '').replace(/\D/g, '').trim();
            if (!numero) { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Informe o número.' }); return; }
            var tipo = (tipoEl && tipoEl.value ? tipoEl.value : '').trim();
            this.disabled = true;
            fetch('/api/pessoas/telefones/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ parte_id: parteIdAtual, tipo: tipo || null, numero: numero })
            })
            .then(function(r) {
                var ok = r.ok;
                return r.json().then(function(d) { return { ok: ok, data: d }; }).catch(function() {
                    return { ok: false, data: { error: "Resposta invalida." } };
                });
            })
            .then(function(result) {
                if (result.ok && result.data.id) {
                    queuePostReloadToast('success', 'Sucesso', 'Telefone adicionado com sucesso.');
                    window.location.reload();
                }
                else {
                    var errMsg = (result.data && result.data.error) || "Erro ao adicionar telefone.";
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: errMsg });
                    document.getElementById('add-tel-btn').disabled = false;
                }
            })
            .catch(function(err) {
                var msg = (err && err.message) ? err.message : 'Erro de conexão.';
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao adicionar telefone: ' + msg });
                document.getElementById('add-tel-btn').disabled = false;
            });
        });
        document.querySelectorAll('.btn-del-tel').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (!requireSecondClick('excluir-telefone-parte-' + (id || ''), 'Clique novamente em Excluir em até 5 segundos para confirmar a remoção do telefone.')) return;
                fetch('/api/pessoas/telefones/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Telefone removido com sucesso.');
                        window.location.reload();
                    }
                });
            });
        });
        document.querySelectorAll('.btn-del-vinculo').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (!requireSecondClick('desvincular-instituicao-parte-' + (id || ''), 'Clique novamente em Excluir em até 5 segundos para confirmar o desvínculo da instituição.')) return;
                fetch('/api/pessoas/vinculos-instituicoes/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Instituição desvinculada com sucesso.');
                        window.location.reload();
                    }
                });
            });
        });

        document.getElementById('buscar-cep-btn').addEventListener('click', function() {
            var cep = (document.getElementById('new-end-cep').value || '').replace(/\D/g, '');
            if (cep.length !== 8) { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Informe um CEP com 8 dígitos.' }); return; }
            this.disabled = true;
            fetch('/api/cep/consulta.php?q=' + encodeURIComponent(cep), { method: 'GET', credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.mode === 'cep' && d.data) {
                    document.getElementById('new-end-logradouro').value = d.data.logradouro || '';
                    document.getElementById('new-end-bairro').value = d.data.bairro || '';
                    document.getElementById('new-end-cidade').value = d.data.cidade || '';
                    document.getElementById('new-end-uf').value = (d.data.uf || '').toUpperCase().slice(0, 2);
                    if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'CEP encontrado.' });
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: (d && d.error) || 'CEP não encontrado.' });
                }
            })
            .catch(function() { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao consultar CEP.' }); })
            .finally(function() { document.getElementById('buscar-cep-btn').disabled = false; });
        });
        document.getElementById('add-end-btn').addEventListener('click', function() {
            var parteIdAtual = getParteId();
            if (!parteIdAtual) {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'ID da pessoa não encontrado. Recarregue a página.' });
                return;
            }
            var logradouro = (document.getElementById('new-end-logradouro').value || '').trim();
            var cidade = (document.getElementById('new-end-cidade').value || '').trim();
            if (!logradouro && !cidade) { if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Informe ao menos logradouro ou cidade.' }); return; }
            var cepRaw = (document.getElementById('new-end-cep').value || '').replace(/\D/g, '');
            var payload = {
                parte_id: parteIdAtual,
                cep: cepRaw.length === 8 ? cepRaw : null,
                logradouro: logradouro || null,
                numero: (document.getElementById('new-end-numero').value || '').trim() || null,
                complemento: (document.getElementById('new-end-complemento').value || '').trim() || null,
                bairro: (document.getElementById('new-end-bairro').value || '').trim() || null,
                cidade: cidade || null,
                uf: (document.getElementById('new-end-uf').value || '').trim().toUpperCase().slice(0, 2) || null,
                principal: document.getElementById('new-end-principal').checked ? 1 : 0
            };
            this.disabled = true;
            fetch('/api/pessoas/enderecos/create.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(function(r) {
                var ok = r.ok;
                return r.json().then(function(d) { return { ok: ok, data: d }; }).catch(function() {
                    return { ok: false, data: { error: "Resposta invalida." } };
                });
            })
            .then(function(result) {
                if (result.ok && result.data.id) {
                    queuePostReloadToast('success', 'Sucesso', 'Endereço adicionado com sucesso.');
                    window.location.reload();
                }
                else {
                    var errMsg = (result.data && result.data.error) || "Erro ao adicionar endereco.";
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: errMsg });
                    document.getElementById('add-end-btn').disabled = false;
                }
            })
            .catch(function(err) {
                var msg = (err && err.message) ? err.message : 'Erro de conexão.';
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro ao adicionar endereço: ' + msg });
                document.getElementById('add-end-btn').disabled = false;
            });
        });
        document.querySelectorAll('.btn-del-end').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                if (!requireSecondClick('excluir-endereco-parte-' + (id || ''), 'Clique novamente em Excluir em até 5 segundos para confirmar a remoção do endereço.')) return;
                if (!id) return;
                fetch('/api/pessoas/enderecos/delete.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.deleted) {
                        queuePostReloadToast('success', 'Sucesso', 'Endereço removido com sucesso.');
                        window.location.reload();
                    }
                });
            });
        });
    })();
    </script>
</body>
</html>
