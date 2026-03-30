(function () {
    var pendingConfirmations = {};
    var initialAudienciaSnapshot = '';

    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function requireSecondClick(key, message) {
        var now = Date.now();
        var validUntil = Number(pendingConfirmations[key] || 0);
        if (validUntil > now) {
            delete pendingConfirmations[key];
            return true;
        }
        pendingConfirmations[key] = now + 5000;
        notify('info', 'Confirmacao', message);
        return false;
    }

    function readPartPayload() {
        return {
            nome_parte: String(document.getElementById('parte-nome')?.value || '').trim(),
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
        // Se o nome foi selecionado via DataJud (readOnly), não altere o texto.
        if (nomeInput.readOnly) return;
        var nome = String(nomeInput.value || '').trim();
        if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nome = TextFormatter.formatProperName(nome, 255);
        }
        nomeInput.value = nome;
    }

    function normalizeSelectedName(val) {
        var s = String(val || '').trim();
        if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            s = TextFormatter.formatProperName(s, 255);
        }
        return s;
    }

    function currentAudienciaSnapshot() {
        return JSON.stringify({
            tipo: String(document.getElementById('audiencia-edit-tipo')?.value || '').trim(),
            data: String(document.getElementById('audiencia-edit-data')?.value || '').trim(),
            hora: String(document.getElementById('audiencia-edit-hora')?.value || '').trim(),
            localizacao: String(document.getElementById('audiencia-edit-sala')?.value || '').trim(),
            classe: String(document.getElementById('audiencia-edit-classe')?.value || '').trim(),
            status: String(document.getElementById('audiencia-edit-status')?.value || '').trim()
        });
    }

    function isAudienciaDirty() {
        return currentAudienciaSnapshot() !== initialAudienciaSnapshot;
    }

    function saveAudiencia(autoMessage) {
        var id = String(document.getElementById('audiencia-edit-id')?.value || '').trim();
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
                status: String(document.getElementById('audiencia-edit-status')?.value || '').trim()
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    initialAudienciaSnapshot = currentAudienciaSnapshot();
                    if (autoMessage) {
                        notify('info', 'Atualização automática', 'Dados da audiência salvos antes de adicionar a parte.');
                    } else {
                        notify('success', 'Sucesso', 'Alterações salvas.');
                    }
                    return true;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao salvar.');
                return false;
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
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
        if (!selectParteNome || !input) return;

        input.value = '';
        input.placeholder = 'Digite o nome da parte';

        var has = String(selectParteNome.dataset.hasDatajudParts || '0');
        if (has === '1') {
            // Volta ao padrão: mostra só select (com o primeiro nome).
            selectParteNome.style.display = '';
            selectParteNome.disabled = false;

            var firstVal = String(selectParteNome.dataset.firstDatajudValue || '');
            if (firstVal && selectParteNome.querySelector('option[value="' + firstVal + '"]')) {
                selectParteNome.value = firstVal;
            } else {
                // fallback: pega primeira opção diferente de __OUTRO__
                var opts = selectParteNome.querySelectorAll('option');
                var picked = '';
                opts.forEach(function (o) {
                    if (picked) return;
                    if ((o.value || '') !== '__OUTRO__') picked = o.value;
                });
                if (picked) selectParteNome.value = picked;
            }

            input.value = String(selectParteNome.value || '');
            input.style.display = 'none';
            input.readOnly = true;
        } else {
            // Sem nomes no DataJud: mostra input e oculta select.
            selectParteNome.style.display = 'none';
            selectParteNome.disabled = true;
            input.style.display = '';
            input.readOnly = false;
        }
    }

    document.getElementById('btn-audiencia-edit-voltar')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapauta';
    });

    document.getElementById('btn-parte-limpar')?.addEventListener('click', clearPartForm);

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
                notify('error', 'Validacao', 'Informe: ' + missing + '.');
                return;
            }
            var idPauta = String(document.getElementById('audiencia-edit-id')?.value || '').trim();
            fetch('/api/sala-audiencias-pauta/partes-create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({ id_pauta: idPauta }, payload))
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.id) {
                        try {
                            sessionStorage.setItem('salapauta.postReloadToast', JSON.stringify([{ type: 'success', title: 'Sucesso', message: 'Parte adicionada.' }]));
                        } catch (e) {}
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao adicionar parte.');
                    }
                })
                .catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
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
            window.location.href = '/frame.php?page=consultapartesedit&id=' + encodeURIComponent(idGlobal);
        });
    });

    // Some UIs use `.button-table` while others use `.btn-editar stop`; handler must be generic.
    document.querySelectorAll('[data-parte-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!requireSecondClick('pauta-remover-parte-' + btn.getAttribute('data-parte-remove'), 'Clique novamente em Remover parte em ate 5 segundos para confirmar.')) return;
            fetch('/api/sala-audiencias-pauta/partes-delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id_parte: btn.getAttribute('data-parte-remove') })
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao remover parte.');
                    }
                })
                .catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
        });
    });

    document.getElementById('btn-audiencia-edit-cancelar')?.addEventListener('click', function () {
        if (!requireSecondClick('pauta-cancelar-audiencia', 'Clique novamente em Cancelar audiencia em ate 5 segundos para confirmar.')) return;
        var id = String(document.getElementById('audiencia-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/cancel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id })
        }).then(function () { window.location.reload(); }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
    });

    document.getElementById('btn-audiencia-edit-excluir')?.addEventListener('click', function () {
        if (!requireSecondClick('pauta-excluir-audiencia', 'Clique novamente em Excluir audiencia em ate 5 segundos para confirmar.')) return;
        var id = String(document.getElementById('audiencia-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id })
        }).then(function () {
            window.location.href = '/frame.php?page=salapauta';
        }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
    });

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
     * Cache DataJud de partes (sessionStorage + localStorage). Evita re-consulta a cada reload após adicionar parte.
     * Uma chave por CNJ (20 dígitos): valor JSON = null | ["Nome1", ...]
     *   null  → sem polo passivo ou falha já registrada (UI manual; não refaz fetch a cada F5)
     *   array → nomes brutos (antes do filtro de partes já cadastradas)
     */
    var STORAGE_KEY_DATAJUD_POLO_LEGACY = 'salapauta.datajudPoloPassivo';
    var STORAGE_PREFIX_DATAJUD_POLO = 'salapauta.datajudPoloByCnj.v1.';

    function datajudStorageKey(processDigits) {
        return STORAGE_PREFIX_DATAJUD_POLO + processDigits;
    }

    function readLegacyDatajudObjectCache(processDigits) {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY_DATAJUD_POLO_LEGACY)
                || localStorage.getItem(STORAGE_KEY_DATAJUD_POLO_LEGACY);
            if (!raw) return undefined;
            var o = JSON.parse(raw);
            if (!o || typeof o !== 'object' || Array.isArray(o)) return undefined;
            if (!(processDigits in o)) return undefined;
            return o[processDigits];
        } catch (e) {
            return undefined;
        }
    }

    function readDatajudCache(processDigits) {
        var key = datajudStorageKey(processDigits);
        try {
            var raw = sessionStorage.getItem(key) || localStorage.getItem(key);
            if (raw != null && raw !== '') {
                return JSON.parse(raw);
            }
        } catch (e) {
            // continua para legado
        }
        var legacy = readLegacyDatajudObjectCache(processDigits);
        if (legacy !== undefined) {
            writeDatajudCache(processDigits, legacy);
        }
        return legacy;
    }

    function writeDatajudCache(processDigits, nomes) {
        var key = datajudStorageKey(processDigits);
        var payload = Array.isArray(nomes) ? nomes : null;
        var s = JSON.stringify(payload);
        try {
            sessionStorage.setItem(key, s);
        } catch (e) {}
        try {
            localStorage.setItem(key, s);
        } catch (e) {}
    }

    /** Mantém compatibilidade com chave legada criada em conversas anteriores. */
    var STORAGE_KEY_DATAJUD_SEM_POLO_LEGACY = 'salapauta.datajudSemPoloPassivo';
    function clearLegacyStorage() {
        try { sessionStorage.removeItem(STORAGE_KEY_DATAJUD_SEM_POLO_LEGACY); } catch (e) {}
    }
    clearLegacyStorage();

    function initParteNomeDatajudSelect() {
        var processoInput = document.getElementById('audiencia-edit-processo');
        var select = document.getElementById('parte-nome-selecao');
        var input = document.getElementById('parte-nome');
        var statusEl = document.getElementById('parte-nome-datajud-status');
        if (!processoInput || !select || !input) return;

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // Evita múltiplas consultas.
        if (select.dataset.loaded === '1') return;
        select.dataset.loaded = '1';

        select.addEventListener('change', function () {
            var val = String(select.value || '').trim();
            if (typeof window.DataJudCycle !== 'undefined' && val === window.DataJudCycle.DATAJUD_SELECT_LOADING_VALUE) {
                return;
            }
            if (val === '__OUTRO__') {
                // Selecionou "Outro": mostra input e oculta select.
                select.classList.remove('status-finished');
                input.style.display = '';
                input.readOnly = false;
                input.focus();
                select.style.display = 'none';
                return;
            }

            // Selecionou nome da lista: oculta input (mantém select visível).
            input.value = normalizeSelectedName(val);
            input.style.display = 'none';
            input.readOnly = true;
        });

        // Estado inicial: mostra só o select; input fica oculto.
        select.value = '__OUTRO__';
        select.disabled = true;
        select.style.display = '';
        input.style.display = 'none';
        input.readOnly = true;
        input.placeholder = 'Digite o nome da parte';
        input.value = '';

        select.dataset.hasDatajudParts = '0';
        select.dataset.firstDatajudValue = '';

        var digits = String(processoInput.value || '').replace(/\D/g, '');
        if (digits.length !== 20) {
            select.disabled = false;
            return;
        }

        if (typeof window.DataJudCycle === 'undefined') {
            console.error('pauta-edit.js: DataJudCycle não encontrado. Inclua /assets/js/modules/datajud/datajud-cycle-helper.js antes deste script.');
            select.disabled = false;
            return;
        }

        function applyNoDatajudPartesUi(toastPayload) {
            select.classList.remove('status-finished');
            select.dataset.hasDatajudParts = '0';
            select.dataset.firstDatajudValue = '';
            select.innerHTML = '<option value="__OUTRO__" selected>Outro (digite abaixo)</option>';
            select.value = '__OUTRO__';
            select.disabled = true;
            select.style.display = 'none';

            input.value = '';
            input.style.display = '';
            input.readOnly = false;
            input.placeholder = 'Digite o nome da parte';
            if (statusEl && typeof window.DataJudCycle !== 'undefined') {
                window.DataJudCycle.setFieldStatus(statusEl, '', null);
            }
            if (toastPayload && typeof showToast === 'function') {
                showToast(toastPayload);
            }
        }

        var cached = readDatajudCache(digits);
        if (cached !== undefined) {
            if (!Array.isArray(cached) || cached.length === 0) {
                applyNoDatajudPartesUi(null);
                return;
            }
            var cachedExistingSigs = Array.isArray(window.PAUTA_EXISTING_PART_NAMES_SIGS) ? window.PAUTA_EXISTING_PART_NAMES_SIGS : [];
            var cachedExistingSet = new Set(cachedExistingSigs);
            function signatureNomeCached(n) {
                var s = String(n || '').trim().toLowerCase();
                try { s = s.replace(/[^\p{L}\p{N}]+/gu, ''); } catch (e) { s = s.replace(/[^a-z0-9]+/gi, ''); }
                return s;
            }
            var filteredCached = cached.filter(function (nome) {
                var sig = signatureNomeCached(nome);
                return sig && !cachedExistingSet.has(sig);
            });
            if (filteredCached.length === 0) {
                applyNoDatajudPartesUi(null);
                return;
            }
            var optionsCached = '';
            filteredCached.forEach(function (n) {
                var safeVal = escapeHtml(n);
                optionsCached += '<option value="' + safeVal + '">' + safeVal + '</option>';
            });
            optionsCached += '<option value="__OUTRO__">Outro (digite abaixo)</option>';
            select.innerHTML = optionsCached;
            select.dataset.hasDatajudParts = '1';
            select.dataset.firstDatajudValue = filteredCached[0] || '';
            select.value = filteredCached[0] || '';
            input.value = normalizeSelectedName(select.value || '');
            select.classList.add('status-finished');
            select.disabled = false;
            select.style.display = '';
            input.style.display = 'none';
            input.readOnly = true;
            if (statusEl && typeof window.DataJudCycle !== 'undefined') {
                window.DataJudCycle.setFieldStatus(statusEl, '*Nomes obtidos via DataJud', 'var(--color-success-active)');
            }
            return;
        }

        var DC = window.DataJudCycle;
        var API_ENDPOINT = '/api/datajud/search-processo.php';

        DC.cancelPrevious();
        var myOpId = DC.getOperationId();
        var session = DC.startSelectLoading(select, statusEl);

        function applyManualFallback(toastPayload) {
            DC.endSelectCycleUi(session);
            DC.clearSelectLoading(select);
            applyNoDatajudPartesUi(toastPayload);
        }

        function signatureNome(n) {
            var s = String(n || '').trim().toLowerCase();
            try {
                s = s.replace(/[^\p{L}\p{N}]+/gu, '');
            } catch (e) {
                s = s.replace(/[^a-z0-9]+/gi, '');
            }
            return s;
        }

        function fetchAttempt(attemptIndex) {
            if (DC.isStaleOperation(myOpId)) {
                return Promise.resolve();
            }

            var controller = new AbortController();
            DC.registerFetchController(controller);
            var tid = setTimeout(function () {
                DC.setPendingAbortReason('timeout');
                controller.abort();
            }, DC.FETCH_TIMEOUT_MS);

            return fetch(API_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ numero: digits }),
                signal: controller.signal,
            })
                .then(function (r) {
                    return r.text().then(function (txt) {
                        var parsed = null;
                        var parseError = null;
                        try {
                            parsed = txt ? JSON.parse(txt) : null;
                        } catch (e) {
                            parseError = e ? (e.message || 'json-parse-error') : 'json-parse-error';
                        }
                        return { ok: r.ok, status: r.status, j: parsed, parseError: parseError };
                    });
                })
                .then(function (res) {
                    if (DC.isStaleOperation(myOpId)) {
                        return;
                    }
                    if (res.parseError) {
                        var pe = new Error('parse');
                        pe.code = 'parse';
                        throw pe;
                    }
                    if (!res.ok) {
                        var he = new Error('http');
                        he.isHttp = true;
                        he.msg = (res.j && res.j.error) ? res.j.error : 'Erro ao consultar DataJud.';
                        throw he;
                    }
                    var data = res.j && res.j.data ? res.j.data : null;
                    var nomes = [];
                    if (data && Array.isArray(data.polo_passivo)) {
                        nomes = data.polo_passivo;
                    }
                    nomes = dedupeStrings(nomes).sort(function (a, b) { return a.localeCompare(b, 'pt-BR'); });

                    // Grava o resultado bruto (antes do filtro de partes já cadastradas) para reuso no reload.
                    writeDatajudCache(digits, nomes);

                    var existingSigs = Array.isArray(window.PAUTA_EXISTING_PART_NAMES_SIGS) ? window.PAUTA_EXISTING_PART_NAMES_SIGS : [];
                    var existingSet = new Set(existingSigs);
                    nomes = nomes.filter(function (nome) {
                        var sig = signatureNome(nome);
                        return sig && !existingSet.has(sig);
                    });

                    DC.endSelectCycleUi(session);
                    DC.clearSelectLoading(select);

                    if (nomes.length > 0) {
                        var options = '';
                        nomes.forEach(function (n) {
                            var safeVal = escapeHtml(n);
                            options += '<option value="' + safeVal + '">' + safeVal + '</option>';
                        });
                        options += '<option value="__OUTRO__">Outro (digite abaixo)</option>';
                        select.innerHTML = options;

                        select.dataset.hasDatajudParts = '1';
                        select.dataset.firstDatajudValue = nomes[0] || '';

                        select.value = nomes[0] || '';
                        input.value = normalizeSelectedName(select.value || '');

                        select.classList.add('status-finished');
                        select.disabled = false;
                        select.style.display = '';
                        input.style.display = 'none';
                        input.readOnly = true;
                        if (statusEl) {
                            DC.setFieldStatus(statusEl, '*Nomes obtidos via DataJud', 'var(--color-success-active)');
                        }
                    } else {
                        applyNoDatajudPartesUi(null);
                    }
                })
                .catch(function (err) {
                    if (DC.isStaleOperation(myOpId)) {
                        return;
                    }
                    if (err.name === 'AbortError') {
                        var reason = DC.consumeAbortReason();
                        if (reason === 'user') {
                            return;
                        }
                        if (reason === 'timeout' && attemptIndex < DC.MAX_FETCH_ATTEMPTS) {
                            return fetchAttempt(attemptIndex + 1);
                        }
                        if (reason === 'timeout') {
                            writeDatajudCache(digits, null);
                            applyManualFallback(typeof showToast === 'function' ? {
                                type: 'info',
                                variant: 'simple',
                                message: DC.MSG_NO_RESULT,
                            } : null);
                        }
                        return;
                    }
                    console.error('DataJud (partes) falhou', err);
                    if (err && err.code === 'parse') {
                        writeDatajudCache(digits, null);
                        applyManualFallback(typeof showToast === 'function' ? {
                            type: 'error',
                            variant: 'simple',
                            message: DC.MSG_HTTP_GENERIC,
                        } : null);
                        return;
                    }
                    if (err && err.isHttp) {
                        writeDatajudCache(digits, null);
                        applyManualFallback(typeof showToast === 'function' ? {
                            type: 'error',
                            variant: 'simple',
                            message: err.msg || DC.MSG_HTTP_GENERIC,
                        } : null);
                        return;
                    }
                    writeDatajudCache(digits, null);
                    applyManualFallback(typeof showToast === 'function' ? {
                        type: 'info',
                        variant: 'simple',
                        message: 'Não foi possível consultar nomes do processo no DataJud. Você pode digitar manualmente.',
                    } : null);
                })
                .finally(function () {
                    clearTimeout(tid);
                    DC.clearFetchController();
                });
        }

        fetchAttempt(1);
    }

    function readGradeEdit() {
        var g = window.PAUTA_HORARIOS_GRADE;
        return Array.isArray(g) ? g : [];
    }

    function fillHoraSelectEdit(available) {
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
        if (extra && (available || []).indexOf(extra) < 0) {
            var ox = document.createElement('option');
            ox.value = extra;
            ox.textContent = extra + ' (registro atual)';
            sel.appendChild(ox);
        }
        var canKeep = false;
        if (preserved) {
            if ((available || []).indexOf(preserved) >= 0) {
                canKeep = true;
            } else if (extra && preserved === extra) {
                canKeep = true;
            }
        }
        if (canKeep) {
            sel.value = preserved;
        }
    }

    function buildUrlOccupiedSlots(data) {
        var id = String(document.getElementById('audiencia-edit-id')?.value || '').trim();
        var q = '?data=' + encodeURIComponent(data);
        if (id) {
            q += '&except_id=' + encodeURIComponent(id);
        }
        return '/api/sala-audiencias-pauta/occupied-slots.php' + q;
    }

    function refreshHoraEditDisponiveis(done) {
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
            fillHoraSelectEdit(grade);
            if (typeof done === 'function') {
                done();
            }
            return;
        }
        fetch(buildUrlOccupiedSlots(d), { credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !Array.isArray(res.j.available)) {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao carregar horários.');
                    fillHoraSelectEdit(grade);
                    if (typeof done === 'function') {
                        done();
                    }
                    return;
                }
                fillHoraSelectEdit(res.j.available);
                if (res.j.available.length === 0) {
                    var extra = String(window.PAUTA_HORA_FORA_GRADE || '').trim();
                    var cur = String(sel.value || '').trim();
                    if (!(extra && cur === extra)) {
                        notify('info', 'Horários', 'Não há horários livres nesta data.');
                    }
                }
                if (typeof done === 'function') {
                    done();
                }
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
                fillHoraSelectEdit(grade);
                if (typeof done === 'function') {
                    done();
                }
            });
    }

    document.getElementById('audiencia-edit-data')?.addEventListener('change', function () {
        refreshHoraEditDisponiveis();
    });

    initParteNomeDatajudSelect();

    refreshHoraEditDisponiveis(function () {
        initialAudienciaSnapshot = currentAudienciaSnapshot();
    });
})();
