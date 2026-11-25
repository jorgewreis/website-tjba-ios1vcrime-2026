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
        let documentoPendente = false;
        let documentoIncerto = false;
        let documentoNavegando = false;

        function atualizarBotoesDocumento() {
            const bloqueado = documentoPendente || documentoIncerto || documentoNavegando;
            const addButton = global.document.getElementById('add-doc-btn');
            if (addButton) addButton.disabled = bloqueado;
            global.document.querySelectorAll('.btn-del-doc').forEach(function (button) { button.disabled = bloqueado; });
        }

        function avisoResultadoIncerto(message) {
            documentoIncerto = true;
            let aviso = global.document.getElementById('documento-resultado-incerto');
            if (!aviso) {
                aviso = global.document.createElement('div');
                aviso.id = 'documento-resultado-incerto';
                aviso.setAttribute('role', 'alert');
                aviso.className = 'card-page padding-x-md padding-y-md';
                const anchor = global.document.getElementById('add-doc-btn');
                (anchor && anchor.parentNode ? anchor.parentNode : global.document.body).prepend(aviso);
            }
            aviso.textContent = message;
            atualizarBotoesDocumento();
        }

        function getParteId() {
            return String(global.document.getElementById('parte-id')?.value || '').trim();
        }

        const nomeEl = global.document.getElementById('nome_completo');
        if (nomeEl && App.ProperNameHelper) {
            App.ProperNameHelper.applyOnBlur(nomeEl, 255);
        } else if (nomeEl && typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
            nomeEl.addEventListener('blur', function () {
                nomeEl.value = global.TextFormatter.formatProperName(nomeEl.value, 255);
            });
        }
        const newDocTipo = global.document.getElementById('new-doc-tipo');
        const newDocNumero = global.document.getElementById('new-doc-numero');
        if (newDocTipo && newDocNumero) {
            newDocTipo.addEventListener('change', function () {
                if (newDocTipo.value === 'CPF' && App.CPF && typeof App.CPF.init === 'function') {
                    App.CPF.init(newDocNumero);
                }
            });
            if (newDocTipo.value === 'CPF' && App.CPF && typeof App.CPF.init === 'function') {
                App.CPF.init(newDocNumero);
            }
        }

        function syncProfissaoOutra() {
            const select = global.document.getElementById('profissao_selecao');
            const wrap = global.document.getElementById('profissao-outra-wrap');
            const outraEl = global.document.getElementById('profissao_outra');
            if (!select || !wrap) {
                return;
            }
            const outraToken = global.PROFISSAO_SELECT_OUTRA || '__OUTRA__';
            const show = select.value === outraToken;
            wrap.style.display = show ? 'block' : 'none';
            if (!show && outraEl) {
                outraEl.value = '';
            }
        }

        const profSel = global.document.getElementById('profissao_selecao');
        if (profSel) {
            profSel.addEventListener('change', syncProfissaoOutra);
            syncProfissaoOutra();
        }
        const profOutra = global.document.getElementById('profissao_outra');
        if (profOutra && typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
            profOutra.addEventListener('blur', function () {
                profOutra.value = global.TextFormatter.formatProperName(profOutra.value, 120);
            });
        }

        global.document.getElementById('voltar-btn')?.addEventListener('click', function () {
            global.location.href = settings.backHref || '/frame.php?page=consultapartes';
        });

        global.document.getElementById('vincular-instituicao-btn')?.addEventListener('click', function () {
            const parteId = getParteId();
            if (parteId) {
                global.location.href = '/frame.php?page=partesinstituicoescreate&parte_id=' + encodeURIComponent(parteId);
            }
        });

        global.document.getElementById('salvar-btn')?.addEventListener('click', function () {
            const saveButton = this;
            const nomeVal = String(global.document.getElementById('nome_completo')?.value || '').trim();
            const outraToken = global.PROFISSAO_SELECT_OUTRA || '__OUTRA__';
            const profissaoSelect = global.document.getElementById('profissao_selecao');
            const profissaoOutra = global.document.getElementById('profissao_outra');
            let profissaoOutraFmt = '';
        if (profissaoSelect && profissaoSelect.value === outraToken && profissaoOutra) {
            profissaoOutraFmt = (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function')
                ? App.ProperNameHelper.formatProper(profissaoOutra.value || '', 120)
                : (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName
                    ? global.TextFormatter.formatProperName(profissaoOutra.value || '', 120)
                    : String(profissaoOutra.value || '').trim());
            profissaoOutra.value = profissaoOutraFmt;
        }
            const payload = {
                id: getParteId(),
                nome_completo: (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function')
                    ? App.ProperNameHelper.formatProper(nomeVal, 255)
                    : (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName
                        ? global.TextFormatter.formatProperName(nomeVal, 255)
                        : nomeVal),
                sexo: String(global.document.getElementById('sexo')?.value || '').trim(),
                data_nascimento: String(global.document.getElementById('data_nascimento')?.value || '').trim(),
                profissao_selecao: profissaoSelect ? String(profissaoSelect.value || '').trim() : '',
                profissao_outra: profissaoOutraFmt
            };
            saveButton.disabled = true;
            common.postJson('/api/pessoas/update.php', payload).then(function (result) {
                if (result.ok && result.data && result.data.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.record.save.success', {}, 'success', 'Sucesso', 'Dados salvos.');
                    } else {
                        common.notify('success', 'Sucesso', 'Dados salvos.');
                    }
                } else {
                    common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                }
                saveButton.disabled = false;
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexão.');
                }
                saveButton.disabled = false;
            });
        });

        global.document.getElementById('add-doc-btn')?.addEventListener('click', function () {
            const addButton = this;
            if (documentoPendente || documentoIncerto || documentoNavegando) return;
            const parteId = getParteId();
            if (!parteId) {
                common.notify('error', 'Erro', 'ID da pessoa não encontrado. Recarregue a página.');
                return;
            }
            const tipo = String(global.document.getElementById('new-doc-tipo')?.value || '').trim().toUpperCase();
            let numero = String(global.document.getElementById('new-doc-numero')?.value || '').trim();
            if (!tipo || !numero) {
                common.notify('error', 'Erro', 'Informe o tipo e o número do documento.');
                return;
            }
            if (tipo === 'CPF') {
                numero = numero.replace(/\D/g, '');
                if (numero.length === 11 && App.CPF && !App.CPF.validar(numero)) {
                    common.notify('error', 'Erro', 'CPF inválido. Verifique os dígitos.');
                    return;
                }
            }
            documentoPendente = true;
            atualizarBotoesDocumento();
            common.postJson('/api/pessoas/documentos/create.php', {
                parte_id: parteId,
                tipo: tipo,
                numero: numero
            }, { requireJson: true }).then(function (result) {
                if (result.ok && result.data && result.data.id) {
                    documentoNavegando = true;
                    common.queuePostReloadToast('success', 'Sucesso', 'Documento adicionado com sucesso.', storageKey);
                    global.location.reload();
                    return;
                }
                if (result.status >= 500 || !result.data || typeof result.data !== 'object' || (!result.data.error && !result.data.id) || result.data.resultado === 'verificacao_necessaria') {
                    avisoResultadoIncerto('Não foi possível confirmar o resultado. Recarregue e consulte os documentos antes de tentar novamente.');
                    return;
                } else {
                    common.notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao adicionar documento.');
                }
            }).catch(function () {
                avisoResultadoIncerto('Não foi possível confirmar o resultado. Recarregue e consulte os documentos antes de tentar novamente.');
            }).finally(function () {
                documentoPendente = false;
                atualizarBotoesDocumento();
            });
        });

        global.document.querySelectorAll('.btn-del-doc').forEach(function (button) {
            button.addEventListener('click', function () {
                if (documentoPendente || documentoIncerto || documentoNavegando) return;
                const id = button.getAttribute('data-id');
                if (!id) {
                    return;
                }
                documentoPendente = true;
                atualizarBotoesDocumento();
                common.postJson('/api/pessoas/documentos/delete.php', { id: id }, { requireJson: true }).then(function (result) {
                    if (result.ok && result.data && result.data.deleted === true) {
                        documentoNavegando = true;
                        common.queuePostReloadToast('success', 'Sucesso', 'Documento removido com sucesso.', storageKey);
                        global.location.reload();
                    } else if (result.status >= 500 || !result.data || typeof result.data !== 'object' || (!result.data.error && typeof result.data.deleted !== 'boolean') || result.data.resultado === 'verificacao_necessaria') {
                        avisoResultadoIncerto('Não foi possível confirmar o resultado. Recarregue e consulte os documentos antes de tentar novamente.');
                    } else {
                        common.notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao excluir documento.');
                    }
                }).catch(function () { avisoResultadoIncerto('Não foi possível confirmar o resultado. Recarregue e consulte os documentos antes de tentar novamente.'); }).finally(function () {
                    documentoPendente = false;
                    atualizarBotoesDocumento();
                });
            });
        });

        global.document.getElementById('add-tel-btn')?.addEventListener('click', function () {
            const addButton = this;
            const parteId = getParteId();
            if (!parteId) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.entity.id.required', {}, 'error', 'Erro', 'ID da pessoa não encontrado. Recarregue a página.');
                } else {
                    common.notify('error', 'Erro', 'ID da pessoa não encontrado. Recarregue a página.');
                }
                return;
            }
            const numero = String(global.document.getElementById('new-tel-numero')?.value || '').replace(/\D/g, '').trim();
            if (!numero) {
                common.notify('error', 'Erro', 'Informe o número.');
                return;
            }
            const tipo = String(global.document.getElementById('new-tel-tipo')?.value || '').trim();
            addButton.disabled = true;
            common.postJson('/api/pessoas/telefones/create.php', {
                parte_id: parteId,
                tipo: tipo || null,
                numero: numero
            }).then(function (result) {
                if (result.ok && result.data && result.data.id) {
                    if (typeof common.queuePostReloadToastByKey === 'function') {
                        common.queuePostReloadToastByKey(
                            'generic.phone.add.success',
                            {},
                            'success',
                            'Sucesso',
                            'Telefone adicionado com sucesso.',
                            storageKey
                        ).then(function () {
                            global.location.reload();
                        });
                        return;
                    }
                    common.queuePostReloadToast('success', 'Sucesso', 'Telefone adicionado com sucesso.', storageKey);
                    global.location.reload();
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao adicionar telefone.');
                addButton.disabled = false;
            }).catch(function (error) {
                common.notify('error', 'Erro', 'Erro ao adicionar telefone: ' + ((error && error.message) ? error.message : 'Erro de conexão.'));
                addButton.disabled = false;
            });
        });

        global.document.querySelectorAll('.btn-del-tel').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                common.postJson('/api/pessoas/telefones/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        if (typeof common.queuePostReloadToastByKey === 'function') {
                            common.queuePostReloadToastByKey(
                                'generic.phone.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                'Telefone removido com sucesso.',
                                storageKey
                            ).then(function () {
                                global.location.reload();
                            });
                            return;
                        }
                        common.queuePostReloadToast('success', 'Sucesso', 'Telefone removido com sucesso.', storageKey);
                        global.location.reload();
                    }
                });
            });
        });

        global.document.querySelectorAll('.btn-del-vinculo').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                common.postJson('/api/pessoas/vinculos-instituicoes/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        if (typeof common.queuePostReloadToastByKey === 'function') {
                            common.queuePostReloadToastByKey(
                                'generic.link.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                'Instituição desvinculada com sucesso.',
                                storageKey
                            ).then(function () {
                                global.location.reload();
                            });
                            return;
                        }
                        common.queuePostReloadToast('success', 'Sucesso', 'Instituição desvinculada com sucesso.', storageKey);
                        global.location.reload();
                    }
                });
            });
        });
    }

    App.ConsultaPartesEdit = {
        initialize: initialize
    };
})(window);
