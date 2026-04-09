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

        let alocState = null;
        if (settings.alocacaoPrefix && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function') {
            alocState = App.CartorioAlocacaoDirecionadaCreate.init(settings.alocacaoPrefix);
        }

        button.addEventListener('click', function () {
            const processo = helpers.normalizeProcessValue(helpers.getValue('filter-processo'), {
                inputId: 'filter-processo',
                feedback: feedback,
                requiredMessage: 'O número do processo é obrigatório.'
            });
            if (!processo.ok) {
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            if (classeVal === 'Aguarde...') {
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                return;
            }

            const tipoEdital = helpers.getValue('filter-tipo-edital');
            if (!tipoEdital) {
                feedback('O tipo de edital é obrigatório.', 'error');
                return;
            }

            const prazoDias = parseInt(global.document.getElementById('filter-prazo-dias')?.value || '0', 10);
            if (isNaN(prazoDias) || prazoDias < 1) {
                feedback('O prazo do edital é obrigatório e deve ser de pelo menos 1 dia.', 'error');
                return;
            }

            button.disabled = true;

            const payload = Object.assign({
                processo: processo.value,
                tipo_edital: tipoEdital,
                prazo_dias: prazoDias
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }

            fetch(settings.createEndpoint || '/api/cartorio-editais/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (data) {
                            throw new Error(data.error || 'Erro ao criar');
                        });
                    }
                    return response.json();
                })
                .then(function (data) {
                    const id = data && data.id;
                    if (!id) {
                        throw new Error('Resposta inválida do servidor.');
                    }

                    return fetch(settings.firstSubtaskEndpoint || '/api/cartorio-editais/criar-primeira-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ edital_id: id }),
                        credentials: 'same-origin'
                    }).then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (body) {
                                throw new Error(body.error || 'Erro ao criar subtarefas');
                            });
                        }
                        return id;
                    });
                })
                .then(function () {
                    feedback('Edital cadastrado.', 'success');
                    global.location.href = settings.successRedirect || '/frame.php?page=editais';
                })
                .catch(function (error) {
                    button.disabled = false;
                    feedback(error && error.message ? error.message : 'Erro ao cadastrar.', 'error');
                });
        });
    }

    App.CartorioEditaisCreate = {
        initialize: initialize
    };
})(window);
