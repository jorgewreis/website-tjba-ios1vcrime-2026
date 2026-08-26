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

    function queuePostReloadToast(storageKey, payload) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(payload, null, null, storageKey);
            return;
        }
        try {
            const raw = global.sessionStorage.getItem(storageKey);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
            list.push(payload);
            global.sessionStorage.setItem(storageKey, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function queueByKey(storageKey, key, vars, fallbackPayload) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            queuePostReloadToast(storageKey, fallbackPayload);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    queuePostReloadToast(storageKey, fallbackPayload);
                    return;
                }
                queuePostReloadToast(storageKey, {
                    type: toast.type || fallbackPayload.type,
                    title: toast.title || fallbackPayload.title,
                    message: toast.message
                });
            })
            .catch(function () {
                queuePostReloadToast(storageKey, fallbackPayload);
            });
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (App.Page && typeof App.Page.notifyByKey === 'function') {
            return App.Page.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
        }
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function bind(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'cartorio.postReloadToast';
        const cancelEndpointDefault = String(settings.cancelEndpoint || '').trim();
        const cancelToastKey = String(settings.cancelToastKey || 'oficios.cancel.success').trim();
        const deleteEndpointDefault = String(settings.deleteEndpoint || '').trim();
        const deleteToastKey = String(settings.deleteToastKey || 'registro.delete.success').trim();
        const cancelFallback = settings.cancelFallback || {
            type: 'success',
            title: 'Sucesso',
            message: 'Registro cancelado.'
        };
        const deleteFallback = settings.deleteFallback || {
            type: 'success',
            title: 'Sucesso',
            message: 'Registro removido.'
        };

        global.document.addEventListener('click', function (event) {
            const editBtn = event.target.closest('[data-action~="editar-registro"], [data-action~="editar-oficio"]');
            if (editBtn) {
                const href = editBtn.getAttribute('data-edit-href') || '';
                if (href) {
                    global.location.href = href;
                }
                return;
            }

            const deleteBtn = event.target.closest('[data-action~="excluir-registro"]');
            if (deleteBtn) {
                const id = String(deleteBtn.getAttribute('data-registro-id') || '').trim();
                const endpoint = String(deleteBtn.getAttribute('data-delete-endpoint') || deleteEndpointDefault).trim();
                if (!id || !endpoint || deleteBtn.disabled) {
                    return;
                }
                if (!global.confirm('Remover este registro? Esta ação deve ser usada apenas em cadastro indevido ou incorreto.')) {
                    return;
                }

                deleteBtn.disabled = true;
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id }),
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (json) {
                            return { ok: response.ok, data: json };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && (result.data.deleted || result.data.success)) {
                            queueByKey(storageKey, deleteToastKey, {}, deleteFallback).then(function () {
                                global.location.reload();
                            });
                            return;
                        }

                        notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao remover.');
                        deleteBtn.disabled = false;
                    })
                    .catch(function () {
                        notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                        deleteBtn.disabled = false;
                    });
                return;
            }

            const cancelBtn = event.target.closest('[data-action~="cancelar-registro"], [data-action~="cancelar-oficio"]');
            if (!cancelBtn) {
                return;
            }

            const id = String(
                cancelBtn.getAttribute('data-registro-id')
                || cancelBtn.getAttribute('data-oficio-id')
                || ''
            ).trim();
            const endpoint = String(cancelBtn.getAttribute('data-cancel-endpoint') || cancelEndpointDefault).trim();
            if (!id || !endpoint) {
                return;
            }
            if (cancelBtn.disabled) {
                return;
            }

            cancelBtn.disabled = true;
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        queueByKey(storageKey, cancelToastKey, {}, cancelFallback).then(function () {
                            global.location.reload();
                        });
                        return;
                    }

                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao cancelar.');
                    cancelBtn.disabled = false;
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    cancelBtn.disabled = false;
                });
        });
    }

    App.RegistroMetadataList = {
        bind: bind
    };
})(window);
