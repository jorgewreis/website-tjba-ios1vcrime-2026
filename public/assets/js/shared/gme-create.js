(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const helpers = App.CartorioCreateHelpers || null;

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
        }
    }

    function queuePostReloadToast(storageKey, payload) {
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(payload, null, null, storageKey);
            return;
        }

        try {
            const raw = global.sessionStorage.getItem(storageKey);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : (current && current.message ? [current] : []);
            list.push(payload);
            global.sessionStorage.setItem(storageKey, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function formatCnjFromDigits(digits) {
        if (!digits || digits.length !== 20) {
            return '';
        }
        return digits.substr(0, 7) + '-' + digits.substr(7, 2) + '.' + digits.substr(9, 4) + '.' + digits.substr(13, 1) + '.' + digits.substr(14, 2) + '.' + digits.substr(16, 4);
    }

    function formatCpfDisplay(cpf) {
        const digits = String(cpf || '').replace(/\D/g, '');
        if (digits.length !== 11) {
            return cpf || '';
        }
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9);
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (App.Page && typeof App.Page.notifyByKey === 'function') {
            return App.Page.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
        }
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }

        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function queueByKey(storageKey, key, vars, fallbackPayload) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            queuePostReloadToast(storageKey, fallbackPayload);
            return Promise.resolve();
        }

        return catalog.resolve(key, vars || {})
            .then(function (toast) {
                if (!toast || !toast.message) {
                    queuePostReloadToast(storageKey, fallbackPayload);
                    return;
                }
                queuePostReloadToast(storageKey, {
                    type: toast.type || fallbackPayload.type,
                    title: toast.title || fallbackPayload.title,
                    message: toast.message
                });
            })
            .catch(function () {
                queuePostReloadToast(storageKey, fallbackPayload);
            });
    }

    function onlyDigits(value) {
        if (helpers && typeof helpers.onlyDigits === 'function') {
            return helpers.onlyDigits(value);
        }
        return String(value || '').replace(/\D/g, '');
    }

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'gme.postReloadToast';
        const registerButton = global.document.getElementById(settings.registerButtonId || 'btn-gab-mon-registrar');
        const reuSelect = global.document.getElementById(settings.reuSelectId || 'gab-mon-reu-select');
        const reuNome = global.document.getElementById(settings.reuInputId || 'gab-mon-reu-nome');
        const processoInput = global.document.getElementById(settings.processInputId || 'filter-processo');
        const classeInput = global.document.getElementById(settings.classInputId || 'filter-classe');
        let lastSuggestionNome = '';
        let escolhaNovo = null;
        let nomeConsultado = null;
        let reusProcessoConsultado = '';
        let isSubmitting = false;
        let reusRequestVersion = 0;
        let suggestionRequestVersion = 0;
        let suggestionPending = false;
        let activeProcessDigits = onlyDigits(processoInput?.value || '');

        const applyButtonState = function (label, disabled) {
            if (!registerButton) {
                return;
            }
            registerButton.textContent = label;
            registerButton.disabled = disabled === true;
        };

        function hasValidProcess() {
            return onlyDigits(processoInput?.value || '').length === 20;
        }

        function hasReuFilled() {
            if (reuSelect && reuSelect.style.display !== 'none') {
                const selected = (reuSelect.value || '').trim();
                if (!selected) {
                    return false;
                }
                if (selected === 'outro' || selected === '__novo__') {
                    return (reuNome?.value || '').trim().length >= 2;
                }
                return true;
            }
            return (reuNome?.value || '').trim().length >= 2;
        }

        function syncRegisterButtonState() {
            if (!registerButton || isSubmitting) {
                return;
            }
            if (!hasValidProcess()) {
                applyButtonState('Novo cadastro', true);
                return;
            }
            if (suggestionPending || !hasReuFilled()) {
                applyButtonState('Novo cadastro', true);
                return;
            }
            applyButtonState('Novo cadastro', false);
        }

        applyButtonState('Novo cadastro', true);

        function showReuInput() {
            if (reuSelect) {
                reuSelect.style.display = 'none';
                reuSelect.required = false;
                reuSelect.innerHTML = '';
                reuSelect.value = '';
            }
            if (reuNome) {
                reuNome.style.display = '';
                reuNome.required = true;
            }
            syncRegisterButtonState();
        }

        function addEscolhaInicial(select) {
            const placeholder = global.document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione uma pessoa';
            placeholder.disabled = true;
            placeholder.selected = true;
            select.appendChild(placeholder);
        }

        function showReuSelect(reus) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
            addEscolhaInicial(reuSelect);
            reus.forEach(function (reu) {
                const option = global.document.createElement('option');
                option.value = reu.id ? ('processo_parte:' + reu.id) : '';
                option.textContent = (reu.nome || '') + (reu.tipo_parte ? ' (' + reu.tipo_parte + ')' : '');
                reuSelect.appendChild(option);
            });
            const outro = global.document.createElement('option');
            outro.value = 'outro';
            outro.textContent = 'Outro';
            reuSelect.appendChild(outro);
            reuSelect.value = '';
            reuSelect.required = true;
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            reuNome.value = '';
            syncRegisterButtonState();
        }

        function showReuSelectSugestoes(pessoas, nomeDigitado) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
            addEscolhaInicial(reuSelect);
            pessoas.forEach(function (pessoa) {
                const option = global.document.createElement('option');
                option.value = pessoa.id ? ('parte:' + pessoa.id) : '';
                let label = (pessoa.nome_completo || '').trim();
                if ((pessoa.cpf || '').replace(/\D/g, '').length === 11) {
                    label += ' - CPF: ' + formatCpfDisplay(pessoa.cpf);
                } else {
                    label += ' - CPF não informado';
                }
                const profissao = String(pessoa.profissao_exibicao || '').trim();
                const lotacao = String(pessoa.lotacao_exibicao || '').trim();
                if (profissao) label += ' - ' + profissao;
                if (lotacao) label += ' - ' + lotacao;
                option.textContent = label || pessoa.id;
                reuSelect.appendChild(option);
            });
            const novo = global.document.createElement('option');
            novo.value = '__novo__';
            novo.textContent = 'Usar nome informado: ' + (nomeDigitado || '');
            reuSelect.appendChild(novo);
            lastSuggestionNome = nomeDigitado || '';
            reuSelect.value = '';
            reuSelect.required = true;
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            syncRegisterButtonState();
        }

        function mostrarEscolhaAposFalha(nome) {
            if (!reuSelect || !reuNome) return;
            reuSelect.innerHTML = '';
            addEscolhaInicial(reuSelect);
            const repetir = global.document.createElement('option');
            repetir.value = '__repetir__';
            repetir.textContent = 'Tentar consulta novamente';
            reuSelect.appendChild(repetir);
            const usarNome = global.document.createElement('option');
            usarNome.value = '__novo__';
            usarNome.textContent = 'Usar nome informado: ' + nome;
            reuSelect.appendChild(usarNome);
            lastSuggestionNome = nome;
            reuSelect.value = '';
            reuSelect.required = true;
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            syncRegisterButtonState();
        }

        function resetClasseSeProcessoIncompleto() {
            if (classeInput) {
                classeInput.value = '';
                classeInput.readOnly = true;
            }
            const hiddenClassCode = global.document.getElementById(settings.classCodeInputId || 'hidden-classe-codigo');
            const hiddenClassDescription = global.document.getElementById(settings.classDescriptionInputId || 'hidden-classe-descricao');
            if (hiddenClassCode) { hiddenClassCode.value = ''; }
            if (hiddenClassDescription) { hiddenClassDescription.value = ''; }
            showReuInput();
            if (reuNome) {
                reuNome.placeholder = 'Digite o nome completo';
                reuNome.disabled = false;
            }
            syncRegisterButtonState();
        }

        function carregarReus(numeroFormatado) {
            if (!reuSelect) {
                return;
            }
            const requestedDigits = onlyDigits(numeroFormatado);
            if (requestedDigits.length !== 20 || requestedDigits !== onlyDigits(processoInput?.value || '')) return;
            reusProcessoConsultado = requestedDigits;
            const requestVersion = ++reusRequestVersion;
            suggestionRequestVersion++;
            suggestionPending = false;
            const requestIsCurrent = function () {
                return requestVersion === reusRequestVersion && requestedDigits === onlyDigits(processoInput?.value || '');
            };
            showReuInput();
            if (reuNome) {
                reuNome.placeholder = 'Carregando réus…';
                reuNome.disabled = true;
                reuNome.value = '';
            }
            syncRegisterButtonState();

            const classCode = global.document.getElementById(settings.classCodeInputId || 'hidden-classe-codigo')?.value || '';
            const classDescription = global.document.getElementById(settings.classDescriptionInputId || 'hidden-classe-descricao')?.value || '';
            const payload = {
                numero_processo: numeroFormatado,
                classe_codigo: classCode,
                classe_descricao: classDescription
            };

            fetch(settings.reusEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        if (!response.ok) {
                            throw new Error((body && body.error) ? body.error : 'Erro ao listar réus.');
                        }
                        return body;
                    });
                })
                .then(function (data) {
                    if (!requestIsCurrent()) return;
                    if (!data || !Array.isArray(data.reus)) throw new Error('Resposta inválida ao carregar réus.');
                    if (reuNome) {
                        reuNome.placeholder = 'Digite o nome completo';
                        reuNome.disabled = false;
                    }
                    const reus = data.reus;
                    if (reus.length > 0) {
                        showReuSelect(reus);
                    } else {
                        showReuInput();
                        if (data && typeof data.mensagem === 'string' && data.mensagem.trim()) {
                            notify('info', 'Réus', data.mensagem);
                        }
                    }
                })
                .catch(function (error) {
                    if (!requestIsCurrent()) return;
                    reusProcessoConsultado = '';
                    if (reuNome) {
                        reuNome.placeholder = 'Digite o nome completo';
                        reuNome.disabled = false;
                    }
                    notify('error', 'Réus', error && error.message ? error.message : 'Erro ao carregar réus.');
                    showReuInput();
                });
        }

        function onDatajudClasseResolved(event) {
            const detail = event && event.detail ? event.detail : {};
            const processoInputId = detail.processoInputId || '';
            if (processoInputId && processoInputId !== 'filter-processo' && processoInputId !== 'gab-mon-processo') {
                return;
            }
            const processo = processoInput?.value?.trim() || '';
            if (processo && onlyDigits(processo) !== reusProcessoConsultado) {
                carregarReus(processo);
            }
            syncRegisterButtonState();
        }

        global.document.getElementById(settings.backButtonId || 'btn-gab-mon-voltar')?.addEventListener('click', function () {
            global.location.href = settings.listHref;
        });

        if (reuSelect) {
            reuSelect.addEventListener('change', function () {
                suggestionRequestVersion++;
                suggestionPending = false;
                escolhaNovo = this.value === '__novo__' ? { nome: lastSuggestionNome, processo: onlyDigits(processoInput?.value || '') } : null;
                if (this.value === 'outro' || this.value === '__novo__' || this.value === '__repetir__') {
                    reuSelect.style.display = 'none';
                    reuSelect.required = false;
                    if (reuNome) {
                        if (this.value === '__novo__' || this.value === '__repetir__') {
                            reuNome.value = lastSuggestionNome;
                        }
                        reuNome.style.display = '';
                        reuNome.required = true;
                        reuNome.focus();
                        if (this.value === '__repetir__') buscarSugestoes();
                    }
                } else if (this.value !== '' && reuNome) {
                    reuNome.style.display = 'none';
                    reuNome.required = false;
                }
                syncRegisterButtonState();
            });
        }

        if (reuNome) {
            reuNome.addEventListener('input', function () {
                if (escolhaNovo && (reuNome.value || '').trim() !== escolhaNovo.nome) escolhaNovo = null;
                if (nomeConsultado && (reuNome.value || '').trim() !== nomeConsultado.nome) nomeConsultado = null;
                suggestionRequestVersion++;
                suggestionPending = false;
                syncRegisterButtonState();
            });
        }

        function buscarSugestoes() {
                let nome = (reuNome.value || '').trim();
                if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                    nome = App.ProperNameHelper.formatProper(nome, 255);
                    reuNome.value = nome;
                } else if (typeof global.TextFormatter !== 'undefined' && typeof global.TextFormatter.formatProperName === 'function') {
                    nome = global.TextFormatter.formatProperName(nome, 255);
                    reuNome.value = nome;
                }
                syncRegisterButtonState();
                if (nome.length < 2) {
                    return;
                }
                if (escolhaNovo && escolhaNovo.nome === nome && escolhaNovo.processo === onlyDigits(processoInput?.value || '')) return;
                if (nomeConsultado && nomeConsultado.nome === nome && nomeConsultado.processo === onlyDigits(processoInput?.value || '')) return;
                if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') {
                    return;
                }
                const suggestionVersion = ++suggestionRequestVersion;
                suggestionPending = true;
                syncRegisterButtonState();
                const requestedProcess = onlyDigits(processoInput?.value || '');
                const suggestionController = new AbortController();
                let suggestionTimer;
                Promise.race([
                    fetch(settings.searchPeopleEndpoint + '?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin', signal: suggestionController.signal })
                        .then(function (response) {
                            if (!response.ok) throw new Error('Não foi possível consultar as pessoas.');
                            return response.json();
                        }),
                    new Promise(function (_, reject) {
                        suggestionTimer = setTimeout(function () {
                            suggestionController.abort();
                            reject(new Error('Tempo limite da consulta de pessoas.'));
                        }, 10000);
                    })
                ])
                    .then(function (data) {
                        if (suggestionVersion !== suggestionRequestVersion
                            || nome !== (reuNome.value || '').trim()
                            || requestedProcess !== onlyDigits(processoInput?.value || '')
                            || reuNome.disabled || reuNome.style.display === 'none') return;
                        suggestionPending = false;
                        if (!data || !Array.isArray(data.pessoas)) throw new Error('Resposta inválida ao buscar pessoas.');
                        const pessoas = data.pessoas;
                        if (pessoas.length > 0) {
                            showReuSelectSugestoes(pessoas, nome);
                        } else {
                            nomeConsultado = { nome: nome, processo: requestedProcess };
                            syncRegisterButtonState();
                        }
                    })
                    .catch(function () {
                        if (suggestionVersion !== suggestionRequestVersion) return;
                        suggestionPending = false;
                        notify('error', 'Consulta de pessoas', 'Não foi possível consultar sugestões. Tente novamente ou confirme o nome informado.');
                        mostrarEscolhaAposFalha(nome);
                    })
                    .finally(function () { clearTimeout(suggestionTimer); });
        }
        if (reuNome) reuNome.addEventListener('blur', buscarSugestoes);

        if (processoInput) {
            processoInput.addEventListener('input', function () {
                const digits = onlyDigits(processoInput.value || '');
                if (digits !== activeProcessDigits) {
                    activeProcessDigits = digits;
                    escolhaNovo = null;
                    nomeConsultado = null;
                    reusProcessoConsultado = '';
                    reusRequestVersion++;
                    suggestionRequestVersion++;
                    suggestionPending = false;
                    showReuInput();
                    if (reuNome) {
                        reuNome.value = '';
                        reuNome.disabled = false;
                        reuNome.placeholder = 'Digite o nome completo';
                    }
                }
                if (digits.length !== 20) {
                    resetClasseSeProcessoIncompleto();
                    return;
                }
                syncRegisterButtonState();
            });
        }

        global.addEventListener('cnjInvalid', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (!id || id === 'filter-processo' || id === 'gab-mon-processo') {
                reusProcessoConsultado = '';
                nomeConsultado = null;
                escolhaNovo = null;
                reusRequestVersion++;
                suggestionRequestVersion++;
                suggestionPending = false;
                showReuInput();
                if (reuNome) {
                    reuNome.disabled = false;
                    reuNome.placeholder = 'Digite o nome completo';
                }
                syncRegisterButtonState();
            }
        });

        global.addEventListener('cnjValidated', function (event) {
            const id = event && event.detail ? event.detail.inputId : '';
            if (id && id !== (settings.processInputId || 'filter-processo') && id !== 'gab-mon-processo') return;
            const digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
            if (digits.length !== 20 || digits !== onlyDigits(processoInput?.value || '')) return;
            carregarReus(formatCnjFromDigits(digits));
            syncRegisterButtonState();
        });

        global.addEventListener('datajudClasseResolved', onDatajudClasseResolved);

        registerButton?.addEventListener('click', function () {
            if (registerButton.disabled || isSubmitting) {
                return;
            }

            const processoEl = processoInput;
            let processo = processoEl?.value?.trim() || '';
            if (helpers && processoEl) {
                const normalized = helpers.normalizeProcessValue(processo, {
                    inputId: settings.processInputId || 'filter-processo',
                    feedback: function (message) {
                        notify('error', 'Validação', message);
                    },
                    requiredMessage: 'Informe o número do processo.',
                    invalidMessage: 'O número do processo está incompleto ou inválido (20 dígitos).'
                });
                if (!normalized.ok) {
                    syncRegisterButtonState();
                    return;
                }
                processo = normalized.value;
            } else if (onlyDigits(processo).length !== 20) {
                notifyByKey('gme.monitoring.validation.process.required', {}, 'error', 'Validação', 'Informe o número do processo.');
                syncRegisterButtonState();
                return;
            }

            const classText = classeInput?.value?.trim() || '';

            const reuSelectEl = reuSelect;
            const reuInputEl = reuNome;
            const reuSelecionado = reuSelectEl && reuSelectEl.style.display !== 'none' ? ((reuSelectEl.value || '').trim()) : '';
            const nomeLivre = reuInputEl?.value?.trim() || '';

            const processoAtual = onlyDigits(processo);
            const nomeConfirmado = [escolhaNovo, nomeConsultado].some(function (item) {
                return item && item.nome === nomeLivre && item.processo === processoAtual;
            });
            if (!reuSelecionado && nomeLivre.length >= 2 && !nomeConfirmado) {
                buscarSugestoes();
                return;
            }

            if (!hasReuFilled()) {
                notifyByKey('gme.monitoring.validation.reu.required', {}, 'error', 'Validação', 'Informe o nome completo do réu.');
                syncRegisterButtonState();
                return;
            }

            const body = Object.assign({
                numero_processo: processo,
                reu_complemento: ''
            }, helpers && typeof helpers.buildClassePayload === 'function'
                ? helpers.buildClassePayload({ classeValue: classText })
                : {
                    classe_codigo: global.document.getElementById(settings.classCodeInputId || 'hidden-classe-codigo')?.value || '',
                    classe_descricao: global.document.getElementById(settings.classDescriptionInputId || 'hidden-classe-descricao')?.value || ''
                });

            if (reuSelecionado && reuSelecionado.indexOf('processo_parte:') === 0) {
                body.processo_parte_id = reuSelecionado.substring('processo_parte:'.length);
            } else if (reuSelecionado && reuSelecionado.indexOf('parte:') === 0) {
                body.reu_id = reuSelecionado.substring('parte:'.length);
            } else {
                body.reu_complemento = nomeLivre;
            }

            isSubmitting = true;
            applyButtonState('Aguarde...', true);

            fetch(settings.createEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, data: json };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.id) {
                        queueByKey(storageKey, 'gme.monitoring.create.success', {}, {
                            type: 'success',
                            title: 'Sucesso',
                            message: 'Monitoramento registrado.'
                        }).then(function () {
                            global.location.href = settings.listHref;
                        });
                        return;
                    }
                    notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao registrar.');
                    isSubmitting = false;
                    syncRegisterButtonState();
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                    isSubmitting = false;
                    syncRegisterButtonState();
                });
        });
    }

    App.GmeCreate = {
        initialize: initialize
    };
})(window);
