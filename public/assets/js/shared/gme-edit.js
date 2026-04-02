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
        global.document.getElementById(buttonId)?.addEventListener('click', function () {
            global.location.href = href;
        });
    }

    function bindReadOnlyTracker(trackRootId, saveButtonId) {
        const trackRoot = global.document.getElementById(trackRootId);
        const saveButton = global.document.getElementById(saveButtonId);
        if (!trackRoot || !saveButton) {
            return;
        }

        function snapshotTrackableValues() {
            const elements = Array.from(trackRoot.querySelectorAll('input:not([type="hidden"]), select, textarea')).filter(function (element) {
                return !element.readOnly && !element.disabled;
            });
            return elements.map(function (element) {
                return (element.id || element.name || '') + '\t' + String(element.value);
            }).join('\n');
        }

        let initialSnapshot = snapshotTrackableValues();
        function syncSaveDisabled() {
            saveButton.disabled = snapshotTrackableValues() === initialSnapshot;
        }

        Array.from(trackRoot.querySelectorAll('input:not([type="hidden"]), select, textarea')).forEach(function (element) {
            if (element.readOnly || element.disabled) {
                return;
            }
            element.addEventListener('input', syncSaveDisabled);
            element.addEventListener('change', syncSaveDisabled);
        });
        syncSaveDisabled();

        saveButton.addEventListener('click', function () {
            if (saveButton.disabled) {
                return;
            }
            notify('info', 'Somente leitura', 'Esta tela exibe os dados do registro. Alterações de datas e subtarefas são feitas na lista.');
        });
    }

    function bindJsonAction(buttonId, endpoint, body, handlers) {
        global.document.getElementById(buttonId)?.addEventListener('click', function () {
            if (handlers.beforeSend && handlers.beforeSend() === false) {
                return;
            }
            const button = this;
            button.disabled = true;
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body()),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    handlers.onResult(result, button);
                })
                .catch(function () {
                    handlers.onError(button);
                });
        });
    }

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'gme.postReloadToast';
        const requireSecondClick = createSecondClickGuard();

        bindBackButton(settings.backButtonId, settings.listHref);
        bindReadOnlyTracker(settings.trackRootId, settings.saveButtonId);

        bindJsonAction(settings.cancelButtonId, settings.cancelEndpoint, function () {
            return { id: global.document.getElementById(settings.idFieldId)?.value || '' };
        }, {
            beforeSend: function () {
                return requireSecondClick(settings.cancelConfirmKey, settings.cancelConfirmMessage);
            },
            onResult: function (result, button) {
                if (result.ok && result.data && result.data.success) {
                    queuePostReloadToast(storageKey, 'success', 'Sucesso', settings.cancelSuccessMessage || 'Monitoramento cancelado.');
                    global.location.href = settings.listHref;
                    return;
                }
                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao cancelar.');
                button.disabled = false;
            },
            onError: function (button) {
                notify('error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });

        bindJsonAction(settings.deleteButtonId, settings.deleteEndpoint, function () {
            return { id: global.document.getElementById(settings.idFieldId)?.value || '' };
        }, {
            beforeSend: function () {
                return requireSecondClick(settings.deleteConfirmKey, settings.deleteConfirmMessage);
            },
            onResult: function (result, button) {
                if (result.ok && result.data && (result.data.deleted || result.data.message)) {
                    queuePostReloadToast(storageKey, 'success', 'Sucesso', result.data.message || settings.deleteSuccessMessage || 'Registro excluído.');
                    global.location.href = settings.listHref;
                    return;
                }
                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao excluir.');
                button.disabled = false;
            },
            onError: function (button) {
                notify('error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });
    }

    App.GmeEdit = {
        initialize: initialize
    };
})(window);
