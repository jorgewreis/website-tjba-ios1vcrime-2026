(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function formatProfissao(input) {
        if (!input) {
            return null;
        }
        let value = String(input.value || '').trim();
        if (value !== '' && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            value = App.ProperNameHelper.formatProper(value, 120);
        } else if (value !== '' && typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
            value = global.TextFormatter.formatProperName(value, 120);
        }
        input.value = value;
        return value || null;
    }

    function normalizeCargoFuncao(value) {
        let s = String(value || '').trim();
        if (s === '') {
            return '';
        }
        try {
            s = s.toLocaleUpperCase('pt-BR');
        } catch (e) {
            s = s.toUpperCase();
        }
        return s.slice(0, 120);
    }

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        const profissaoEl = global.document.getElementById('profissao');
        const cargoFuncaoEl = global.document.getElementById('cargo_funcao');
        profissaoEl?.addEventListener('blur', function () {
            formatProfissao(profissaoEl);
        });
        cargoFuncaoEl?.addEventListener('blur', function () {
            cargoFuncaoEl.value = normalizeCargoFuncao(cargoFuncaoEl.value || '');
        });

        global.document.getElementById('salvar-btn')?.addEventListener('click', function () {
            const button = this;
            const dataInclusao = String(global.document.getElementById('data_inclusao')?.value || '').trim();
            if (!dataInclusao) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.inclusion_date.required', {}, 'error', 'Erro', 'Data de inclusao e obrigatoria.');
                } else {
                    common.notify('error', 'Erro', 'Data de inclusao e obrigatoria.');
                }
                return;
            }

            const cargoEl = global.document.getElementById('cargo_funcao');
            const cargoNorm = normalizeCargoFuncao(cargoEl?.value || '');
            if (cargoEl) {
                cargoEl.value = cargoNorm;
            }

            button.disabled = true;
            common.postJson('/api/pessoas/vinculos-instituicoes/update.php', {
                id: String(global.document.getElementById('vinculo-id')?.value || ''),
                setor_id: String(global.document.getElementById('setor_id')?.value || '').trim() || null,
                profissao: formatProfissao(profissaoEl),
                cargo_funcao: cargoNorm || null,
                data_inclusao: dataInclusao,
                data_inicio: String(global.document.getElementById('data_inicio')?.value || '').trim() || null,
                data_fim: String(global.document.getElementById('data_fim')?.value || '').trim() || null,
                ativo: !!global.document.getElementById('ativo')?.checked,
                observacoes: String(global.document.getElementById('observacoes')?.value || '').trim() || null
            }).then(function (result) {
                if (result.ok && result.data && result.data.success) {
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
                    global.location.href = settings.backHref || '/frame.php?page=partesinstituicoeslist';
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

    App.PartesInstituicoesEdit = {
        initialize: initialize
    };
})(window);
