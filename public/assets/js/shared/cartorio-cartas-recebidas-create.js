(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const helpers = App.CartorioCreateHelpers || null;

    function initialize(config) {
        if (!helpers) {
            return;
        }

        const settings = config || {};
        const button = global.document.getElementById('cadastrar-btn');
        const gate = helpers.initCreateButtonGate({ button: button, initialLabel: 'Novo cadastro', readyLabel: 'Cadastrar' });
        const feedback = helpers.notify;
        const processoInput = global.document.getElementById('filter-processo');

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

        let alocState = null;
        if (settings.alocacaoPrefix && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function') {
            alocState = App.CartorioAlocacaoDirecionadaCreate.init(settings.alocacaoPrefix);
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

            const cidadeOrigem = App.ProperNameHelper && App.ProperNameHelper.formatProper
                ? App.ProperNameHelper.formatProper(helpers.getValue('filter-cidade'), 50)
                : helpers.formatProperName(helpers.getValue('filter-cidade'), 50);
            const ufOrigem = helpers.getValue('filter-estado');
            const finalidade = helpers.getValue('filter-tipo');

            if (!cidadeOrigem) {
                feedback('Informe a cidade de origem.', 'error');
                return;
            }
            if (!ufOrigem) {
                feedback('Informe o estado.', 'error');
                return;
            }
            if (!finalidade) {
                feedback('Informe a finalidade.', 'error');
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            if (classeVal === 'Aguarde...') {
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                gate.setWaiting();
                return;
            }

            const prazo = parseInt(global.document.getElementById('filter-prazo')?.value ?? '30', 10) || 30;
            const payload = Object.assign({
                numero_processo_origem: processo.value,
                cidade_origem: cidadeOrigem,
                uf_origem: ufOrigem,
                finalidade: finalidade,
                prazo_dias: prazo
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }

            gate.setSubmitting();
            feedback('Enviando dados...', 'info', 'Informação');

            try {
                const response = await fetch(settings.endpoint || '/api/cartorio-cartas-recebidas/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Falha ao cadastrar a carta recebida.');
                }
                gate.setInitial();
                global.location.href = settings.successRedirect || '/frame.php?page=cartasrecebidas';
            } catch (error) {
                feedback(error.message || 'Não foi possível cadastrar.', 'error');
                gate.setInitial();
            }
        });
    }

    App.CartorioCartasRecebidasCreate = {
        initialize: initialize
    };
})(window);
