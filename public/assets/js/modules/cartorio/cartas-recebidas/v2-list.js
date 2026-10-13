(function (window) {
    'use strict';

    function bindCopyProcessNumber() {
        document.querySelectorAll('.js-v2-copy-processo').forEach(function (button) {
            function copy(event) {
                event.preventDefault();
                var number = String(button.getAttribute('data-processo-numero') || '').trim();
                if (!number) return;
                var notify = function () {
                    if (typeof window.showToast === 'function') window.showToast({ type: 'success', title: 'Sucesso', message: 'O número ' + number + ' foi copiado com sucesso.' });
                };
                if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(number).then(notify).catch(notify);
                else notify();
            }
            button.addEventListener('click', copy);
            button.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') copy(event); });
        });
    }

    function bindCompleteSubtasks(ctx) {
        document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (button) {
            button.addEventListener('click', function () {
                var subtarefaId = String(button.getAttribute('data-subtarefa-id') || '').trim();
                if (!subtarefaId || button.disabled) return;
                button.disabled = true;
                fetch('/api/cartorio-cartas-recebidas/concluir-subtarefa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ subtarefa_id: subtarefaId, dados_especificos: null })
                }).then(function (response) {
                    return response.json().then(function (data) { return { ok: response.ok, data: data }; });
                }).then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        ctx.queuePostReloadToast('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                        if (result.data.tarefa_principal_concluida && result.data.tarefa_principal_mensagem) {
                            ctx.queuePostReloadToast('success', 'Sucesso', result.data.tarefa_principal_mensagem);
                        }
                        window.location.reload();
                        return;
                    }
                    ctx.notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao concluir a etapa.');
                    button.disabled = false;
                }).catch(function () {
                    ctx.notify('error', 'Erro', 'Erro de conexão.');
                    button.disabled = false;
                });
            });
        });
    }

    function initialize(config) {
        var settings = config || {};
        bindCopyProcessNumber();
        var exportSelect = document.getElementById('list-export-v2');
        if (exportSelect) exportSelect.addEventListener('change', function () {
            var value = exportSelect.value;
            if (value === 'print') window.print();
            else if (value === 'csv' || value === 'pdf') {
                var url = exportSelect.getAttribute(value === 'csv' ? 'data-csv-href' : 'data-pdf-href');
                if (url) window.open(url, '_blank', 'noopener,noreferrer');
            }
            exportSelect.selectedIndex = 0;
        });
        if (window.CartasRecebidasSearch && typeof window.CartasRecebidasSearch.initializeProcessSearch === 'function') {
            window.CartasRecebidasSearch.initializeProcessSearch({
                inputId: 'search-input',
                filters: [{ id: 'filter-tipo', param: 'finalidade' }, { id: 'filter-status', param: 'status' }],
                minSearchDigits: 3,
                debounceDelay: 1000,
                initialProcessFilter: settings.initialProcessFilter || ''
            });
        }
        if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
            App.RegistroMetadataList.bind({ storageKey: 'cartorio.postReloadToast' });
        }
        if (window.App && App.CartorioTaskList && typeof App.CartorioTaskList.initialize === 'function') {
            App.CartorioTaskList.initialize({
                storageKey: 'cartorio.postReloadToast',
                enableDesalocar: false,
                newButtonId: 'novo-cadastro-btn',
                newHref: '/frame.php?page=cartasrecebidascreate',
                onReady: function (ctx) {
                    bindCompleteSubtasks(ctx);
                    if (window.App.Page && typeof App.Page.flushPostReloadToast === 'function') App.Page.flushPostReloadToast('cartorio.postReloadToast');
                }
            });
        }
    }

    window.CartasRecebidasV2List = { initialize: initialize };
})(window);
