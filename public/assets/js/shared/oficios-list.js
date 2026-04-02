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

    function flushPostReloadToast(storageKey, highlightField) {
        let list = [];

        if (App.Page && typeof App.Page.flushPostReloadToast === 'function') {
            list = App.Page.flushPostReloadToast(storageKey, { type: 'info', title: 'Informação' }) || [];
        } else {
            try {
                const raw = global.sessionStorage.getItem(storageKey);
                if (raw) {
                    global.sessionStorage.removeItem(storageKey);
                    const data = JSON.parse(raw);
                    list = Array.isArray(data) ? data : (data && data.message ? [data] : []);
                    list.forEach(function (item) {
                        if (item && item.message) {
                            notify(item.type || 'info', item.title || 'Informação', item.message);
                        }
                    });
                }
            } catch (error) {
                list = [];
            }
        }

        const field = highlightField || '';
        if (!field) {
            return list;
        }

        list.forEach(function (item) {
            const rowId = item && item[field] ? String(item[field]).trim() : '';
            if (!rowId) {
                return;
            }
            const row = global.document.getElementById(rowId);
            if (!row) {
                return;
            }
            row.classList.add('status-finished');
            global.setTimeout(function () {
                row.classList.remove('status-finished');
            }, 10000);
        });

        return list;
    }

    function buildSecondClickGuard() {
        if (App.Page && typeof App.Page.createSecondClickGuard === 'function') {
            return App.Page.createSecondClickGuard();
        }

        const pending = {};
        return function requireSecondClick(key, message) {
            const now = Date.now();
            if ((pending[key] || 0) > now) {
                delete pending[key];
                return true;
            }
            pending[key] = now + 5000;
            notify('info', 'Confirmação', message);
            return false;
        };
    }

    function bindFilters(config) {
        const applyFilters = function () {
            const processo = global.document.getElementById(config.searchInputId || 'search-input')?.value?.trim() ?? '';
            const perPage = global.document.getElementById(config.itemsPerPageId || 'items-per-page')?.value ?? '25';
            const params = new URLSearchParams(global.location.search);
            params.set('page', String(config.routePage || ''));
            params.set('page_number', '1');
            params.set('items_per_page', perPage);

            if (processo) {
                params.set('processo', processo);
                params.delete('status');
                params.delete('ano');
            } else {
                params.delete('processo');
                const status = global.document.getElementById(config.statusId || 'filter-status')?.value ?? '';
                const ano = global.document.getElementById(config.yearId || 'filter-ano')?.value ?? '';
                if (status) { params.set('status', status); } else { params.delete('status'); }
                if (ano) { params.set('ano', ano); } else { params.delete('ano'); }
            }

            global.location.href = '?' + params.toString();
        };

        global.document.getElementById(config.statusId || 'filter-status')?.addEventListener('change', applyFilters);
        global.document.getElementById(config.yearId || 'filter-ano')?.addEventListener('change', applyFilters);
        global.document.getElementById(config.itemsPerPageId || 'items-per-page')?.addEventListener('change', applyFilters);
        global.document.getElementById(config.searchInputId || 'search-input')?.addEventListener('keypress', function (event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        });
    }

    function bindEditButtons(config) {
        if (!config.editButtonSelector || typeof config.editHrefBuilder !== 'function') {
            return;
        }

        global.document.querySelectorAll(config.editButtonSelector).forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.dataset.oficioId || '';
                if (id) {
                    global.location.href = config.editHrefBuilder(id, button);
                }
            });
        });
    }

    function bindProcessRule(config) {
        const typeSelect = global.document.getElementById(config.typeSelectId);
        const processHint = config.processHintId ? global.document.getElementById(config.processHintId) : null;
        const processLabel = config.processLabelSelector ? global.document.querySelector(config.processLabelSelector) : null;

        const isAdministrative = function () {
            const option = typeSelect?.selectedOptions?.[0];
            return !!(option && option.getAttribute('data-oficio-grupo') === 'administrativo');
        };

        const updateProcessRule = function () {
            const admin = isAdministrative();
            if (processHint) {
                processHint.textContent = admin ? 'opcional' : '*obrigatório';
                processHint.className = admin ? 'label-hint-automatizado' : 'label-hint-obrigatorio';
            }
            if (processLabel) {
                processLabel.title = admin
                    ? 'Para tipos administrativos o número do processo pode ficar em branco.'
                    : '';
            }
        };

        typeSelect?.addEventListener('change', updateProcessRule);
        updateProcessRule();

        return {
            isAdministrative: isAdministrative,
            updateProcessRule: updateProcessRule
        };
    }

    function bindClearForm(config, ctx) {
        if (!config.clearButtonId) {
            return;
        }

        global.document.getElementById(config.clearButtonId)?.addEventListener('click', function () {
            const processInput = global.document.getElementById(config.processInputId || 'filter-processo');
            const typeSelect = global.document.getElementById(config.typeSelectId);
            const classInput = global.document.getElementById(config.classInputId || 'filter-classe');
            const hiddenClassCode = global.document.getElementById(config.classCodeInputId || 'hidden-classe-codigo');
            const hiddenClassDescription = global.document.getElementById(config.classDescriptionInputId || 'hidden-classe-descricao');

            if (processInput) { processInput.value = ''; }
            if (typeSelect) { typeSelect.selectedIndex = 0; }
            if (global.DataJudCycle && typeof global.DataJudCycle.resetInputs === 'function') {
                global.DataJudCycle.resetInputs({
                    processInput: processInput || null,
                    classeInput: classInput || null,
                    classeCodigo: hiddenClassCode || null,
                    classeDescricao: hiddenClassDescription || null
                });
            } else {
                if (classInput) { classInput.value = ''; classInput.removeAttribute('data-datajud-initial'); }
                if (hiddenClassCode) { hiddenClassCode.value = ''; }
                if (hiddenClassDescription) { hiddenClassDescription.value = ''; }
            }

            if (typeof config.onClear === 'function') {
                config.onClear(ctx);
            }

            if (ctx.updateProcessRule) {
                ctx.updateProcessRule();
            }
        });
    }

    function bindCreate(config, ctx) {
        if (!config.createButtonId || !config.createEndpoint || typeof config.buildRequest !== 'function') {
            return;
        }

        global.document.getElementById(config.createButtonId)?.addEventListener('click', function () {
            const request = config.buildRequest(ctx);
            if (!request) {
                return;
            }

            fetch(config.createEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(request),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.id) {
                        const payload = typeof config.buildSuccessPayload === 'function'
                            ? config.buildSuccessPayload(result.data, ctx)
                            : { type: 'success', title: 'Sucesso', message: 'Ofício registrado com sucesso.' };
                        queuePostReloadToast(ctx.storageKey, payload);
                        global.location.reload();
                        return;
                    }

                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao registrar.');
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                });
        });
    }

    function bindDesalocar(config) {
        if (!config.enableDesalocar) {
            return;
        }

        const requireSecondClick = buildSecondClickGuard();
        global.document.addEventListener('click', function (event) {
            const button = event.target.closest(config.desalocarSelector || '.js-desalocar');
            if (!button) {
                return;
            }

            const alocadoId = button.getAttribute('data-alocado-id') || '';
            const tipo = button.getAttribute('data-tipo') || '';
            const endpoint = button.getAttribute('data-endpoint') || '';
            if (!alocadoId || !endpoint) {
                return;
            }

            if (!requireSecondClick('desalocar-' + alocadoId, config.desalocarConfirmMessage || 'Clique novamente para confirmar a remoção da alocação.')) {
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
                        notify('success', 'Sucesso', config.desalocarSuccessMessage || 'Alocação removida.');
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

    function initialize(config) {
        const settings = config || {};
        const ctx = {
            storageKey: settings.storageKey || 'cartorio.postReloadToast',
            notify: notify,
            queuePostReloadToast: function (payload) {
                queuePostReloadToast(settings.storageKey || 'cartorio.postReloadToast', payload);
            }
        };

        flushPostReloadToast(ctx.storageKey, settings.highlightField || '');
        bindFilters(settings);
        bindEditButtons(settings);

        const processRule = bindProcessRule(settings);
        ctx.isAdministrative = processRule.isAdministrative;
        ctx.updateProcessRule = processRule.updateProcessRule;

        bindClearForm(settings, ctx);
        bindCreate(settings, ctx);
        bindDesalocar(settings);

        if (typeof settings.onReady === 'function') {
            settings.onReady(ctx);
        }

        return ctx;
    }

    App.OficiosList = {
        initialize: initialize
    };
})(window);
