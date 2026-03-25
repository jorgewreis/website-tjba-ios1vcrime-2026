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

    function initParteNomeDatajudSelect() {
        var processoInput = document.getElementById('audiencia-edit-processo');
        var select = document.getElementById('parte-nome-selecao');
        var input = document.getElementById('parte-nome');
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
            if (val === '__OUTRO__') {
                // Selecionou "Outro": mostra input e oculta select.
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

        var API_ENDPOINT = '/api/datajud/search-processo.php';
        var body = { numero: digits };

        fetch(API_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) throw new Error((res.j && res.j.error) ? res.j.error : 'Erro ao consultar DataJud.');
                var data = res.j && res.j.data ? res.j.data : null;
                var nomes = [];
                if (data && Array.isArray(data.polo_passivo)) {
                    nomes = data.polo_passivo;
                }
                nomes = dedupeStrings(nomes).sort(function (a, b) { return a.localeCompare(b, 'pt-BR'); });

                // Filtra do select apenas nomes que ainda não existem na lista de partes.
                // Regra pedida: se já existir na audiência/processo, não deve aparecer no select.
                var nomesAntesFiltro = nomes.length;
                var existingSigs = Array.isArray(window.PAUTA_EXISTING_PART_NAMES_SIGS) ? window.PAUTA_EXISTING_PART_NAMES_SIGS : [];
                var existingSet = new Set(existingSigs);
                function signatureNome(n) {
                    var s = String(n || '').trim().toLowerCase();
                    try {
                        // Unicode property escapes (modern browsers).
                        s = s.replace(/[^\p{L}\p{N}]+/gu, '');
                    } catch (e) {
                        // fallback simples (ASCII).
                        s = s.replace(/[^a-z0-9]+/gi, '');
                    }
                    return s;
                }
                nomes = nomes.filter(function (nome) {
                    var sig = signatureNome(nome);
                    return sig && !existingSet.has(sig);
                });

                // #region agent log H1:DataJud response & filter
                fetch('http://127.0.0.1:7798/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Debug-Session-Id': 'b44cef'
                    },
                    body: JSON.stringify({
                        sessionId: 'b44cef',
                        runId: 'pre',
                        hypothesisId: 'H1',
                        location: 'pauta-edit.js:datajud-filter',
                        message: 'DataJud nomes count (antes/depois do filtro)',
                        data: {
                            digitsLen: String(digits || '').replace(/\\D/g, '').length,
                            poloPassivoIsArray: Boolean(data && Array.isArray(data.polo_passivo)),
                            poloPassivoLen: data && Array.isArray(data.polo_passivo) ? data.polo_passivo.length : null,
                            nomesAntesFiltro: nomesAntesFiltro,
                            nomesDepoisFiltro: nomes.length,
                            existingSignaturesCount: existingSet ? existingSet.size : null
                        },
                        timestamp: Date.now()
                    })
                }).catch(function () {});
                // #endregion

                if (nomes.length > 0) {
                    // Lista encontrada: mostra só select e oculta input.
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

                    select.disabled = false;
                    select.style.display = '';
                    input.style.display = 'none';
                    input.readOnly = true;
                } else {
                    // Nenhuma parte retornada: oculta select e mostra input.
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
                }
            })
            .catch(function () {
                // Se falhar, mantém o input livre (sem bloquear usuário).
                if (typeof showToast === 'function') {
                    showToast({ type: 'info', variant: 'full', title: 'DataJud', message: 'Nao foi possivel consultar nomes do processo no DataJud. Voce pode digitar manualmente.' });
                }
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
            });
    }

    initParteNomeDatajudSelect();
    initialAudienciaSnapshot = currentAudienciaSnapshot();
})();
