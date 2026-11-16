(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function buildFilterNavigator(routePage) {
        return function applyFilters() {
            const status = global.document.getElementById('filter-status')?.value ?? '';
            const tipoEdital = global.document.getElementById('filter-tipo-edital')?.value ?? '';
            const processo = global.document.getElementById('search-input')?.value?.trim() ?? '';
            const perPage = '15';
            const params = new URLSearchParams(global.location.search);
            params.set('page', routePage);
            params.set('page_number', '1');
            params.set('items_per_page', perPage);

            if (status) {
                params.set('status', status);
            } else {
                params.delete('status');
            }

            if (tipoEdital) {
                params.set('tipo_edital', tipoEdital);
            } else {
                params.delete('tipo_edital');
            }

            if (processo) {
                params.set('processo', processo);
            } else {
                params.delete('processo');
            }

            global.location.href = '?' + params.toString();
        };
    }

    function normalizeTipo(value) {
        return String(value || '').trim().toLowerCase();
    }

    function queueByKey(ctx, key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            ctx.queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    ctx.queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
                    return;
                }
                ctx.queuePostReloadToast(
                    toast.type || fallbackType,
                    toast.title || fallbackTitle,
                    toast.message
                );
            })
            .catch(function () {
                ctx.queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage);
            });
    }

    function notifyByKey(ctx, key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (App.Page && typeof App.Page.notifyByKey === 'function') {
            App.Page.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            ctx.notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }

        catalog.notifyByKey(key, vars || {}).catch(function () {
            ctx.notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function bindConcludeButtons(ctx, endpoint) {
        global.document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                const subtarefaId = button.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }

                const payload = { subtarefa_id: subtarefaId };
                const block = button.closest('[data-subtarefa-id][data-subtarefa-tipo]');
                const tipo = normalizeTipo(block?.getAttribute('data-subtarefa-tipo'));
                const requirePublicationDate = ['certificacao_publicacao', 'certificacao_diario', 'publicacao_certificada'].includes(tipo);

                if (requirePublicationDate) {
                    const dataInput = block ? block.querySelector('.subtask-data-publicacao') : null;
                    const dataPublicacao = dataInput ? (dataInput.value || '').trim() : '';
                    if (!dataPublicacao) {
                        notifyByKey(
                            ctx,
                            'cartorio.editais.publication_date.required',
                            {},
                            'error',
                            'Atenção',
                            'A data da publicação é obrigatória para concluir esta subtarefa.'
                        );
                        return;
                    }
                    payload.dados_especificos = { data_publicacao: dataPublicacao };
                }

                button.disabled = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.success) {
                            queueByKey(
                                ctx,
                                'cartorio.subtask.conclude.success',
                                {},
                                'success',
                                'Sucesso',
                                'Subtarefa concluída com sucesso.'
                            ).then(function () {
                                if (data.tarefa_principal_concluida && data.tarefa_principal_mensagem) {
                                    ctx.queuePostReloadToast('success', 'Sucesso', data.tarefa_principal_mensagem);
                                }
                                global.location.reload();
                            });
                            return;
                        }

                        notifyByKey(
                            ctx,
                            'generic.error.unexpected',
                            {},
                            'error',
                            'Erro',
                            data.error || 'Erro ao concluir.'
                        );
                        button.disabled = false;
                    })
                    .catch(function () {
                        notifyByKey(
                            ctx,
                            'generic.error.connection',
                            {},
                            'error',
                            'Erro',
                            'Erro de conexão.'
                        );
                        button.disabled = false;
                    });
            });
        });
    }

    function bindReconciliation(ctx, settings) {
        const button = global.document.getElementById('reconciliar-editais-btn');
        if (!button || !Array.isArray(settings.editalIds) || settings.editalIds.length === 0) return;
        button.addEventListener('click', function () {
            if (!global.confirm('Atualizar as etapas dos editais exibidos nesta página?')) return;
            button.disabled = true;
            fetch(settings.reconcileEndpoint || '/api/cartorio-editais/reconciliar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ edital_ids: settings.editalIds }),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) {
                        throw new Error('Não foi possível atualizar os editais. Verifique a listagem antes de tentar novamente.');
                    }
                    const result = data.resultado;
                    const integerFields = ['solicitados', 'editais_alterados', 'editais_com_etapas_criadas', 'etapas_criadas', 'editais_com_datas_calculadas', 'inalterados', 'nao_encontrados', 'finalizados', 'falhas'];
                    const validResult = result && integerFields.every(function (field) {
                        return Number.isInteger(result[field]) && result[field] >= 0;
                    });
                    if (!validResult || result.falhas !== 0 ||
                        result.solicitados !== settings.editalIds.length ||
                        result.editais_alterados + result.inalterados + result.nao_encontrados !== result.solicitados ||
                        result.editais_com_etapas_criadas > result.editais_alterados ||
                        result.etapas_criadas < result.editais_com_etapas_criadas ||
                        result.finalizados > result.editais_alterados) {
                        throw new Error('A resposta da atualização está incompleta. Atualize a listagem antes de continuar.');
                    }
                    const etapas = result.etapas_criadas;
                    const editais = result.editais_com_etapas_criadas;
                    const editaisComDatas = result.editais_com_datas_calculadas;
                    const inalterados = result.inalterados;
                    const finalizados = result.finalizados;
                    const ausentes = result.nao_encontrados;
                    ctx.queuePostReloadToast(
                        'success',
                        'Atualização concluída',
                        editais + ' editais receberam ' + etapas + ' etapas; ' + editaisComDatas +
                            ' tiveram as datas calculadas; ' + inalterados + ' permaneceram inalterados; ' +
                            finalizados + ' foram finalizados; ' + ausentes + ' não foram encontrados.'
                    );
                    global.location.reload();
                });
            }).catch(function (error) {
                ctx.notify('error', 'Erro', error.message || 'Não foi possível atualizar os editais.');
                button.disabled = false;
            });
        });
    }

    function initialize(config) {
        if (!App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
            return null;
        }

        const settings = config || {};
        return App.CartorioTaskList.initialize({
            storageKey: settings.storageKey || 'cartorio.postReloadToast',
            newButtonId: 'novo-cadastro-btn',
            newHref: settings.newHref || '/frame.php?page=editaiscreate',
            editButtonSelector: '.btn-editar[data-edital-id]',
            getEditId: function (button) {
                return button.dataset ? button.dataset.editalId || '' : '';
            },
            editHrefBuilder: function (itemId) {
                return (settings.editBaseHref || '/frame.php?page=editaisedit&id=') + encodeURIComponent(itemId);
            },
            filters: {
                triggerIds: ['filter-status', 'filter-tipo-edital'],
                searchInputId: 'search-input',
                searchMode: 'enter',
                apply: buildFilterNavigator(settings.routePage || 'editais')
            },
            onReady: function (ctx) {
                bindConcludeButtons(ctx, settings.concludeEndpoint || '/api/cartorio-editais/concluir-subtarefa.php');
                bindReconciliation(ctx, settings);

                if (typeof settings.onReady === 'function') {
                    settings.onReady(ctx);
                }
            }
        });
    }

    App.CartorioEditaisList = {
        initialize: initialize
    };
})(window);
