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
        const reuSelect = global.document.getElementById('reu-select');
        const reuNome = global.document.getElementById('reu-nome');

        if (!button) {
            return;
        }

        let alocState = null;
        if (settings.alocacaoPrefix && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function') {
            alocState = App.CartorioAlocacaoDirecionadaCreate.init(settings.alocacaoPrefix);
        }

        function collectReu() {
            const useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
            if (useSelect) {
                return { parte_id: reuSelect.value };
            }

            return {
                parte_id: '',
                cpf: helpers.onlyDigits(global.document.getElementById('reu-cpf')?.value || ''),
                nome_completo: App.ProperNameHelper && App.ProperNameHelper.formatProper
                    ? App.ProperNameHelper.formatProper(reuNome?.value || '', 255)
                    : helpers.formatProperName(reuNome?.value || '', 255),
                sexo: helpers.getValue('reu-sexo'),
                data_nascimento: helpers.getValue('reu-data-nascimento')
            };
        }

        function collectVitimas() {
            return Array.from(global.document.querySelectorAll('.vitima-card')).map(function (card) {
                const selectEl = card.querySelector('.vitima-select');
                const nomeInput = card.querySelector('.vitima-nome');
                const useSelect = selectEl && selectEl.style.display !== 'none' && selectEl.value !== '' && selectEl.value !== '__novo__';
                const base = {
                    mulher: !!card.querySelector('.vitima-mulher')?.checked,
                    crianca_adolescente: !!card.querySelector('.vitima-crianca')?.checked,
                    idoso: !!card.querySelector('.vitima-idoso')?.checked,
                    pessoa_deficiencia: !!card.querySelector('.vitima-pcd')?.checked
                };

                if (useSelect) {
                    return Object.assign({
                        parte_id: selectEl.value,
                        cpf: '',
                        nome_completo: '',
                        sexo: '',
                        data_nascimento: ''
                    }, base);
                }

                return Object.assign({
                    parte_id: '',
                    cpf: helpers.onlyDigits(card.querySelector('.vitima-cpf')?.value || ''),
                    nome_completo: App.ProperNameHelper && App.ProperNameHelper.formatProper
                        ? App.ProperNameHelper.formatProper(nomeInput?.value || '', 255)
                        : helpers.formatProperName(nomeInput?.value || '', 255),
                    sexo: (card.querySelector('.vitima-sexo')?.value || '').trim(),
                    data_nascimento: (card.querySelector('.vitima-data-nascimento')?.value || '').trim()
                }, base);
            }).filter(function (vitima) {
                return vitima.parte_id || vitima.cpf || vitima.nome_completo || vitima.sexo || vitima.data_nascimento || vitima.mulher || vitima.crianca_adolescente || vitima.idoso || vitima.pessoa_deficiencia;
            });
        }

        function validateReu(reu) {
            if (reu.parte_id) {
                return '';
            }
            if (!reu.cpf || !reu.nome_completo || !reu.sexo || !reu.data_nascimento) {
                return 'Preencha CPF, nome completo, sexo e data de nascimento do réu (ou selecione um réu existente).';
            }
            return '';
        }

        function validateVitimas(vitimas) {
            for (let i = 0; i < vitimas.length; i += 1) {
                const vitima = vitimas[i];
                if (vitima.parte_id) {
                    continue;
                }
                if (!vitima.cpf || !vitima.nome_completo || !vitima.sexo || !vitima.data_nascimento) {
                    return 'Preencha CPF, nome completo, sexo e data de nascimento para cada vítima informada (ou selecione uma pessoa existente).';
                }
            }
            return '';
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

            const reu = collectReu();
            const reuError = validateReu(reu);
            if (reuError) {
                const reuComp = global.document.getElementById('reu-campos-complementares');
                if (reuComp && reuComp.style.display === 'none' && reuNome && (reuNome.value || '').trim().length >= 2) {
                    reuComp.style.display = '';
                    global.document.getElementById('reu-cpf')?.focus();
                }
                feedback(reuError, 'error');
                return;
            }

            if (!reu.parte_id && reu.cpf && reu.cpf.length === 11 && App.CPF && !App.CPF.validar(reu.cpf)) {
                feedback('CPF do réu inválido. Verifique os dígitos.', 'error');
                global.document.getElementById('reu-cpf')?.focus();
                return;
            }

            const vitimas = collectVitimas();
            const vitimasError = validateVitimas(vitimas);
            if (vitimasError) {
                feedback(vitimasError, 'error');
                return;
            }

            for (let i = 0; i < vitimas.length; i += 1) {
                const vitima = vitimas[i];
                if (!vitima.parte_id && vitima.cpf && vitima.cpf.length === 11 && App.CPF && !App.CPF.validar(vitima.cpf)) {
                    feedback('CPF inválido na vítima ' + (i + 1) + '. Verifique os dígitos.', 'error');
                    return;
                }
            }

            const payload = Object.assign({
                processo: processo.value,
                reu: reu,
                vitimas: vitimas
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }

            button.disabled = true;

            fetch(settings.createEndpoint || '/api/cartorio-medidas-protetivas/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (body) {
                            throw new Error(body.error || 'Erro ao criar');
                        });
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data.id) {
                        throw new Error('Resposta inválida do servidor.');
                    }

                    return fetch(settings.firstSubtaskEndpoint || '/api/cartorio-medidas-protetivas/criar-primeira-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ medida_id: data.id }),
                        credentials: 'same-origin'
                    }).then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (body) {
                                throw new Error(body.error || 'Erro ao criar subtarefas');
                            });
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    feedback('Medida protetiva cadastrada.', 'success');
                    if (data && data.cejusc_criada === true) {
                        feedback('Tarefa do Cejusc criada automaticamente e vinculada à medida protetiva.', 'info');
                    } else if (data && data.cejusc_criada === false) {
                        feedback('Tarefa do Cejusc não foi criada, pois já existe uma tarefa para o mesmo processo e réu.', 'warning');
                    }
                    global.location.href = settings.successRedirect || '/frame.php?page=medidasprotetivas';
                })
                .catch(function (error) {
                    button.disabled = false;
                    feedback(error && error.message ? error.message : 'Erro ao cadastrar medida protetiva.', 'error');
                });
        });
    }

    App.CartorioMedidasProtetivasCreate = {
        initialize: initialize
    };
})(window);
