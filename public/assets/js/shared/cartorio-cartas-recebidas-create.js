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
        const feedback = helpers.notify;

        if (!button) {
            return;
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
                feedback: feedback,
                requiredMessage: 'Informe o número do processo antes de cadastrar.'
            });
            if (!processo.ok) {
                return;
            }

            const cidadeOrigem = helpers.formatProperName(helpers.getValue('filter-cidade'), 50);
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

            button.disabled = true;
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
                global.location.href = settings.successRedirect || '/frame.php?page=cartasrecebidas';
            } catch (error) {
                feedback(error.message || 'Não foi possível cadastrar.', 'error');
                button.disabled = false;
            }
        });
    }

    App.CartorioCartasRecebidasCreate = {
        initialize: initialize
    };
})(window);
