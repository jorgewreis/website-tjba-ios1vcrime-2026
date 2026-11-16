(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function notify(type, title, message) {
        if (common) return common.notify(type, title, message);
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function createSecondClickGuard() {
        if (common && typeof common.createSecondClickGuard === 'function') {
            return common.createSecondClickGuard();
        }
        const pending = {};
        return function requireSecondClick(key, message) {
            const now = Date.now();
            const validUntil = Number(pending[key] || 0);
            if (validUntil > now) {
                delete pending[key];
                return true;
            }
            pending[key] = now + 5000;
            notify('info', 'Confirmação', message);
            return false;
        };
    }

    function queuePostReloadToast(type, title, message) {
        if (common && typeof common.queuePostReloadToast === 'function') {
            return common.queuePostReloadToast(type, title, message, 'cartorio.postReloadToast');
        }
        try {
            const key = 'cartorio.postReloadToast';
            const raw = global.sessionStorage.getItem(key);
            const fila = raw ? JSON.parse(raw) : [];
            const lista = Array.isArray(fila) ? fila : (fila && fila.message ? [fila] : []);
            lista.push({ type: type, title: title, message: message });
            global.sessionStorage.setItem(key, JSON.stringify(lista));
        } catch (error) {
            // noop
        }
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (common && typeof common.notifyByKey === 'function') {
            common.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        notify(fallbackType, fallbackTitle, fallbackMessage);
    }

    function resolveToastMessageByKey(key, vars, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            return Promise.resolve(fallbackMessage);
        }
        return catalog.resolve(key, vars || {}).then(function (toast) {
            return (toast && toast.message) ? toast.message : fallbackMessage;
        }).catch(function () {
            return fallbackMessage;
        });
    }

    function sanitizePrazo(value) {
        const digits = String(value || '').replace(/\D+/g, '');
        return digits === '' ? '0' : digits.slice(0, 3);
    }

    function normalizeCidade(value) {
        return String(value || '').trim().toLowerCase()
            .normalize('NFD').replace(/\p{M}/gu, '')
            .replace(/ç/g, 'c');
    }

    function formatCidade(value) {
        if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            return App.ProperNameHelper.formatProper(value, 120);
        }
        if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
            return global.TextFormatter.formatProperName(value, 120);
        }
        return value;
    }

    function initialize(ctx) {
        const context = ctx || global.__CARTAS_RECEBIDAS_CTX || {};
        const initialData = context.initial || {};
        const cartaId = context.cartaId || '';
        const endpoints = context.endpoints || {};

        const salvarBtn = global.document.getElementById('salvar-btn');
        const cidadeInput = global.document.getElementById('filter-cidade');
        const estadoSelect = global.document.getElementById('filter-estado');
        const finalidadeSelect = global.document.getElementById('filter-tipo');
        const prazoSelect = global.document.getElementById('filter-prazo');
        const statusSelect = global.document.getElementById('filter-status');
        const observacoesInput = global.document.getElementById('filter-observacoes');
        const hiddenComarcaInput = global.document.getElementById('hidden-comarca-id');
        const lookupStatus = global.document.getElementById('comarca-lookup-status');
        const lookupRetry = global.document.getElementById('comarca-lookup-retry');
        const voltarBtn = global.document.getElementById('voltar-btn');
        const btnCancelarTarefa = global.document.getElementById('cancelar-tarefa-btn');
        const btnExcluirTarefa = global.document.getElementById('excluir-tarefa-btn');

        if (voltarBtn) {
            voltarBtn.addEventListener('click', function (event) {
                event.preventDefault();
                global.location.href = '/frame.php?page=cartasrecebidas';
            }, { capture: true });
        }

        if (!salvarBtn || !finalidadeSelect || !prazoSelect || !cidadeInput || !estadoSelect) {
            return;
        }

        function currentState() {
            const state = {
                finalidade: (finalidadeSelect.value || '').trim(),
                prazo: sanitizePrazo(prazoSelect.value),
                cidade: cidadeInput.value.trim(),
                estado: (estadoSelect.value || '').trim()
            };
            if (statusSelect) state.status = (statusSelect.value || '').trim();
            if (observacoesInput) state.observacoes = (observacoesInput.value || '').trim();
            return state;
        }

        function hasChanges() {
            const cur = currentState();
            if (initialData.finalidade !== cur.finalidade) return true;
            if (String(initialData.prazoDias || '0') !== cur.prazo) return true;
            if (normalizeCidade(initialData.cidade) !== normalizeCidade(cur.cidade)) return true;
            if ((initialData.estado || '') !== cur.estado) return true;
            if (statusSelect && (initialData.status || '') !== (cur.status || '')) return true;
            if (observacoesInput && (initialData.observacoes || '') !== (cur.observacoes || '')) return true;
            return false;
        }

        let lookupVersion = 0;
        let lookupPending = false;
        let submitting = false;
        let validatedCity = normalizeCidade(cidadeInput.value);
        let validatedUf = (estadoSelect.value || '').trim().toUpperCase();

        function validComarca() {
            return !!hiddenComarcaInput && !!hiddenComarcaInput.value.trim() && !lookupPending &&
                validatedCity === normalizeCidade(cidadeInput.value) && validatedUf === (estadoSelect.value || '').trim().toUpperCase();
        }
        function updateSalvarButton() {
            salvarBtn.disabled = submitting || !hasChanges() || !validComarca();
        }
        function showLookupStatus(message, retry) {
            if (lookupStatus) lookupStatus.textContent = message;
            if (lookupRetry) lookupRetry.hidden = !retry;
        }
        function invalidateComarca() {
            lookupVersion++;
            lookupPending = false;
            validatedCity = '';
            validatedUf = '';
            if (hiddenComarcaInput) hiddenComarcaInput.value = '';
            showLookupStatus('Informe a cidade e o estado e saia do campo para consultar.', false);
            updateSalvarButton();
        }

        function applyCidadeFormatting() {
            cidadeInput.value = formatCidade(cidadeInput.value);
        }

        function fetchCidadeInfo() {
            const cidade = cidadeInput.value.trim();
            const uf = (estadoSelect.value || '').trim().toUpperCase();
            const version = ++lookupVersion;
            if (hiddenComarcaInput) hiddenComarcaInput.value = '';
            validatedCity = '';
            validatedUf = '';
            if (cidade.length < 2 || !uf) { lookupPending = false; showLookupStatus('Informe uma cidade com ao menos dois caracteres e selecione o estado.', false); updateSalvarButton(); return; }
            lookupPending = true;
            showLookupStatus('Consultando comarca...', false);
            updateSalvarButton();
            const endpoint = endpoints.lookupCidade || '/api/cartorio-cartas-recebidas/buscar-cidade.php?cidade=';
            fetch(endpoint + encodeURIComponent(cidade) + '&uf=' + encodeURIComponent(uf))
                .then((response) => { if (!response.ok) throw new Error('Consulta indisponível'); return response.json(); })
                .then((data) => {
                    if (version !== lookupVersion || normalizeCidade(cidadeInput.value) !== normalizeCidade(cidade) || (estadoSelect.value || '').trim().toUpperCase() !== uf) return;
                    if (!data || !data.found || !data.id || String(data.uf || '').toUpperCase() !== uf) {
                        showLookupStatus('Cidade não encontrada neste estado. Confira os dados e consulte novamente.', true);
                        return;
                    }
                    if (String(data.status_cidade || '').trim().toLowerCase() === 'agregada' && data.nome) {
                        cidadeInput.value = formatCidade(data.nome);
                    }
                    if (hiddenComarcaInput) hiddenComarcaInput.value = data.id;
                    validatedCity = normalizeCidade(cidadeInput.value);
                    validatedUf = uf;
                    showLookupStatus('Comarca identificada.', false);
                })
                .catch(() => { if (version === lookupVersion) showLookupStatus('Não foi possível consultar a cidade. Tente novamente.', true); })
                .finally(() => { if (version === lookupVersion) { lookupPending = false; updateSalvarButton(); } });
        }

        if (lookupRetry) lookupRetry.addEventListener('click', fetchCidadeInfo);
        cidadeInput.addEventListener('input', invalidateComarca);
        estadoSelect.addEventListener('change', function () { invalidateComarca(); fetchCidadeInfo(); });
        cidadeInput.addEventListener('blur', function () {
            applyCidadeFormatting();
            if (!validComarca()) fetchCidadeInfo();
        });
        if (App.ProperNameHelper) {
            App.ProperNameHelper.applyOnBlur(cidadeInput, 120);
        }

        const formElements = [finalidadeSelect, prazoSelect, cidadeInput, estadoSelect];
        if (statusSelect) formElements.push(statusSelect);
        if (observacoesInput) formElements.push(observacoesInput);
        ['input', 'change'].forEach(function (eventName) {
            formElements.forEach(function (el) {
                if (el) el.addEventListener(eventName, updateSalvarButton);
            });
        });

        salvarBtn.addEventListener('click', function (event) {
            event.preventDefault();
            if (salvarBtn.disabled) return;
            const payloadState = currentState();
            if (payloadState.finalidade === '' || payloadState.cidade === '' || payloadState.estado === '') {
                return notifyByKey('cartorio.cartas.validation.required.fields', {}, 'error', 'Erro', 'Finalidade, cidade e estado são obrigatórios.');
            }
            if (!hiddenComarcaInput || !hiddenComarcaInput.value.trim()) {
                return notifyByKey('cartorio.cartas.validation.comarca.required', {}, 'error', 'Erro', 'Selecione uma cidade válida para que a comarca seja identificada.');
            }

            const payload = {
                id: cartaId,
                finalidade: payloadState.finalidade,
                prazo_dias: parseInt(payloadState.prazo, 10) || 0,
                cidade_origem: formatCidade(payloadState.cidade),
                uf_origem: payloadState.estado,
                comarca_origem_id: hiddenComarcaInput.value.trim()
            };
            if (statusSelect) payload.status = payloadState.status || '';
            if (observacoesInput) payload.observacoes = payloadState.observacoes || '';

            submitting = true;
            updateSalvarButton();
            notifyByKey('cartorio.cartas.update.progress.info', {}, 'info', 'Informação', 'Atualizando dados...');

            fetch(endpoints.update || '/api/cartorio-cartas-recebidas/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (response) {
                    return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || !result.data.success) {
                        throw new Error(result.data && result.data.error ? result.data.error : 'Falha ao atualizar a carta recebida.');
                    }
                    global.location.href = '/frame.php?page=cartasrecebidas';
                })
                .catch(function (error) {
                    notify('error', 'Erro', error.message || 'Erro ao salvar.');
                    submitting = false;
                    updateSalvarButton();
                });
        });

        updateSalvarButton();

        if (btnCancelarTarefa && cartaId) {
            btnCancelarTarefa.addEventListener('click', function () {
                btnCancelarTarefa.disabled = true;
                fetch(endpoints.cancel || '/api/cartorio-cartas-recebidas/cancel.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: cartaId })
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        if (result.ok && result.data && result.data.success) {
                            notifyByKey('generic.task.cancel.success', {}, 'info', 'Informação', 'Tarefa cancelada com sucesso.');
                            global.setTimeout(function () { global.location.reload(); }, 250);
                            return;
                        }
                        notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao cancelar a tarefa.');
                    })
                    .catch(function () {
                        notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão ao cancelar.');
                    })
                    .finally(function () { btnCancelarTarefa.disabled = false; });
            });
        }

        if (btnExcluirTarefa && cartaId) {
            const requireSecondClick = createSecondClickGuard();
            let deleteConfirmMessage = 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão desta tarefa.';
            resolveToastMessageByKey('cartorio.cartas.delete.confirm', {}, deleteConfirmMessage).then(function (message) {
                deleteConfirmMessage = message;
            });
            btnExcluirTarefa.addEventListener('click', function () {
                if (!requireSecondClick('excluir-carta-recebida', deleteConfirmMessage)) {
                    return;
                }
                btnExcluirTarefa.disabled = true;
                fetch(endpoints.delete || '/api/cartorio-cartas-recebidas/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: cartaId })
                })
                    .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                    .then(function (result) {
                        const data = result.data || {};
                        if (result.ok && data.deleted) {
                            (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach(function (msg) {
                                queuePostReloadToast('info', 'Subtarefa excluída', msg);
                            });
                            if (data.tarefa_toast) {
                                queuePostReloadToast('success', 'Sucesso', data.tarefa_toast);
                            }
                            global.location.href = '/frame.php?page=cartasrecebidas';
                            return;
                        }
                        notify('error', 'Erro', data.error || 'Erro ao excluir a tarefa.');
                    })
                    .catch(function () {
                        notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão ao excluir.');
                    })
                    .finally(function () { btnExcluirTarefa.disabled = false; });
            });
        }
    }

    App.CartorioCartasRecebidasEdit = { initialize: initialize };
})(window);
