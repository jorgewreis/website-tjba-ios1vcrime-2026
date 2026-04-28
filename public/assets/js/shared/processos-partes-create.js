(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        global.document.getElementById('vincular-btn')?.addEventListener('click', function () {
            const button = this;
            const processoId = String(global.document.getElementById('processo-id')?.value || '');
            const parteId = String(global.document.getElementById('parte-id')?.value || '').trim();
            const tipoParte = String(global.document.getElementById('tipo_parte')?.value || '').trim();
            const polo = String(global.document.getElementById('polo')?.value || '').trim();

            if (!parteId || !tipoParte || !polo) {
                common.notify('error', 'Erro', 'Preencha parte, tipo e polo.');
                return;
            }

            button.disabled = true;
            common.postJson('/api/processos-partes/create.php', {
                processo_id: processoId,
                parte_id: parteId,
                tipo_parte: tipoParte,
                polo: polo
            }).then(function (result) {
                if (result.data && result.data.id) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'generic.link.create.success',
                            {},
                            'success',
                            'Sucesso',
                            'Parte vinculada.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Parte vinculada.');
                    }
                    global.location.href = (settings.backBaseHref || '/frame.php?page=consultaprocessosedit&id=') + encodeURIComponent(processoId);
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                button.disabled = false;
            }).catch(function () {
                button.disabled = false;
            });
        });
    }

    App.ProcessosPartesCreate = {
        initialize: initialize
    };
})(window);
