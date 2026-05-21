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
        const instId = global.document.getElementById('inst-id')?.value || '';

        function normalizeSiglaUpper(value) {
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

        global.document.getElementById('voltar-btn')?.addEventListener('click', function () {
            global.location.href = settings.backHref || '/frame.php?page=instituicoespublicas';
        });

        const nomeEl = global.document.getElementById('nome');
        const nomeFantasiaEl = global.document.getElementById('nome_fantasia');
        if (nomeEl) {
            nomeEl.addEventListener('blur', function () {
                nomeEl.value = String(nomeEl.value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
            });
        }
        if (nomeFantasiaEl) {
            nomeFantasiaEl.addEventListener('blur', function () {
                nomeFantasiaEl.value = normalizeSiglaUpper(nomeFantasiaEl.value || '');
            });
        }
        if (App.CNPJ && typeof App.CNPJ.init === 'function') {
            App.CNPJ.init('#cnpj');
        }

        global.document.getElementById('salvar-btn')?.addEventListener('click', function () {
            const saveButton = this;
            const cnpjRaw = String(global.document.getElementById('cnpj')?.value || '').replace(/\D/g, '');
            if (cnpjRaw && cnpjRaw.length === 14 && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.cnpj.invalid', {}, 'error', 'Erro', 'CNPJ inválido. Verifique os dígitos.');
                } else {
                    common.notify('error', 'Erro', 'CNPJ inválido. Verifique os dígitos.');
                }
                return;
            }
            const payload = {
                id: instId,
                nome: String(global.document.getElementById('nome')?.value || '').trim().replace(/\s+/g, ' ').slice(0, 255),
                nome_fantasia: normalizeSiglaUpper(global.document.getElementById('nome_fantasia')?.value || '') || null,
                cnpj: cnpjRaw || null,
                observacoes: String(global.document.getElementById('observacoes')?.value || '').trim() || null
            };
            saveButton.disabled = true;
            common.postJson('/api/instituicoes-publicas/update.php', payload).then(function (result) {
                if (result.data && result.data.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.record.save.success', {}, 'success', 'Sucesso', 'Salvo.');
                    } else {
                        common.notify('success', 'Sucesso', 'Salvo.');
                    }
                } else {
                    common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
                }
                saveButton.disabled = false;
            }).catch(function () {
                saveButton.disabled = false;
            });
        });

        global.document.getElementById('add-tel-btn')?.addEventListener('click', function () {
            const numero = String(global.document.getElementById('new-tel-numero')?.value || '').trim();
            if (!numero) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.phone.required', {}, 'error', 'Erro', 'Informe o número.');
                } else {
                    common.notify('error', 'Erro', 'Informe o número.');
                }
                return;
            }
            common.postJson('/api/instituicoes-publicas/telefones/create.php', {
                instituicao_id: instId,
                tipo: String(global.document.getElementById('new-tel-tipo')?.value || '').trim() || null,
                numero: numero,
                ramal: String(global.document.getElementById('new-tel-ramal')?.value || '').trim() || null,
                principal: global.document.getElementById('new-tel-principal')?.checked ? 1 : 0
            }).then(function (result) {
                if (result.data && result.data.id) {
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
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
            });
        });

        global.document.querySelectorAll('.btn-del-tel').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                common.postJson('/api/instituicoes-publicas/telefones/delete.php', { id: id }).then(function (result) {
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

        global.document.getElementById('add-setor-btn')?.addEventListener('click', function () {
            const nomeSetor = String(global.document.getElementById('new-setor-nome')?.value || '').trim();
            if (!nomeSetor) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.department.required', {}, 'error', 'Erro', 'Informe o nome do setor.');
                } else {
                    common.notify('error', 'Erro', 'Informe o nome do setor.');
                }
                return;
            }
            common.postJson('/api/instituicoes-publicas/setores/create.php', {
                instituicao_id: instId,
                nome_setor: nomeSetor,
                sigla: String(global.document.getElementById('new-setor-sigla')?.value || '').trim() || null
            }).then(function (result) {
                if (result.data && result.data.id) {
                    if (typeof common.queuePostReloadToastByKey === 'function') {
                        common.queuePostReloadToastByKey(
                            'generic.department.add.success',
                            {},
                            'success',
                            'Sucesso',
                            'Setor adicionado com sucesso.',
                            storageKey
                        ).then(function () {
                            global.location.reload();
                        });
                        return;
                    }
                    common.queuePostReloadToast('success', 'Sucesso', 'Setor adicionado com sucesso.', storageKey);
                    global.location.reload();
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Erro.');
            });
        });

        global.document.querySelectorAll('.btn-del-setor').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                common.postJson('/api/instituicoes-publicas/setores/delete.php', { id: id }).then(function (result) {
                    if (result.data && result.data.deleted) {
                        if (typeof common.queuePostReloadToastByKey === 'function') {
                            common.queuePostReloadToastByKey(
                                'generic.department.remove.success',
                                {},
                                'success',
                                'Sucesso',
                                'Setor removido com sucesso.',
                                storageKey
                            ).then(function () {
                                global.location.reload();
                            });
                            return;
                        }
                        common.queuePostReloadToast('success', 'Sucesso', 'Setor removido com sucesso.', storageKey);
                        global.location.reload();
                    }
                });
            });
        });

        global.document.getElementById('add-end-btn')?.addEventListener('click', function () {
            const logradouro = String(global.document.getElementById('new-end-logradouro')?.value || '').trim();
            const cidade = String(global.document.getElementById('new-end-cidade')?.value || '').trim();
            if (!logradouro && !cidade) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.validation.address.minimum.required', {}, 'error', 'Erro', 'Informe ao menos logradouro ou cidade.');
                } else {
                    common.notify('error', 'Erro', 'Informe ao menos logradouro ou cidade.');
                }
                return;
            }
            const cepRaw = String(global.document.getElementById('new-end-cep')?.value || '').replace(/\D/g, '');
            common.postJson('/api/instituicoes-publicas/enderecos/create.php', {
                instituicao_id: instId,
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
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Não foi possível adicionar o endereço.');
            });
        });

        global.document.querySelectorAll('.btn-del-end').forEach(function (button) {
            button.addEventListener('click', function () {
                const id = button.getAttribute('data-id');
                common.postJson('/api/instituicoes-publicas/enderecos/delete.php', { id: id }).then(function (result) {
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

    App.InstituicoesPublicasEdit = {
        initialize: initialize
    };
})(window);
