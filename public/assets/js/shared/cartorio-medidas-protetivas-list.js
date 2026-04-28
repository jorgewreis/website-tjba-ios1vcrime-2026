(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function buildFilterNavigator(routePage) {
        return function applyFilters() {
            const status = global.document.getElementById('filter-status')?.value || '';
            const processo = global.document.getElementById('search-input')?.value?.trim() || '';
            const perPage = global.document.getElementById('items-per-page')?.value || '25';
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
                const block = button.closest('.subtask[data-subtarefa-id][data-subtarefa-tipo]');
                const subtarefaId = block ? (block.getAttribute('data-subtarefa-id') || '') : '';
                const qtdInput = block ? block.querySelector('.subtask-qtd') : null;
                const tipo = block ? (block.getAttribute('data-subtarefa-tipo') || '') : '';

                if (!subtarefaId) {
                    return;
                }

                const exigeQuantidade = !!qtdInput;
                let minQ = tipo === 'cadastro_partes_pje' ? 0 : 1;
                let maxQ = 10;
                let qtdInt = 1;
                if (exigeQuantidade) {
                    const qtdRaw = String(qtdInput.value || '').trim();
                    qtdInt = parseInt(qtdRaw, 10);

                    if (qtdInput.getAttribute('data-qtd-min') !== null) {
                        minQ = parseInt(qtdInput.getAttribute('data-qtd-min'), 10);
                        if (isNaN(minQ)) {
                            minQ = tipo === 'cadastro_partes_pje' ? 0 : 1;
                        }
                    }

                    if (qtdInput.getAttribute('data-qtd-max') !== null) {
                        maxQ = parseInt(qtdInput.getAttribute('data-qtd-max'), 10);
                        if (isNaN(maxQ)) {
                            maxQ = 10;
                        }
                    }

                    if (qtdRaw === '' || isNaN(qtdInt)) {
                        ctx.notify('error', 'Campo obrigatório', 'Informe a quantidade.');
                        qtdInput.focus();
                        return;
                    }

                    if (qtdInt < minQ || qtdInt > maxQ) {
                        ctx.notify('error', 'Quantidade inválida', 'Use um valor entre ' + minQ + ' e ' + maxQ + '.');
                        qtdInput.focus();
                        return;
                    }
                }

                button.disabled = true;

                const dadosEspecificos = exigeQuantidade ? { qtd: qtdInt } : {};
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subtarefa_id: subtarefaId,
                        dados_especificos: dadosEspecificos
                    }),
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
            newHref: settings.newHref || '/frame.php?page=medidasprotetivascreate',
            editButtonSelector: '.btn-editar[data-medida-id]',
            getEditId: function (button) {
                return button.dataset ? button.dataset.medidaId || '' : '';
            },
            editHrefBuilder: function (itemId) {
                return (settings.editBaseHref || '/frame.php?page=medidasprotetivasedit&id=') + encodeURIComponent(itemId);
            },
            filters: {
                triggerIds: ['filter-status', 'items-per-page'],
                searchInputId: 'search-input',
                searchMode: 'enter',
                apply: buildFilterNavigator(settings.routePage || 'medidasprotetivas')
            },
            buttons: [
                {
                    selector: '.btn-open-cejusc',
                    onClick: function (button, event, ctx) {
                        const cejuscId = (button.getAttribute('data-cejusc-id') || '').trim();
                        if (!cejuscId) {
                            ctx.notify('error', 'Erro', 'Identificador da tarefa do Cejusc não encontrado.');
                            return;
                        }
                        global.location.href = '/frame.php?page=cejuscedit&id=' + encodeURIComponent(cejuscId);
                    }
                }
            ],
            onReady: function (ctx) {
                bindConcludeButtons(ctx, settings.concludeEndpoint || '/api/cartorio-medidas-protetivas/concluir-subtarefa.php');

                if (typeof settings.onReady === 'function') {
                    settings.onReady(ctx);
                }
            }
        });
    }

    App.CartorioMedidasProtetivasList = {
        initialize: initialize
    };
})(window);
