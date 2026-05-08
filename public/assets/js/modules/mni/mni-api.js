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

    function setLoading(isLoading, submitBtn) {
        if (!submitBtn) return;
        submitBtn.disabled = !!isLoading;
        submitBtn.textContent = isLoading ? 'Consultando...' : 'Consultar dados';
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

    async function consultar(cnjDigits) {
        var response = await fetch('/api/mni/search-processo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ numero: cnjDigits })
        });

        var payload = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            throw new Error(payload && payload.error ? payload.error : 'Erro ao consultar processo.');
        }

        if (!payload || !payload.data) {
            throw new Error('Resposta MNI inválida.');
        }

        return payload.data;
    }

    function bindForm() {
        var form = document.getElementById('mni-test-form');
        var input = document.getElementById('mni-cnj-input');
        var submitBtn = document.getElementById('mni-submit-btn');
        if (!form || !input || !submitBtn || !global.MniCNJ) return;

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
    }

    global.addEventListener('DOMContentLoaded', bindForm);
})(typeof window !== 'undefined' ? window : this);
