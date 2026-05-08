(function (global) {
    'use strict';

    function showToastSafe(payload) {
        if (typeof global.showToast === 'function') {
            global.showToast(payload);
            return;
        }
        if (global.App && global.App.Toast && typeof global.App.Toast.show === 'function') {
            global.App.Toast.show(payload);
        }
    }

    async function parseApiResponse(response, fallbackMessage) {
        var rawText = await response.text();
        var payload = {};
        try {
            payload = rawText ? JSON.parse(rawText) : {};
        } catch (e) {
            payload = {};
        }

        if (!response.ok) {
            var serverMessage = payload && payload.error ? payload.error : '';
            throw new Error(serverMessage || fallbackMessage + ' (HTTP ' + response.status + ')');
        }

        if (!payload || !payload.data) {
            throw new Error('Resposta inválida da API pública PJe.');
        }

        return payload.data;
    }

    function setButtonLoading(button, isLoading, loadingText, idleText) {
        if (!button) return;
        button.disabled = !!isLoading;
        button.textContent = isLoading ? loadingText : idleText;
    }

    function renderRow(resultBody, label, value) {
        var row = document.createElement('tr');
        var labelCell = document.createElement('td');
        var valueCell = document.createElement('td');
        var pre = document.createElement('pre');

        pre.className = 'pje-publico-test-pre';
        pre.textContent = typeof value === 'string' ? value : JSON.stringify(value == null ? 'Não informado' : value, null, 2);
        labelCell.textContent = label;
        valueCell.appendChild(pre);
        row.appendChild(labelCell);
        row.appendChild(valueCell);
        resultBody.appendChild(row);
    }

    function renderPayload(payload, mode) {
        var resultWrapper = document.getElementById('pje-publico-result-wrapper');
        var resultBody = document.getElementById('pje-publico-result-body');
        if (!resultWrapper || !resultBody) return;

        resultBody.textContent = '';
        renderRow(resultBody, 'modo', mode);
        renderRow(resultBody, 'resumo', payload && payload.resumo ? payload.resumo : {});
        renderRow(resultBody, 'partes', payload && payload.partes ? payload.partes : []);
        renderRow(resultBody, 'andamentos', payload && payload.andamentos ? payload.andamentos : []);
        renderRow(resultBody, 'documentos', payload && payload.documentos ? payload.documentos : []);
        renderRow(resultBody, 'meta consulta', payload && payload.meta_consulta ? payload.meta_consulta : {});
        if (payload && payload.dados_brutos && Object.keys(payload.dados_brutos).length) {
            renderRow(resultBody, 'dados brutos', payload.dados_brutos);
        }

        resultWrapper.hidden = false;
    }

    async function consultar(cnjDigits, raw) {
        var url = '/api/pje-publico/search-processo.php?numero=' + encodeURIComponent(cnjDigits);
        if (raw) {
            url += '&raw=1';
        }

        var response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        return parseApiResponse(response, 'Erro ao consultar processo no PJe público');
    }

    async function healthcheck() {
        var response = await fetch('/api/pje-publico/search-processo.php?healthcheck=1', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        return parseApiResponse(response, 'Erro ao executar teste de conectividade PJe público');
    }

    function bindForm() {
        var form = document.getElementById('pje-publico-test-form');
        var input = document.getElementById('pje-publico-cnj-input');
        var submitBtn = document.getElementById('pje-publico-submit-btn');
        var healthcheckBtn = document.getElementById('pje-publico-healthcheck-btn');
        var rawInput = document.getElementById('pje-publico-raw-input');
        if (!form || !input || !submitBtn || !healthcheckBtn || !global.PjePublicoCNJ) return;

        global.PjePublicoCNJ.bindInput(input);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            var digits = global.PjePublicoCNJ.onlyDigits(input.value);
            if (!global.PjePublicoCNJ.validarCNJ(digits)) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: 'Número CNJ inválido. Verifique o dígito verificador e tente novamente.'
                });
                input.focus();
                return;
            }

            input.value = global.PjePublicoCNJ.formatarCNJ(digits);
            setButtonLoading(submitBtn, true, 'Consultando...', 'Consultar dados');
            try {
                var data = await consultar(digits, rawInput && rawInput.checked);
                renderPayload(data, rawInput && rawInput.checked ? 'consulta raw' : 'consulta normal');
                showToastSafe({
                    type: 'success',
                    message: 'Consulta pública PJe concluída.'
                });
            } catch (error) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: error && error.message ? error.message : 'Falha na consulta pública PJe.'
                });
            } finally {
                setButtonLoading(submitBtn, false, 'Consultando...', 'Consultar dados');
            }
        });

        healthcheckBtn.addEventListener('click', async function () {
            setButtonLoading(healthcheckBtn, true, 'Testando...', 'Testar conectividade');
            try {
                var data = await healthcheck();
                renderPayload(data, 'healthcheck');
                showToastSafe({
                    type: 'success',
                    message: 'Conectividade PJe público validada com sucesso.'
                });
            } catch (error) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: error && error.message ? error.message : 'Falha no teste de conectividade PJe público.'
                });
            } finally {
                setButtonLoading(healthcheckBtn, false, 'Testando...', 'Testar conectividade');
            }
        });
    }

    global.addEventListener('DOMContentLoaded', bindForm);
})(typeof window !== 'undefined' ? window : this);
