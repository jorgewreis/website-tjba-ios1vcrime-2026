(function () {
    function onlyDigits(value) {
        if (typeof window.onlyDigitsCNJ === 'function') {
            return window.onlyDigitsCNJ(value);
        }
        return String(value || '').replace(/\D+/g, '');
    }

    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function requiredValue(id, label) {
        var el = document.getElementById(id);
        var value = String(el && el.value ? el.value : '').trim();
        if (!value) {
            notify('error', 'Validacao', 'Informe: ' + label + '.');
            return '';
        }
        return value;
    }

    function setupDataMinimaAudiencia() {
        var dataEl = document.getElementById('audiencia-data');
        if (!dataEl) return;
        var minD = String(dataEl.getAttribute('min') || '').trim();
        function clampData() {
            var v = String(dataEl.value || '').trim();
            if (v && minD && v < minD) {
                dataEl.value = minD;
                notify('info', 'Data', 'Datas anteriores a hoje não são permitidas.');
            }
        }
        dataEl.addEventListener('change', clampData);
        dataEl.addEventListener('blur', clampData);
    }

    setupDataMinimaAudiencia();

    document.getElementById('btn-audiencia-voltar')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapauta';
    });

    document.getElementById('btn-audiencia-cadastrar')?.addEventListener('click', function () {
        var processo = requiredValue('audiencia-processo', 'Numero do processo');
        if (!processo) return;
        var cnjResult = typeof window.normalizeAndValidateCNJValue === 'function'
            ? window.normalizeAndValidateCNJValue(processo, { toastOnInvalid: true })
            : { ok: onlyDigits(processo).length === 20, formatted: processo };
        if (!cnjResult.ok) {
            notify('error', 'Validacao', 'Número do processo inválido. Informe no padrão CNJ.');
            return;
        }
        processo = String(cnjResult.formatted || processo).trim();
        var processoInput = document.getElementById('audiencia-processo');
        if (processoInput) processoInput.value = processo;
        var tipoRaw = requiredValue('audiencia-tipo', 'Tipo de audiencia');
        if (!tipoRaw) return;
        var tipoSelect = document.getElementById('audiencia-tipo');
        var tipo = tipoSelect && tipoSelect.options && tipoSelect.selectedIndex >= 0
            ? String(tipoSelect.options[tipoSelect.selectedIndex].text || '').trim()
            : tipoRaw;
        if (!tipo) return;
        var data = requiredValue('audiencia-data', 'Data');
        if (!data) return;
        var minData = String(document.getElementById('audiencia-data')?.getAttribute('min') || '').trim();
        if (minData && data < minData) {
            notify('error', 'Validacao', 'A data da audiência não pode ser anterior a hoje.');
            return;
        }
        var hora = requiredValue('audiencia-hora', 'Hora');
        if (!hora) return;
        var sala = requiredValue('audiencia-sala', 'Localizacao');
        if (!sala) return;
        var status = String(document.getElementById('audiencia-status')?.value || 'designada').trim();
        var classeCodigo = String(document.getElementById('hidden-classe-codigo')?.value || '').trim();
        var classeDescricaoHidden = String(document.getElementById('hidden-classe-descricao')?.value || '').trim();
        var classeDescricaoCampo = String(document.getElementById('audiencia-classe')?.value || '').trim();
        var classeDescricao = classeDescricaoHidden || classeDescricaoCampo;
        if (!classeDescricao) {
            notify('error', 'Validacao', 'Informe: Classe (aguarde o DataJud ou preencha manualmente).');
            return;
        }

        fetch('/api/sala-audiencias-pauta/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                numero_processo: processo,
                tipo_audiencia: tipo,
                data_audiencia: data,
                hora_audiencia: hora,
                localizacao: sala,
                status: status,
                classe_codigo: classeCodigo,
                classe_descricao: classeDescricao
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.id) {
                    try {
                        sessionStorage.setItem('salapauta.postReloadToast', JSON.stringify([{ type: 'success', title: 'Sucesso', message: 'Audiência cadastrada.' }]));
                    } catch (e) {}
                    window.location.href = '/frame.php?page=salapauta';
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cadastrar audiência.');
                }
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
            });
    });
})();
