(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        const storageKey = settings.storageKey || 'cartorio.postReloadToast';
        const requireSecondClick = common.createSecondClickGuard();

        global.document.querySelectorAll('.btn-desvincular').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                const processoId = button.getAttribute('data-processo-id');
                if (!id || !processoId) {
                    return;
                }
                if (!requireSecondClick('desvincular-processo-parte-' + id, 'Clique novamente em Desvincular em até 5 segundos para confirmar.')) {
                    return;
                }
                button.disabled = true;
                common.postJson('/api/processos-partes/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        if (typeof common.queuePostReloadToastByKey === 'function') {
                            common.queuePostReloadToastByKey(
                                'generic.link.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                'Vínculo removido com sucesso.',
                                storageKey
                            ).then(function () {
                                global.location.reload();
                            });
                            return;
                        }
                        common.queuePostReloadToast('success', 'Sucesso', 'Vínculo removido com sucesso.', storageKey);
                        global.location.reload();
                        return;
                    }
                    common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                    button.disabled = false;
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexão.');
                    }
                    button.disabled = false;
                });
            });
        });
    }

    App.ConsultaProcessosEdit = {
        initialize: initialize
    };
})(window);
