(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const DEFAULT_ENDPOINT = '/api/config/toast-messages.php';
    const DEFAULT_CACHE_KEY = 'app.toastCatalog.v1';

    let memoryCatalog = null;
    let pendingLoad = null;

    function safeJsonParse(raw) {
        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function readSessionCache(cacheKey) {
        try {
            const raw = global.sessionStorage.getItem(cacheKey);
            if (!raw) return null;
            const payload = safeJsonParse(raw);
            if (!payload || !payload.messages || typeof payload.messages !== 'object') return null;
            return payload;
        } catch (error) {
            return null;
        }
    }

    function writeSessionCache(cacheKey, payload) {
        try {
            global.sessionStorage.setItem(cacheKey, JSON.stringify(payload));
        } catch (error) {
            // noop
        }
    }

    function fillTemplate(template, vars) {
        const text = String(template || '');
        const data = (vars && typeof vars === 'object') ? vars : {};
        return text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (_, key) {
            const value = data[key];
            if (value === null || value === undefined) return '';
            return String(value);
        });
    }

    function normalizeToastConfig(config) {
        const item = (config && typeof config === 'object') ? config : {};
        return {
            type: item.type || 'info',
            title: item.title || 'Informação',
            message: item.message || '',
            vars: Array.isArray(item.vars) ? item.vars.slice() : [],
            forceVariant: item.forceVariant || null,
            critical: item.critical === true || item.blocking === true
        };
    }

    function resolveMessage(catalog, key, vars) {
        if (!catalog || !catalog.messages || typeof catalog.messages !== 'object') return null;
        const raw = catalog.messages[key];
        if (!raw || typeof raw !== 'object') return null;

        const config = normalizeToastConfig(raw);
        const mergedVars = Object.assign({}, vars || {});
        config.message = fillTemplate(config.message, mergedVars);
        config.title = fillTemplate(config.title, mergedVars);

        return config;
    }

    function loadCatalog(options) {
        const opts = options || {};
        const endpoint = opts.endpoint || DEFAULT_ENDPOINT;
        const cacheKey = opts.cacheKey || DEFAULT_CACHE_KEY;
        const force = opts.force === true;

        if (!force && memoryCatalog) {
            return Promise.resolve(memoryCatalog);
        }

        if (!force && pendingLoad) {
            return pendingLoad;
        }

        if (!force) {
            const cached = readSessionCache(cacheKey);
            if (cached) {
                memoryCatalog = cached;
                return Promise.resolve(cached);
            }
        }

        pendingLoad = fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha ao carregar catalogo de toast.');
                }
                return response.json();
            })
            .then(function (catalog) {
                if (!catalog || typeof catalog !== 'object' || !catalog.messages) {
                    throw new Error('Catalogo de toast invalido.');
                }
                memoryCatalog = catalog;
                writeSessionCache(cacheKey, catalog);
                return catalog;
            })
            .finally(function () {
                pendingLoad = null;
            });

        return pendingLoad;
    }

    function notifyByKey(key, vars, options) {
        const opts = options || {};
        return loadCatalog(opts)
            .then(function (catalog) {
                const toast = resolveMessage(catalog, key, vars);
                if (!toast) {
                    throw new Error('Chave de toast nao encontrada: ' + key);
                }

                if (App.Page && typeof App.Page.notify === 'function') {
                    App.Page.notify(toast.type, toast.title, toast.message);
                    return toast;
                }

                if (App.Toast && typeof App.Toast.show === 'function') {
                    App.Toast.show({
                        type: toast.type,
                        title: toast.title,
                        message: toast.message,
                        forceVariant: toast.forceVariant || undefined,
                        critical: toast.critical
                    });
                    return toast;
                }

                return toast;
            });
    }

    App.ToastCatalog = {
        load: loadCatalog,
        resolve: function (key, vars, options) {
            return loadCatalog(options).then(function (catalog) {
                return resolveMessage(catalog, key, vars);
            });
        },
        notifyByKey: notifyByKey,
        clearCache: function (cacheKey) {
            memoryCatalog = null;
            try {
                global.sessionStorage.removeItem(cacheKey || DEFAULT_CACHE_KEY);
            } catch (error) {
                // noop
            }
        }
    };
})(window);
