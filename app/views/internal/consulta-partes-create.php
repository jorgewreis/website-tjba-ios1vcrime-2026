<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
$__connPartes = Conection::getInstance()->getConnection();
$profissoesLista = (new PartesRepository($__connPartes))->listProfissoesDistintas();
$profissaoAtual = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta Partes - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
        <p class="description-page">Dados básicos da parte (pessoa). Apenas o nome completo é obrigatório; documentos, sexo e data de nascimento podem ser informados quando disponíveis.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações da parte</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="nome_completo">Nome completo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="nome_completo" name="nome_completo" placeholder="Digite o nome completo" required>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="sexo">Sexo</label>
                <select class="width-200 height-md white" id="sexo" name="sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col">
                <label for="data_nascimento">Data de nascimento</label>
                <input type="date" class="width-200 height-md white" id="data_nascimento" name="data_nascimento">
            </div>
        </div>

        <?php include __DIR__ . '/_partes_profissao_fields.php'; ?>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Documentos (opcional)</h2>
            </div>
        </div>
        <div id="documentos-cadastro">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md documento-row">
                <div class="col">
                    <label>Tipo</label>
                    <select class="width-200 height-md white doc-tipo">
                        <?php $documentoTipoSelecionado = ''; include __DIR__ . '/_partes_documento_tipo_options.php'; ?>
                    </select>
                </div>
                <div class="col">
                    <label>Número</label>
                    <input type="text" class="width-300 height-md white doc-numero" placeholder="Número do documento" maxlength="60">
                </div>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <button type="button" class="btn btn-secondary-outline" id="add-documento-btn">Adicionar outro documento</button>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Telefones (opcional)</h2>
            </div>
        </div>
        <div id="telefones-cadastro">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md telefone-row">
                <div class="col">
                    <label>Tipo</label>
                    <select class="width-200 height-md white tel-tipo">
                        <option value="">Selecione</option>
                        <option value="Telefone fixo">Telefone fixo</option>
                        <option value="Celular">Celular</option>
                    </select>
                </div>
                <div class="col">
                    <label>Número <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-200 height-md white tel-numero" placeholder="(00) 00000-0000">
                </div>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <button type="button" class="btn btn-secondary-outline" id="add-telefone-btn">Adicionar outro telefone</button>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Endereços (opcional)</h2>
            </div>
        </div>
        <div id="enderecos-cadastro">
            <div class="endereco-row display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col"><label>CEP</label><input type="text" class="end-cep width-100 height-md white" placeholder="00000-000" maxlength="9"></div>
                <div class="col"><label>Logradouro</label><input type="text" class="end-logradouro width-600 height-md white" placeholder="Rua, avenida..."></div>
                <div class="col"><label>Número</label><input type="text" class="end-numero width-100 height-md white" placeholder="Nº"></div>
                <div class="col"><label>Complemento</label><input type="text" class="end-complemento width-200 height-md white" placeholder="Sala, andar..."></div>
                <div class="col"><label>Bairro</label><input type="text" class="end-bairro width-200 height-md white" placeholder="Bairro"></div>
                <div class="col"><label>Cidade</label><input type="text" class="end-cidade width-200 height-md white" placeholder="Cidade"></div>
                <div class="col"><label>UF</label><input type="text" class="end-uf width-xs height-md white" placeholder="UF" maxlength="2"></div>
                <div class="col"><label class="inline"><input type="checkbox" class="end-principal"> Principal</label></div>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <button type="button" class="btn btn-secondary-outline" id="add-endereco-btn">Adicionar outro endereço</button>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>

        
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/shared/cpf.js"></script>
    <script>
    (function() {
        var nomeEl = document.getElementById('nome_completo');
        if (nomeEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nomeEl.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 255);
            });
        }

        function aplicarMascaraDocumentoRow(row) {
            if (!row || typeof App === 'undefined' || !App.CPF) return;
            var tipoEl = row.querySelector('.doc-tipo');
            var numeroEl = row.querySelector('.doc-numero');
            if (!tipoEl || !numeroEl) return;
            if (tipoEl.value === 'CPF') {
                App.CPF.init(numeroEl);
            }
        }

        function bindDocumentoRow(row) {
            if (!row) return;
            var tipoEl = row.querySelector('.doc-tipo');
            if (tipoEl) {
                tipoEl.addEventListener('change', function() { aplicarMascaraDocumentoRow(row); });
            }
            aplicarMascaraDocumentoRow(row);
        }

        document.querySelectorAll('#documentos-cadastro .documento-row').forEach(bindDocumentoRow);

        document.getElementById('add-documento-btn').addEventListener('click', function() {
            var container = document.getElementById('documentos-cadastro');
            var tpl = document.querySelector('#documentos-cadastro .documento-row').cloneNode(true);
            var sel = tpl.querySelector('.doc-tipo');
            if (sel) sel.selectedIndex = 0;
            var num = tpl.querySelector('.doc-numero');
            if (num) num.value = '';
            container.appendChild(tpl);
            bindDocumentoRow(tpl);
        });

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
        var profSelEl = document.getElementById('profissao_selecao');
        if (profSelEl) {
            profSelEl.addEventListener('change', syncProfissaoOutra);
            syncProfissaoOutra();
        }
        var profOutraEl = document.getElementById('profissao_outra');
        if (profOutraEl && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            profOutraEl.addEventListener('blur', function() {
                this.value = TextFormatter.formatProperName(this.value, 120);
            });
        }

        document.getElementById('voltar-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=consultapartes';
        });

        document.getElementById('add-telefone-btn').addEventListener('click', function() {
            var container = document.getElementById('telefones-cadastro');
            var tpl = document.querySelector('#telefones-cadastro .telefone-row').cloneNode(true);
            var sel = tpl.querySelector('.tel-tipo');
            if (sel) sel.selectedIndex = 0;
            tpl.querySelector('.tel-numero').value = '';
            container.appendChild(tpl);
        });

        document.getElementById('add-endereco-btn').addEventListener('click', function() {
            var container = document.getElementById('enderecos-cadastro');
            var tpl = document.querySelector('#enderecos-cadastro .endereco-row').cloneNode(true);
            tpl.querySelector('.end-cep').value = '';
            tpl.querySelector('.end-logradouro').value = '';
            tpl.querySelector('.end-numero').value = '';
            tpl.querySelector('.end-complemento').value = '';
            tpl.querySelector('.end-bairro').value = '';
            tpl.querySelector('.end-cidade').value = '';
            tpl.querySelector('.end-uf').value = '';
            tpl.querySelector('.end-principal').checked = false;
            container.appendChild(tpl);
        });

        document.getElementById('cadastrar-btn').addEventListener('click', function() {
            var nome = (document.getElementById('nome_completo').value || '').trim();
            if (!nome) {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Nome completo é obrigatório.' });
                return;
            }
            var documentos = [];
            var tiposUsados = {};
            var docRows = document.querySelectorAll('#documentos-cadastro .documento-row');
            for (var di = 0; di < docRows.length; di++) {
                var drow = docRows[di];
                var tipoDoc = (drow.querySelector('.doc-tipo') && drow.querySelector('.doc-tipo').value ? drow.querySelector('.doc-tipo').value : '').trim().toUpperCase();
                var numeroDoc = (drow.querySelector('.doc-numero') && drow.querySelector('.doc-numero').value ? drow.querySelector('.doc-numero').value : '').trim();
                if (tipoDoc === '' && numeroDoc === '') continue;
                if (tipoDoc === '' || numeroDoc === '') {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Informe tipo e número para cada documento preenchido.' });
                    return;
                }
                if (tiposUsados[tipoDoc]) {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Informe apenas um documento por tipo (' + tipoDoc + ').' });
                    return;
                }
                tiposUsados[tipoDoc] = true;
                if (tipoDoc === 'CPF') {
                    numeroDoc = numeroDoc.replace(/\D/g, '');
                    if (numeroDoc.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(numeroDoc)) {
                        if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'CPF inválido. Verifique os dígitos.' });
                        return;
                    }
                }
                documentos.push({ tipo: tipoDoc, numero: numeroDoc });
            }

            var sexo = (document.getElementById('sexo').value || '').trim();
            var data = (document.getElementById('data_nascimento').value || '').trim();

            var telefones = [];
            document.querySelectorAll('#telefones-cadastro .telefone-row').forEach(function(row) {
                var numeroEl = row.querySelector('.tel-numero');
                var tipoEl = row.querySelector('.tel-tipo');
                var numero = (numeroEl && numeroEl.value ? numeroEl.value : '').replace(/\D/g, '').trim();
                if (numero !== '') {
                    var tipo = (tipoEl && tipoEl.value ? tipoEl.value : '').trim();
                    telefones.push({ tipo: tipo || null, numero: numero });
                }
            });

            var enderecos = [];
            document.querySelectorAll('#enderecos-cadastro .endereco-row').forEach(function(row) {
                var cep = (row.querySelector('.end-cep') && row.querySelector('.end-cep').value ? row.querySelector('.end-cep').value : '').replace(/\D/g, '').trim();
                var logradouro = (row.querySelector('.end-logradouro') && row.querySelector('.end-logradouro').value ? row.querySelector('.end-logradouro').value : '').trim();
                var numero = (row.querySelector('.end-numero') && row.querySelector('.end-numero').value ? row.querySelector('.end-numero').value : '').trim();
                var complemento = (row.querySelector('.end-complemento') && row.querySelector('.end-complemento').value ? row.querySelector('.end-complemento').value : '').trim();
                var bairro = (row.querySelector('.end-bairro') && row.querySelector('.end-bairro').value ? row.querySelector('.end-bairro').value : '').trim();
                var cidade = (row.querySelector('.end-cidade') && row.querySelector('.end-cidade').value ? row.querySelector('.end-cidade').value : '').trim();
                var uf = (row.querySelector('.end-uf') && row.querySelector('.end-uf').value ? row.querySelector('.end-uf').value : '').trim().toUpperCase().slice(0, 2);
                var principal = row.querySelector('.end-principal') && row.querySelector('.end-principal').checked;
                if (cep !== '' || logradouro !== '' || numero !== '' || complemento !== '' || bairro !== '' || cidade !== '' || uf !== '') {
                    enderecos.push({
                        cep: cep || null,
                        logradouro: logradouro || null,
                        numero: numero || null,
                        complemento: complemento || null,
                        bairro: bairro || null,
                        cidade: cidade || null,
                        uf: uf || null,
                        principal: principal ? 1 : 0
                    });
                }
            });

            this.disabled = true;
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
                nome_completo: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nome, 255) : nome,
                sexo: sexo || null,
                data_nascimento: data || null,
                profissao_selecao: ps ? (ps.value || '').trim() : '',
                profissao_outra: profissaoOutraFmt
            };
            if (documentos.length > 0) payload.documentos = documentos;
            if (telefones.length > 0) payload.telefones = telefones;
            if (enderecos.length > 0) payload.enderecos = enderecos;

            fetch('/api/pessoas/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
            .then(function(result) {
                if (result.ok && result.data.id) {
                    if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Pessoa cadastrada.' });
                    window.location.href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(result.data.id);
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: result.data.error || 'Erro ao cadastrar.' });
                    document.getElementById('cadastrar-btn').disabled = false;
                }
            })
            .catch(function() {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Erro de conexão.' });
                document.getElementById('cadastrar-btn').disabled = false;
            });
        });
    })();
    </script>
</body>
</html>
