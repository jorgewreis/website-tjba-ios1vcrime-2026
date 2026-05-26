(function () {
    var pageTools = window.App && App.Page ? App.Page : null;
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

    function requiredValue(id, label) {
        var el = document.getElementById(id);
        var value = String(el && el.value ? el.value : '').trim();
        if (!value) {
            notifyByKey('audiencias.validation.required.field', { campo: label }, 'error', 'Validacao', 'Informe: ' + label + '.');
            return '';
        }
        return value;
    }

    /**
     * Formata nome de pessoa respeitando as excecoes (de, da, do, das, dos, e, em, no, na).
     * Usa TextFormatter.formatProperName quando disponivel; delega ao helper do App em fallback.
     */
    function formatProperName(value, maxLen) {
        var v = String(value == null ? '' : value);
        var max = typeof maxLen === 'number' && maxLen > 0 ? maxLen : 255;
        if (window.TextFormatter && typeof window.TextFormatter.formatProperName === 'function') {
            return window.TextFormatter.formatProperName(v, max);
        }
        if (window.App && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            return App.ProperNameHelper.formatProper(v, max);
        }
        return v.trim();
    }

    function applyProperNameOnBlur(id, maxLen) {
        var el = document.getElementById(id);
        if (!el || el.readOnly) return;
        el.addEventListener('blur', function () {
            el.value = formatProperName(el.value, maxLen);
        });
    }

    function toggleDefensorFields() {
        var tipoParte = String(document.getElementById('parte-edit-tipo')?.value || '').trim();
        var wrapper = document.getElementById('parte-edit-defensor-wrapper');
        var tipoDefensor = document.getElementById('parte-edit-tipo-defensor');
        var publicoWrap = document.getElementById('parte-edit-defensor-publico-wrapper');
        var publicoOutroWrap = document.getElementById('parte-edit-defensor-publico-outro-wrapper');
        var privadoWrap = document.getElementById('parte-edit-defensor-privado-wrapper');
        var outroWrap = document.getElementById('parte-edit-defensor-privado-outro-wrapper');
        var defensorPublico = document.getElementById('parte-edit-defensor-publico');
        var defensorPublicoOutro = document.getElementById('parte-edit-defensor-publico-outro');
        var defensorPrivado = document.getElementById('parte-edit-defensor-privado');
        var defensorOutro = document.getElementById('parte-edit-defensor-privado-outro');

        if (!wrapper || !tipoDefensor || !publicoWrap || !privadoWrap || !outroWrap) return;

        if (tipoParte === 'reu' || tipoParte === 'vitima') {
            wrapper.style.display = '';
        } else {
            wrapper.style.display = 'none';
            tipoDefensor.value = '';
            if (defensorPublico) defensorPublico.value = '';
            if (defensorPublicoOutro) defensorPublicoOutro.value = '';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
            publicoWrap.style.display = 'none';
            if (publicoOutroWrap) publicoOutroWrap.style.display = 'none';
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            return;
        }

        var td = String(tipoDefensor.value || '').trim();
        if (td === 'publico') {
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
            var pubVal = String(defensorPublico?.value || '').trim();
            if (pubVal === 'outro') {
                publicoWrap.style.display = 'none';
                if (publicoOutroWrap) publicoOutroWrap.style.display = '';
            } else {
                publicoWrap.style.display = '';
                if (publicoOutroWrap) publicoOutroWrap.style.display = 'none';
                if (defensorPublicoOutro) defensorPublicoOutro.value = '';
            }
        } else if (td === 'privado') {
            publicoWrap.style.display = 'none';
            if (publicoOutroWrap) publicoOutroWrap.style.display = 'none';
            if (defensorPublico) defensorPublico.value = '';
            if (defensorPublicoOutro) defensorPublicoOutro.value = '';
            var valPrivado = String(defensorPrivado?.value || '').trim();
            if (valPrivado === 'outro') {
                privadoWrap.style.display = 'none';
                outroWrap.style.display = '';
            } else {
                privadoWrap.style.display = '';
                outroWrap.style.display = 'none';
                if (defensorOutro) defensorOutro.value = '';
            }
        } else {
            publicoWrap.style.display = 'none';
            if (publicoOutroWrap) publicoOutroWrap.style.display = 'none';
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            if (defensorPublico) defensorPublico.value = '';
            if (defensorPublicoOutro) defensorPublicoOutro.value = '';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
        }
    }

    document.getElementById('btn-parte-edit-voltar')?.addEventListener('click', function () {
        history.back();
    });

    document.getElementById('btn-parte-edit-remover')?.addEventListener('click', function () {
        var idParte = String(document.getElementById('parte-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/partes-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id_parte: idParte })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    notifyByKey('audiencias.pauta.part.remove.success', {}, 'success', 'Sucesso', 'Parte removida.');
                    setTimeout(function () { history.back(); }, 300);
                } else {
                    notifyByKey('audiencias.part.remove.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao remover parte.');
                }
            })
            .catch(function () { notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.'); });
    });

    document.getElementById('btn-parte-edit-salvar')?.addEventListener('click', function () {
        var nomeEl = document.getElementById('parte-edit-nome');
        if (nomeEl && !nomeEl.readOnly) {
            nomeEl.value = formatProperName(nomeEl.value, 255);
        }
        var defPubOutroEl = document.getElementById('parte-edit-defensor-publico-outro');
        if (defPubOutroEl && !defPubOutroEl.readOnly && defPubOutroEl.offsetParent !== null) {
            defPubOutroEl.value = formatProperName(defPubOutroEl.value, 255);
        }
        var defPrivOutroEl = document.getElementById('parte-edit-defensor-privado-outro');
        if (defPrivOutroEl && !defPrivOutroEl.readOnly && defPrivOutroEl.offsetParent !== null) {
            defPrivOutroEl.value = formatProperName(defPrivOutroEl.value, 255);
        }

        var nome = requiredValue('parte-edit-nome', 'Nome da parte');
        if (!nome) return;
        var tipo = requiredValue('parte-edit-tipo', 'Tipo de parte');
        if (!tipo) return;
        var maioridade = requiredValue('parte-edit-maioridade', 'Maioridade');
        if (!maioridade) return;
        var expedicao = requiredValue('parte-edit-expedicao', 'Expedicao');
        if (!expedicao) return;
        var cumprimento = requiredValue('parte-edit-cumprimento', 'Cumprimento');
        if (!cumprimento) return;

        var idParte = String(document.getElementById('parte-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/partes-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                id_parte: idParte,
                parte_id_global: String(document.getElementById('parte-edit-parte-global-id')?.value || '').trim(),
                processo_parte_id: String(document.getElementById('parte-edit-processo-parte-id')?.value || '').trim(),
                nome_parte: nome,
                tipo_parte: tipo,
                tipo_defensor: String(document.getElementById('parte-edit-tipo-defensor')?.value || '').trim(),
                defensor_publico_id: String(document.getElementById('parte-edit-defensor-publico')?.value || '').trim(),
                defensor_publico_outro: String(document.getElementById('parte-edit-defensor-publico-outro')?.value || '').trim(),
                defensor_privado_id: String(document.getElementById('parte-edit-defensor-privado')?.value || '').trim(),
                defensor_privado_outro: String(document.getElementById('parte-edit-defensor-privado-outro')?.value || '').trim(),
                maioridade: maioridade,
                expedicao: expedicao,
                cumprimento: cumprimento,
                id_documento_expedido: String(document.getElementById('parte-edit-id-expedido')?.value || '').trim(),
                id_documento_cumprido: String(document.getElementById('parte-edit-id-cumprido')?.value || '').trim(),
                contatos: String(document.getElementById('parte-edit-contatos')?.value || '').trim().slice(0, 200)
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    notifyByKey('audiencias.pauta.update.success', {}, 'success', 'Sucesso', 'Parte atualizada.');
                    setTimeout(function () {
                        var idPauta = String(document.getElementById('parte-edit-pauta-id')?.value || '').trim();
                        if (idPauta) {
                            window.location.href = '/frame.php?page=salapautaedit&id=' + encodeURIComponent(idPauta);
                        } else {
                            history.back();
                        }
                    }, 250);
                } else {
                    notifyByKey('audiencias.part.update.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao atualizar parte.');
                }
            })
            .catch(function () { notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.'); });
    });

    document.getElementById('parte-edit-tipo')?.addEventListener('change', toggleDefensorFields);
    document.getElementById('parte-edit-tipo-defensor')?.addEventListener('change', toggleDefensorFields);
    document.getElementById('parte-edit-defensor-privado')?.addEventListener('change', toggleDefensorFields);
    document.getElementById('parte-edit-defensor-publico')?.addEventListener('change', toggleDefensorFields);
    toggleDefensorFields();

    applyProperNameOnBlur('parte-edit-nome', 255);
    applyProperNameOnBlur('parte-edit-defensor-publico-outro', 255);
    applyProperNameOnBlur('parte-edit-defensor-privado-outro', 255);
})();
