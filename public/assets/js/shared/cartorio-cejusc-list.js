(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function buildFilterNavigator(routePage) {
        return function applyFilters() {
            const status = global.document.getElementById('filter-status')?.value ?? '';
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

            if (processo) {
                params.set('processo', processo);
            } else {
                params.delete('processo');
            }

            global.location.href = '?' + params.toString();
        };
    }

    function bindConcludeButtons(ctx, endpoint) {
        global.document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                const subtarefaId = button.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }

                button.disabled = true;

                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subtarefa_id: subtarefaId })
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
            newHref: settings.newHref || '/frame.php?page=cejusccreate',
            editButtonSelector: '.btn-editar[data-carta-id]',
            getEditId: function (button) {
                return button.dataset ? button.dataset.cartaId || '' : '';
            },
            editHrefBuilder: function (itemId) {
                return (settings.editBaseHref || '/frame.php?page=cejuscedit&id=') + encodeURIComponent(itemId);
            },
            filters: {
                triggerIds: ['filter-status', 'items-per-page'],
                searchInputId: 'search-input',
                searchMode: 'enter',
                apply: buildFilterNavigator(settings.routePage || 'cejusc')
            },
            onReady: function (ctx) {
                bindConcludeButtons(ctx, settings.concludeEndpoint || '/api/cartorio-cejusc/concluir-subtarefa.php');

                if (typeof settings.onReady === 'function') {
                    settings.onReady(ctx);
                }
            }
        });
    }

    App.CartorioCejuscList = {
        initialize: initialize
    };
})(window);
