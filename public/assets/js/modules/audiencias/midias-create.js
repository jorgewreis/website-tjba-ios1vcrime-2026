(function () {
    var pageTools = window.App && window.App.Page ? window.App.Page : null;
    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
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

    function byId(id) {
        return document.getElementById(id);
    }

    var processoInput = byId('audiencia-processo');
    var classeInput = byId('audiencia-classe');
    var classeCodigo = byId('hidden-classe-codigo');
    var classeDescricao = byId('hidden-classe-descricao');
    var dataInput = byId('midias-data-audiencia');
    var pautaSelect = byId('midias-pauta-id');
    var qtdInput = byId('midias-qtd');
    var btnCadastrar = byId('btn-midias-cadastrar');
    function setCadastrarState(label, disabled) {
        if (!btnCadastrar) return;
        btnCadastrar.textContent = label;
        btnCadastrar.disabled = disabled === true;
    }
    setCadastrarState('Novo cadastro', false);
    processoInput && processoInput.addEventListener('input', function () { setCadastrarState('Aguarde...', true); });
    window.addEventListener('cnjInvalid', function (event) {
        var id = event && event.detail ? event.detail.inputId : '';
        if (!id || id === 'audiencia-processo') setCadastrarState('Erro', true);
    });
    window.addEventListener('datajudClasseResolved', function (event) {
        var detail = event && event.detail ? event.detail : {};
        if (!detail.processoInputId || detail.processoInputId === 'audiencia-processo') {
            setCadastrarState('Cadastrar', false);
        }
    });
    var btnVoltar = byId('midias-create-voltar');

    var alocCreate = (window.App && window.App.CartorioAlocacaoDirecionadaCreate)
        ? window.App.CartorioAlocacaoDirecionadaCreate.init('midias')
        : { getIds: function () { return []; } };

    if (btnVoltar) {
        btnVoltar.addEventListener('click', function () {
            window.location.href = '/frame.php?page=salamidias';
        });
    }

    function normalizeProcesso(raw) {
        if (typeof window.normalizeAndValidateCNJValue === 'function') {
            var res = window.normalizeAndValidateCNJValue(raw, { toastOnInvalid: false });
            if (res && res.ok) {
                return String(res.formatted || raw || '').trim();
            }
        }
        return String(raw || '').trim();
    }

    function loadPautas() {
        var dataAud = String(dataInput && dataInput.value ? dataInput.value : '').trim();
        if (!dataAud) {
            pautaSelect.innerHTML = '<option value="">Selecione a audiência</option>';
            return;
        }
        var processo = normalizeProcesso(processoInput ? processoInput.value : '');
        var url = '/api/sala-midias/pautas-por-data.php?data_audiencia=' + encodeURIComponent(dataAud);
        if (processo !== '') {
            url += '&numero_processo=' + encodeURIComponent(processo);
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao carregar audiências.');
                    return;
                }
                var rows = Array.isArray(res.j.rows) ? res.j.rows : [];
                pautaSelect.innerHTML = '<option value="">Selecione a audiência</option>';
                rows.forEach(function (row) {
                    var opt = document.createElement('option');
                    opt.value = String(row.id_pauta || '');
                    opt.textContent = String(row.hora_audiencia || '').slice(0, 5) + ' - ' + String(row.tipo_audiencia || '');
                    opt.setAttribute('data-tipo-audiencia', String(row.tipo_audiencia || ''));
                    opt.setAttribute('data-processo', String(row.numero_processo || ''));
                    opt.setAttribute('data-classe', String(row.classe_nome || ''));
                    pautaSelect.appendChild(opt);
                });
                if (rows.length === 0) {
                    notifyByKey(
                        'audiencias.midias.schedule.empty.info',
                        {},
                        'info',
                        'Sem audiências',
                        'Não há audiências finalizadas para os filtros informados.'
                    );
                }
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão ao carregar audiências.');
            });
    }

    function preloadUltimaAudienciaPorProcesso() {
        var processo = normalizeProcesso(processoInput ? processoInput.value : '');
        if (!processo) {
            return;
        }
        fetch('/api/sala-midias/pauta-ultima-por-processo.php?numero_processo=' + encodeURIComponent(processo), {
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j) {
                    return;
                }
                var row = res.j.row;
                if (!row || !row.id_pauta) {
                    return;
                }
                var dataAud = String(row.data_audiencia || '').trim();
                if (dataInput && dataAud) {
                    dataInput.value = dataAud;
                }
                if (classeInput) {
                    classeInput.value = String(row.classe_nome || classeInput.value || '').trim();
                }
                if (classeDescricao) {
                    classeDescricao.value = String(row.classe_nome || classeDescricao.value || '').trim();
                }
                loadPautas();
                window.setTimeout(function () {
                    var options = pautaSelect ? pautaSelect.options : [];
                    for (var i = 0; i < options.length; i += 1) {
                        if (String(options[i].value || '') === String(row.id_pauta)) {
                            pautaSelect.selectedIndex = i;
                            pautaSelect.dispatchEvent(new Event('change'));
                            break;
                        }
                    }
                }, 120);
            })
            .catch(function () {
                // sem toast para não poluir UX a cada blur
            });
    }

    dataInput && dataInput.addEventListener('change', loadPautas);
    processoInput && processoInput.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter') {
            return;
        }
        event.preventDefault();
        preloadUltimaAudienciaPorProcesso();
    });
    processoInput && processoInput.addEventListener('blur', function () {
        preloadUltimaAudienciaPorProcesso();
        loadPautas();
    });
    pautaSelect && pautaSelect.addEventListener('change', function () {
        var opt = pautaSelect.options[pautaSelect.selectedIndex];
        if (!opt || !opt.value) {
            return;
        }
        var proc = String(opt.getAttribute('data-processo') || '');
        var cls = String(opt.getAttribute('data-classe') || '');
        if (processoInput && proc) {
            processoInput.value = proc;
        }
        if (classeInput && cls) {
            classeInput.value = cls;
        }
        if (classeDescricao && cls) {
            classeDescricao.value = cls;
        }
    });

    btnCadastrar && btnCadastrar.addEventListener('click', function () {
        setCadastrarState('Aguarde...', true);
        var processo = normalizeProcesso(processoInput ? processoInput.value : '');
        var classe = String((classeDescricao && classeDescricao.value) || (classeInput && classeInput.value) || '').trim();
        var dataAud = String(dataInput && dataInput.value ? dataInput.value : '').trim();
        var pautaId = String(pautaSelect && pautaSelect.value ? pautaSelect.value : '').trim();
        var selectedOption = pautaSelect && pautaSelect.options ? pautaSelect.options[pautaSelect.selectedIndex] : null;
        var tipoAud = String(selectedOption && selectedOption.getAttribute('data-tipo-audiencia') ? selectedOption.getAttribute('data-tipo-audiencia') : '').trim();
        var qtdMidias = parseInt(String(qtdInput && qtdInput.value ? qtdInput.value : '1'), 10);
        var maxDate = String(dataInput && dataInput.getAttribute('max') ? dataInput.getAttribute('max') : '').trim();

        if (!processo || !classe || !dataAud || !pautaId || !tipoAud) {
            notifyByKey(
                'audiencias.midias.create.required.error',
                {},
                'error',
                'Validação',
                'Preencha processo, classe, data e audiência finalizada.'
            );
            setCadastrarState('Erro', true);
            return;
        }
        if (maxDate && dataAud > maxDate) {
            notifyByKey(
                'audiencias.midias.create.date.future.error',
                {},
                'error',
                'Validação',
                'A data da audiência não pode ser futura.'
            );
            setCadastrarState('Erro', true);
            return;
        }
        if (!qtdMidias || qtdMidias < 1) {
            qtdMidias = 1;
        }

        fetch('/api/sala-midias/create.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                numero_processo: processo,
                classe_processo: classe,
                classe_codigo: String(classeCodigo && classeCodigo.value ? classeCodigo.value : '').trim(),
                data_audiencia: dataAud,
                pauta_id: pautaId,
                tipo_audiencia: tipoAud,
                qtd_midias: qtdMidias,
                alocar_usuario_ids: alocCreate.getIds()
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !res.j.id) {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cadastrar tarefa.');
                    setCadastrarState('Novo cadastro', false);
                    return;
                }
                notifyByKey(
                    'audiencias.midias.task.create.success',
                    {},
                    'success',
                    'Sucesso',
                    'Tarefa de mídia cadastrada com sucesso.'
                );
                setCadastrarState('Novo cadastro', false);
                window.setTimeout(function () {
                    window.location.href = '/frame.php?page=salamidias';
                }, 500);
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão ao cadastrar tarefa.');
                setCadastrarState('Novo cadastro', false);
            });
    });
})();
