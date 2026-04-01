(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
            return;
        }

        if (App.Toast && typeof App.Toast.show === 'function') {
            App.Toast.show({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
        }
    }

    function createSecondClickGuard(options) {
        const opts = options || {};
        const ttlMs = Number(opts.ttlMs) || 5000;
        const pendingConfirmations = {};

        return function requireSecondClick(key, message) {
            const now = Date.now();
            const validUntil = Number(pendingConfirmations[key] || 0);
            if (validUntil > now) {
                delete pendingConfirmations[key];
                return true;
            }
            pendingConfirmations[key] = now + ttlMs;
            notify('info', 'Confirmacao', message);
            return false;
        };
    }

    function queuePostReloadToast(type, title, message, storageKey) {
        const payload = type && typeof type === 'object'
            ? Object.assign({}, type)
            : { type: type, title: title, message: message };
        const key = String(storageKey || 'cartorio.postReloadToast');
        try {
            const raw = global.sessionStorage.getItem(key);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
            list.push(payload);
            global.sessionStorage.setItem(key, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function flushPostReloadToast(storageKey, defaults) {
        const key = String(storageKey || 'cartorio.postReloadToast');
        const fallback = defaults || {};
        try {
            const raw = global.sessionStorage.getItem(key);
            if (!raw) {
                return [];
            }
            global.sessionStorage.removeItem(key);
            const data = JSON.parse(raw);
            const list = Array.isArray(data) ? data : (data && data.message ? [data] : []);
            list.forEach(function (item) {
                if (!item || !item.message) {
                    return;
                }
                notify(
                    item.type || fallback.type || 'info',
                    item.title || fallback.title || 'Informação',
                    item.message
                );
            });
            return list;
        } catch (error) {
            return [];
        }
    }

    function getFramePhpHref() {
        const path = global.location.pathname || '/frame.php';
        const slash = path.lastIndexOf('/');
        const dir = slash >= 0 ? path.slice(0, slash + 1) : '/';
        return dir + 'frame.php';
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    App.Page = {
        notify,
        createSecondClickGuard,
        queuePostReloadToast,
        flushPostReloadToast,
        getFramePhpHref,
        escapeHtml
    };
})(window);
