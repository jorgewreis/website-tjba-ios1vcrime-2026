(function (window) {
    'use strict';

    function copyProcessNumber(numero) {
        var text = String(numero || '').trim();
        if (text === '') {
            return;
        }
        var payload = {
            type: 'success',
            title: 'Sucesso',
            message: 'O número ' + text + ' foi copiado com sucesso.'
        };
        function done() {
            if (typeof window.showToast === 'function') {
                window.showToast(payload);
            } else if (window.App && App.Page && typeof App.Page.notify === 'function') {
                App.Page.notify('success', payload.title, payload.message);
            }
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(done);
        } else {
            done();
        }
    }

    function initializePanelToggles() {
        var panels = document.querySelectorAll('[data-v2-panel]');

        function setPanelVisible(name, visible) {
            var panel = document.querySelector('[data-v2-panel="' + name + '"]');
            if (!panel) {
                return;
            }
            panel.style.display = visible ? 'flex' : 'none';
        }

        function panelNameFromTrigger(el) {
            return el.getAttribute('data-v2-panel-trigger') || '';
        }

        document.querySelectorAll('[data-v2-panel-trigger]').forEach(function (trigger) {
            function onActivate(event) {
                if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                if (event.type === 'keydown') {
                    event.preventDefault();
                }
                var name = panelNameFromTrigger(trigger);
                if (!name) {
                    return;
                }
                var panel = document.querySelector('[data-v2-panel="' + name + '"]');
                var willShow = panel && panel.style.display === 'none';
                panels.forEach(function (p) {
                    p.style.display = 'none';
                });
                if (willShow) {
                    setPanelVisible(name, true);
                }
            }
            trigger.addEventListener('click', onActivate);
            trigger.addEventListener('keydown', onActivate);
        });
    }

    function bindCopyProcesso() {
        document.querySelectorAll('.js-v2-copy-processo').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                var numero = btn.getAttribute('data-processo-numero') || '';
                copyProcessNumber(numero);
            });
        });
    }

    function bindContinuarSubtarefa(ctx) {
        var notify = ctx.notify;
        var queuePostReloadToast = ctx.queuePostReloadToast;

        document.querySelectorAll('.js-continuar-subtarefa').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var subtarefaId = btn.getAttribute('data-subtarefa-id');
                if (!subtarefaId) {
                    return;
                }
                btn.disabled = true;
                fetch('/api/cartorio-cartas-expedidas/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        subtarefa_id: subtarefaId,
                        dados_especificos: null
                    })
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                            if (data.tarefa_principal_concluida && data.tarefa_principal_mensagem) {
                                queuePostReloadToast('success', 'Sucesso', data.tarefa_principal_mensagem);
                            }
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', data.error || 'Erro ao concluir subtarefa.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão.');
                        btn.disabled = false;
                    });
            });
        });
    }

    function bindExportSelect() {
        var sel = document.getElementById('list-export-v2');
        if (!sel) {
            return;
        }
        sel.addEventListener('change', function () {
            var v = String(sel.value || '');
            if (v === 'print') {
                window.print();
                sel.selectedIndex = 0;
                return;
            }
            var href = '';
            if (v === 'csv') {
                href = String(sel.getAttribute('data-csv-href') || '').trim();
            } else if (v === 'pdf') {
                href = String(sel.getAttribute('data-pdf-href') || '').trim();
            }
            if (href !== '') {
                window.open(href, '_blank', 'noopener,noreferrer');
            }
            sel.selectedIndex = 0;
        });
    }

    function initialize(config) {
        initializePanelToggles();
        bindCopyProcesso();
        bindExportSelect();

        if (window.CartasExpedidasSearch && typeof window.CartasExpedidasSearch.initializeProcessSearch === 'function') {
            window.CartasExpedidasSearch.initializeProcessSearch({
                inputId: 'search-input',
                itemsPerPageId: 'items-per-page',
                filters: [
                    { id: 'filter-tipo', param: 'finalidade' },
                    { id: 'filter-status', param: 'status' }
                ],
                minSearchDigits: 3,
                debounceDelay: 1000,
                initialProcessFilter: config.initialProcessFilter || ''
            });
        }

        if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
            App.RegistroMetadataList.bind({
                storageKey: 'cartorio.postReloadToast',
                cancelEndpoint: '/api/cartorio-cartas-expedidas/cancel.php',
                cancelToastKey: 'cartorio.cartas_expedidas.cancel.success',
                cancelFallback: { type: 'success', title: 'Sucesso', message: 'Carta cancelada.' }
            });
        }

        if (!window.App || !App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
            return;
        }

        App.CartorioTaskList.initialize({
            storageKey: 'cartorio.postReloadToast',
            newButtonId: 'novo-cadastro-btn',
            newHref: '/frame.php?page=cartasexpedidascreate',
            onReady: function (ctx) {
                bindContinuarSubtarefa(ctx);
                if (window.App && App.Page && typeof App.Page.flushPostReloadToast === 'function') {
                    App.Page.flushPostReloadToast('cartorio.postReloadToast');
                }
            }
        });
    }

    window.CartasExpedidasV2List = {
        initialize: initialize
    };
})(window);
