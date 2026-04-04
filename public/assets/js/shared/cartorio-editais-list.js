(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function buildFilterNavigator(routePage) {
        return function applyFilters() {
            const status = global.document.getElementById('filter-status')?.value ?? '';
            const tipoEdital = global.document.getElementById('filter-tipo-edital')?.value ?? '';
            const processo = global.document.getElementById('search-input')?.value?.trim() ?? '';
            const perPage = global.document.getElementById('items-per-page')?.value ?? '25';
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
                        ctx.notify('error', 'Atenção', 'A data da publicação é obrigatória para concluir esta subtarefa.');
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
                            ctx.queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                            if (data.tarefa_principal_concluida && data.tarefa_principal_mensagem) {
                                ctx.queuePostReloadToast('success', 'Sucesso', data.tarefa_principal_mensagem);
                            }
                            global.location.reload();
                            return;
                        }

                        ctx.notify('error', 'Erro', data.error || 'Erro ao concluir.');
                        button.disabled = false;
                    })
                    .catch(function () {
                        ctx.notify('error', 'Erro', 'Erro de conexão.');
                        button.disabled = false;
                    });
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
                triggerIds: ['filter-status', 'filter-tipo-edital', 'items-per-page'],
                searchInputId: 'search-input',
                searchMode: 'enter',
                apply: buildFilterNavigator(settings.routePage || 'editais')
            },
            onReady: function (ctx) {
                bindConcludeButtons(ctx, settings.concludeEndpoint || '/api/cartorio-editais/concluir-subtarefa.php');

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
