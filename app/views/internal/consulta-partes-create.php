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
        <p class="description-page">Dados básicos da parte (pessoa). Apenas o nome completo é obrigatório; CPF, sexo e data de nascimento podem ser informados quando disponíveis.</p>
    </div>

    <div class="card-page">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Informações da parte</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="nome_completo">Nome completo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="nome_completo" name="nome_completo" placeholder="Nome completo da pessoa" required>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="cpf">CPF</label>
                <input type="text" class="width-200 height-md white" id="cpf" name="cpf" placeholder="Somente números">
            </div>
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

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Telefones (opcional)</div>
        <div id="telefones-cadastro">
            <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line telefone-row">
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
                    <input type="text" class="width-200 height-md white tel-numero" placeholder="Somente números">
                </div>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <button type="button" class="btn btn-secondary-outline" id="add-telefone-btn">Adicionar outro telefone</button>
        </div>

        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Endereços (opcional)</div>
        <div id="enderecos-cadastro">
            <div class="endereco-row row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
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
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <button type="button" class="btn btn-secondary-outline" id="add-endereco-btn">Adicionar outro endereço</button>
        </div>

        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
        </div>

        <div class="row table between padding-tb-lg padding-lr-md">
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
            var payload = {
                nome_completo: (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) ? TextFormatter.formatProperName(nome, 255) : nome,
                cpf: cpf,
                sexo: sexo || null,
                data_nascimento: data || null
            };
            if (telefones.length > 0) payload.telefones = telefones;
            if (enderecos.length > 0) payload.enderecos = enderecos;

            fetch('/api/pessoas/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
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
