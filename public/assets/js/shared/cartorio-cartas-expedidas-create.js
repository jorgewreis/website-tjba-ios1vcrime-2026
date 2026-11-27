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
        const ccmConfirmed = new Set();
        let cityLookupPending = false;
        let saving = false;
        const restoreAfterError = function () {
            if (settings.retryOnError) gate.setReady();
            else gate.setInitial();
        };
        const pendingRequestStorageKey = 'cartorio.cartasExpedidas.pendingRequestKey';
        let pendingRequestSignature = '';
        let pendingRequestKey = '';
        let pendingRequestKeyRestored = false;

        try {
            if (global.sessionStorage) {
                const storageProbe = pendingRequestStorageKey + '.probe';
                global.sessionStorage.setItem(storageProbe, '1');
                global.sessionStorage.removeItem(storageProbe);
                pendingRequestKey = String(global.sessionStorage.getItem(pendingRequestStorageKey) || '');
                pendingRequestKeyRestored = /^[a-f0-9]{64}$/i.test(pendingRequestKey);
                if (!pendingRequestKeyRestored) pendingRequestKey = '';
            }
        } catch (error) {
            pendingRequestKey = '';
            pendingRequestKeyRestored = false;
        }

        function resetPendingRequestState() {
            pendingRequestSignature = '';
            pendingRequestKey = '';
            pendingRequestKeyRestored = false;
        }

        function clearPendingRequestKey() {
            resetPendingRequestState();
            try {
                if (global.sessionStorage) global.sessionStorage.removeItem(pendingRequestStorageKey);
            } catch (error) {
                // A falha ao limpar não afeta uma criação confirmada; a próxima carga
                // pode receber conflito e remover a chave antiga com segurança.
            }
        }

        function persistPendingRequestKey() {
            try {
                const storage = global.sessionStorage;
                if (!storage || !pendingRequestKey) return false;
                storage.setItem(pendingRequestStorageKey, pendingRequestKey);
                return String(storage.getItem(pendingRequestStorageKey) || '') === pendingRequestKey;
            } catch (error) {
                return false;
            }
        }

        function newRequestKey() {
            if (!global.crypto || typeof global.crypto.getRandomValues !== 'function') return '';
            const bytes = new Uint8Array(32);
            global.crypto.getRandomValues(bytes);
            return Array.from(bytes, function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
        }
        function confirmCcm(city, uf) {
            const key = normalizeCidadeForCompare(city) + '|' + String(uf || '').trim().toUpperCase();
            if (ccmConfirmed.has(key)) return true;
            const accepted = global.confirm('A comarca de ' + city + ' j\u00e1 possui Central de Mandados (CCM). Em regra, deve ser expedido Mandado em vez de Carta Precat\u00f3ria. Deseja cadastrar a carta mesmo assim?');
            if (accepted) ccmConfirmed.add(key);
            return accepted;
        }

        if (!button) {
            return;
        }
        App.CartorioCartasExpedidasCreate.confirmCcm = confirmCcm;
        processoInput?.addEventListener('input', function () { if (!saving && !cityLookupPending) gate.setWaiting(); });
        global.addEventListener('cnjInvalid', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (!saving && !cityLookupPending && (!id || id === 'filter-processo')) gate.setError();
        });
        global.addEventListener('datajudClasseResolved', function (event) {
            const detail = event && event.detail ? event.detail : {};
            if (!saving && !cityLookupPending && (!detail.processoInputId || detail.processoInputId === 'filter-processo')) gate.setReady();
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

        function getSubmissionInputSnapshot() {
            return JSON.stringify({
                processo: helpers.getValue('filter-processo'),
                classe: helpers.getValue('filter-classe'),
                finalidade: helpers.getValue('filter-tipo'),
                prazo: helpers.getValue('filter-prazo'),
                estado: helpers.getValue('filter-estado'),
                cidade: helpers.getValue('filter-cidade'),
                prioridade: global.document.getElementById('ce-prioridade-tarefa')?.value || ''
            });
        }

        button.addEventListener('click', async function (event) {
            event.preventDefault();
            if (button.disabled || cityLookupPending || saving) {
                return;
            }

            global.document.getElementById('filter-processo')?.blur();

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

            const cidadeInput = global.document.getElementById('filter-cidade');
            const estadoSelect = global.document.getElementById('filter-estado');
            let confirmacaoCcm = false;
            const cidadeDestino = App.ProperNameHelper && App.ProperNameHelper.formatProper
                ? App.ProperNameHelper.formatProper(helpers.getValue('filter-cidade'), 50)
                : helpers.formatProperName(helpers.getValue('filter-cidade'), 50);
            const cityLookupEndpoint = String(settings.cityLookupEndpoint || '').trim();
            if (cityLookupEndpoint) {
                // Fecha a guarda antes do primeiro await. Sem isso, cliques
                // durante a consulta da cidade iniciavam múltiplos cadastros.
                const inputSnapshot = getSubmissionInputSnapshot();
                cityLookupPending = true;
                gate.setSubmitting();
                try {
                    const lookupUrl = new URL(cityLookupEndpoint, global.location.origin);
                    lookupUrl.searchParams.set('cidade', cidadeDestino);
                    lookupUrl.searchParams.set('uf', estadoSelect ? estadoSelect.value : '');
                    let lookupData;
                    if (typeof settings.lookupCity === 'function') {
                        lookupData = await settings.lookupCity(cidadeDestino, estadoSelect?.value || '');
                    } else {
                        const lookupResponse = await fetch(lookupUrl.toString(), { credentials: 'same-origin' });
                        lookupData = await lookupResponse.json();
                        if (!lookupResponse.ok) {
                            throw new Error(lookupData.error || 'Não foi possível validar a comarca de destino.');
                        }
                    }
                    if (inputSnapshot !== getSubmissionInputSnapshot()) {
                        feedback('Os dados do cadastro mudaram durante a validação da comarca. Confira os campos e tente novamente.', 'error');
                        gate.setReady();
                        return;
                    }
                    if (lookupData.is_ccm === true || String(lookupData.status_cidade || '').trim().toUpperCase() === 'CCM') {
                        if (!confirmCcm(cidadeDestino, estadoSelect ? estadoSelect.value : '')) {
                            gate.setReady();
                            return;
                        }
                        confirmacaoCcm = true;
                        if (cidadeInput) delete cidadeInput.dataset.ccmBlocked;
                    }
                } catch (error) {
                    feedback(error.message || 'Não foi possível validar a comarca de destino.', 'error');
                    if (settings.retryOnError) gate.setReady();
                    else gate.setError();
                    return;
                } finally {
                    cityLookupPending = false;
                }
            }

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
            payload.confirmacao_ccm = confirmacaoCcm;
            const prioridade = global.document.getElementById('ce-prioridade-tarefa');
            if (prioridade) payload.prioridade_tarefa = prioridade.value;

            const requestSignature = JSON.stringify(payload);
            if (requestSignature !== pendingRequestSignature) {
                if (!pendingRequestKeyRestored) pendingRequestKey = newRequestKey();
                pendingRequestSignature = requestSignature;
                pendingRequestKeyRestored = false;
                if (!pendingRequestKey) {
                    resetPendingRequestState();
                    feedback('Não foi possível guardar a chave de envio seguro nesta sessão. Ative o armazenamento da sessão e tente novamente.', 'error');
                    gate.setError();
                    return;
                }
            }
            // Confirme a persistência em cada tentativa, inclusive após a
            // revalidação do formulário ou quando a chave foi restaurada.
            if (!persistPendingRequestKey()) {
                resetPendingRequestState();
                feedback('Não foi possível guardar a chave de envio seguro nesta sessão. Ative o armazenamento da sessão e tente novamente.', 'error');
                gate.setError();
                return;
            }
            payload.idempotency_key = pendingRequestKey;

            saving = true;
            gate.setSubmitting();
            if (!settings.onSuccess) feedback('Enviando dados...', 'info', 'Informação');

            let response = null;
            try {
                response = await fetch(settings.endpoint || '/api/cartorio-cartas-expedidas/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!response.ok || (settings.onSuccess && (typeof data?.id !== 'string' || !data.id.trim()))) {
                    throw new Error(data?.error || 'Não foi possível confirmar o cadastro. Tente novamente.');
                }
                clearPendingRequestKey();
                if (typeof settings.onSuccess === 'function' && settings.onSuccess() === true) return;
                if (submitDelayMs > 0) {
                    await new Promise(function (resolve) { setTimeout(resolve, submitDelayMs); });
                }
                global.location.href = settings.successRedirect || '/frame.php?page=cartasexpedidas';
            } catch (error) {
                if (response && response.status === 409) clearPendingRequestKey();
                feedback(error.message || 'Não foi possível cadastrar a carta expedida.', 'error');
                restoreAfterError();
            } finally {
                saving = false;
            }
        });
    }

    App.CartorioCartasExpedidasCreate = {
        initialize: initialize
    };
})(window);
