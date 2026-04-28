(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        global.document.getElementById('salvar-btn')?.addEventListener('click', function () {
            const button = this;
            const processoId = String(global.document.getElementById('processo-id')?.value || '');
            const tipoParte = String(global.document.getElementById('tipo_parte')?.value || '').trim();
            const polo = String(global.document.getElementById('polo')?.value || '').trim();

            if (!tipoParte || !polo) {
                common.notify('error', 'Erro', 'Preencha tipo e polo.');
                return;
            }

            button.disabled = true;
            common.postJson('/api/processos-partes/update.php', {
                id: String(global.document.getElementById('vinculo-id')?.value || ''),
                tipo_parte: tipoParte,
                polo: polo,
                prioridade_mulher: !!global.document.getElementById('prioridade_mulher')?.checked,
                prioridade_crianca_adolescente: !!global.document.getElementById('prioridade_crianca_adolescente')?.checked,
                prioridade_idoso: !!global.document.getElementById('prioridade_idoso')?.checked,
                prioridade_pessoa_deficiencia: !!global.document.getElementById('prioridade_pessoa_deficiencia')?.checked
            }).then(function (result) {
                if (result.data && result.data.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'generic.link.update.success',
                            {},
                            'success',
                            'Sucesso',
                            'Vinculo atualizado.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Vinculo atualizado.');
                    }
                    global.location.href = (settings.backBaseHref || '/frame.php?page=consultaprocessosedit&id=') + encodeURIComponent(processoId);
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
    }

    App.ProcessosPartesEdit = {
        initialize: initialize
    };
})(window);
