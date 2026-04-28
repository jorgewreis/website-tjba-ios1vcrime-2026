(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function getVal(id) {
        const el = global.document.getElementById(id);
        return el && el.value ? el.value.trim() : '';
    }

    function showReuInput(reuSelect, reuInput) {
        if (reuSelect) {
            reuSelect.style.display = 'none';
            reuSelect.innerHTML = '';
            reuSelect.value = '';
        }
        if (reuInput) {
            reuInput.style.display = '';
            reuInput.required = true;
        }
    }

    function showReuSelect(reus, reuSelect, reuInput) {
        if (!reuSelect || !reuInput) return;
        reuSelect.innerHTML = '';
        reus.forEach(function (r) {
            const opt = global.document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.nome;
            reuSelect.appendChild(opt);
        });
        const outro = global.document.createElement('option');
        outro.value = 'outro';
        outro.textContent = 'Outro';
        reuSelect.appendChild(outro);
        reuSelect.style.display = '';
        reuInput.style.display = 'none';
        reuInput.required = false;
    }

    function showReuSelectSugestoes(pessoas, reuSelect, reuInput) {
        if (!reuSelect || !reuInput) return;
        reuSelect.innerHTML = '';
        pessoas.forEach(function (p) {
            const opt = global.document.createElement('option');
            opt.value = p.id;
            opt.textContent = (p.nome_completo || p.id || '').trim();
            reuSelect.appendChild(opt);
        });
        reuSelect.style.display = '';
        reuInput.style.display = 'none';
        reuInput.required = false;
    }

    function bindReuLookup(processoInput, reuSelect, reuInput, lockProcesso) {
        function loadByDigits(digits) {
            if (!digits || digits.length !== 20) {
                showReuInput(reuSelect, reuInput);
                return;
            }
            fetch('/api/cartorio-guias/reus-por-processo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ numero: digits }),
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); })
            .then(function (data) {
                const reus = (data && data.reus) ? data.reus : [];
                if (reus.length > 0) {
                    showReuSelect(reus, reuSelect, reuInput);
                } else {
                    showReuInput(reuSelect, reuInput);
                }
            }).catch(function () {
                showReuInput(reuSelect, reuInput);
            });
        }

        if (!lockProcesso) {
            processoInput?.addEventListener('input', function () {
                const digits = String(processoInput.value || '').replace(/\D/g, '');
                if (digits.length !== 20) {
                    const classeInput = global.document.getElementById('filter-classe');
                    const hCod = global.document.getElementById('hidden-classe-codigo');
                    const hDesc = global.document.getElementById('hidden-classe-descricao');
                    if (classeInput) {
                        classeInput.value = '';
                        classeInput.readOnly = true;
                    }
                    if (hCod) hCod.value = '';
                    if (hDesc) hDesc.value = '';
                    showReuInput(reuSelect, reuInput);
                }
            });
        }

        global.addEventListener('cnjValidated', function (event) {
            const digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
            loadByDigits(digits);
        });

        const currentDigits = String(processoInput?.value || '').replace(/\D/g, '');
        if (currentDigits.length === 20) {
            loadByDigits(currentDigits);
        }
    }

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        const lockProcesso = !!settings.lockProcesso;
        const reuSelect = global.document.getElementById('filter-reu-select');
        const reuInput = global.document.getElementById('filter-reu');
        const processoInput = global.document.getElementById('filter-processo');
        const salvarBtn = global.document.getElementById('salvar-btn');
        const excluirBtn = global.document.getElementById('excluir-btn');
        const requireSecondClick = common.createSecondClickGuard();

        bindReuLookup(processoInput, reuSelect, reuInput, lockProcesso);

        reuInput?.addEventListener('blur', function () {
            let nome = String(reuInput.value || '').trim();
            if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                nome = App.ProperNameHelper.formatProper(nome, 120);
                reuInput.value = nome;
            } else if (typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                nome = global.TextFormatter.formatProperName(nome, 120);
                reuInput.value = nome;
            }
            if (nome.length < 2) return;
            if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') return;
            fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const pessoas = (data && data.pessoas) ? data.pessoas : [];
                    if (pessoas.length > 0) {
                        showReuSelectSugestoes(pessoas, reuSelect, reuInput);
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexão.');
                    }
                });
        });

        reuSelect?.addEventListener('change', function () {
            if (this.value === 'outro') {
                this.style.display = 'none';
                reuInput.style.display = '';
                reuInput.required = true;
                reuInput.focus();
            }
        });

        salvarBtn?.addEventListener('click', function () {
            const id = getVal('hidden-ceapa-id');
            if (!id) {
                return common.notify('error', 'Erro', 'Identificador do cadastro não informado.');
            }

            if (!lockProcesso && processoInput && typeof global.normalizeAndValidateCNJValue === 'function') {
                const normalized = global.normalizeAndValidateCNJValue(getVal('filter-processo'), { toastOnInvalid: true });
                if (!normalized.ok) {
                    processoInput.value = '';
                    processoInput.focus();
                    return;
                }
                processoInput.value = normalized.formatted;
            }

            const numeroProcesso = getVal('filter-processo');
            const classeVal = getVal('filter-classe');
            const classeCodigo = getVal('hidden-classe-codigo');
            const classeDescricao = getVal('hidden-classe-descricao');
            const tipoSuspensao = getVal('filter-tipo-suspensao');
            const decisaoCumprir = getVal('filter-decisao-cumprir');
            const dataDecisao = getVal('filter-data-decisao');
            const observacoes = getVal('filter-observacoes-cumprimento');
            const statusCumprimento = getVal('filter-status-cumprimento');

            const useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
            const reuPayload = useSelect
                ? { reu_id: reuSelect.value, nome_reu: reuSelect.options[reuSelect.selectedIndex]?.textContent || '' }
                : { reu_id: '', nome_reu: getVal('filter-reu') };

            if (!numeroProcesso) return common.notify('error', 'Erro', 'Informe o número do processo.');
            if (classeVal === 'Aguarde...') return common.notify('error', 'Erro', 'Aguarde a consulta ao DataJud terminar.');
            if (!reuPayload.nome_reu) return common.notify('error', 'Erro', 'Informe o réu.');
            if (!tipoSuspensao) return common.notify('error', 'Erro', 'Informe o tipo de suspensão.');
            if (!decisaoCumprir) return common.notify('error', 'Erro', 'Informe a decisão a cumprir.');
            if (!dataDecisao) return common.notify('error', 'Erro', 'Informe a data da decisão.');

            salvarBtn.disabled = true;
            common.notify('info', 'Informação', 'Salvando alterações...');

            const payload = {
                id: id,
                numero_processo_origem: numeroProcesso,
                nome_reu: reuPayload.nome_reu,
                tipo_suspensao: tipoSuspensao,
                decisao_cumprir: decisaoCumprir,
                data_decisao: dataDecisao,
                observacoes_cumprimento: observacoes,
                status_cumprimento: statusCumprimento
            };
            if (reuPayload.reu_id) payload.reu_id = reuPayload.reu_id;
            if (classeCodigo) payload.classe_codigo = classeCodigo;
            if (classeDescricao) payload.classe_descricao = classeDescricao;

            common.postJson('/api/cartorio-ceapa/update.php', payload).then(function (result) {
                if (result.data && result.data.success) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'cartorio.ceapa.update.success',
                            {},
                            'success',
                            'Sucesso',
                            'Cadastro CEAPA atualizado com sucesso.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Cadastro CEAPA atualizado com sucesso.');
                    }
                    global.location.href = '/frame.php?page=ceapa';
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Não foi possível atualizar.');
                salvarBtn.disabled = false;
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexão.');
                }
                salvarBtn.disabled = false;
            });
        });

        excluirBtn?.addEventListener('click', function () {
            const id = getVal('hidden-ceapa-id');
            if (!id) {
                return common.notify('error', 'Erro', 'Identificador do cadastro não informado.');
            }
            if (!requireSecondClick('excluir-ceapa', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.')) {
                return;
            }
            excluirBtn.disabled = true;
            common.postJson('/api/cartorio-ceapa/delete.php', { id: id }).then(function (result) {
                if (result.data && result.data.deleted) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'cartorio.ceapa.delete.success',
                            {},
                            'success',
                            'Sucesso',
                            'Cadastro CEAPA excluído com sucesso.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Cadastro CEAPA excluído com sucesso.');
                    }
                    global.location.href = '/frame.php?page=ceapa';
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Não foi possível excluir.');
                excluirBtn.disabled = false;
            }).catch(function () {
                if (typeof common.notifyByKey === 'function') {
                    common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                } else {
                    common.notify('error', 'Erro', 'Erro de conexão.');
                }
                excluirBtn.disabled = false;
            });
        });

        global.document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            global.location.href = '/frame.php?page=ceapa';
        }, { capture: true });
    }

    App.CeapaEdit = {
        initialize: initialize
    };
})(window);
