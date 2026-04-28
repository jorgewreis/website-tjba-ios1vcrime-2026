(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function formatNome(value) {
        let nome = String(value || '').trim().replace(/\s+/g, ' ');
        if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            nome = App.ProperNameHelper.formatProper(nome, 255);
        } else if (typeof global.TextFormatter !== 'undefined' && typeof global.TextFormatter.formatProperName === 'function') {
            nome = global.TextFormatter.formatProperName(nome, 255);
        }
        return nome.slice(0, 255);
    }

    function initialize() {
        if (!common) {
            return;
        }

        const nomeEl = global.document.getElementById('nome');
        const nomeFantasiaEl = global.document.getElementById('nome_fantasia');
        const cnpjEl = global.document.getElementById('cnpj');

        nomeEl?.addEventListener('blur', function () {
            nomeEl.value = formatNome(nomeEl.value || '');
        });

        nomeFantasiaEl?.addEventListener('blur', function () {
            nomeFantasiaEl.value = String(nomeFantasiaEl.value || '').trim().toUpperCase().slice(0, 120);
        });

        if (App.CNPJ && typeof App.CNPJ.init === 'function') {
            App.CNPJ.init('#cnpj');
        }

        global.document.getElementById('cadastrar-btn')?.addEventListener('click', function () {
            const button = this;
            const nome = formatNome(nomeEl?.value || '');
            if (nomeEl) {
                nomeEl.value = nome;
            }
            if (!nome) {
                common.notify('error', 'Erro', 'Nome e obrigatorio.');
                return;
            }

            const cnpjRaw = String(cnpjEl?.value || '').replace(/\D/g, '');
            if (cnpjRaw && cnpjRaw.length === 14 && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
                common.notify('error', 'Erro', 'CNPJ invalido. Verifique os digitos.');
                return;
            }

            button.disabled = true;
            common.postJson('/api/instituicoes-publicas/create.php', {
                nome: nome,
                nome_fantasia: String(nomeFantasiaEl?.value || '').trim().toUpperCase().slice(0, 120) || null,
                cnpj: cnpjRaw || null,
                observacoes: String(global.document.getElementById('observacoes')?.value || '').trim() || null
            }).then(function (result) {
                if (result.data && result.data.id) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'generic.institution.create.success',
                            {},
                            'success',
                            'Sucesso',
                            'Cadastrada.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Cadastrada.');
                    }
                    global.location.href = '/frame.php?page=instituicoespublicasedit&id=' + encodeURIComponent(result.data.id);
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

    App.InstituicoesPublicasCreate = {
        initialize: initialize
    };
})(window);
