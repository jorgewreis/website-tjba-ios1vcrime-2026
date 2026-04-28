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

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        const profissaoEl = global.document.getElementById('profissao');
        const setorEl = global.document.getElementById('setor_id');

        profissaoEl?.addEventListener('blur', function () {
            formatProfissao(profissaoEl);
        });

        global.document.getElementById('voltar-btn')?.addEventListener('click', function () {
            global.location.href = settings.backHref || '/frame.php?page=partesinstituicoeslist';
        });
        global.document.getElementById('btn-buscar-pessoa')?.addEventListener('click', function () {
            global.open('/frame.php?page=consultapartes', '_blank');
        });
        global.document.getElementById('btn-buscar-instituicao')?.addEventListener('click', function () {
            global.open('/frame.php?page=instituicoespublicas', '_blank');
        });

        global.document.getElementById('instituicao_id')?.addEventListener('change', function () {
            const instituicaoId = String(this.value || '').trim();
            if (!setorEl) {
                return;
            }
            setorEl.innerHTML = '<option value="">— Carregando —</option>';
            if (!instituicaoId) {
                setorEl.innerHTML = '<option value="">— Selecione a instituicao primeiro —</option>';
                return;
            }
            fetch('/api/instituicoes-publicas/setores.php?instituicao_id=' + encodeURIComponent(instituicaoId), {
                method: 'GET',
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                let options = '<option value="">— Nenhum setor —</option>';
                (data.setores || []).forEach(function (setor) {
                    const label = setor.nome_setor || setor.sigla || setor.id || '';
                    options += '<option value="' + String(setor.id || '') + '">' + String(label) + '</option>';
                });
                setorEl.innerHTML = options;
            }).catch(function () {
                setorEl.innerHTML = '<option value="">— Erro —</option>';
            });
        });

        global.document.getElementById('salvar-btn')?.addEventListener('click', function () {
            const button = this;
            const parteId = String(global.document.getElementById('parte_id')?.value || '').trim();
            const instituicaoId = String(global.document.getElementById('instituicao_id')?.value || '').trim();
            const cargoFuncao = String(global.document.getElementById('cargo_funcao')?.value || '').trim();
            if (!parteId || !instituicaoId) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.person.institution.required', {}, 'error', 'Erro', 'Selecione a pessoa e a instituicao.');
                } else {
                    common.notify('error', 'Erro', 'Selecione a pessoa e a instituicao.');
                }
                return;
            }
            if (!cargoFuncao) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.role.required', {}, 'error', 'Validacao', 'Informe o cargo ou funcao.');
                } else {
                    common.notify('error', 'Validacao', 'Informe o cargo ou funcao.');
                }
                return;
            }

            button.disabled = true;
            common.postJson('/api/pessoas/vinculos-instituicoes/create.php', {
                parte_id: parteId,
                instituicao_id: instituicaoId,
                setor_id: String(setorEl?.value || '').trim() || null,
                profissao: formatProfissao(profissaoEl),
                cargo_funcao: cargoFuncao,
                observacoes: String(global.document.getElementById('observacoes')?.value || '').trim() || null
            }).then(function (result) {
                if (result.ok && result.data && result.data.id) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'generic.link.create.success',
                            {},
                            'success',
                            'Sucesso',
                            'Vinculo criado.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Vinculo criado.');
                    }
                    global.location.href = settings.backHref || '/frame.php?page=partesinstituicoeslist';
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Nao foi possivel salvar o vinculo.');
                button.disabled = false;
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Falha de conexao ou resposta invalida.');
                } else {
                    common.notify('error', 'Erro', 'Falha de conexao ou resposta invalida.');
                }
                button.disabled = false;
            });
        });
    }

    App.PartesInstituicoesCreate = {
        initialize: initialize
    };
})(window);
