<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Consulta CEP - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Consultas - CEP -</span>
            <span class="specific">Consulta</span>
        </div>
        <h1 class="title-page">Consulta CEP</h1>
        <p class="description-page">Pesquise por CEP ou por parte do endereço. Exemplos: 45.658-250, jasmin, ilheus ou jasmin, nelson costa, ilheus.</p>
    </div>

    <div class="card-page">
        <div class="row table header">Consulta</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-busca">CEP ou endereço <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-xl height-md" id="filter-busca" placeholder="Digite um CEP ou um endereço, como jasmin, nelson costa, ilheus" required>
            </div>
        </div>

        <div class="row info">
            <span>*Para endereço, informe no formato <strong style="margin-left: 3px">logradouro, cidade</strong> ou <strong style="margin-left: 3px">logradouro, bairro, cidade</strong>. Se informar apenas o logradouro, a consulta considera Ilhéus/BA como padrão.</span>
        </div>

        <div class="row buttons">
            <button class="btn btn-secondary" id="limpar-btn" type="button">Limpar</button>
            <button class="btn btn-primary" id="consultar-btn" type="button">Consultar</button>
        </div>

        <div class="row table header">Resultados</div>
        <div id="resultado-container">
            <div class="row pagination">
                <div class="col">
                    <label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>
                </div>
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
        (function () {
            var buscaInput = document.getElementById('filter-busca');
            var consultarBtn = document.getElementById('consultar-btn');
            var limparBtn = document.getElementById('limpar-btn');
            var resultadoContainer = document.getElementById('resultado-container');
            var feedback = document.getElementById('consulta-feedback');

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function formatCep(value) {
                var digits = String(value || '').replace(/\D/g, '');
                if (digits.length !== 8) return value || '';
                return digits.slice(0, 2) + '.' + digits.slice(2, 5) + '-' + digits.slice(5);
            }

            function setFeedback(message, type) {
                if (!feedback) return;
                feedback.innerHTML = message
                    ? '<label class="status ' + (type === 'error' ? 'status-cancelled' : 'status-finished') + '">' + escapeHtml(message) + '</label>'
                    : '';
            }

            function resetResultContainer() {
                if (!resultadoContainer) return;
                resultadoContainer.innerHTML = '' +
                    '<div class="row pagination">' +
                        '<div class="col">' +
                            '<label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>' +
                        '</div>' +
                    '</div>';
                feedback = document.getElementById('consulta-feedback');
            }

            function renderSingleResult(data) {
                if (!resultadoContainer) return;
                resultadoContainer.innerHTML = '' +
                    '<div class="row filter">' +
                        '<div class="col filter">' +
                            '<label>Logradouro</label>' +
                            '<input type="text" class="width-xl height-md" readonly value="' + escapeHtml(data.logradouro || '') + '">' +
                        '</div>' +
                        '<div class="col filter">' +
                            '<label>Bairro</label>' +
                            '<input type="text" class="width-lg height-md" readonly value="' + escapeHtml(data.bairro || '') + '">' +
                        '</div>' +
                    '</div>' +
                    '<div class="row filter">' +
                        '<div class="col filter">' +
                            '<label>Cidade</label>' +
                            '<input type="text" class="width-lg height-md" readonly value="' + escapeHtml(data.cidade || '') + '">' +
                        '</div>' +
                        '<div class="col filter">' +
                            '<label>UF</label>' +
                            '<input type="text" class="width-sm height-md" readonly value="' + escapeHtml(data.uf || '') + '">' +
                        '</div>' +
                        '<div class="col filter">' +
                            '<label>CEP</label>' +
                            '<input type="text" class="width-md height-md" readonly value="' + escapeHtml(formatCep(data.cep || '')) + '">' +
                        '</div>' +
                    '</div>' +
                    '<div class="row pagination">' +
                        '<div class="col">' +
                            '<label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>' +
                        '</div>' +
                    '</div>';
                feedback = document.getElementById('consulta-feedback');
                setFeedback('1 endereço encontrado.', 'success');
            }

            function renderListResults(results) {
                if (!resultadoContainer) return;

                var html = '';
                results.forEach(function (item, index) {
                    html += '' +
                        '<div class="row table header">Endereço ' + (index + 1) + '</div>' +
                        '<div class="row filter">' +
                            '<div class="col filter">' +
                                '<label>Logradouro</label>' +
                                '<input type="text" class="width-xl height-md" readonly value="' + escapeHtml(item.logradouro || '') + '">' +
                            '</div>' +
                            '<div class="col filter">' +
                                '<label>Bairro</label>' +
                                '<input type="text" class="width-lg height-md" readonly value="' + escapeHtml(item.bairro || '') + '">' +
                            '</div>' +
                        '</div>' +
                        '<div class="row filter">' +
                            '<div class="col filter">' +
                                '<label>Cidade</label>' +
                                '<input type="text" class="width-lg height-md" readonly value="' + escapeHtml(item.cidade || '') + '">' +
                            '</div>' +
                            '<div class="col filter">' +
                                '<label>UF</label>' +
                                '<input type="text" class="width-sm height-md" readonly value="' + escapeHtml(item.uf || '') + '">' +
                            '</div>' +
                            '<div class="col filter">' +
                                '<label>CEP</label>' +
                                '<input type="text" class="width-md height-md" readonly value="' + escapeHtml(formatCep(item.cep || '')) + '">' +
                            '</div>' +
                        '</div>';
                });

                html += '' +
                    '<div class="row pagination">' +
                        '<div class="col">' +
                            '<label id="consulta-feedback" class="status-placeholder" aria-live="polite"></label>' +
                        '</div>' +
                    '</div>';

                resultadoContainer.innerHTML = html;
                feedback = document.getElementById('consulta-feedback');
                setFeedback(results.length + ' endereço(s) encontrado(s).', 'success');
            }

            function showError(message) {
                resetResultContainer();
                setFeedback(message, 'error');
                if (typeof showToast === 'function') {
                    showToast({
                        type: 'error',
                        variant: 'full',
                        title: 'Erro',
                        message: message
                    });
                }
            }

            function performSearch() {
                var query = (buscaInput && buscaInput.value ? buscaInput.value : '').trim();
                if (!query) {
                    showError('Informe um CEP ou endereço para consulta.');
                    return;
                }

                consultarBtn.disabled = true;
                resetResultContainer();
                setFeedback('Consultando...', 'success');

                fetch('/api/cep/consulta.php?q=' + encodeURIComponent(query), {
                    method: 'GET',
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (body) {
                            return { ok: response.ok, body: body };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error(result.body && result.body.error ? result.body.error : 'Não foi possível concluir a consulta.');
                        }

                        if (result.body.mode === 'cep' && result.body.data) {
                            renderSingleResult(result.body.data);
                            return;
                        }

                        if (Array.isArray(result.body.results)) {
                            renderListResults(result.body.results);
                            return;
                        }

                        throw new Error('Resposta inválida da consulta.');
                    })
                    .catch(function (error) {
                        showError(error && error.message ? error.message : 'Erro ao consultar CEP.');
                    })
                    .finally(function () {
                        consultarBtn.disabled = false;
                    });
            }

            consultarBtn?.addEventListener('click', performSearch);
            limparBtn?.addEventListener('click', function () {
                if (buscaInput) buscaInput.value = '';
                resetResultContainer();
                setFeedback('', 'success');
                buscaInput?.focus();
            });
            buscaInput?.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    performSearch();
                }
            });
        })();
    </script>
</body>
</html>
