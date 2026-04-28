(function () {
    var pageTools = window.App && App.Page ? App.Page : null;

    function onlyDigits(value) {
        if (typeof window.onlyDigitsCNJ === 'function') {
            return window.onlyDigitsCNJ(value);
        }
        return String(value || '').replace(/\D+/g, '');
    }

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
        var catalog = window.App && window.App.ToastCatalog ? window.App.ToastCatalog : null;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function queuePostReloadToastByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage, storageKey) {
        var targetKey = storageKey || 'salapauta.postReloadToast';
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

    function requiredValue(id, label) {
        var el = document.getElementById(id);
        var value = String(el && el.value ? el.value : '').trim();
        if (!value) {
            notifyByKey('audiencias.validation.required.field', { campo: label }, 'error', 'Validacao', 'Informe: ' + label + '.');
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
                notifyByKey('audiencias.date.past.not.allowed', {}, 'info', 'Data', 'Datas anteriores a hoje não são permitidas.');
            }
        }
        dataEl.addEventListener('change', clampData);
        dataEl.addEventListener('blur', clampData);
    }

    setupDataMinimaAudiencia();

    function readGradeFromWindow() {
        var g = window.AUDIENCIA_HORARIOS_GRADE;
        return Array.isArray(g) ? g : [];
    }

    function fillHoraSelectCreate(available) {
        var sel = document.getElementById('audiencia-hora');
        if (!sel) return;
        var preserved = String(sel.value || '').trim();
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
        if (preserved && (available || []).indexOf(preserved) >= 0) {
            sel.value = preserved;
        }
    }

    function refreshHoraDisponiveisCreate() {
        var dataEl = document.getElementById('audiencia-data');
        var d = String(dataEl && dataEl.value || '').trim();
        var grade = readGradeFromWindow();
        if (!d) {
            fillHoraSelectCreate(grade);
            return;
        }
        fetch('/api/sala-audiencias-pauta/occupied-slots.php?data=' + encodeURIComponent(d), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !Array.isArray(res.j.available)) {
                    notifyByKey('audiencias.schedule.load.error', {}, 'error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao carregar horários.');
                    fillHoraSelectCreate(grade);
                    return;
                }
                fillHoraSelectCreate(res.j.available);
                if (res.j.available.length === 0) {
                    notifyByKey('audiencias.schedule.unavailable.info', {}, 'info', 'Horários', 'Não há horários livres nesta data.');
                }
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                fillHoraSelectCreate(grade);
            });
    }

    document.getElementById('audiencia-data')?.addEventListener('change', refreshHoraDisponiveisCreate);
    document.getElementById('audiencia-data')?.addEventListener('blur', refreshHoraDisponiveisCreate);
    refreshHoraDisponiveisCreate();

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
            notifyByKey('audiencias.validation.process.invalid', {}, 'error', 'Validacao', 'Número do processo inválido. Informe no padrão CNJ.');
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
            notifyByKey('audiencias.validation.date.before.today', {}, 'error', 'Validacao', 'A data da audiência não pode ser anterior a hoje.');
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
            notifyByKey('audiencias.validation.class.required', {}, 'error', 'Validacao', 'Informe: Classe (aguarde o DataJud finalizar o preenchimento automático).');
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
                    queuePostReloadToastByKey(
                        'audiencias.pauta.create.success',
                        {},
                        'success',
                        'Sucesso',
                        'Audiência cadastrada.',
                        'salapauta.postReloadToast'
                    );
                    window.location.href = '/frame.php?page=salapauta';
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cadastrar audiência.');
                }
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
            });
    });
})();
