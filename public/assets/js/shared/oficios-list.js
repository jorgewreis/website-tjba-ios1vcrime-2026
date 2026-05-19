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

    function bindFilters(config) {
        const applyFilters = function () {
            const oficio = global.document.getElementById(config.searchInputId || 'search-input')?.value?.trim() ?? '';
            const perPage = global.document.getElementById(config.itemsPerPageId || 'items-per-page')?.value ?? '25';
            const params = new URLSearchParams(global.location.search);
            params.set('page', String(config.routePage || ''));
            params.set('page_number', '1');
            params.set('items_per_page', perPage);

            if (oficio) {
                params.set('oficio', oficio);
                params.delete('status');
                const ano = global.document.getElementById(config.yearId || 'filter-ano')?.value ?? '';
                if (ano) {
                    params.set('ano', ano);
                } else {
                    params.delete('ano');
                }
            } else {
                params.delete('oficio');
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

    function bindRegistroMetadataActions(config, ctx) {
        const cancelEndpointDefault = String(config.cancelEndpoint || '').trim();
        if (cancelEndpointDefault === '' && !config.editButtonSelector) {
            return;
        }

        if (App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
            App.RegistroMetadataList.bind({
                storageKey: ctx.storageKey,
                cancelEndpoint: cancelEndpointDefault,
                cancelToastKey: 'oficios.cancel.success',
                cancelFallback: { type: 'success', title: 'Sucesso', message: 'Ofício cancelado.' }
            });
        }
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

        const createButton = global.document.getElementById(config.createButtonId);
        const processInput = global.document.getElementById(config.processInputId || 'filter-processo');
        const gate = (App.CartorioCreateHelpers && typeof App.CartorioCreateHelpers.initCreateButtonGate === 'function')
            ? App.CartorioCreateHelpers.initCreateButtonGate({ button: createButton, initialLabel: 'Novo cadastro', readyLabel: 'Cadastrar' })
            : null;
        const hasDataJudFlow = !!global.document.querySelector('[data-datajud-related="true"]');

        if (gate && processInput) {
            const refreshGateState = function () {
                if (ctx.isAdministrative()) {
                    gate.setReady();
                    return;
                }

                const rawProcess = String(processInput.value || '').trim();
                const processDigits = rawProcess.replace(/\D/g, '');
                if (processDigits.length !== 20) {
                    gate.setWaiting();
                    return;
                }

                const classInput = global.document.getElementById(config.classInputId || 'filter-classe');
                const isClassLoading = !!(classInput && (classInput.value === 'Aguarde...' || classInput.value === 'Consultando DataJud…'));
                // Libera cadastro com processo valido, mesmo sem classe retornada pelo DataJud.
                if (!isClassLoading) {
                    gate.setReady();
                    return;
                }

                gate.setWaiting();
            };

            processInput.addEventListener('input', function () {
                const digits = String(processInput.value || '').replace(/\D/g, '');
                if (digits.length < 20) {
                    gate.setWaiting();
                    return;
                }
                refreshGateState();
            });
            const typeSelect = global.document.getElementById(config.typeSelectId || '');
            typeSelect?.addEventListener('change', function () {
                refreshGateState();
            });
            global.addEventListener('datajudClasseResolved', function (event) {
                const detail = event && event.detail ? event.detail : {};
                if (detail.processoInputId && processInput.id && detail.processoInputId !== processInput.id) {
                    return;
                }
                refreshGateState();
            });
            global.addEventListener('cnjInvalid', function (event) {
                const detail = event && event.detail ? event.detail : {};
                if (detail.inputId && processInput.id && detail.inputId !== processInput.id) {
                    return;
                }
                refreshGateState();
            });
            refreshGateState();
        }

        if (gate && !hasDataJudFlow) {
            gate.setReady();
        }

        createButton?.addEventListener('click', function () {
            if (gate) {
                gate.setWaiting();
            }
            if (processInput && typeof global.normalizeAndValidateCNJValue === 'function' && !ctx.isAdministrative()) {
                const normalized = global.normalizeAndValidateCNJValue(processInput.value || '', { toastOnInvalid: true });
                if (!normalized.ok) {
                    processInput.value = '';
                    processInput.focus();
                    if (gate) {
                        gate.setError();
                    }
                    return;
                }
                processInput.value = normalized.formatted;
            }
            const request = config.buildRequest(ctx);
            if (!request) {
                if (gate) {
                    gate.setError();
                }
                return;
            }
            if (gate) {
                gate.setSubmitting();
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
                        if (typeof config.buildSuccessPayload === 'function') {
                            const payload = config.buildSuccessPayload(result.data, ctx);
                            queuePostReloadToast(ctx.storageKey, payload);
                            if (gate) {
                                gate.setInitial();
                            }
                            global.location.reload();
                            return;
                        }

                        const numeroOficio = String(result.data.numero_oficio || '').trim();
                        const vars = numeroOficio ? { numero_oficio: numeroOficio } : {};
                        queueByKey(ctx.storageKey, 'oficios.create.success', vars, {
                            type: 'success',
                            title: 'Sucesso',
                            message: 'Ofício registrado com sucesso.'
                        }).then(function () {
                            if (gate) {
                                gate.setInitial();
                            }
                            global.location.reload();
                        });
                        return;
                    }

                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao registrar.');
                    if (gate) {
                        gate.setInitial();
                    }
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    if (gate) {
                        gate.setInitial();
                    }
                });
        });
    }

    function bindDesalocar(config) {
        if (!config.enableDesalocar) {
            return;
        }

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
                        if (config.desalocarSuccessMessage) {
                            notify('success', 'Sucesso', config.desalocarSuccessMessage);
                        } else {
                            notifyByKey('cartorio.allocation.remove.success', {}, 'success', 'Sucesso', 'Alocação removida.');
                        }
                        global.setTimeout(function () {
                            global.location.reload();
                        }, 800);
                        return;
                    }

                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.');
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
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
        bindRegistroMetadataActions(settings, ctx);

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
