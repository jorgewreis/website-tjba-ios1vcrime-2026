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
        const pendingStorageKey = 'cartorio-editais-pending-subtasks';
        const createRequestStorageKey = 'cartorio-editais-create-request';
        let pendingEditalId = '';
        let pendingCreateRequestKey = '';
        try { pendingEditalId = global.sessionStorage.getItem(pendingStorageKey) || ''; } catch (_error) {}
        try { pendingCreateRequestKey = global.sessionStorage.getItem(createRequestStorageKey) || ''; } catch (_error) {}

        function createRequestKey() {
            if (pendingCreateRequestKey !== '') {
                return pendingCreateRequestKey;
            }
            const cryptoApi = global.crypto;
            if (!cryptoApi || typeof cryptoApi.randomUUID !== 'function') {
                throw new Error('Não foi possível iniciar o cadastro com segurança. Atualize a página e tente novamente.');
            }
            pendingCreateRequestKey = cryptoApi.randomUUID();
            try {
                global.sessionStorage.setItem(createRequestStorageKey, pendingCreateRequestKey);
                if (global.sessionStorage.getItem(createRequestStorageKey) !== pendingCreateRequestKey) {
                    throw new Error('sessionStorage did not retain the request key');
                }
            } catch (_error) {
                try { global.sessionStorage.removeItem(createRequestStorageKey); } catch (_ignored) {}
                pendingCreateRequestKey = '';
                throw new Error('Não foi possível guardar a tentativa de cadastro com segurança. Libere o armazenamento da sessão e tente novamente.');
            }
            return pendingCreateRequestKey;
        }

        function clearCreateRequestKey() {
            pendingCreateRequestKey = '';
            try { global.sessionStorage.removeItem(createRequestStorageKey); } catch (_error) {}
        }

        function completeSubtasks(id) {
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
                pendingEditalId = '';
                try { global.sessionStorage.removeItem(pendingStorageKey); } catch (_error) {}
                return id;
            });
        }

        function finishCreation() {
            gate.setInitial();
            feedback('Edital cadastrado.', 'success');
            global.location.href = settings.successRedirect || '/frame.php?page=editais';
        }

        if (!button) {
            return;
        }

        processoInput?.addEventListener('input', function () {
            gate.setWaiting();
        });
        global.addEventListener('cnjInvalid', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (!id || id === 'filter-processo') {
                gate.setError();
            }
        });
        global.addEventListener('datajudClasseResolved', function (event) {
            const detail = event && event.detail ? event.detail : {};
            if (detail.processoInputId && detail.processoInputId !== 'filter-processo') {
                return;
            }
            gate.setReady();
        });

        button.addEventListener('click', function () {
            if (pendingEditalId !== '') {
                gate.setSubmitting();
                completeSubtasks(pendingEditalId).then(finishCreation).catch(function () {
                    gate.setInitial();
                    feedback('O edital já foi criado. Clique novamente em Cadastrar para concluir suas etapas.', 'error');
                });
                return;
            }

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
            if (classeVal === 'Aguarde...') {
                gate.setWaiting();
                feedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                return;
            }

            const tipoEdital = helpers.getValue('filter-tipo-edital');
            if (!tipoEdital) {
                feedback('O tipo de edital é obrigatório.', 'error');
                gate.setError();
                return;
            }

            const prazoDias = parseInt(global.document.getElementById('filter-prazo-dias')?.value || '0', 10);
            if (isNaN(prazoDias) || prazoDias < 1) {
                feedback('O prazo do edital é obrigatório e deve ser de pelo menos 1 dia.', 'error');
                gate.setError();
                return;
            }

            gate.setSubmitting();

            let requestKey;
            try {
                // Persist before sending so a lost HTTP response reuses the same
                // server-side idempotency key after retry or page reload.
                requestKey = createRequestKey();
            } catch (error) {
                gate.setInitial();
                feedback(error.message, 'error');
                return;
            }
            const payload = Object.assign({
                processo: processo.value,
                tipo_edital: tipoEdital,
                prazo_dias: prazoDias,
                idempotency_key: requestKey
            }, helpers.buildClassePayload({ classeValue: classeVal }));

            fetch(settings.createEndpoint || '/api/cartorio-editais/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (data) {
                            const error = new Error(data.error || 'Erro ao criar');
                            error.status = response.status;
                            throw error;
                        });
                    }
                    return response.json();
                })
                .then(function (data) {
                    const id = data && data.id;
                    if (!id) {
                        throw new Error('Resposta inválida do servidor.');
                    }
                    clearCreateRequestKey();
                    return id;
                })
                .then(finishCreation)
                .catch(function (error) {
                    gate.setInitial();
                    if (error && error.status === 409) {
                        clearCreateRequestKey();
                        feedback('A tentativa anterior já foi usada com outros dados. Confira a lista de editais e clique em Cadastrar novamente para iniciar uma nova tentativa.', 'error');
                        return;
                    }
                    feedback(pendingEditalId !== ''
                        ? 'O edital já foi criado. Clique novamente em Cadastrar para concluir suas etapas.'
                        : (error && error.message ? error.message : 'Erro ao cadastrar.'), 'error');
                });
        });
    }

    App.CartorioEditaisCreate = {
        initialize: initialize
    };
})(window);
