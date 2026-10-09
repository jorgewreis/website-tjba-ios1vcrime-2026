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

    function flushPostReloadToast(storageKey) {
        if (App.Page && typeof App.Page.flushPostReloadToast === 'function') {
            App.Page.flushPostReloadToast(storageKey, { type: 'info', title: 'Informação' });
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

    function queueByKey(storageKey, key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            queuePostReloadToast(storageKey, fallbackType, fallbackTitle, fallbackMessage);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    queuePostReloadToast(storageKey, fallbackType, fallbackTitle, fallbackMessage);
                    return;
                }
                queuePostReloadToast(
                    storageKey,
                    toast.type || fallbackType,
                    toast.title || fallbackTitle,
                    toast.message
                );
            })
            .catch(function () {
                queuePostReloadToast(storageKey, fallbackType, fallbackTitle, fallbackMessage);
            });
    }

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'gme.postReloadToast';

        flushPostReloadToast(storageKey);

        global.document.getElementById(settings.newButtonId || 'novo-cadastro-btn')?.addEventListener('click', function () {
            global.location.href = settings.newHref;
        });

        const applyFilters = function () {
            const status = global.document.getElementById(settings.statusId || 'filter-status')?.value ?? '';
            const processo = global.document.getElementById(settings.searchInputId || 'search-input')?.value?.trim() ?? '';
            const perPage = '15';
            const params = new URLSearchParams(global.location.search);
            params.set('page', String(settings.routePage || ''));
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (status) { params.set('status', status); } else { params.delete('status'); }
            if (processo) { params.set('processo', processo); } else { params.delete('processo'); }
            global.location.href = '?' + params.toString();
        };

        global.document.getElementById(settings.statusId || 'filter-status')?.addEventListener('change', applyFilters);
        global.document.getElementById(settings.searchInputId || 'search-input')?.addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });

        global.document.querySelectorAll(settings.editButtonSelector || '.btn-editar[data-monitoramento-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.dataset.monitoramentoId;
                if (id) {
                    global.location.href = settings.editHrefBuilder(id, button);
                }
            });
        });

        global.document.querySelectorAll('input[name="' + (settings.subtaskRadioName || 'open-subtask') + '"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const targetId = 'subtask-' + this.value;
                global.document.querySelectorAll(settings.subtaskPanelSelector || '.gme-subtask-panel').forEach(function (row) {
                    const isTarget = row.id === targetId;
                    row.classList.toggle('subtask-collapsed', !isTarget);
                    row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                });
            });
        });

        global.document.querySelectorAll(settings.concludeButtonSelector || '.btn-gme-concluir').forEach(function (button) {
            button.addEventListener('click', function () {
                const subtarefaId = this.getAttribute('data-subtarefa-id');
                const precisaData = this.getAttribute('data-precisa-data') === '1';
                if (!subtarefaId) {
                    return;
                }

                let dataInicio = null;
                if (precisaData) {
                    const input = global.document.getElementById('data-inicio-' + subtarefaId);
                    dataInicio = input && input.value ? input.value : '';
                    if (!dataInicio) {
                        notifyByKey('gme.monitoring.validation.start_date.required', {}, 'error', 'Validação', 'Informe a data de início.');
                        return;
                    }
                }

                button.disabled = true;
                const body = { subtarefa_id: subtarefaId };
                if (dataInicio) {
                    body.data_inicio = dataInicio;
                }

                fetch(settings.concludeEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (json) {
                            return { ok: response.ok, data: json };
                        });
                    })
                    .then(function (result) {
                        if (result.ok && result.data && result.data.success) {
                            if (settings.concludeSuccessMessage) {
                                queuePostReloadToast(storageKey, 'success', 'Sucesso', settings.concludeSuccessMessage);
                                global.location.reload();
                                return;
                            }
                            queueByKey(storageKey, 'generic.operation.success', {}, 'success', 'Sucesso', 'Operação concluída.')
                                .then(function () {
                                    global.location.reload();
                                });
                            return;
                        }
                        notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao concluir.');
                        button.disabled = false;
                    })
                    .catch(function () {
                        notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                        button.disabled = false;
                    });
            });
        });
    }

    App.GmeList = {
        initialize: initialize
    };
})(window);
