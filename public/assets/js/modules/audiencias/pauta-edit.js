(function () {
    var pageTools = window.App && App.Page ? App.Page : null;
    var requireSecondClick = pageTools && typeof pageTools.createSecondClickGuard === 'function'
        ? pageTools.createSecondClickGuard()
        : (function () {
            var pendingConfirmations = {};
            return function (key, message) {
                var now = Date.now();
                var validUntil = Number(pendingConfirmations[key] || 0);
                if (validUntil > now) {
                    delete pendingConfirmations[key];
                    return true;
                }
                pendingConfirmations[key] = now + 5000;
                notify('info', 'Confirmacao', message);
                return false;
            };
        })();
    var initialAudienciaSnapshot = '';

    function notify(type, title, message) {
        if (pageTools && typeof pageTools.notify === 'function') {
            pageTools.notify(type, title, message);
            return;
        }
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (pageTools && typeof pageTools.notifyByKey === 'function') {
            pageTools.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        var catalog = window.App && window.App.ToastCatalog ? window.App.ToastCatalog : null;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function resolveToastMessageByKey(key, vars, fallbackMessage) {
        var catalog = window.App && window.App.ToastCatalog ? window.App.ToastCatalog : null;
        if (!catalog || typeof catalog.resolve !== 'function') {
            return Promise.resolve(fallbackMessage);
        }
        return catalog.resolve(key, vars || {}).then(function (toast) {
            return (toast && toast.message) ? toast.message : fallbackMessage;
        }).catch(function () {
            return fallbackMessage;
        });
    }

    function queuePostReloadToastByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage, storageKey) {
        var targetKey = storageKey || 'salapauta.postReloadToast';
        if (pageTools && typeof pageTools.queuePostReloadToastByKey === 'function') {
            pageTools.queuePostReloadToastByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage, targetKey);
            return;
        }
        var catalog = window.App && window.App.ToastCatalog ? window.App.ToastCatalog : null;
        if (!catalog || typeof catalog.resolve !== 'function') {
            if (pageTools && typeof pageTools.queuePostReloadToast === 'function') {
                pageTools.queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage, targetKey);
                return;
            }
            try {
                sessionStorage.setItem(targetKey, JSON.stringify([{ type: fallbackType, title: fallbackTitle, message: fallbackMessage }]));
            } catch (e) {}
            return;
        }
        catalog.resolve(key, vars || {}).then(function (toast) {
            var type = (toast && toast.type) || fallbackType;
            var title = (toast && toast.title) || fallbackTitle;
            var message = (toast && toast.message) || fallbackMessage;
            if (pageTools && typeof pageTools.queuePostReloadToast === 'function') {
                pageTools.queuePostReloadToast(type, title, message, targetKey);
                return;
            }
            try {
                sessionStorage.setItem(targetKey, JSON.stringify([{ type: type, title: title, message: message }]));
            } catch (e) {}
        }).catch(function () {
            if (pageTools && typeof pageTools.queuePostReloadToast === 'function') {
                pageTools.queuePostReloadToast(fallbackType, fallbackTitle, fallbackMessage, targetKey);
                return;
            }
            try {
                sessionStorage.setItem(targetKey, JSON.stringify([{ type: fallbackType, title: fallbackTitle, message: fallbackMessage }]));
            } catch (e) {}
        });
    }

    function readPartPayload() {
        return {
            nome_parte: String(document.getElementById('parte-nome')?.value || '').trim(),
            parte_id_global: String(document.getElementById('parte-id-global')?.value || '').trim(),
            tipo_parte: String(document.getElementById('parte-tipo')?.value || '').trim(),
            maioridade: String(document.getElementById('parte-maioridade')?.value || '').trim()
        };
    }

    function validatePart(payload) {
        if (!payload.nome_parte) return 'Nome da parte';
        if (!payload.tipo_parte) return 'Tipo de parte';
        if (!payload.maioridade) return 'Maioridade';
        return '';
    }

    function normalizePartNameInput() {
        var nomeInput = document.getElementById('parte-nome');
        if (!nomeInput) return;
        // Se o nome foi selecionado na lista (readOnly), não altere o texto.
        if (nomeInput.readOnly) return;
        var nome = String(nomeInput.value || '').trim();
        if (App && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            nome = App.ProperNameHelper.formatProper(nome, 255);
        } else if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nome = TextFormatter.formatProperName(nome, 255);
        }
        nomeInput.value = nome;
    }

    function updateParteAddButtonState() {
        var btnParte = document.getElementById('btn-parte-adicionar');
        var tipoParte = document.getElementById('parte-tipo');
        if (!btnParte || !tipoParte) return;
        btnParte.disabled = String(tipoParte.value || '').trim() === '';
    }

    function updateAlocarButtonState() {
        var btnAlocar = document.getElementById('btn-pauta-alocar');
        var selectServidor = document.getElementById('pauta-alocar-usuario');
        if (!btnAlocar || !selectServidor) return;
        btnAlocar.disabled = String(selectServidor.value || '').trim() === '';
    }

    function normalizeSelectedName(val) {
        var s = String(val || '').trim();
        if (App && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            s = App.ProperNameHelper.formatProper(s, 255);
        } else if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            s = TextFormatter.formatProperName(s, 255);
        }
        return s;
    }

    function formatCpfDisplay(cpf) {
        var digits = String(cpf || '').replace(/\D/g, '');
        if (digits.length !== 11) return String(cpf || '').trim();
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9, 11);
    }

    function setParteNomeStatus(text, color) {
        var statusEl = document.getElementById('parte-nome-processo-status');
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.style.color = color || '';
    }

    function resolvePessoaOptionLabel(pessoa) {
        if (pessoa && String(pessoa.label || '').trim()) {
            return String(pessoa.label).trim();
        }

        var nome = String((pessoa && pessoa.nome_completo) || '').trim();
        var cpf = String((pessoa && (pessoa.cpf_formatado || pessoa.cpf)) || '').trim();
        var profissao = String((pessoa && pessoa.profissao_exibicao) || '').trim();
        var lotacao = String((pessoa && pessoa.lotacao_exibicao) || '').trim();
        var label = nome;
        if (cpf) {
            label = formatCpfDisplay(cpf) + ' - ' + label;
        }
        var suffix = [];
        if (profissao) suffix.push(profissao);
        if (lotacao) suffix.push(lotacao);
        if (suffix.length) {
            label += ' (' + suffix.join(' - ') + ')';
        }
        return label;
    }

    function fillParteNomeManualUi(select, input, hiddenId, preserveValue) {
        if (!select || !input) return;
        select.classList.remove('status-finished');
        select.dataset.mode = 'manual';
        select.disabled = true;
        select.style.display = 'none';
        input.style.display = '';
        input.readOnly = false;
        input.placeholder = 'Digite o nome completo';
        input.value = typeof preserveValue === 'string' ? preserveValue : '';
        if (hiddenId) hiddenId.value = '';
        setParteNomeStatus('', '');
    }

    function fillParteNomeSelectWithProcessoNames(select, input, hiddenId, nomes) {
        if (!select || !input) return;
        var processoNames = Array.isArray(nomes) ? nomes.slice() : [];
        select.innerHTML = '';
        processoNames.forEach(function (nome) {
            var option = document.createElement('option');
            option.value = nome;
            option.textContent = nome;
            option.dataset.nomeParte = nome;
            select.appendChild(option);
        });
        var outro = document.createElement('option');
        outro.value = '__OUTRO__';
        outro.textContent = 'Outro (digite abaixo)';
        select.appendChild(outro);

        select.dataset.mode = 'processo';
        select.dataset.hasProcessoReusParts = processoNames.length > 0 ? '1' : '0';
        select.dataset.firstProcessoReuValue = processoNames[0] || '';
        select.dataset.processoNamesJson = JSON.stringify(processoNames);
        select.dataset.lastSuggestedName = '';
        select.classList.add('status-finished');
        select.disabled = false;
        select.style.display = '';

        if (hiddenId) hiddenId.value = '';
        if (processoNames.length > 0) {
            select.value = processoNames[0];
            input.value = normalizeSelectedName(processoNames[0]);
            input.style.display = 'none';
        } else {
            fillParteNomeManualUi(select, input, hiddenId, '');
        }
    }

    function fillParteNomeSelectWithPeopleSuggestions(select, input, hiddenId, pessoas, nomeDigitado) {
        if (!select || !input) return;
        var suggestions = Array.isArray(pessoas) ? pessoas.slice(0, 3) : [];
        if (!suggestions.length) {
            fillParteNomeManualUi(select, input, hiddenId, nomeDigitado);
            return;
        }

        select.innerHTML = '';
        var outraPessoa = document.createElement('option');
        outraPessoa.value = '__OUTRA_PESSOA__';
        outraPessoa.textContent = 'Outra pessoa (usar: ' + nomeDigitado + ')';
        outraPessoa.dataset.nomeParte = nomeDigitado;
        outraPessoa.dataset.parteIdGlobal = '';
        select.appendChild(outraPessoa);

        suggestions.forEach(function (pessoa) {
            var option = document.createElement('option');
            option.value = String((pessoa && pessoa.id) || '').trim();
            option.textContent = resolvePessoaOptionLabel(pessoa);
            option.dataset.nomeParte = String((pessoa && pessoa.nome_completo) || '').trim();
            option.dataset.parteIdGlobal = String((pessoa && pessoa.id) || '').trim();
            select.appendChild(option);
        });

        var novo = document.createElement('option');
        novo.value = '__novo__';
        novo.textContent = 'Outro nome (digite abaixo)';
        select.appendChild(novo);

        select.dataset.mode = 'people';
        select.dataset.lastSuggestedName = nomeDigitado;
        select.classList.add('status-finished');
        select.disabled = false;
        select.style.display = '';

        // A busca já ordena as pessoas pela maior semelhança com o nome digitado.
        // Portanto, a primeira pessoa cadastrada deve ser a seleção atual.
        var firstPersonOption = select.querySelector('option[data-parte-id-global][value]:not([value="__OUTRA_PESSOA__"])');
        if (firstPersonOption && String(firstPersonOption.value || '').trim() !== '') {
            select.value = firstPersonOption.value;
            input.value = normalizeSelectedName(firstPersonOption.dataset.nomeParte || nomeDigitado);
            if (hiddenId) {
                hiddenId.value = String(firstPersonOption.dataset.parteIdGlobal || '').trim();
            }
            setParteNomeStatus('Parte vinculada', 'var(--color-success-active)');
        } else {
            select.value = '__OUTRA_PESSOA__';
            input.value = normalizeSelectedName(nomeDigitado);
            if (hiddenId) {
                hiddenId.value = '';
            }
        }
        input.style.display = 'none';
    }

    function currentAudienciaSnapshot() {
        return JSON.stringify({
            tipo: String(document.getElementById('audiencia-edit-tipo')?.value || '').trim(),
            data: String(document.getElementById('audiencia-edit-data')?.value || '').trim(),
            hora: String(document.getElementById('audiencia-edit-hora')?.value || ((horariosEditUnresolved && String(document.getElementById('audiencia-edit-data')?.value || '').trim() === horariosEditPendingDate) ? horariosEditPendingHour : '') || '').trim(),
            localizacao: String(document.getElementById('audiencia-edit-sala')?.value || '').trim(),
            classe: String(document.getElementById('audiencia-edit-classe')?.value || '').trim(),
            status: String(document.getElementById('audiencia-edit-status')?.value || '').trim(),
            link_audiencia: String(document.getElementById('audiencia-edit-link-audin')?.value || '').trim()
        });
    }

    function isAudienciaDirty() {
        return currentAudienciaSnapshot() !== initialAudienciaSnapshot;
    }

    function pickNonEmptyPautaId(candidates) {
        for (var i = 0; i < candidates.length; i += 1) {
            var s = String(candidates[i] == null ? '' : candidates[i]).trim();
            if (s !== '') {
                return s;
            }
        }
        return '';
    }

    function resolvePautaEditId() {
        var fromGlobal = pickNonEmptyPautaId([window.PAUTA_EDIT_ID]);
        if (fromGlobal) {
            return fromGlobal;
        }
        var hiddenEl = document.getElementById('audiencia-edit-id');
        var fromHidden = pickNonEmptyPautaId([hiddenEl ? hiddenEl.value : '']);
        if (fromHidden) {
            return fromHidden;
        }
        try {
            var params = new URLSearchParams(window.location.search || '');
            return pickNonEmptyPautaId([params.get('id'), params.get('id_pauta')]);
        } catch (e) {
            return '';
        }
    }

    function syncHiddenPautaEditId() {
        var id = resolvePautaEditId();
        var hiddenEl = document.getElementById('audiencia-edit-id');
        if (!hiddenEl || !id) {
            return;
        }
        if (!String(hiddenEl.value || '').trim()) {
            hiddenEl.value = id;
        }
    }

    function saveAudiencia(autoMessage) {
        var id = resolvePautaEditId();
        if (!id) {
            notify('error', 'Erro', 'Não foi possível identificar a audiência. Volte à pauta e abra a edição novamente.');
            return Promise.resolve(false);
        }
        syncHiddenPautaEditId();
        return fetch('/api/sala-audiencias-pauta/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                id: id,
                tipo_audiencia: String(document.getElementById('audiencia-edit-tipo')?.value || '').trim(),
                data_audiencia: String(document.getElementById('audiencia-edit-data')?.value || '').trim(),
                hora_audiencia: String(document.getElementById('audiencia-edit-hora')?.value || '').trim(),
                localizacao: String(document.getElementById('audiencia-edit-sala')?.value || '').trim(),
                classe: String(document.getElementById('audiencia-edit-classe')?.value || '').trim(),
                status: String(document.getElementById('audiencia-edit-status')?.value || '').trim(),
                link_audiencia: String(document.getElementById('audiencia-edit-link-audin')?.value || '').trim()
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                var body = res.j && typeof res.j === 'object' ? res.j : {};
                var saved = res.ok && (body.success === true || body.success === 1 || body.success === '1');
                if (saved) {
                    initialAudienciaSnapshot = currentAudienciaSnapshot();
                    if (autoMessage) {
                        notifyByKey(
                            'audiencias.pauta.auto_save.info',
                            {},
                            'info',
                            'Atualização automática',
                            'Dados da audiência salvos antes de adicionar a parte.'
                        );
                    } else {
                        notifyByKey('audiencias.pauta.update.success', {}, 'success', 'Sucesso', 'Alterações salvas.');
                    }
                    return true;
                }
                var errMsg = body.error ? String(body.error) : 'Falha ao salvar.';
                notifyByKey('audiencias.pauta.update.error', {}, 'error', 'Erro', errMsg);
                return false;
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                return false;
            });
    }

    function clearPartForm() {
        // Limpa tipo/maioridade.
        [
            'parte-tipo',
            'parte-maioridade',
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            }
        });

        var selectParteNome = document.getElementById('parte-nome-selecao');
        var input = document.getElementById('parte-nome');
        var hiddenId = document.getElementById('parte-id-global');
        if (!selectParteNome || !input) return;

        input.value = '';
        input.placeholder = 'Digite o nome completo';
        if (hiddenId) hiddenId.value = '';
        var has = String(selectParteNome.dataset.hasProcessoReusParts || '0');
        if (has === '1') {
            var nomes = [];
            try {
                nomes = JSON.parse(String(selectParteNome.dataset.processoNamesJson || '[]'));
            } catch (e) {
                nomes = [];
            }
            fillParteNomeSelectWithProcessoNames(selectParteNome, input, hiddenId, nomes);
        } else {
            fillParteNomeManualUi(selectParteNome, input, hiddenId, '');
        }
    }

    document.getElementById('btn-audiencia-edit-voltar')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapauta';
    });

    document.getElementById('btn-parte-limpar')?.addEventListener('click', clearPartForm);
    document.getElementById('parte-tipo')?.addEventListener('change', updateParteAddButtonState);
    document.getElementById('pauta-alocar-usuario')?.addEventListener('change', updateAlocarButtonState);

    document.getElementById('btn-parte-adicionar')?.addEventListener('click', function () {
        var continueFlow = Promise.resolve(true);
        if (isAudienciaDirty()) {
            continueFlow = saveAudiencia(true);
        }

        continueFlow.then(function (saved) {
            if (!saved) return;
            normalizePartNameInput();
            var payload = readPartPayload();
            var missing = validatePart(payload);
            if (missing) {
                notifyByKey('audiencias.validation.required.field', { campo: missing }, 'error', 'Validacao', 'Informe: ' + missing + '.');
                return;
            }
            var idPauta = resolvePautaEditId();
            if (!idPauta) {
                notify('error', 'Erro', 'Não foi possível identificar a audiência. Volte à pauta e abra a edição novamente.');
                return;
            }
            syncHiddenPautaEditId();
            fetch('/api/sala-audiencias-pauta/partes-create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({ id_pauta: idPauta }, payload))
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.id) {
                        queuePostReloadToastByKey(
                            'audiencias.pauta.part.add.success',
                            {},
                            'success',
                            'Sucesso',
                            'Parte adicionada.',
                            'salapauta.postReloadToast'
                        );
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 450);
                    } else {
                        notifyByKey('audiencias.part.add.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao adicionar parte.');
                    }
                })
                .catch(function () { notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.'); });
        });
    });

    document.querySelectorAll('.btn-editar[data-parte-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idParte = String(btn.getAttribute('data-parte-edit') || '').trim();
            if (!idParte) return;
            window.location.href = '/frame.php?page=salapautaparteedit&id_parte=' + encodeURIComponent(idParte);
        });
    });

    document.querySelectorAll('.btn-editar[data-parte-global-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idGlobal = String(btn.getAttribute('data-parte-global-id') || '').trim();
            if (!idGlobal) return;
            var pautaId = '';
            try {
                pautaId = String(new URLSearchParams(window.location.search).get('id') || '').trim();
            } catch (e) {
                pautaId = '';
            }
            var href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(idGlobal);
            if (pautaId) {
                href += '&back_page=salapautaedit&back_id=' + encodeURIComponent(pautaId);
            }
            window.location.href = href;
        });
    });

    // Some UIs use `.button-table` while others use `.btn-editar stop`; handler must be generic.
    document.querySelectorAll('[data-parte-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fetch('/api/sala-audiencias-pauta/partes-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id_parte: btn.getAttribute('data-parte-remove') })
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        queuePostReloadToastByKey(
                            'audiencias.pauta.part.remove.success',
                            {},
                            'success',
                            'Sucesso',
                            'Parte removida.',
                            'salapauta.postReloadToast'
                        );
                        window.location.reload();
                    } else {
                        notifyByKey('audiencias.part.remove.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao remover parte.');
                    }
                })
                .catch(function () { notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.'); });
        });
    });

    var pautaActionPending = false;
    function executarAcaoPauta(url, id, actionButton, onSuccess) {
        if (pautaActionPending) return;
        pautaActionPending = true;
        var restoreFocus = document.activeElement === actionButton;
        var succeeded = false;
        var buttons = [document.getElementById('btn-audiencia-edit-cancelar'), document.getElementById('btn-audiencia-edit-excluir')];
        var previousDisabled = buttons.map(function (button) { return button ? button.disabled : false; });
        buttons.forEach(function (button) { if (button) button.disabled = true; });
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id })
        }).then(function (response) {
            return response.json().then(function (body) {
                if (response.ok && body && body.success === true) {
                    succeeded = true;
                    onSuccess();
                    return;
                }
                var message = response.status === 401 ? 'Sessão expirada. Faça login novamente.' :
                    response.status === 403 ? 'Você não tem permissão para realizar esta ação.' :
                    'Não foi possível concluir a ação. Tente novamente.';
                notify('error', 'Erro', message);
            }, function () {
                notify('error', 'Erro', 'Resposta inválida do servidor. Confira a audiência antes de tentar novamente.');
            });
        }).catch(function () {
            notify('error', 'Erro', 'Não foi possível conectar. Confira a audiência antes de tentar novamente.');
        }).finally(function () {
            pautaActionPending = false;
            buttons.forEach(function (button, index) { if (button) button.disabled = previousDisabled[index]; });
            var active = document.activeElement;
            if (!succeeded && restoreFocus && actionButton && !actionButton.disabled && actionButton.isConnected !== false &&
                (!active || active === document.body || active === document.documentElement) && typeof actionButton.focus === 'function') {
                actionButton.focus();
            }
        });
    }

    document.getElementById('btn-audiencia-edit-cancelar')?.addEventListener('click', function () {
        var id = resolvePautaEditId();
        if (!id) {
            notify('error', 'Erro', 'Não foi possível identificar a audiência. Volte à pauta e abra a edição novamente.');
            return;
        }
        syncHiddenPautaEditId();
        executarAcaoPauta('/api/sala-audiencias-pauta/cancel.php', id, document.getElementById('btn-audiencia-edit-cancelar'), function () { window.location.reload(); });
    });

    var pautaDeleteConfirmMessage = 'Clique novamente em Excluir audiência em até 5 segundos para confirmar.';
    resolveToastMessageByKey('audiencias.pauta.delete.confirm', {}, pautaDeleteConfirmMessage).then(function (message) {
        pautaDeleteConfirmMessage = message;
    });
    document.getElementById('btn-audiencia-edit-excluir')?.addEventListener('click', function () {
        if (!requireSecondClick('pauta-excluir-audiencia', pautaDeleteConfirmMessage)) return;
        var id = resolvePautaEditId();
        if (!id) {
            notify('error', 'Erro', 'Não foi possível identificar a audiência. Volte à pauta e abra a edição novamente.');
            return;
        }
        syncHiddenPautaEditId();
        executarAcaoPauta('/api/sala-audiencias-pauta/delete.php', id, document.getElementById('btn-audiencia-edit-excluir'), function () {
            window.location.href = '/frame.php?page=salapauta';
        });
    });

    function syncDateMinForStatusPauta() {
        var st = document.getElementById('audiencia-edit-status');
        var dt = document.getElementById('audiencia-edit-data');
        if (!st || !dt) {
            return;
        }
        var today = String(dt.getAttribute('data-min-designada') || '').trim();
        if (String(st.value || '') === 'designada' && today !== '') {
            var cur = String(dt.value || '').trim();
            if (cur !== '' && cur < today) {
                dt.setAttribute('min', cur);
            } else {
                dt.setAttribute('min', today);
            }
        } else {
            dt.removeAttribute('min');
        }
    }

    function setupLocalizacaoAudienciaEdit() {
        var dataEl = document.getElementById('audiencia-edit-data');
        var salaEl = document.getElementById('audiencia-edit-sala');
        if (!salaEl) return;
        var lifesizeOption = salaEl.querySelector('option[value="lifesize"]');
        var audinOption = salaEl.querySelector('option[value="audin"]');
        var limiteLifesize = '2026-09-01';

        function syncLocalizacao() {
            var data = String(dataEl && dataEl.value || '').trim();
            var bloquearLifesize = data !== '' && data > limiteLifesize;
            var linkAudinWrap = document.getElementById('audiencia-edit-link-audin-wrap');
            if (lifesizeOption) {
                lifesizeOption.disabled = bloquearLifesize;
            }
            if (audinOption && (!salaEl.value || (bloquearLifesize && salaEl.value === 'lifesize'))) {
                salaEl.value = 'audin';
            }
            if (linkAudinWrap) {
                linkAudinWrap.style.display = salaEl.value === 'audin' ? 'flex' : 'none';
            }
        }

        if (!salaEl.value && audinOption) {
            salaEl.value = 'audin';
        }
        salaEl.addEventListener('change', syncLocalizacao);
        dataEl?.addEventListener('change', syncLocalizacao);
        dataEl?.addEventListener('blur', syncLocalizacao);
        syncLocalizacao();
    }

    document.getElementById('audiencia-edit-status')?.addEventListener('change', syncDateMinForStatusPauta);
    setupLocalizacaoAudienciaEdit();

    document.getElementById('btn-audiencia-edit-salvar')?.addEventListener('click', function () {
        normalizePartNameInput();
        saveAudiencia(false);
    });

    document.getElementById('parte-nome')?.addEventListener('blur', normalizePartNameInput);

    function dedupeStrings(arr) {
        var out = [];
        var seen = new Set();
        (arr || []).forEach(function (v) {
            var s = String(v || '').trim();
            if (!s) return;
            var key = s.toLowerCase();
            if (seen.has(key)) return;
            seen.add(key);
            out.push(s);
        });
        return out;
    }

    /**
     * Preenche o select de nome da parte com partes já vinculadas ao processo no sistema.
     * Dados vêm de `window.PAUTA_PARTES_NOMES_INITIAL` (servidor), sem chamada ao DataJud.
     */
    function initParteNomeSelectFromProcesso() {
        var select = document.getElementById('parte-nome-selecao');
        var input = document.getElementById('parte-nome');
        var hiddenId = document.getElementById('parte-id-global');
        if (!select || !input) return;

        function signatureNome(n) {
            var s = String(n || '').trim().toLowerCase();
            try {
                s = s.replace(/[^\p{L}\p{N}]+/gu, '');
            } catch (e) {
                s = s.replace(/[^a-z0-9]+/gi, '');
            }
            return s;
        }

        function fetchPeopleSuggestions(rawName) {
            var nome = normalizeSelectedName(rawName);
            if (input.style.display === 'none' || nome.length < 2) {
                return;
            }
            fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=3', { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var pessoas = data && Array.isArray(data.pessoas) ? data.pessoas : [];
                    if (pessoas.length > 0) {
                        fillParteNomeSelectWithPeopleSuggestions(select, input, hiddenId, pessoas, nome);
                    } else {
                        if (hiddenId) hiddenId.value = '';
                        setParteNomeStatus('', '');
                    }
                })
                .catch(function () {
                    if (hiddenId) hiddenId.value = '';
                });
        }

        if (select.dataset.loaded === '1') return;
        select.dataset.loaded = '1';

        select.addEventListener('change', function () {
            var mode = String(select.dataset.mode || '').trim();
            var val = String(select.value || '').trim();
            if (val === '__OUTRO__' || val === '__novo__') {
                select.classList.remove('status-finished');
                fillParteNomeManualUi(select, input, hiddenId, String(select.dataset.lastSuggestedName || ''));
                input.focus();
                return;
            }

            var selectedOption = select.options[select.selectedIndex];
            var nomeSelecionado = String(selectedOption?.dataset.nomeParte || val).trim();
            input.value = normalizeSelectedName(nomeSelecionado);
            input.style.display = 'none';
            select.style.display = '';
            select.classList.add('status-finished');
            if (mode === 'people') {
                var parteIdGlobal = String(selectedOption?.dataset.parteIdGlobal || '').trim();
                if (hiddenId) {
                    hiddenId.value = parteIdGlobal;
                }
                if (parteIdGlobal !== '') {
                    setParteNomeStatus('Parte vinculada', 'var(--color-success-active)');
                }
            }
        });

        input.addEventListener('blur', function () {
            if (hiddenId) hiddenId.value = '';
            normalizePartNameInput();
            fetchPeopleSuggestions(String(input.value || '').trim());
        });

        var raw = Array.isArray(window.PAUTA_PARTES_NOMES_INITIAL)
            ? window.PAUTA_PARTES_NOMES_INITIAL
            : (Array.isArray(window.PAUTA_REUS_NOMES_INITIAL) ? window.PAUTA_REUS_NOMES_INITIAL : []);
        var nomes = dedupeStrings(raw).sort(function (a, b) { return a.localeCompare(b, 'pt-BR'); });

        var existingSigs = Array.isArray(window.PAUTA_EXISTING_PART_NAMES_SIGS) ? window.PAUTA_EXISTING_PART_NAMES_SIGS : [];
        var existingSet = new Set(existingSigs);
        nomes = nomes.filter(function (nome) {
            var sig = signatureNome(nome);
            return sig && !existingSet.has(sig);
        });

        if (nomes.length > 0) {
            fillParteNomeSelectWithProcessoNames(select, input, hiddenId, nomes);
        } else {
            select.dataset.hasProcessoReusParts = '0';
            select.dataset.processoNamesJson = '[]';
            fillParteNomeManualUi(select, input, hiddenId, '');
        }
    }

    function readGradeEdit() {
        var g = window.PAUTA_HORARIOS_GRADE;
        return Array.isArray(g) ? g : [];
    }

    function fillHoraSelectEdit(available, includeExtra) {
        var sel = document.getElementById('audiencia-edit-hora');
        if (!sel) return;
        var preserved = String(sel.value || '').trim();
        var extra = String(window.PAUTA_HORA_FORA_GRADE || '').trim();
        sel.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.disabled = true;
        opt0.selected = true;
        opt0.textContent = 'Selecione a hora';
        sel.appendChild(opt0);
        (available || []).forEach(function (hm) {
            var o = document.createElement('option');
            o.value = hm;
            o.textContent = hm;
            sel.appendChild(o);
        });
        if (includeExtra !== false && extra && (available || []).indexOf(extra) < 0) {
            var ox = document.createElement('option');
            ox.value = extra;
            ox.textContent = extra + ' (registro atual)';
            sel.appendChild(ox);
        }
        var canKeep = false;
        if (preserved) {
            if ((available || []).indexOf(preserved) >= 0) {
                canKeep = true;
            } else if (includeExtra !== false && extra && preserved === extra) {
                canKeep = true;
            }
        }
        if (canKeep) {
            sel.value = preserved;
        }
    }

    function buildUrlOccupiedSlots(data) {
        var id = resolvePautaEditId();
        var q = '?data=' + encodeURIComponent(data);
        if (id) {
            q += '&except_id=' + encodeURIComponent(id);
        }
        return '/api/sala-audiencias-pauta/occupied-slots.php' + q;
    }

    var dataOriginalPauta = String(document.getElementById('audiencia-edit-data')?.value || '').trim();
    var horariosEditRequest = 0;
    var horariosEditPendingDate = '';
    var horariosEditPendingHour = '';
    var horariosEditUnresolved = false;
    function refreshHoraEditDisponiveis(done) {
        var request = ++horariosEditRequest;
        var dataEl = document.getElementById('audiencia-edit-data');
        var sel = document.getElementById('audiencia-edit-hora');
        if (!dataEl || !sel) {
            if (typeof done === 'function') {
                done();
            }
            return;
        }
        var d = String(dataEl.value || '').trim();
        var grade = readGradeEdit();
        if (!d) {
            horariosEditPendingDate = '';
            horariosEditPendingHour = '';
            horariosEditUnresolved = false;
            fillHoraSelectEdit(grade, false);
            if (typeof done === 'function') {
                done();
            }
            return;
        }
        var previousHour = String(sel.value || '').trim();
        if (!previousHour && horariosEditPendingDate === d) previousHour = horariosEditPendingHour;
        horariosEditPendingDate = d;
        horariosEditPendingHour = previousHour;
        horariosEditUnresolved = true;
        fillHoraSelectEdit([], false);
        fetch(buildUrlOccupiedSlots(d), { credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (request !== horariosEditRequest || String(dataEl.value || '').trim() !== d) return;
                if (!res.ok || !res.j || !Array.isArray(res.j.available)) {
                    notifyByKey('audiencias.schedule.load.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao carregar horários.');
                    fillHoraSelectEdit([], false);
                    if (typeof done === 'function') {
                        done();
                    }
                    return;
                }
                horariosEditUnresolved = false;
                var allowOriginalHour = d === dataOriginalPauta;
                fillHoraSelectEdit(res.j.available, allowOriginalHour);
                if (res.j.available.indexOf(previousHour) >= 0 || (allowOriginalHour && previousHour === String(window.PAUTA_HORA_FORA_GRADE || '').trim())) sel.value = previousHour;
                if (res.j.available.length === 0) {
                    var extra = String(window.PAUTA_HORA_FORA_GRADE || '').trim();
                    var cur = String(sel.value || '').trim();
                    if (!(allowOriginalHour && extra && cur === extra)) {
                        notifyByKey('audiencias.schedule.unavailable.info', {}, 'info', 'Horários', 'Não há horários livres nesta data.');
                    }
                }
                if (typeof done === 'function') {
                    done();
                }
            })
            .catch(function () {
                if (request !== horariosEditRequest || String(dataEl.value || '').trim() !== d) return;
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                fillHoraSelectEdit([], false);
                if (typeof done === 'function') {
                    done();
                }
            });
    }

    document.getElementById('audiencia-edit-data')?.addEventListener('change', function () {
        refreshHoraEditDisponiveis();
    });

    document.getElementById('btn-pauta-alocar')?.addEventListener('click', function () {
        var idPauta = resolvePautaEditId();
        var sel = document.getElementById('pauta-alocar-usuario');
        var uid = sel ? String(sel.value || '').trim() : '';
        if (!idPauta) {
            notifyByKey('audiencias.validation.hearing.invalid', {}, 'error', 'Erro', 'Audiência inválida.');
            return;
        }
        if (!uid) {
            notifyByKey('audiencias.validation.server.required', {}, 'info', 'Alocação', 'Selecione um servidor.');
            return;
        }
        var btnAl = document.getElementById('btn-pauta-alocar');
        if (btnAl) {
            btnAl.disabled = true;
        }
        fetch('/api/sala-audiencias-pauta/alocar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: idPauta, usuario_id: uid })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (btnAl) {
                    btnAl.disabled = false;
                }
                if (res.ok && res.j && res.j.success) {
                    notifyByKey(
                        'audiencias.pauta.server.allocate.success',
                        {},
                        'success',
                        'Sucesso',
                        'Servidor alocado.'
                    );
                    window.location.reload();
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao alocar.');
            })
            .catch(function () {
                if (btnAl) {
                    btnAl.disabled = false;
                }
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
            });
    });

    syncHiddenPautaEditId();

    (function bindResumoDownload() {
        var sel = document.getElementById('audiencia-edit-resumo');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var v = String(sel.value || '');
            if (v !== 'txt' && v !== 'pdf') return;
            var base = sel.getAttribute('data-resumo-base') || '';
            if (!base) return;
            var href = base + '&formato=' + encodeURIComponent(v);
            window.open(href, '_blank', 'noopener,noreferrer');
            sel.selectedIndex = 0;
        });
    })();

    initParteNomeSelectFromProcesso();
    updateParteAddButtonState();
    updateAlocarButtonState();

    syncDateMinForStatusPauta();
    initialAudienciaSnapshot = currentAudienciaSnapshot();
    refreshHoraEditDisponiveis();
})();
