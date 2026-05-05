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
        const gate = helpers.initCreateButtonGate({ button: button, defaultLabel: 'Cadastrar' });
        const feedback = helpers.notify;
        const processoInput = global.document.getElementById('filter-processo');
        const reuSelect = global.document.getElementById('reu-select');
        const reuNome = global.document.getElementById('reu-nome');

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

        function validateReu(reu) {
            if (reu.parte_id) {
                return '';
            }
            const nome = (reu.nome_completo || '').trim();
            if (!nome) {
                return 'Informe o nome completo do réu ou selecione um réu existente.';
            }
            if (reu.cpf && reu.cpf.length > 0 && reu.cpf.length !== 11) {
                return 'CPF do réu incompleto. Informe 11 dígitos ou deixe em branco.';
            }
            return '';
        }

        button.addEventListener('click', function () {
            const processo = helpers.normalizeProcessValue(helpers.getValue('filter-processo'), {
                inputId: 'filter-processo',
                buttonGate: gate,
                feedback: feedback,
                requiredMessage: 'O número do processo é obrigatório.'
            });
            if (!processo.ok) {
                return;
            }

            const classeVal = helpers.getValue('filter-classe');
            const DC = global.DataJudCycle;
            let classeEmCarregamento = classeVal === 'Aguarde...';
            if (DC && typeof DC.getClasseLoadingPlaceholder === 'function' && DC.MAX_FETCH_ATTEMPTS) {
                for (let i = 1; i <= DC.MAX_FETCH_ATTEMPTS; i += 1) {
                    if (classeVal === DC.getClasseLoadingPlaceholder(i)) {
                        classeEmCarregamento = true;
                        break;
                    }
                }
            }
            if (!classeEmCarregamento && DC && classeVal === DC.PLACEHOLDER_LOADING) {
                classeEmCarregamento = true;
            }
            if (classeEmCarregamento) {
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                gate.setWaiting();
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
                gate.setError();
                return;
            }

            if (!reu.parte_id && reu.cpf && reu.cpf.length === 11 && App.CPF && !App.CPF.validar(reu.cpf)) {
                feedback('CPF do réu inválido. Verifique os dígitos.', 'error');
                gate.setError();
                global.document.getElementById('reu-cpf')?.focus();
                return;
            }

            const payload = Object.assign({
                processo: processo.value,
                reu: reu
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }

            gate.setSubmitting();

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
                    gate.setReady();
                    feedback(error && error.message ? error.message : 'Erro ao cadastrar medida protetiva.', 'error');
                });
        });
    }

    App.CartorioMedidasProtetivasCreate = {
        initialize: initialize
    };
})(window);
