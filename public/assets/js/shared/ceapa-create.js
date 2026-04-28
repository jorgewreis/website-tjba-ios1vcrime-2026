(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

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
        reuInput.value = '';
    }

    function showReuSelectSugestoes(pessoas, nomeDigitado, reuSelect, reuInput) {
        if (!reuSelect || !reuInput) return;
        reuSelect.innerHTML = '';
        pessoas.forEach(function (p) {
            const opt = global.document.createElement('option');
            opt.value = p.id;
            let label = (p.nome_completo || '').trim() || p.id;
            const cpfDigits = String(p.cpf || '').replace(/\D/g, '');
            if (cpfDigits.length === 11) {
                label += ' - ' + cpfDigits.slice(0, 3) + '.' + cpfDigits.slice(3, 6) + '.' + cpfDigits.slice(6, 9) + '-' + cpfDigits.slice(9);
            }
            opt.textContent = label;
            reuSelect.appendChild(opt);
        });
        const novo = global.document.createElement('option');
        novo.value = '__novo__';
        novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
        reuSelect.appendChild(novo);
        reuSelect.dataset.lastSuggestion = nomeDigitado || '';
        reuSelect.style.display = '';
        reuInput.style.display = 'none';
        reuInput.required = false;
    }

    function bindDataJudReuLookup(processoInput, reuSelect, reuInput) {
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

        global.addEventListener('cnjValidated', function (event) {
            const digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
            loadByDigits(digits);
        });

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

    function initialize(config) {
        if (!common) {
            return;
        }

        const settings = config || {};
        let alocState = null;
        if (settings.alocacaoPrefix && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function') {
            alocState = App.CartorioAlocacaoDirecionadaCreate.init(settings.alocacaoPrefix);
        }

        const processoInput = global.document.getElementById('filter-processo');
        const reuSelect = global.document.getElementById('filter-reu-select');
        const reuInput = global.document.getElementById('filter-reu');
        const classeInput = global.document.getElementById('filter-classe');
        const classeCodigo = global.document.getElementById('hidden-classe-codigo');
        const classeDescricao = global.document.getElementById('hidden-classe-descricao');

        bindDataJudReuLookup(processoInput, reuSelect, reuInput);

        reuSelect?.addEventListener('change', function () {
            if (this.value === 'outro') {
                this.style.display = 'none';
                reuInput.style.display = '';
                reuInput.required = true;
                reuInput.focus();
            } else if (this.value === '__novo__') {
                const last = reuSelect.dataset.lastSuggestion || '';
                this.style.display = 'none';
                this.innerHTML = '';
                this.value = '';
                reuInput.value = last;
                reuInput.style.display = '';
                reuInput.required = true;
                reuInput.focus();
            }
        });

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
                        showReuSelectSugestoes(pessoas, nome, reuSelect, reuInput);
                    }
                }).catch(function () {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    } else {
                        common.notify('error', 'Erro', 'Erro de conexão.');
                    }
                });
        });

        global.document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            global.location.href = '/frame.php?page=ceapa';
        }, { capture: true });

        global.document.getElementById('cadastrar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            const button = this;
            if (button.disabled) return;
            processoInput?.blur();
            reuInput?.blur();

            let numeroProcesso = String(processoInput?.value || '').trim();
            if (processoInput && typeof global.normalizeAndValidateCNJValue === 'function') {
                const normalized = global.normalizeAndValidateCNJValue(numeroProcesso, { toastOnInvalid: true });
                if (!normalized.ok) {
                    processoInput.value = '';
                    processoInput.focus();
                    return;
                }
                processoInput.value = normalized.formatted;
                numeroProcesso = normalized.formatted;
            }

            const classeVal = String(classeInput?.value || '').trim();
            const tipoSuspensao = String(global.document.getElementById('filter-tipo-suspensao')?.value || '').trim();
            const decisaoCumprir = String(global.document.getElementById('filter-decisao-cumprir')?.value || '').trim();
            const dataDecisao = String(global.document.getElementById('filter-data-decisao')?.value || '').trim();

            const useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
            const reuPayload = useSelect
                ? { reu_id: reuSelect.value, nome_reu: reuSelect.options[reuSelect.selectedIndex]?.textContent || '' }
                : { reu_id: '', nome_reu: String(reuInput?.value || '').trim() };

            if (!numeroProcesso) return common.notify('error', 'Erro', 'Informe o número do processo.');
            if (classeVal === 'Aguarde...') return common.notify('error', 'Erro', 'Aguarde a consulta ao DataJud terminar antes de cadastrar.');
            if (!reuPayload.nome_reu) return common.notify('error', 'Erro', 'Informe o réu.');
            if (!tipoSuspensao) return common.notify('error', 'Erro', 'Informe o tipo de suspensão.');
            if (!decisaoCumprir) return common.notify('error', 'Erro', 'Informe a decisão a cumprir.');
            if (!dataDecisao) return common.notify('error', 'Erro', 'Informe a data da decisão.');

            button.disabled = true;
            common.notify('info', 'Informação', 'Cadastrando...');

            const payload = {
                numero_processo_origem: numeroProcesso,
                nome_reu: reuPayload.nome_reu,
                tipo_suspensao: tipoSuspensao,
                decisao_cumprir: decisaoCumprir,
                data_decisao: dataDecisao,
                status_cumprimento: 'pendente'
            };
            if (reuPayload.reu_id) payload.reu_id = reuPayload.reu_id;
            if (classeCodigo && classeCodigo.value.trim()) payload.classe_codigo = classeCodigo.value.trim();
            if (classeDescricao && classeDescricao.value.trim()) {
                payload.classe_descricao = classeDescricao.value.trim();
            } else if (classeVal && classeVal !== 'Classe não informada' && classeVal !== 'Aguarde...') {
                payload.classe_descricao = classeVal;
                if (!payload.classe_codigo && classeVal) {
                    payload.classe_codigo = classeVal.replace(/\s+/g, '').substring(0, 20);
                }
            }

            if (alocState && typeof alocState.getIds === 'function') {
                const extra = alocState.getIds();
                if (extra && extra.length) {
                    payload.alocar_usuario_ids = extra;
                }
            }

            common.postJson('/api/cartorio-ceapa/create.php', payload).then(function (result) {
                if (result.data && result.data.id) {
                    if (typeof common.notifyByKey === 'function') {
                        common.notifyByKey(
                            'cartorio.ceapa.create.success',
                            {},
                            'success',
                            'Sucesso',
                            'Cadastro CEAPA realizado com sucesso.'
                        );
                    } else {
                        common.notify('success', 'Sucesso', 'Cadastro CEAPA realizado com sucesso.');
                    }
                    global.location.href = '/frame.php?page=ceapa';
                    return;
                }
                common.notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Não foi possível cadastrar.');
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

        global.addEventListener('DOMContentLoaded', function () {
            if (App.User && typeof App.User.setupRuleButton === 'function') {
                App.User.setupRuleButton({
                    buttonId: 'alterar-regra-btn',
                    targetPage: 'ceaparules'
                });
            }
        });
    }

    App.CeapaCreate = {
        initialize: initialize
    };
})(window);
