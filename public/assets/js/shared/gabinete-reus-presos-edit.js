(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        const reuId = String(global.document.getElementById('hidden-grp-id')?.value || '').trim();
        const requireSecondClick = (typeof common.createSecondClickGuard === 'function')
            ? common.createSecondClickGuard()
            : null;

        function goBack() {
            const origem = new URLSearchParams(global.location.search).get('origem');
            global.location.href = origem === 'reuspresossubstituto' ? '/frame.php?page=reuspresossubstituto' : (settings.backHref || '/frame.php?page=reuspresos');
        }

        global.document.getElementById('btn-erro-voltar')?.addEventListener('click', goBack);
        global.document.getElementById('btn-grp-voltar')?.addEventListener('click', goBack);

        function postJson(url, body) {
            if (new URLSearchParams(global.location.search).get('origem') === 'reuspresossubstituto') {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'contexto=substituto';
            }
            return common.postJson(url, body).then(function (result) {
                return { ok: result.ok, j: result.data || {} };
            });
        }

        function numeroProcessoFromRow(row) {
            if (!row) {
                return '';
            }
            const fromAttr = String(row.getAttribute('data-numero-processo') || '').trim();
            if (fromAttr) {
                return fromAttr;
            }
            const copyVal = row.querySelector('.processo-bloco-copy-value');
            if (copyVal) {
                return String(copyVal.textContent || '').trim();
            }
            const legacy = row.querySelector('.grp-numero-processo');
            if (legacy) {
                return String(legacy.textContent || '').trim();
            }
            const numeroEl = row.querySelector('.container-processo .content-number')
                || row.querySelector('.container-processo .main-processo .numero');
            if (numeroEl) {
                return String(numeroEl.textContent || '').trim();
            }

            return '';
        }

        global.document.getElementById('btn-grp-salvar-nome')?.addEventListener('click', function () {
            let nome = String(global.document.getElementById('grp-edit-nome')?.value || '').trim();
            if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                nome = App.ProperNameHelper.formatProper(nome, 255);
                global.document.getElementById('grp-edit-nome').value = nome;
            } else if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                nome = global.TextFormatter.formatProperName(nome, 255);
                global.document.getElementById('grp-edit-nome').value = nome;
            }
            if (!nome) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('gabinete.reus_presos.validation.name.required', {}, 'error', 'Validacao', 'Informe o nome.');
                } else {
                    common.notify('error', 'Validacao', 'Informe o nome.');
                }
                return;
            }
            postJson('/api/gabinete-reus-presos/update.php', { id: reuId, nome: nome }).then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.link.update.success', {}, 'success', 'Sucesso', 'Nome atualizado.');
                    } else {
                        common.notify('success', 'Sucesso', 'Nome atualizado.');
                    }
                } else {
                    common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao salvar.');
                }
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexao.');
                }
            });
        });

        global.document.querySelectorAll('select.grp-principal-select').forEach(function (selectEl) {
            selectEl.setAttribute('data-prev', selectEl.value);
            selectEl.addEventListener('focus', function () {
                selectEl.setAttribute('data-prev', selectEl.value);
            });
            selectEl.addEventListener('change', function () {
                const oldValue = selectEl.getAttribute('data-prev') || selectEl.value;
                const nextValue = selectEl.value;
                const linkId = selectEl.getAttribute('data-link-id');
                const row = selectEl.closest('.grp-proc-link');
                const isPrincipalRow = row && row.getAttribute('data-is-principal') === '1';
                const totalLinks = global.document.querySelectorAll('.grp-proc-link').length;

                if (nextValue === 'secundario') {
                    if (totalLinks < 2) {
                        selectEl.value = 'principal';
                        selectEl.setAttribute('data-prev', selectEl.value);
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey(
                                'gabinete.reus_presos.validation.single_process_principal',
                                {},
                                'error',
                                'Validacao',
                                'O unico processo vinculado deve ser o principal.'
                            );
                        } else {
                            common.notify('error', 'Validacao', 'O unico processo vinculado deve ser o principal.');
                        }
                        return;
                    }
                    if (isPrincipalRow) {
                        selectEl.value = oldValue;
                        selectEl.setAttribute('data-prev', oldValue);
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey(
                                'gabinete.reus_presos.validation.define_other_principal_first',
                                {},
                                'info',
                                'Validacao',
                                'Defina outro processo como principal antes de marcar este como secundario.'
                            );
                        } else {
                            common.notify('info', 'Validacao', 'Defina outro processo como principal antes de marcar este como secundario.');
                        }
                        return;
                    }
                    selectEl.setAttribute('data-prev', nextValue);
                    return;
                }

                if (nextValue === 'principal' && linkId) {
                    postJson('/api/gabinete-reus-presos/set-principal.php', {
                        reu_preso_id: reuId,
                        link_id: linkId
                    }).then(function (res) {
                        if (res.ok && res.j && res.j.success) {
                            if (typeof common.notifyByKey === 'function') {
                                common.notifyByKey(
                                    'generic.link.update.success',
                                    {},
                                    'success',
                                    'Sucesso',
                                    'Processo principal atualizado. Os demais vinculos passam a ser secundarios.'
                                );
                            } else {
                                common.notify('success', 'Sucesso', 'Processo principal atualizado. Os demais vinculos passam a ser secundarios.');
                            }
                            global.location.reload();
                        } else {
                            common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                            selectEl.value = oldValue;
                            selectEl.setAttribute('data-prev', oldValue);
                        }
                    }).catch(function () {
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                        } else {
                            common.notify('error', 'Erro', 'Erro de conexao.');
                        }
                        selectEl.value = oldValue;
                        selectEl.setAttribute('data-prev', oldValue);
                    });
                }
            });
        });

        function collectLinkPayload(linkId, numeroProcesso, classeCodigo, classeDescricao) {
            const dataPrisao = global.document.querySelector('.grp-dp[data-link-id="' + linkId + '"]');
            const dataFato = global.document.querySelector('.grp-df[data-link-id="' + linkId + '"]');
            const vinculacao = global.document.querySelector('select.grp-principal-select[data-link-id="' + linkId + '"]');
            return {
                reu_preso_id: reuId,
                link_id: linkId,
                numero_processo: numeroProcesso,
                classe_codigo: classeCodigo || '',
                classe_descricao: classeDescricao || '',
                situacao: 'preso',
                data_prisao: dataPrisao && dataPrisao.value ? dataPrisao.value : null,
                data_fato: dataFato && dataFato.value ? dataFato.value : null,
                principal: !!(vinculacao && vinculacao.value === 'principal')
            };
        }

        global.document.querySelectorAll('.grp-save-link').forEach(function (button) {
            button.addEventListener('click', function () {
                const linkId = button.getAttribute('data-link-id');
                const row = global.document.querySelector('.grp-proc-link[data-link-id="' + linkId + '"]');
                const numeroProcesso = numeroProcessoFromRow(row);
                postJson('/api/gabinete-reus-presos/save-processo.php', collectLinkPayload(linkId, numeroProcesso, '', '')).then(function (res) {
                    if (res.ok && res.j && res.j.link_id) {
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey('generic.link.update.success', {}, 'success', 'Sucesso', 'Vinculo atualizado.');
                        } else {
                            common.notify('success', 'Sucesso', 'Vinculo atualizado.');
                        }
                        global.location.reload();
                    } else {
                        common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexao.');
                    }
                });
            });
        });

        global.document.querySelectorAll('.grp-unlink').forEach(function (button) {
            button.addEventListener('click', function () {
                const linkId = button.getAttribute('data-link-id');
                postJson('/api/gabinete-reus-presos/unlink-processo.php', { link_id: linkId }).then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey('generic.link.remove.success', {}, 'success', 'Sucesso', 'Vinculo removido.');
                        } else {
                            common.notify('success', 'Sucesso', 'Vinculo removido.');
                        }
                        global.location.reload();
                    } else {
                        common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexao.');
                    }
                });
            });
        });

        function applyProcessMetadata(meta) {
            const classeInput = global.document.getElementById('filter-classe');
            const classeCodigo = global.document.getElementById('hidden-classe-codigo');
            const classeDescricao = global.document.getElementById('hidden-classe-descricao');
            if (classeInput) {
                classeInput.value = meta.classe_descricao || '';
            }
            if (classeCodigo) {
                classeCodigo.value = meta.classe_codigo || '';
            }
            if (classeDescricao) {
                classeDescricao.value = meta.classe_descricao || '';
            }

            if (global.document.querySelectorAll('.grp-proc-link').length > 0) {
                return;
            }

            const dataPrisao = global.document.getElementById('grp-new-dp');
            const dataFato = global.document.getElementById('grp-new-df');
            const vinculacao = global.document.getElementById('grp-new-vinc');
            if (dataPrisao && meta.data_prisao) {
                dataPrisao.value = meta.data_prisao;
            }
            if (dataFato && meta.data_fato) {
                dataFato.value = meta.data_fato;
            }
            if (vinculacao) {
                vinculacao.value = 'principal';
            }
        }

        function loadProcessMetadataIfExists() {
            const processoValue = String(global.document.getElementById('filter-processo')?.value || '').trim();
            const digits = processoValue.replace(/\D/g, '');
            if (digits.length !== 20) {
                return Promise.resolve();
            }
            return postJson('/api/gabinete-reus-presos/processo-meta.php', {
                numero_processo: processoValue
            }).then(function (res) {
                if (res.ok && res.j && res.j.found) {
                    applyProcessMetadata(res.j);
                }
            }).catch(function () {
                return undefined;
            });
        }

        const processoInput = global.document.getElementById('filter-processo');
        if (processoInput) {
            processoInput.addEventListener('input', function () {
                const digits = String(processoInput.value || '').replace(/\D/g, '');
                if (digits.length !== 20) {
                    const classeInput = global.document.getElementById('filter-classe');
                    const classeCodigo = global.document.getElementById('hidden-classe-codigo');
                    const classeDescricao = global.document.getElementById('hidden-classe-descricao');
                    if (classeInput) classeInput.value = '';
                    if (classeCodigo) classeCodigo.value = '';
                    if (classeDescricao) classeDescricao.value = '';
                }
            });
            processoInput.addEventListener('blur', function () {
                loadProcessMetadataIfExists();
            });
        }

        global.addEventListener('cnjValidated', function () {
            if (global.document.getElementById('filter-processo')) {
                loadProcessMetadataIfExists();
            }
        });

        function getLinkSourceRowForInheritance() {
            return global.document.querySelector('.grp-proc-link[data-is-principal="1"]') || global.document.querySelector('.grp-proc-link');
        }

        function getInheritedLinkData() {
            const row = getLinkSourceRowForInheritance();
            if (!row) {
                return { dataPrisao: '', dataFato: '' };
            }
            return {
                dataPrisao: row.querySelector('.grp-dp')?.value || '',
                dataFato: row.querySelector('.grp-df')?.value || ''
            };
        }

        function syncNewProcessInheritedFields() {
            const dataPrisao = global.document.getElementById('grp-new-dp');
            const dataFato = global.document.getElementById('grp-new-df');
            if (!dataPrisao || !dataFato) {
                return;
            }
            if (global.document.querySelectorAll('.grp-proc-link').length === 0) {
                dataPrisao.disabled = false;
                dataFato.disabled = false;
                return;
            }
            const inherited = getInheritedLinkData();
            dataPrisao.value = inherited.dataPrisao || '';
            dataFato.value = inherited.dataFato || '';
            dataPrisao.disabled = true;
            dataFato.disabled = true;
        }

        syncNewProcessInheritedFields();

        global.document.getElementById('btn-grp-add-proc')?.addEventListener('click', function () {
            const processo = String(global.document.getElementById('filter-processo')?.value || '').trim();
            const classeCodigo = String(global.document.getElementById('hidden-classe-codigo')?.value || '');
            const classeDescricao = String(global.document.getElementById('hidden-classe-descricao')?.value || '');
            const hasLinkedProcess = global.document.querySelectorAll('.grp-proc-link').length > 0;
            let dataPrisao = String(global.document.getElementById('grp-new-dp')?.value || '');
            let dataFato = String(global.document.getElementById('grp-new-df')?.value || '');
            const principal = String(global.document.getElementById('grp-new-vinc')?.value || 'secundario') === 'principal';

            if (hasLinkedProcess) {
                const inherited = getInheritedLinkData();
                dataPrisao = inherited.dataPrisao || dataPrisao;
                dataFato = inherited.dataFato || dataFato;
            }

            if (!processo) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('gabinete.reus_presos.validation.process.required', {}, 'error', 'Validacao', 'Informe o numero do processo.');
                } else {
                    common.notify('error', 'Validacao', 'Informe o numero do processo.');
                }
                return;
            }

            const sendAddProcess = function () {
                postJson('/api/gabinete-reus-presos/save-processo.php', {
                    reu_preso_id: reuId,
                    numero_processo: processo,
                    classe_codigo: classeCodigo,
                    classe_descricao: classeDescricao,
                    situacao: 'preso',
                    data_prisao: dataPrisao || null,
                    data_fato: dataFato || null,
                    principal: principal
                }).then(function (res) {
                    if (res.ok && res.j && res.j.link_id) {
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey('generic.process.link.success', {}, 'success', 'Sucesso', 'Processo vinculado.');
                        } else {
                            common.notify('success', 'Sucesso', 'Processo vinculado.');
                        }
                        global.location.reload();
                    } else {
                        common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexao.');
                    }
                });
            };

            if (!classeCodigo && !classeDescricao) {
                loadProcessMetadataIfExists().finally(sendAddProcess);
                return;
            }

            sendAddProcess();
        });

        global.document.getElementById('btn-grp-add-mov')?.addEventListener('click', function () {
            const tipo = String(global.document.getElementById('grp-mov-tipo')?.value || '');
            const data = String(global.document.getElementById('grp-mov-data')?.value || '');
            const observacoes = String(global.document.getElementById('grp-mov-obs')?.value || '').trim();
            if (!data) {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey(
                        'gabinete.reus_presos.validation.movement.date.required',
                        {},
                        'error',
                        'Validacao',
                        'Informe a data da movimentacao.'
                    );
                } else {
                    common.notify('error', 'Validacao', 'Informe a data da movimentacao.');
                }
                return;
            }
            postJson('/api/gabinete-reus-presos/add-movimentacao.php', {
                reu_preso_id: reuId,
                tipo_codigo: tipo,
                data_movimentacao: data,
                observacoes: observacoes || undefined
            }).then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.movement.create.success', {}, 'success', 'Sucesso', 'Movimentacao registrada.');
                    } else {
                        common.notify('success', 'Sucesso', 'Movimentacao registrada.');
                    }
                    global.location.reload();
                } else {
                    common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                }
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexao.');
                }
            });
        });

        global.document.querySelectorAll('.grp-del-mov').forEach(function (button) {
            button.addEventListener('click', function () {
                const movimentacaoId = button.getAttribute('data-mov-id') || '';
                if (!movimentacaoId) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('gabinete.reus_presos.validation.movement.invalid', {}, 'error', 'Erro', 'Movimentacao invalida.');
                    } else {
                        common.notify('error', 'Erro', 'Movimentacao invalida.');
                    }
                    return;
                }
                postJson('/api/gabinete-reus-presos/delete-movimentacao.php', {
                    reu_preso_id: reuId,
                    movimentacao_id: movimentacaoId
                }).then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        if (typeof common.notifyByKey === 'function') {
                            common.notifyByKey('generic.movement.delete.success', {}, 'success', 'Sucesso', 'Movimentacao excluida.');
                        } else {
                            common.notify('success', 'Sucesso', 'Movimentacao excluida.');
                        }
                        global.location.reload();
                    } else {
                        common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexao.');
                    }
                });
            });
        });

        global.document.getElementById('btn-grp-arquivar')?.addEventListener('click', function () {
            if (typeof requireSecondClick === 'function') {
                const confirmed = requireSecondClick(
                    'gabinete-reus-presos-arquivar',
                    'Clique novamente para confirmar o arquivamento definitivo.'
                );
                if (!confirmed) {
                    return;
                }
            }
            postJson('/api/gabinete-reus-presos/archive.php', { id: reuId }).then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.record.archive.success', {}, 'success', 'Sucesso', 'Registro arquivado.');
                    } else {
                        common.notify('success', 'Sucesso', 'Registro arquivado.');
                    }
                    goBack();
                } else {
                    common.notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                }
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexao.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexao.');
                }
            });
        });
    }

    App.GabineteReusPresosEdit = {
        initialize: initialize
    };
})(window);
