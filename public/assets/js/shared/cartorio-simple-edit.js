(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
        }
    }

    function queuePostReloadToast(storageKey, type, title, message) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, storageKey);
            return;
        }

        try {
            const raw = global.sessionStorage.getItem(storageKey);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
            list.push({ type: type, title: title, message: message });
            global.sessionStorage.setItem(storageKey, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function createSecondClickGuard() {
        if (App.Page && typeof App.Page.createSecondClickGuard === 'function') {
            return App.Page.createSecondClickGuard();
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

    function bindBackButton(buttonId, href) {
        const button = global.document.getElementById(buttonId);
        if (!button || !href) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();
            global.location.href = href;
        }, { capture: true });
    }

    function bindLinkedButton(buttonId, hrefBuilder) {
        const button = global.document.getElementById(buttonId);
        if (!button || typeof hrefBuilder !== 'function') {
            return;
        }

        button.addEventListener('click', function () {
            const href = hrefBuilder(button);
            if (href) {
                global.location.href = href;
            }
        });
    }

    function bindObservationSave(settings, state) {
        if (!settings.saveButtonId || !settings.observationFieldId || !settings.updateEndpoint) {
            return;
        }

        const saveButton = global.document.getElementById(settings.saveButtonId);
        const observationField = global.document.getElementById(settings.observationFieldId);
        if (!saveButton || !observationField) {
            return;
        }

        function getCurrentValue() {
            return (observationField.value || '').trim();
        }

        function syncSaveDisabled() {
            saveButton.disabled = getCurrentValue() === state.initialObservation;
        }

        observationField.addEventListener('input', syncSaveDisabled);
        syncSaveDisabled();

        saveButton.addEventListener('click', function () {
            if (saveButton.disabled) {
                return;
            }

            const payload = { id: settings.entityId, observacoes: getCurrentValue() };
            saveButton.disabled = true;
            fetch(settings.updateEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        notify('success', 'Sucesso', settings.updateSuccessMessage || 'Alterações salvas.');
                        state.initialObservation = payload.observacoes;
                        syncSaveDisabled();
                        return;
                    }
                    notify('error', 'Erro', data.error || 'Erro ao salvar.');
                    saveButton.disabled = false;
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    saveButton.disabled = false;
                });
        });
    }

    function bindCancel(settings) {
        const button = global.document.getElementById(settings.cancelButtonId);
        if (!button || !settings.cancelEndpoint || !settings.entityId) {
            return;
        }

        button.addEventListener('click', function () {
            button.disabled = true;
            fetch(settings.cancelEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: settings.entityId })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.success) {
                        notify('info', 'Informação', settings.cancelSuccessMessage || 'Tarefa cancelada com sucesso.');
                        if (settings.cancelAction === 'redirect') {
                            global.location.href = settings.backHref;
                            return;
                        }
                        global.setTimeout(function () { global.location.reload(); }, settings.cancelReloadDelayMs || 250);
                        return;
                    }
                    notify('error', 'Erro', data.error || 'Erro ao cancelar.');
                    button.disabled = false;
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    button.disabled = false;
                });
        });
    }

    function bindDelete(settings) {
        const button = global.document.getElementById(settings.deleteButtonId);
        if (!button || !settings.deleteEndpoint || !settings.entityId) {
            return;
        }

        const requireSecondClick = createSecondClickGuard();

        button.addEventListener('click', function () {
            if (!requireSecondClick(settings.deleteConfirmKey || 'delete-entity', settings.deleteConfirmMessage || 'Clique novamente para confirmar a exclusão.')) {
                return;
            }

            button.disabled = true;
            fetch(settings.deleteEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: settings.entityId })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.deleted) {
                        (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach(function (message) {
                            queuePostReloadToast(settings.storageKey, 'info', 'Subtarefa excluída', message);
                        });
                        if (data.tarefa_toast) {
                            queuePostReloadToast(settings.storageKey, 'success', 'Sucesso', data.tarefa_toast);
                        }
                        global.location.href = settings.backHref;
                        return;
                    }
                    notify('error', 'Erro', data.error || 'Erro ao excluir.');
                    button.disabled = false;
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    button.disabled = false;
                });
        });
    }

    function initialize(config) {
        const settings = Object.assign({
            storageKey: 'cartorio.postReloadToast',
            cancelAction: 'reload',
            cancelReloadDelayMs: 250
        }, config || {});
        const state = {
            initialObservation: String(settings.initialObservation || '')
        };

        bindBackButton(settings.backButtonId, settings.backHref);
        bindLinkedButton(settings.linkedButtonId, settings.linkedHrefBuilder);
        bindObservationSave(settings, state);
        bindCancel(settings);
        bindDelete(settings);
    }

    App.CartorioSimpleEdit = {
        initialize: initialize
    };
})(window);
