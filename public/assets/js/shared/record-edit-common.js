(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type || 'info', title || 'Informação', message || '');
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

    function queuePostReloadToast(type, title, message, storageKey) {
        const key = storageKey || 'cartorio.postReloadToast';
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, key);
            return;
        }
        try {
            const raw = global.sessionStorage.getItem(key);
            const fila = raw ? JSON.parse(raw) : [];
            const lista = Array.isArray(fila) ? fila : ((fila && fila.message) ? [fila] : []);
            lista.push({ type: type, title: title, message: message });
            global.sessionStorage.setItem(key, JSON.stringify(lista));
        } catch (error) {
            // noop
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

    function queuePostReloadToastByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage, storageKey) {
        if (App.Page && typeof App.Page.queuePostReloadToastByKey === 'function') {
            return App.Page.queuePostReloadToastByKey(
                key,
                vars || {},
                fallbackType,
                fallbackTitle,
                fallbackMessage,
                storageKey
            );
        }
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage, storageKey);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage, storageKey);
                    return;
                }
                queuePostReloadToast(
                    toast.type || fallbackType,
                    toast.title || fallbackTitle,
                    toast.message,
                    storageKey
                );
            })
            .catch(function () {
                queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage, storageKey);
            });
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

    function postJson(url, body, options) {
        const settings = options || {};
        return fetch(url, {
            method: settings.method || 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, settings.headers || {}),
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: settings.credentials || 'same-origin'
        }).then(function (response) {
            return response.text().then(function (text) {
                let data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (error) {
                    data = {};
                }
                return { ok: response.ok, data: data };
            });
        });
    }

    function lookupCep(cep, options) {
        const settings = options || {};
        const digits = String(cep || '').replace(/\D/g, '');
        if (digits.length !== 8) {
            notify('error', 'Erro', settings.invalidMessage || 'Informe um CEP com 8 dígitos.');
            return Promise.resolve({ ok: false, data: null });
        }

        return fetch((settings.endpoint || '/api/cep/consulta.php?q=') + encodeURIComponent(digits), {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.mode === 'cep' && data.data) {
                    return { ok: true, data: data.data };
                }
                notify('error', 'Erro', data.error || settings.notFoundMessage || 'CEP não encontrado.');
                return { ok: false, data: null };
            })
            .catch(function () {
                notify('error', 'Erro', settings.errorMessage || 'Erro ao consultar CEP.');
                return { ok: false, data: null };
            });
    }

    App.RecordEditCommon = {
        notify: notify,
        notifyByKey: notifyByKey,
        queuePostReloadToast: queuePostReloadToast,
        queuePostReloadToastByKey: queuePostReloadToastByKey,
        createSecondClickGuard: createSecondClickGuard,
        postJson: postJson,
        lookupCep: lookupCep
    };
})(window);
