(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                title: title || '',
                message: message || '',
                variant: 'full'
            });
            return;
        }
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
        }
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
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
        return catalog.resolve(key, vars || {}).then(function (toast) {
            return (toast && toast.message) ? toast.message : fallbackMessage;
        }).catch(function () {
            return fallbackMessage;
        });
    }

    function buildSecondClickGuard() {
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

    /**
     * @param {{ prefix: string, entityId: string, alocarEndpoint: string }} config
     */
    function init(config) {
        const settings = config || {};
        const prefix = String(settings.prefix || '').replace(/[^a-z0-9_-]/gi, '');
        const entityId = String(settings.entityId || '').trim();
        const alocarEndpoint = String(settings.alocarEndpoint || '').trim();
        const rootId = prefix + '-alocacao-edit-root';
        const root = global.document.getElementById(rootId);
        if (!root || !entityId) {
            return;
        }

        const requireSecondClick = buildSecondClickGuard();

        root.addEventListener('click', function (event) {
            const button = event.target.closest('.js-desalocar');
            if (!button || !root.contains(button)) {
                return;
            }

            const alocadoId = button.getAttribute('data-alocado-id') || '';
            const tipo = button.getAttribute('data-tipo') || '';
            const endpoint = button.getAttribute('data-endpoint') || '';
            if (!alocadoId || !endpoint) {
                return;
            }

            resolveToastMessageByKey(
                'cartorio.allocation.remove.confirm',
                {},
                'Clique novamente para confirmar a remoção da alocação.'
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
                        notifyByKey('cartorio.allocation.remove.success', {}, 'success', 'Sucesso', 'Alocação removida.');
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 800);
                        return;
                    }
                    notifyByKey('cartorio.allocation.remove.error', {}, 'error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.');
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                });
            });
        });

        if (alocarEndpoint === '') {
            return;
        }

        const sel = global.document.getElementById(prefix + '-alocar-usuario-select');
        const btnAdd = global.document.getElementById(prefix + '-alocar-adicionar');
        if (!sel || !btnAdd) {
            return;
        }

        btnAdd.addEventListener('click', function () {
            const uid = (sel.value || '').trim();
            if (!uid) {
                return;
            }
            btnAdd.disabled = true;
            fetch(alocarEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: entityId, usuario_id: uid }),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    btnAdd.disabled = false;
                    if (result.ok && result.data && result.data.success) {
                        notifyByKey('cartorio.allocation.add.success', {}, 'success', 'Sucesso', 'Servidor alocado.');
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 600);
                        return;
                    }
                    notifyByKey(
                        'cartorio.allocation.add.error',
                        {},
                        'error',
                        'Erro',
                        (result.data && result.data.error) ? result.data.error : 'Falha ao alocar.'
                    );
                })
                .catch(function () {
                    btnAdd.disabled = false;
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                });
        });
    }

    App.CartorioAlocacaoDirecionadaEdit = {
        init: init
    };
})(window);
