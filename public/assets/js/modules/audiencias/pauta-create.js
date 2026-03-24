(function () {
    function onlyDigits(value) {
        if (typeof window.onlyDigitsCNJ === 'function') {
            return window.onlyDigitsCNJ(value);
        }
        return String(value || '').replace(/\D+/g, '');
    }

    function setClasseFieldReadOnly(readOnly) {
        var classeInput = document.getElementById('audiencia-classe');
        if (!classeInput) return;
        if (readOnly) {
            classeInput.setAttribute('readonly', 'readonly');
            classeInput.classList.add('readonly');
        } else {
            classeInput.removeAttribute('readonly');
            classeInput.classList.remove('readonly');
        }
    }

    function loadClasseByProcesso() {
        var processoInput = document.getElementById('audiencia-processo');
        var classeInput = document.getElementById('audiencia-classe');
        if (!processoInput || !classeInput) return Promise.resolve(null);

        var digits = onlyDigits(processoInput.value);
        if (digits.length !== 20) {
            classeInput.value = '';
            setClasseFieldReadOnly(false);
            return Promise.resolve(null);
        }

        var numeroFormatado = formatCNJ(digits);
        processoInput.value = numeroFormatado;

        return fetch('/api/consulta-processos/list.php?numero=' + encodeURIComponent(numeroFormatado) + '&items_per_page=10&page=1', {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                var data = (res.j && Array.isArray(res.j.data)) ? res.j.data : [];
                if (res.ok && data.length > 0) {
                    var classeNome = String(data[0].classe_nome || '').trim();
                    classeInput.value = classeNome;
                    setClasseFieldReadOnly(classeNome !== '');
                    return data[0];
                }
                classeInput.value = '';
                setClasseFieldReadOnly(false);
                return null;
            })
            .catch(function () {
                classeInput.value = '';
                setClasseFieldReadOnly(false);
                return null;
            });
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
        var hora = requiredValue('audiencia-hora', 'Hora');
        if (!hora) return;
        var sala = requiredValue('audiencia-sala', 'Localizacao');
        if (!sala) return;
        var status = String(document.getElementById('audiencia-status')?.value || 'designada').trim();
        var classeDescricao = String(document.getElementById('audiencia-classe')?.value || '').trim();
        if (!classeDescricao) {
            notify('error', 'Validacao', 'Informe: Classe.');
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

    document.getElementById('audiencia-processo')?.addEventListener('blur', function () {
        if (typeof window.normalizeAndValidateCNJValue === 'function') {
            var result = window.normalizeAndValidateCNJValue(this.value, { toastOnInvalid: true });
            if (result.empty || !result.ok) {
                this.value = '';
                var classeInputInvalid = document.getElementById('audiencia-classe');
                if (classeInputInvalid) {
                    classeInputInvalid.value = '';
                    setClasseFieldReadOnly(false);
                }
                return;
            }
            this.value = result.formatted;
        }

        loadClasseByProcesso().then(function (row) {
            if (row) return;
            var digits = onlyDigits(document.getElementById('audiencia-processo')?.value || '');
            if (digits.length === 20) {
                notify('info', 'Informação', 'Processo não encontrado. Informe a classe para cadastrar o processo.');
            }
        });
    });
})();
