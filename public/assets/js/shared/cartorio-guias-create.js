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

        function getDenunciadoPayload() {
            const denunciadoSelect = global.document.getElementById('filter-denunciado-select');
            const useSelect = denunciadoSelect
                && denunciadoSelect.style.display !== 'none'
                && denunciadoSelect.value !== ''
                && denunciadoSelect.value !== 'outro'
                && denunciadoSelect.value !== '__novo__';

            if (useSelect) {
                const option = denunciadoSelect.options[denunciadoSelect.selectedIndex];
                return {
                    reu_id: denunciadoSelect.value,
                    denunciado: option ? option.textContent : ''
                };
            }

            return {
                reu_id: '',
                denunciado: helpers.formatProperName(helpers.getValue('filter-denunciado'), 255)
            };
        }

        function doCreate(payload) {
            feedback('Enviando dados...', 'info', 'Informação');
            fetch(settings.createEndpoint || '/api/cartorio-guias/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.id) {
                        throw new Error(data.error || 'Não foi possível cadastrar.');
                    }

                    if (data.valor_multa_calculado) {
                        feedback('Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + data.valor_multa_calculado + '.', 'success', 'Cálculo automático');
                    } else {
                        feedback('Guia cadastrada com sucesso.', 'success');
                    }

                    global.location.href = settings.successRedirect || '/frame.php?page=guias';
                })
                .catch(function (error) {
                    feedback(error && error.message ? error.message : 'Erro de conexão.', 'error');
                    button.disabled = false;
                });
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            if (button.disabled) {
                return;
            }

            global.document.getElementById('filter-processo')?.blur();
            global.document.getElementById('filter-denunciado')?.blur();

            const processo = helpers.normalizeProcessValue(helpers.getValue('filter-processo'), {
                inputId: 'filter-processo',
                feedback: feedback,
                requiredMessage: 'Informe o número do processo.'
            });
            if (!processo.ok) {
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            if (classeVal === 'Aguarde...') {
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                return;
            }

            const denunciadoPayload = getDenunciadoPayload();
            if (!denunciadoPayload.denunciado) {
                feedback('Informe o denunciado/réu (selecione na lista ou digite em "Outro").', 'error');
                return;
            }

            const regime = helpers.getValue('filter-regime');
            const dataFato = helpers.getValue('filter-data-fato');
            const diasMultaValue = global.document.getElementById('filter-dias-multa')?.value;
            const diasMulta = diasMultaValue !== '' && typeof diasMultaValue !== 'undefined'
                ? parseInt(diasMultaValue, 10)
                : null;

            if (dataFato) {
                const hoje = new Date();
                hoje.setHours(0, 0, 0, 0);
                const dtFato = new Date(dataFato);
                if (dtFato >= hoje) {
                    feedback('A data do fato deve ser anterior à data de hoje.', 'error');
                    return;
                }
            }

            if (regime && ['Aberto', 'Semiaberto', 'Fechado'].indexOf(regime) === -1) {
                feedback('Regime inválido. Selecione Aberto, Semiaberto ou Fechado.', 'error');
                return;
            }

            const payload = Object.assign({
                numero_processo: processo.value,
                denunciado: denunciadoPayload.denunciado,
                tipo: helpers.getValue('filter-tipo') || 'Guia - Tipo não informado'
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (denunciadoPayload.reu_id) {
                payload.reu_id = denunciadoPayload.reu_id;
            }
            if (regime) {
                payload.regime = regime;
            }
            if (dataFato) {
                payload.data_fato = dataFato;
            }
            if (diasMulta !== null && !isNaN(diasMulta)) {
                payload.dias_multa = diasMulta;
            }

            button.disabled = true;
            feedback('Verificando duplicidade...', 'info', 'Informação');

            fetch(settings.duplicateEndpoint || '/api/cartorio-guias/check-duplicate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    numero_processo: processo.value,
                    denunciado: denunciadoPayload.denunciado,
                    tipo: payload.tipo
                })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.exists && !global.confirm('Já existe uma Guia cadastrada com o mesmo tipo e mesmo réu. Deseja cadastrar assim mesmo?')) {
                        button.disabled = false;
                        return;
                    }
                    doCreate(payload);
                })
                .catch(function () {
                    feedback('Erro ao verificar duplicidade. Tente novamente.', 'error');
                    button.disabled = false;
                });
        });
    }

    App.CartorioGuiasCreate = {
        initialize: initialize
    };
})(window);
