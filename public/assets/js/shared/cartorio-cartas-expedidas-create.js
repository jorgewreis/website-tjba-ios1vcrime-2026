(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const helpers = App.CartorioCreateHelpers || null;

    function sanitizePrazo(value) {
        const digits = String(value || '').replace(/\D+/g, '');
        if (digits === '') {
            return '0';
        }
        return digits.slice(0, 3);
    }

    function normalizeCidadeForCompare(value) {
        return String(value || '').trim().toLowerCase()
            .normalize('NFD').replace(/\p{M}/gu, '')
            .replace(/ç/g, 'c');
    }

    function initialize(config) {
        if (!helpers) {
            return;
        }

        const settings = config || {};
        const button = global.document.getElementById('cadastrar-btn');
        const gate = helpers.initCreateButtonGate({ button: button, initialLabel: 'Novo cadastro', readyLabel: 'Cadastrar' });
        const feedback = helpers.notify;
        const processoInput = global.document.getElementById('filter-processo');
        const submitDelayMs = (typeof settings.submitDelayMs === 'number' && settings.submitDelayMs >= 0)
            ? settings.submitDelayMs
            : 0;

        if (!button) {
            return;
        }
        processoInput?.addEventListener('input', function () { gate.setWaiting(); });
        global.addEventListener('cnjInvalid', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (!id || id === 'filter-processo') gate.setError();
        });
        global.addEventListener('datajudClasseResolved', function (event) {
            const detail = event && event.detail ? event.detail : {};
            if (!detail.processoInputId || detail.processoInputId === 'filter-processo') gate.setReady();
        });

        const requiredFields = [
            { id: 'filter-processo', label: 'Número do processo' },
            { id: 'filter-tipo', label: 'Finalidade' },
            { id: 'filter-prazo', label: 'Prazo de cumprimento' },
            { id: 'filter-estado', label: 'Estado' },
            { id: 'filter-cidade', label: 'Cidade' }
        ];

        function validateRequiredFields() {
            for (const field of requiredFields) {
                const element = global.document.getElementById(field.id);
                if (!element) {
                    continue;
                }
                if (!String(element.value || '').trim()) {
                    feedback('O campo ' + field.label + ' é obrigatório.', 'error');
                    return false;
                }
            }
            return true;
        }

        button.addEventListener('click', async function (event) {
            event.preventDefault();
            if (button.disabled) {
                return;
            }

            global.document.getElementById('filter-processo')?.blur();
            global.document.getElementById('filter-cidade')?.blur();

            const processo = helpers.normalizeProcessValue(helpers.getValue('filter-processo'), {
                inputId: 'filter-processo',
                buttonGate: gate,
                feedback: feedback,
                requiredMessage: 'Informe o número do processo antes de cadastrar.'
            });
            if (!processo.ok) {
                return;
            }

            if (!validateRequiredFields()) {
                gate.setError();
                return;
            }

            const cidadeDestino = App.ProperNameHelper && App.ProperNameHelper.formatProper
                ? App.ProperNameHelper.formatProper(helpers.getValue('filter-cidade'), 50)
                : helpers.formatProperName(helpers.getValue('filter-cidade'), 50);
            if (normalizeCidadeForCompare(cidadeDestino) === 'ilheus') {
                feedback('O cadastro não é permitido para a cidade de Ilhéus (comarca de origem).', 'error');
                gate.setError();
                return;
            }

            const usuarioAtual = (typeof global.__currentUserName === 'string' && global.__currentUserName.trim() !== '')
                ? global.__currentUserName.trim()
                : (typeof global.getCurrentUserName === 'function' ? global.getCurrentUserName() : null);
            if (!usuarioAtual) {
                feedback('Não foi possível identificar o usuário logado.', 'error');
                gate.setError();
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            if (classeVal === 'Aguarde...') {
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                gate.setWaiting();
                return;
            }

            const payload = Object.assign({
                numero_processo: processo.value,
                classe: classeVal || '',
                finalidade: helpers.getValue('filter-tipo'),
                prazo: sanitizePrazo(helpers.getValue('filter-prazo')),
                estado: helpers.getValue('filter-estado'),
                cidade: cidadeDestino,
                usuario: usuarioAtual
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            gate.setSubmitting();
            feedback('Enviando dados...', 'info', 'Informação');

            try {
                const response = await fetch(settings.endpoint || '/api/cartorio-cartas-expedidas/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Falha ao salvar a carta expedida.');
                }
                if (submitDelayMs > 0) {
                    await new Promise(function (resolve) { setTimeout(resolve, submitDelayMs); });
                }
                global.location.href = settings.successRedirect || '/frame.php?page=cartasexpedidas';
            } catch (error) {
                feedback(error.message || 'Não foi possível cadastrar a carta expedida.', 'error');
                gate.setInitial();
            }
        });
    }

    App.CartorioCartasExpedidasCreate = {
        initialize: initialize
    };
})(window);
