(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function getPageTools() {
        return App.Page || null;
    }

    function getToastCatalog() {
        return App.ToastCatalog || null;
    }

    function buildNotify(pageTools) {
        return function notify(type, title, message) {
            if (pageTools && typeof pageTools.notify === 'function') {
                pageTools.notify(type, title, message);
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

    function buildQueuePostReloadToast(storageKey, pageTools) {
        return function queuePostReloadToast(type, title, message) {
            if (pageTools && typeof pageTools.queuePostReloadToast === 'function') {
                pageTools.queuePostReloadToast(type, title, message, storageKey);
                return;
            }

            const payload = type && typeof type === 'object'
                ? Object.assign({}, type)
                : { type: type, title: title, message: message };

            try {
                const raw = global.sessionStorage.getItem(storageKey);
                const current = raw ? JSON.parse(raw) : [];
                const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
                list.push(payload);
                global.sessionStorage.setItem(storageKey, JSON.stringify(list));
            } catch (error) {
                // noop
            }
        };
    }

    function flushPostReloadToast(storageKey, pageTools, notify) {
        if (pageTools && typeof pageTools.flushPostReloadToast === 'function') {
            pageTools.flushPostReloadToast(storageKey, { type: 'info', title: 'Informação' });
            return;
        }

        try {
            const raw = global.sessionStorage.getItem(storageKey);
            if (!raw) {
                return;
            }
            global.sessionStorage.removeItem(storageKey);
            const data = JSON.parse(raw);
            const list = Array.isArray(data) ? data : (data && data.message ? [data] : []);
            list.forEach(function (item) {
                if (item && item.message) {
                    notify(item.type || 'info', item.title || 'Informação', item.message);
                }
            });
        } catch (error) {
            // noop
        }
    }

    function bindNewButton(buttonId, href) {
        if (!buttonId || !href) {
            return;
        }

        const button = global.document.getElementById(buttonId);
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            global.location.href = href;
        });
    }

    function bindEditButtons(selector, hrefBuilder, getId) {
        if (!selector || typeof hrefBuilder !== 'function' || typeof getId !== 'function') {
            return;
        }

        global.document.querySelectorAll(selector).forEach(function (button) {
            button.addEventListener('click', function () {
                const itemId = getId(button);
                if (!itemId) {
                    return;
                }
                global.location.href = hrefBuilder(itemId, button);
            });
        });
    }

    function bindSubtaskExpansion(radioName) {
        const name = radioName || 'open-subtask';
        global.document.querySelectorAll('input[name="' + name + '"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const targetId = 'subtask-' + this.value;
                global.document.querySelectorAll('.col.subtask').forEach(function (subtaskRow) {
                    const isTarget = subtaskRow.id === targetId;
                    subtaskRow.classList.toggle('subtask-collapsed', !isTarget);
                    subtaskRow.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                });
                global.document.querySelectorAll('input[name="' + name + '"]').forEach(function (entry) {
                    entry.setAttribute('aria-expanded', entry.value === radio.value ? 'true' : 'false');
                });
            });
        });
    }

    function bindFilters(config) {
        const filters = config.filters || null;
        if (!filters || typeof filters.apply !== 'function') {
            return;
        }

        const applyFilters = function () {
            filters.apply();
        };

        (filters.triggerIds || []).forEach(function (id) {
            const element = global.document.getElementById(id);
            if (!element) {
                return;
            }
            element.addEventListener('change', applyFilters);
        });

        const searchInputId = filters.searchInputId || '';
        if (!searchInputId) {
            return;
        }

        const searchInput = global.document.getElementById(searchInputId);
        if (!searchInput) {
            return;
        }

        if (filters.searchMode === 'debounce') {
            const delay = Number(filters.searchDelay) > 0 ? Number(filters.searchDelay) : 800;
            let timerId = null;
            searchInput.addEventListener('input', function () {
                global.clearTimeout(timerId);
                timerId = global.setTimeout(applyFilters, delay);
            });
            return;
        }

        searchInput.addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });
    }

    function bindCustomButtons(config, context) {
        (config.buttons || []).forEach(function (definition) {
            if (!definition || !definition.selector || typeof definition.onClick !== 'function') {
                return;
            }

            global.document.querySelectorAll(definition.selector).forEach(function (button) {
                button.addEventListener('click', function (event) {
                    definition.onClick(button, event, context);
                });
            });
        });
    }

    function resolveToastMessageByKey(key, vars, fallbackMessage) {
        const toastCatalog = getToastCatalog();
        if (!toastCatalog || typeof toastCatalog.resolve !== 'function') {
            return Promise.resolve(fallbackMessage);
        }
        return toastCatalog.resolve(key, vars || {}).then(function (toast) {
            return (toast && toast.message) ? toast.message : fallbackMessage;
        }).catch(function () {
            return fallbackMessage;
        });
    }

    function bindDesalocarButtons(options, pageTools, notify) {
        if (options.enableDesalocar === false) {
            return;
        }

        const selector = options.removeSelector || '.js-desalocar';
        const confirmMessage = options.removeConfirmMessage || 'Clique novamente para confirmar a remoção da alocação.';
        const successMessage = options.removeSuccessMessage || 'Alocação removida.';
        const toastCatalog = getToastCatalog();
        const notifyByKey = function (key, vars, fallbackType, fallbackTitle, fallbackMessage) {
            if (toastCatalog && typeof toastCatalog.notifyByKey === 'function') {
                toastCatalog.notifyByKey(key, vars || {}).catch(function () {
                    notify(fallbackType, fallbackTitle, fallbackMessage);
                });
                return;
            }
            notify(fallbackType, fallbackTitle, fallbackMessage);
        };
        const requireSecondClick = pageTools && typeof pageTools.createSecondClickGuard === 'function'
            ? pageTools.createSecondClickGuard()
            : (function () {
                const pending = {};
                return function (key, message) {
                    const now = Date.now();
                    if ((pending[key] || 0) > now) {
                        delete pending[key];
                        return true;
                    }
                    pending[key] = now + 5000;
                    notify('info', 'Confirmação', message);
                    return false;
                };
            })();

        global.document.addEventListener('click', function (event) {
            const button = event.target.closest(selector);
            if (!button) {
                return;
            }

            const alocadoId = button.getAttribute('data-alocado-id') || '';
            const tipo = button.getAttribute('data-tipo') || '';
            const endpoint = button.getAttribute('data-endpoint') || '';

            if (!alocadoId || !endpoint) {
                return;
            }

            resolveToastMessageByKey('cartorio.allocation.remove.confirm', {}, confirmMessage).then(function (resolvedConfirmMessage) {
                if (!requireSecondClick('desalocar-' + alocadoId, resolvedConfirmMessage)) {
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
                        if (options.removeSuccessMessage) {
                            notify('success', 'Sucesso', successMessage);
                        } else {
                            notifyByKey(
                                'cartorio.allocation.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                successMessage
                            );
                        }
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 800);
                        return;
                    }

                    const errorMessage = result.data && result.data.error
                        ? result.data.error
                        : 'Falha ao remover alocação.';
                    if (result.data && result.data.error) {
                        notify('error', 'Erro', errorMessage);
                    } else {
                        notifyByKey(
                            'cartorio.allocation.remove.error',
                            {},
                            'error',
                            'Erro',
                            errorMessage
                        );
                    }
                })
                .catch(function () {
                    notifyByKey(
                        'generic.error.connection',
                        {},
                        'error',
                        'Erro',
                        'Erro de conexão.'
                    );
                });
            });
        });
    }

    function initialize(options) {
        const config = options || {};
        const pageTools = getPageTools();
        const storageKey = String(config.storageKey || 'cartorio.postReloadToast');
        const notify = buildNotify(pageTools);
        const queuePostReloadToast = buildQueuePostReloadToast(storageKey, pageTools);

        flushPostReloadToast(storageKey, pageTools, notify);
        bindNewButton(config.newButtonId, config.newHref);
        bindEditButtons(
            config.editButtonSelector,
            config.editHrefBuilder,
            config.getEditId || function (button) {
                const key = config.editIdAttribute || 'id';
                return button && button.dataset ? button.dataset[key] || '' : '';
            }
        );
        bindFilters(config);
        bindSubtaskExpansion(config.subtaskRadioName || 'open-subtask');
        bindDesalocarButtons(config, pageTools, notify);

        const context = {
            pageTools: pageTools,
            notify: notify,
            queuePostReloadToast: queuePostReloadToast,
            storageKey: storageKey
        };

        bindCustomButtons(config, context);

        if (typeof config.onReady === 'function') {
            config.onReady(context);
        }

        return context;
    }

    App.CartorioTaskList = {
        initialize: initialize
    };
})(window);
