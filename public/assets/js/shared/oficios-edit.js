(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function buildNotify() {
        return function notify(type, title, message) {
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
        };
    }

    function buildQueuePostReloadToast(storageKey) {
        return function queuePostReloadToast(type, title, message) {
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
        };
    }

    function buildSecondClickGuard(notify) {
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

    function bindDesalocar(settings, notify, requireSecondClick) {
        if (!settings.enableDesalocar) {
            return;
        }

        global.document.addEventListener('click', function (event) {
            const button = event.target.closest(settings.desalocarSelector || '.js-desalocar');
            if (!button) {
                return;
            }

            const alocadoId = button.getAttribute('data-alocado-id') || '';
            const tipo = button.getAttribute('data-tipo') || '';
            const endpoint = button.getAttribute('data-endpoint') || '';
            if (!alocadoId || !endpoint) {
                return;
            }

            if (!requireSecondClick('desalocar-' + alocadoId, settings.desalocarConfirmMessage || 'Clique novamente para confirmar a remoção da alocação.')) {
                return;
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alocado_id: alocadoId, tipo: tipo }),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.deleted) {
                        notify('success', 'Sucesso', settings.desalocarSuccessMessage || 'Alocação removida.');
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 800);
                        return;
                    }

                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.');
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                });
        });
    }

    function bindJsonAction(buttonId, requestConfig) {
        const button = global.document.getElementById(buttonId);
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            if (typeof requestConfig.beforeSend === 'function' && requestConfig.beforeSend(button) === false) {
                return;
            }

            button.disabled = true;
            fetch(requestConfig.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestConfig.body),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    if (typeof requestConfig.handleResult === 'function') {
                        const handled = requestConfig.handleResult(result, button);
                        if (handled !== false) {
                            return;
                        }
                    }
                    button.disabled = false;
                })
                .catch(function () {
                    if (typeof requestConfig.handleError === 'function') {
                        requestConfig.handleError(button);
                        return;
                    }
                    button.disabled = false;
                });
        });
    }

    function initialize(config) {
        const settings = config || {};
        const notify = buildNotify();
        const queuePostReloadToast = buildQueuePostReloadToast(settings.storageKey || 'cartorio.postReloadToast');
        const requireSecondClick = buildSecondClickGuard(notify);
        const entityId = (global.document.getElementById(settings.idFieldId)?.value || '').trim();

        global.document.getElementById(settings.backButtonId)?.addEventListener('click', function () {
            global.location.href = settings.listHref;
        });

        bindJsonAction(settings.cancelButtonId, {
            endpoint: settings.cancelEndpoint,
            body: { id: entityId },
            handleResult: function (result, button) {
                if (result.ok && result.data && result.data.success) {
                    queuePostReloadToast('success', 'Sucesso', settings.cancelSuccessMessage || 'Ofício cancelado.');
                    global.location.href = settings.listHref;
                    return true;
                }

                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao cancelar.');
                button.disabled = false;
                return true;
            },
            handleError: function (button) {
                notify('error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });

        bindJsonAction(settings.deleteButtonId, {
            endpoint: settings.deleteEndpoint,
            body: { id: entityId },
            beforeSend: function () {
                return requireSecondClick(settings.deleteConfirmKey || 'delete-entity', settings.deleteConfirmMessage || 'Clique novamente para confirmar a exclusão.');
            },
            handleResult: function (result, button) {
                if (result.ok && result.data && (result.data.deleted || result.data.message)) {
                    queuePostReloadToast('success', 'Sucesso', result.data.message || settings.deleteSuccessMessage || 'Ofício excluído.');
                    global.location.href = settings.listHref;
                    return true;
                }

                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao excluir.');
                button.disabled = false;
                return true;
            },
            handleError: function (button) {
                notify('error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });

        bindDesalocar(settings, notify, requireSecondClick);
    }

    App.OficiosEdit = {
        initialize: initialize
    };
})(window);
