(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

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

    function initialize(config) {
        const settings = config || {};
        const storageKey = settings.storageKey || 'gme.postReloadToast';
        const reuSelect = global.document.getElementById(settings.reuSelectId || 'gab-mon-reu-select');
        const reuNome = global.document.getElementById(settings.reuInputId || 'gab-mon-reu-nome');
        const processoInput = global.document.getElementById(settings.processInputId || 'filter-processo');
        let lastSuggestionNome = '';

        function showReuInput() {
            if (reuSelect) {
                reuSelect.style.display = 'none';
                reuSelect.innerHTML = '';
                reuSelect.value = '';
            }
            if (reuNome) {
                reuNome.style.display = '';
                reuNome.required = true;
            }
        }

        function showReuSelect(reus) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
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
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
            reuNome.value = '';
        }

        function showReuSelectSugestoes(pessoas, nomeDigitado) {
            if (!reuSelect || !reuNome) {
                return;
            }
            reuSelect.innerHTML = '';
            pessoas.forEach(function (pessoa) {
                const option = global.document.createElement('option');
                option.value = pessoa.id ? ('parte:' + pessoa.id) : '';
                let label = (pessoa.nome_completo || '').trim();
                if ((pessoa.cpf || '').replace(/\D/g, '').length === 11) {
                    label += ' - ' + formatCpfDisplay(pessoa.cpf);
                }
                option.textContent = label || pessoa.id;
                reuSelect.appendChild(option);
            });
            const novo = global.document.createElement('option');
            novo.value = '__novo__';
            novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
            reuSelect.appendChild(novo);
            lastSuggestionNome = nomeDigitado || '';
            reuSelect.style.display = '';
            reuNome.style.display = 'none';
            reuNome.required = false;
        }

        function resetClasseSeProcessoIncompleto() {
            const classeInput = global.document.getElementById(settings.classInputId || 'filter-classe');
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
        }

        function carregarReus(numeroFormatado) {
            if (!reuSelect) {
                return;
            }
            showReuInput();
            if (reuNome) {
                reuNome.placeholder = 'Carregando réus…';
                reuNome.disabled = true;
                reuNome.value = '';
            }

            const classCode = global.document.getElementById(settings.classCodeInputId || 'hidden-classe-codigo')?.value || '';
            const classDescription = global.document.getElementById(settings.classDescriptionInputId || 'hidden-classe-descricao')?.value || '';
            let queryString = 'numero_processo=' + encodeURIComponent(numeroFormatado);
            if (classCode !== '') {
                queryString += '&classe_codigo=' + encodeURIComponent(classCode);
            }
            if (classDescription !== '') {
                queryString += '&classe_descricao=' + encodeURIComponent(classDescription);
            }

            fetch(settings.reusEndpoint + '?' + queryString, { credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().then(function (body) {
                        if (!response.ok) {
                            throw new Error((body && body.error) ? body.error : 'Erro ao listar réus.');
                        }
                        return body;
                    });
                })
                .then(function (data) {
                    if (reuNome) {
                        reuNome.placeholder = 'Digite o nome completo';
                        reuNome.disabled = false;
                    }
                    const reus = (data && data.reus) ? data.reus : [];
                    if (reus.length > 0) {
                        showReuSelect(reus);
                    } else {
                        showReuInput();
                    }
                })
                .catch(function (error) {
                    if (reuNome) {
                        reuNome.placeholder = 'Digite o nome completo';
                        reuNome.disabled = false;
                    }
                    notify('error', 'Réus', error && error.message ? error.message : 'Erro ao carregar réus.');
                    showReuInput();
                });
        }

        global.document.getElementById(settings.backButtonId || 'btn-gab-mon-voltar')?.addEventListener('click', function () {
            global.location.href = settings.listHref;
        });

        if (reuSelect) {
            reuSelect.addEventListener('change', function () {
                if (this.value === 'outro' || this.value === '__novo__') {
                    reuSelect.style.display = 'none';
                    if (reuNome) {
                        if (this.value === '__novo__') {
                            reuNome.value = lastSuggestionNome;
                        }
                        reuNome.style.display = '';
                        reuNome.required = true;
                        reuNome.focus();
                    }
                } else if (this.value !== '' && reuNome) {
                    reuNome.style.display = 'none';
                    reuNome.required = false;
                }
            });
        }

        if (reuNome) {
            reuNome.addEventListener('blur', function () {
                let nome = (reuNome.value || '').trim();
                if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                    nome = App.ProperNameHelper.formatProper(nome, 255);
                    reuNome.value = nome;
                } else if (typeof global.TextFormatter !== 'undefined' && typeof global.TextFormatter.formatProperName === 'function') {
                    nome = global.TextFormatter.formatProperName(nome, 255);
                    reuNome.value = nome;
                }
                if (nome.length < 2) {
                    return;
                }
                if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') {
                    return;
                }

                fetch(settings.searchPeopleEndpoint + '?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        const pessoas = (data && data.pessoas) ? data.pessoas : [];
                        if (pessoas.length > 0) {
                            showReuSelectSugestoes(pessoas, nome);
                        }
                    })
                    .catch(function () {
                        // noop
                    });
            });
        }

        if (processoInput) {
            processoInput.addEventListener('input', function () {
                const digits = String(processoInput.value || '').replace(/\D/g, '');
                if (digits.length !== 20) {
                    resetClasseSeProcessoIncompleto();
                }
            });
        }

        global.addEventListener('cnjValidated', function (event) {
            const digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
            if (digits.length !== 20) {
                showReuInput();
                return;
            }
            carregarReus(formatCnjFromDigits(digits));
        });

        global.addEventListener('gabineteMonDatajudClassePronta', function () {
            const processo = global.document.getElementById(settings.processInputId || 'filter-processo')?.value?.trim() || '';
            if (processo) {
                carregarReus(processo);
            }
        });

        global.document.getElementById(settings.registerButtonId || 'btn-gab-mon-registrar')?.addEventListener('click', function () {
            const processoEl = global.document.getElementById(settings.processInputId || 'filter-processo');
            const processo = processoEl?.value?.trim() || '';
            if (processoEl && processoEl.disabled) {
                notifyByKey('gme.monitoring.datajud.wait.info', {}, 'info', 'Aguarde', 'Aguarde a consulta ao DataJud terminar antes de registrar.');
                return;
            }

            const classText = global.document.getElementById(settings.classInputId || 'filter-classe')?.value?.trim() || '';
            if (classText === 'Aguarde...') {
                notifyByKey('gme.monitoring.datajud.wait.info', {}, 'info', 'Aguarde', 'Aguarde a consulta ao DataJud terminar antes de registrar.');
                return;
            }

            const classCode = global.document.getElementById(settings.classCodeInputId || 'hidden-classe-codigo')?.value || '';
            const classDescription = global.document.getElementById(settings.classDescriptionInputId || 'hidden-classe-descricao')?.value || '';
            const reuSelectEl = global.document.getElementById(settings.reuSelectId || 'gab-mon-reu-select');
            const reuInputEl = global.document.getElementById(settings.reuInputId || 'gab-mon-reu-nome');
            const reuSelecionado = reuSelectEl && reuSelectEl.style.display !== 'none' ? ((reuSelectEl.value || '').trim()) : '';
            const nomeLivre = reuInputEl?.value?.trim() || '';

            if (!processo) {
                notifyByKey('gme.monitoring.validation.process.required', {}, 'error', 'Validação', 'Informe o número do processo.');
                return;
            }

            const body = {
                numero_processo: processo,
                classe_codigo: classCode,
                classe_descricao: classDescription,
                reu_complemento: ''
            };

            if (reuSelecionado && reuSelecionado.indexOf('processo_parte:') === 0) {
                body.processo_parte_id = reuSelecionado.substring('processo_parte:'.length);
            } else if (reuSelecionado && reuSelecionado.indexOf('parte:') === 0) {
                body.reu_id = reuSelecionado.substring('parte:'.length);
            } else {
                if (!nomeLivre) {
                    notifyByKey('gme.monitoring.validation.reu.required', {}, 'error', 'Validação', 'Informe o nome completo do réu.');
                    return;
                }
                body.reu_complemento = nomeLivre;
            }

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
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                });
        });
    }

    App.GmeCreate = {
        initialize: initialize
    };
})(window);
