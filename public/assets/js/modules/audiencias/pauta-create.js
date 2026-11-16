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

    function setupLocalizacaoAudiencia() {
        var dataEl = document.getElementById('audiencia-data');
        var salaEl = document.getElementById('audiencia-sala');
        if (!salaEl) return;
        var lifesizeOption = salaEl.querySelector('option[value="lifesize"]');
        var audinOption = salaEl.querySelector('option[value="audin"]');
        var limiteLifesize = '2026-09-01';

        function syncLocalizacao() {
            var data = String(dataEl && dataEl.value || '').trim();
            var bloquearLifesize = data !== '' && data > limiteLifesize;
            if (lifesizeOption) {
                lifesizeOption.disabled = bloquearLifesize;
            }
            if (audinOption && (!salaEl.value || (bloquearLifesize && salaEl.value === 'lifesize'))) {
                salaEl.value = 'audin';
            }
        }

        if (!salaEl.value && audinOption) {
            salaEl.value = 'audin';
        }
        dataEl?.addEventListener('change', syncLocalizacao);
        dataEl?.addEventListener('blur', syncLocalizacao);
        syncLocalizacao();
    }

    setupDataMinimaAudiencia();
    setupLocalizacaoAudiencia();
    var btnCadastrar = document.getElementById('btn-audiencia-cadastrar');
    var cadastrando = false;
    var consultaClassePendente = false;
    function setCadastrarState(label, disabled) {
        if (!btnCadastrar) return;
        btnCadastrar.textContent = label;
        btnCadastrar.disabled = disabled === true;
    }
    // O ciclo DataJud não deve restaurar um snapshot sobre uma submissão em curso.
    // A disponibilidade da classe é verificada ao tentar cadastrar.
    if (btnCadastrar) btnCadastrar.setAttribute('data-allow-during-loading', 'true');
    setCadastrarState('Novo cadastro', false);
    var processoCadastro = document.getElementById('audiencia-processo');
    var processoAnterior = onlyDigits(processoCadastro && processoCadastro.value);
    processoCadastro?.addEventListener('input', function () {
        var atual = onlyDigits(processoCadastro.value);
        if (atual === processoAnterior) return;
        processoAnterior = atual;
        consultaClassePendente = false;
        // Invalida a consulta anterior antes de o validador CNJ iniciar outra.
        if (window.DataJudCycle && typeof window.DataJudCycle.cancelPrevious === 'function') {
            window.DataJudCycle.cancelPrevious({
                processoInput: processoCadastro,
                classeInput: document.getElementById('audiencia-classe'),
                statusElement: document.getElementById('audiencia-classe-status') || document.getElementById('processo-ap-info')
            });
        }
        ['audiencia-classe', 'hidden-classe-codigo', 'hidden-classe-descricao'].forEach(function (id) {
            var campo = document.getElementById(id);
            if (campo) campo.value = '';
        });
    }, true);
    window.addEventListener('cnjValidated', function (event) {
        var detail = event && event.detail ? event.detail : {};
        if (!detail.inputId || detail.inputId === 'audiencia-processo') {
            consultaClassePendente = true;
        }
    });
    window.addEventListener('datajudClasseResolved', function (event) {
        var detail = event && event.detail ? event.detail : {};
        if (!detail.processoInputId || detail.processoInputId === 'audiencia-processo') {
            consultaClassePendente = false;
        }
    });

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
        if (cadastrando) return;
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
        if (/^classe\s+n[aã]o\s+informada$/iu.test(classeDescricao)) {
            classeDescricao = '';
        }
        var classeInput = document.getElementById('audiencia-classe');
        if (consultaClassePendente || (classeInput && classeInput.getAttribute('aria-busy') === 'true')) {
            notifyByKey('audiencias.validation.class.required', {}, 'error', 'Validacao', 'Informe: Classe (aguarde o DataJud finalizar o preenchimento automático).');
            return;
        }

        cadastrando = true;
        setCadastrarState('Aguarde...', true);
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
                    setCadastrarState('Novo cadastro', false);
                    window.location.href = '/frame.php?page=salapauta';
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cadastrar audiência.');
                    cadastrando = false;
                    setCadastrarState('Novo cadastro', false);
                }
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                cadastrando = false;
                setCadastrarState('Novo cadastro', false);
            });
    });
})();
