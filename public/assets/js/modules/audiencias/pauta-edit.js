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
        var nome = String(nomeInput.value || '').trim();
        if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
            nome = TextFormatter.formatProperName(nome, 255);
        }
        nomeInput.value = nome;
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
        [
            'parte-nome',
            'parte-tipo',
            'parte-maioridade',
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });
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

    document.querySelectorAll('.button-edit[data-parte-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idParte = String(btn.getAttribute('data-parte-edit') || '').trim();
            if (!idParte) return;
            window.location.href = '/frame.php?page=salapautaparteedit&id_parte=' + encodeURIComponent(idParte);
        });
    });

    document.querySelectorAll('.button-table[data-parte-remove]').forEach(function (btn) {
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
    initialAudienciaSnapshot = currentAudienciaSnapshot();
})();
