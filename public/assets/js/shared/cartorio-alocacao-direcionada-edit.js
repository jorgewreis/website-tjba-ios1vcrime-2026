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

            if (!requireSecondClick('desalocar-' + alocadoId, 'Clique novamente para confirmar a remoção da alocação.')) {
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
                        notify('success', 'Sucesso', 'Alocação removida.');
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
                        notify('success', 'Sucesso', 'Servidor alocado.');
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 600);
                        return;
                    }
                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao alocar.');
                })
                .catch(function () {
                    btnAdd.disabled = false;
                    notify('error', 'Erro', 'Erro de conexão.');
                });
        });
    }

    App.CartorioAlocacaoDirecionadaEdit = {
        init: init
    };
})(window);
