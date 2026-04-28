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
        let phoneRemoveConfirmMessage = 'Clique novamente em Excluir em até 5 segundos para confirmar a remoção do telefone.';
        let linkRemoveConfirmMessage = 'Clique novamente em Excluir em até 5 segundos para confirmar o desvínculo da instituição.';
        let addressRemoveConfirmMessage = 'Clique novamente em Excluir em até 5 segundos para confirmar a remoção do endereço.';

        if (App.ToastCatalog && typeof App.ToastCatalog.resolve === 'function') {
            App.ToastCatalog.resolve('generic.phone.remove.confirm', {}).then(function (toast) {
                if (toast && toast.message) phoneRemoveConfirmMessage = toast.message;
            }).catch(function () {});
            App.ToastCatalog.resolve('generic.unlink.confirm', {}).then(function (toast) {
                if (toast && toast.message) linkRemoveConfirmMessage = toast.message;
            }).catch(function () {});
            App.ToastCatalog.resolve('generic.address.remove.confirm', {}).then(function (toast) {
                if (toast && toast.message) addressRemoveConfirmMessage = toast.message;
            }).catch(function () {});
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
        if (App.CPF && typeof App.CPF.init === 'function') {
            App.CPF.init('#cpf');
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
            const cpfRaw = String(global.document.getElementById('cpf')?.value || '').replace(/\D/g, '');
            if (cpfRaw && cpfRaw.length === 11 && App.CPF && !App.CPF.validar(cpfRaw)) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.cpf.invalid', {}, 'error', 'Erro', 'CPF inválido. Verifique os dígitos.');
                } else {
                    common.notify('error', 'Erro', 'CPF inválido. Verifique os dígitos.');
                }
                return;
            }
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
                cpf: cpfRaw || '',
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
                if (!requireSecondClick('excluir-telefone-parte-' + (id || ''), phoneRemoveConfirmMessage)) {
                    return;
                }
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
                if (!requireSecondClick('desvincular-instituicao-parte-' + (id || ''), linkRemoveConfirmMessage)) {
                    return;
                }
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

        global.document.getElementById('buscar-cep-btn')?.addEventListener('click', function () {
            const button = this;
            button.disabled = true;
            common.lookupCep(global.document.getElementById('new-end-cep')?.value || '').then(function (result) {
                if (result.ok && result.data) {
                    global.document.getElementById('new-end-logradouro').value = result.data.logradouro || '';
                    global.document.getElementById('new-end-bairro').value = result.data.bairro || '';
                    global.document.getElementById('new-end-cidade').value = result.data.cidade || '';
                    global.document.getElementById('new-end-uf').value = String(result.data.uf || '').toUpperCase().slice(0, 2);
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.cep.lookup.success', {}, 'success', 'Sucesso', 'CEP encontrado.');
                    } else {
                        common.notify('success', 'Sucesso', 'CEP encontrado.');
                    }
                }
                button.disabled = false;
            });
        });

        global.document.getElementById('add-end-btn')?.addEventListener('click', function () {
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
            const logradouro = String(global.document.getElementById('new-end-logradouro')?.value || '').trim();
            const cidade = String(global.document.getElementById('new-end-cidade')?.value || '').trim();
            if (!logradouro && !cidade) {
                common.notify('error', 'Erro', 'Informe ao menos logradouro ou cidade.');
                return;
            }
            const cepRaw = String(global.document.getElementById('new-end-cep')?.value || '').replace(/\D/g, '');
            addButton.disabled = true;
            common.postJson('/api/pessoas/enderecos/create.php', {
                parte_id: parteId,
                cep: cepRaw.length === 8 ? cepRaw : null,
                logradouro: logradouro || null,
                numero: String(global.document.getElementById('new-end-numero')?.value || '').trim() || null,
                complemento: String(global.document.getElementById('new-end-complemento')?.value || '').trim() || null,
                bairro: String(global.document.getElementById('new-end-bairro')?.value || '').trim() || null,
                cidade: cidade || null,
                uf: String(global.document.getElementById('new-end-uf')?.value || '').trim().toUpperCase().slice(0, 2) || null,
                principal: global.document.getElementById('new-end-principal')?.checked ? 1 : 0
            }).then(function (result) {
                if (result.ok && result.data && result.data.id) {
                    if (typeof common.queuePostReloadToastByKey === 'function') {
                        common.queuePostReloadToastByKey(
                            'generic.address.add.success',
                            {},
                            'success',
                            'Sucesso',
                            'Endereço adicionado com sucesso.',
                            storageKey
                        ).then(function () {
                            global.location.reload();
                        });
                        return;
                    }
                    common.queuePostReloadToast('success', 'Sucesso', 'Endereço adicionado com sucesso.', storageKey);
                    global.location.reload();
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) || 'Erro ao adicionar endereço.');
                addButton.disabled = false;
            }).catch(function (error) {
                common.notify('error', 'Erro', 'Erro ao adicionar endereço: ' + ((error && error.message) ? error.message : 'Erro de conexão.'));
                addButton.disabled = false;
            });
        });

        global.document.querySelectorAll('.btn-del-end').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                if (!requireSecondClick('excluir-endereco-parte-' + (id || ''), addressRemoveConfirmMessage)) {
                    return;
                }
                if (!id) {
                    return;
                }
                common.postJson('/api/pessoas/enderecos/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        if (typeof common.queuePostReloadToastByKey === 'function') {
                            common.queuePostReloadToastByKey(
                                'generic.address.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                'Endereço removido com sucesso.',
                                storageKey
                            ).then(function () {
                                global.location.reload();
                            });
                            return;
                        }
                        common.queuePostReloadToast('success', 'Sucesso', 'Endereço removido com sucesso.', storageKey);
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
