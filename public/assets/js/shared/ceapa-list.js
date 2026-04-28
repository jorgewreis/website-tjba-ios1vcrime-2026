(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function initialize() {
        if (!common) {
            return;
        }

        function applyFilters() {
            const status = global.document.getElementById('filter-status')?.value || '';
            const processo = global.document.getElementById('search-input')?.value?.trim() || '';
            const tipoSuspensao = global.document.getElementById('filter-tipo-suspensao')?.value || '';
            const perPage = global.document.getElementById('items-per-page')?.value || '25';
            const params = new URLSearchParams(global.location.search);
            params.set('page', 'ceapa');
            params.set('page_number', '1');
            params.set('items_per_page', perPage);
            if (status) params.set('status', status); else params.delete('status');
            if (processo) params.set('processo', processo); else params.delete('processo');
            if (tipoSuspensao) params.set('tipo_suspensao', tipoSuspensao); else params.delete('tipo_suspensao');
            global.location.href = '?' + params.toString();
        }

        global.document.getElementById('filter-status')?.addEventListener('change', applyFilters);
        global.document.getElementById('filter-tipo-suspensao')?.addEventListener('change', applyFilters);
        global.document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
        global.document.getElementById('search-input')?.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') applyFilters();
        });

        global.document.getElementById('novo-cadastro-btn')?.addEventListener('click', function () {
            global.location.href = '/frame.php?page=ceapacreate';
        });

        global.document.querySelectorAll('.btn-editar[data-ceapa-id]').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = this.dataset.ceapaId;
                if (id) {
                    global.location.href = '/frame.php?page=ceapaedit&id=' + encodeURIComponent(id);
                }
            });
        });

        const requireSecondClick = common.createSecondClickGuard();
        global.document.addEventListener('click', function (event) {
            const btn = event.target.closest('.js-desalocar');
            if (!btn) return;
            const alocadoId = btn.getAttribute('data-alocado-id') || '';
            const tipo = btn.getAttribute('data-tipo') || '';
            const endpoint = btn.getAttribute('data-endpoint') || '';
            if (!alocadoId || !endpoint) return;
            if (!requireSecondClick('desalocar-' + alocadoId, 'Clique novamente para confirmar a remoção da alocação.')) {
                return;
            }
            btn.disabled = true;
            common.postJson(endpoint, { alocado_id: alocadoId, tipo: tipo }).then(function (result) {
                if (result.data && result.data.deleted) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'cartorio.allocation.remove.success',
                            {},
                            'success',
                            'Sucesso',
                            'Alocação removida.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Alocação removida.');
                    }
                    setTimeout(function () { global.location.reload(); }, 800);
                    return;
                }
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey(
                        'cartorio.allocation.remove.error',
                        {},
                        'error',
                        'Erro',
                        (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.'
                    );
                } else {
                    common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao remover alocação.');
                }
                btn.disabled = false;
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexão.');
                }
                btn.disabled = false;
            });
        });
    }

    App.CeapaList = {
        initialize: initialize
    };
})(window);
