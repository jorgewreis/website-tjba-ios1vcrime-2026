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
            var rawSnippet = '';
            if (!serverMessage && rawText) {
                rawSnippet = rawText.replace(/\s+/g, ' ').trim().slice(0, 220);
            }
            var fullMessage = serverMessage
                || (fallbackMessage + ' (HTTP ' + response.status + ')' + (rawSnippet ? ' - ' + rawSnippet : ''));
            throw new Error(fullMessage);
        }

        if (!payload || !payload.data) {
            var snippet = rawText ? rawText.replace(/\s+/g, ' ').trim().slice(0, 220) : '';
            throw new Error('Resposta inválida da API MNI' + (snippet ? ': ' + snippet : '.'));
        }

        return payload.data;
    }

    function setLoading(isLoading, submitBtn) {
        if (!submitBtn) return;
        submitBtn.disabled = !!isLoading;
        submitBtn.textContent = isLoading ? 'Consultando...' : 'Consultar dados';
    }

    function setHealthcheckLoading(isLoading, button) {
        if (!button) return;
        button.disabled = !!isLoading;
        button.textContent = isLoading ? 'Testando...' : 'Testar conectividade';
    }

    function renderRows(resultBody, label, value) {
        var row = document.createElement('tr');
        var labelCell = document.createElement('td');
        var valueCell = document.createElement('td');
        var pre = document.createElement('pre');
        pre.className = 'mni-test-pre';
        pre.textContent = String(value == null ? 'Não informado' : value);
        labelCell.textContent = label;
        valueCell.appendChild(pre);
        row.appendChild(labelCell);
        row.appendChild(valueCell);
        resultBody.appendChild(row);
    }

    function renderResult(payload) {
        var resultWrapper = document.getElementById('mni-result-wrapper');
        var resultBody = document.getElementById('mni-result-body');
        if (!resultWrapper || !resultBody) return;

        resultBody.innerHTML = '';
        var resumo = payload && payload.resumo ? payload.resumo : {};

        Object.keys(resumo).forEach(function (key) {
            renderRows(resultBody, key.replace(/_/g, ' '), resumo[key]);
        });

        renderRows(resultBody, 'partes (texto)', JSON.stringify(payload.partes || [], null, 2));
        renderRows(resultBody, 'andamentos (texto)', JSON.stringify(payload.andamentos || [], null, 2));
        renderRows(resultBody, 'documentos (texto)', JSON.stringify(payload.documentos || [], null, 2));

        resultWrapper.hidden = false;
    }

    function renderHealthcheck(payload) {
        var resultWrapper = document.getElementById('mni-result-wrapper');
        var resultBody = document.getElementById('mni-result-body');
        if (!resultWrapper || !resultBody) return;

        resultBody.innerHTML = '';
        renderRows(resultBody, 'status', payload && payload.ok ? 'Conectividade OK' : 'Falha');
        renderRows(resultBody, 'wsdl', payload ? payload.wsdl : 'Não informado');
        renderRows(resultBody, 'location', payload ? payload.location : 'Não informado');
        renderRows(resultBody, 'operação de validação', payload ? payload.auth_check_operation : 'Não informado');
        renderRows(resultBody, 'resposta da validação', JSON.stringify((payload && payload.auth_check_response) || {}, null, 2));
        renderRows(resultBody, 'funções detectadas (amostra)', JSON.stringify((payload && payload.functions_preview) || [], null, 2));
        renderRows(resultBody, 'timestamp', payload ? payload.timestamp : '');

        resultWrapper.hidden = false;
    }

    async function consultar(cnjDigits) {
        var url = '/api/mni/search-processo.php?numero=' + encodeURIComponent(cnjDigits);
        var response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        return parseApiResponse(response, 'Erro ao consultar processo');
    }

    async function healthcheck() {
        var response = await fetch('/api/mni/search-processo.php?healthcheck=1', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        return parseApiResponse(response, 'Erro ao executar teste de conectividade');
    }

    function bindForm() {
        var form = document.getElementById('mni-test-form');
        var input = document.getElementById('mni-cnj-input');
        var submitBtn = document.getElementById('mni-submit-btn');
        var healthcheckBtn = document.getElementById('mni-healthcheck-btn');
        if (!form || !input || !submitBtn || !healthcheckBtn || !global.MniCNJ) return;

        global.MniCNJ.bindInput(input);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            var digits = global.MniCNJ.onlyDigits(input.value);
            var isValid = global.MniCNJ.validarCNJ(digits);
            if (!isValid) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: 'Número CNJ inválido. Verifique o dígito verificador e tente novamente.'
                });
                input.focus();
                return;
            }

            input.value = global.MniCNJ.formatarCNJ(digits);
            setLoading(true, submitBtn);
            try {
                var data = await consultar(digits);
                renderResult(data);
                showToastSafe({
                    type: 'success',
                    message: 'Consulta processual concluída.'
                });
            } catch (error) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: error && error.message ? error.message : 'Falha na consulta processual.'
                });
            } finally {
                setLoading(false, submitBtn);
            }
        });

        healthcheckBtn.addEventListener('click', async function () {
            setHealthcheckLoading(true, healthcheckBtn);
            try {
                var diag = await healthcheck();
                renderHealthcheck(diag);
                showToastSafe({
                    type: 'success',
                    message: 'Conectividade MNI validada com sucesso.'
                });
            } catch (error) {
                showToastSafe({
                    type: 'error',
                    title: 'Erro',
                    message: error && error.message ? error.message : 'Falha no teste de conectividade.'
                });
            } finally {
                setHealthcheckLoading(false, healthcheckBtn);
            }
        });
    }

    global.addEventListener('DOMContentLoaded', bindForm);
})(typeof window !== 'undefined' ? window : this);
