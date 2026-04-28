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

    function notifyByKey(notify, key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }

        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function resolveToastMessageByKey(key, vars, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            return Promise.resolve(fallbackMessage);
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                return (toast && toast.message) ? toast.message : fallbackMessage;
            })
            .catch(function () {
                return fallbackMessage;
            });
    }

    function queueByKey(queuePostReloadToast, key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
                    return;
                }
                queuePostReloadToast(
                    toast.type || fallbackType,
                    toast.title || fallbackTitle,
                    toast.message
                );
            })
            .catch(function () {
                queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
            });
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

            const confirmMessageFromConfig = settings.desalocarConfirmMessage || '';
            resolveToastMessageByKey(
                'cartorio.allocation.remove.confirm',
                {},
                confirmMessageFromConfig || 'Clique novamente para confirmar a remoção da alocação.'
            ).then(function (confirmMessage) {
                if (!requireSecondClick('desalocar-' + alocadoId, confirmMessage)) {
                    return;
                }

                return fetch(endpoint, {
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
                        notifyByKey(
                            notify,
                            'cartorio.allocation.remove.success',
                            {},
                            'success',
                            'Sucesso',
                            settings.desalocarSuccessMessage || 'Alocação removida.'
                        );
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 800);
                        return;
                    }

                    notifyByKey(
                        notify,
                        'cartorio.allocation.remove.error',
                        {},
                        'error',
                        'Erro',
                        (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.'
                    );
                })
                .catch(function () {
                    notifyByKey(notify, 'generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                });
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
        const numeroOficio = String(settings.numeroOficio || '').trim();
        const oficioVars = numeroOficio ? { numero_oficio: numeroOficio } : {};
        const entityId = (global.document.getElementById(settings.idFieldId)?.value || '').trim();
        let deleteConfirmMessage = settings.deleteConfirmMessage || 'Clique novamente para confirmar a exclusão.';

        resolveToastMessageByKey('oficios.delete.confirm', {}, deleteConfirmMessage).then(function (message) {
            deleteConfirmMessage = message;
        });

        global.document.getElementById(settings.backButtonId)?.addEventListener('click', function () {
            global.location.href = settings.listHref;
        });

        bindJsonAction(settings.cancelButtonId, {
            endpoint: settings.cancelEndpoint,
            body: { id: entityId },
            handleResult: function (result, button) {
                if (result.ok && result.data && result.data.success) {
                    if (settings.cancelSuccessMessage) {
                        queuePostReloadToast('success', 'Sucesso', settings.cancelSuccessMessage);
                        global.location.href = settings.listHref;
                        return true;
                    }
                    queueByKey(
                        queuePostReloadToast,
                        'oficios.cancel.success',
                        oficioVars,
                        'success',
                        'Sucesso',
                        'Ofício cancelado.'
                    ).then(function () {
                        global.location.href = settings.listHref;
                    });
                    return true;
                }

                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao cancelar.');
                button.disabled = false;
                return true;
            },
            handleError: function (button) {
                notifyByKey(notify, 'generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });

        bindJsonAction(settings.deleteButtonId, {
            endpoint: settings.deleteEndpoint,
            body: { id: entityId },
            beforeSend: function () {
                return requireSecondClick(settings.deleteConfirmKey || 'delete-entity', deleteConfirmMessage);
            },
            handleResult: function (result, button) {
                if (result.ok && result.data && (result.data.deleted || result.data.message)) {
                    if (settings.deleteSuccessMessage) {
                        queuePostReloadToast('success', 'Sucesso', settings.deleteSuccessMessage);
                        global.location.href = settings.listHref;
                        return true;
                    }
                    queueByKey(
                        queuePostReloadToast,
                        'oficios.delete.success',
                        oficioVars,
                        'success',
                        'Sucesso',
                        result.data.message || 'Ofício excluído.'
                    ).then(function () {
                        global.location.href = settings.listHref;
                    });
                    return true;
                }

                notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao excluir.');
                button.disabled = false;
                return true;
            },
            handleError: function (button) {
                notifyByKey(notify, 'generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                button.disabled = false;
            }
        });

        bindDesalocar(settings, notify, requireSecondClick);
    }

    App.OficiosEdit = {
        initialize: initialize
    };
})(window);
