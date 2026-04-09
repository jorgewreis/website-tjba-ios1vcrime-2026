(function () {
    'use strict';

    function syncCreateButtonState() {
        if (typeof window.__produtividadeSyncCreateButtonState === 'function') {
            window.__produtividadeSyncCreateButtonState();
        }
    }

    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
            return;
        }
        if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
            window.App.Toast.show({ type: type, title: title, message: message });
        }
    }

    function setupRuleSearch() {
        var input = document.getElementById('regra_busca');
        var hidden = document.getElementById('regra_id');
        var list = document.getElementById('regra_sugestoes');
        var select = document.getElementById('regra_sugestoes_select');
        if (!input || !hidden || !list || !select) {
            return;
        }

        function clearSelect() {
            select.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecione uma opção da lista';
            select.appendChild(placeholder);
            select.value = '';
            select.style.display = 'none';
        }

        var timer = null;
        function render(rows) {
            list.innerHTML = '';
            clearSelect();
            if (!rows || rows.length === 0) {
                var empty = document.createElement('span');
                empty.className = 'minor-info';
                empty.textContent = 'Nenhuma regra ativa encontrada';
                list.appendChild(empty);
                return;
            }
            rows.forEach(function (row) {
                var option = document.createElement('option');
                option.value = row.id || '';
                option.textContent = row.label || '';
                select.appendChild(option);
            });
            select.style.display = '';
        }

        select.addEventListener('change', function () {
            var selectedOption = select.options[select.selectedIndex];
            var selectedId = selectedOption ? (selectedOption.value || '') : '';
            hidden.value = selectedId;
            if (selectedId === '') {
                syncCreateButtonState();
                return;
            }
            input.value = selectedOption.textContent || input.value;
            list.innerHTML = '';
            syncCreateButtonState();
        });

        input.addEventListener('input', function () {
            hidden.value = '';
            syncCreateButtonState();
            var q = input.value.trim();
            if (timer) {
                clearTimeout(timer);
            }
            if (q.length < 2) {
                list.innerHTML = '';
                clearSelect();
                return;
            }
            timer = setTimeout(function () {
                fetch('/api/produtividade/regras/search.php?q=' + encodeURIComponent(q) + '&limit=10', {
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            notify('error', 'Erro', (result.data && result.data.error) ? result.data.error : 'Falha ao buscar regras ativas.');
                            list.innerHTML = '';
                            clearSelect();
                            return;
                        }
                        render((result.data && result.data.rows) ? result.data.rows : []);
                    })
                    .catch(function () {
                        list.innerHTML = '';
                        clearSelect();
                    });
            }, 300);
        });

        clearSelect();
    }

    function setupDatajudProcessModel() {
        var processo = document.getElementById('filter-processo');
        var classe = document.getElementById('filter-classe');
        if (!processo || !classe) {
            return;
        }
        if (window.App && App.CartorioCreateBootstrap && typeof App.CartorioCreateBootstrap.bindClasseResetOnShortProcess === 'function') {
            App.CartorioCreateBootstrap.bindClasseResetOnShortProcess({
                processInputId: 'filter-processo',
                classeFieldId: 'filter-classe',
                codigoFieldId: 'hidden-classe-codigo',
                descricaoFieldId: 'hidden-classe-descricao'
            });
        }
    }

    function setupCnjValidation() {
        var processo = document.getElementById('filter-processo');
        if (!processo) {
            return;
        }

        processo.addEventListener('blur', function () {
            var valor = (processo.value || '').trim();
            if (valor === '') {
                return;
            }

            // Reusa o validador padrão do projeto (CNJ + formatação).
            if (window.App && App.CartorioCreateHelpers && typeof App.CartorioCreateHelpers.normalizeProcessValue === 'function') {
                var normalized = App.CartorioCreateHelpers.normalizeProcessValue(valor, {
                    inputId: 'filter-processo',
                    feedback: function (message, type) {
                        notify(type || 'error', 'Erro', message);
                    },
                    requiredMessage: 'Informe o número do processo.',
                    invalidMessage: 'O número do processo está incompleto ou inválido (20 dígitos).'
                });
                if (!normalized.ok) {
                    processo.value = '';
                    return;
                }
                processo.value = normalized.value;
                return;
            }

            if (typeof window.normalizeAndValidateCNJValue === 'function') {
                var result = window.normalizeAndValidateCNJValue(valor, { toastOnInvalid: true });
                if (!result.ok) {
                    processo.value = '';
                    return;
                }
                processo.value = result.formatted;
            }
        });
    }

    function setupCreateManual() {
        var btn = document.getElementById('salvar-lancamento-manual');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            var processoInput = document.getElementById('filter-processo');
            if (processoInput && typeof processoInput.blur === 'function') {
                processoInput.blur();
            }
            var regraId = (document.getElementById('regra_id') || {}).value || '';
            var quantidade = (document.getElementById('quantidade') || {}).value || '';
            var numeroProcesso = (document.getElementById('filter-processo') || {}).value || '';
            var classeProcesso = (document.getElementById('filter-classe') || {}).value || '';
            var observacoes = (document.getElementById('observacoes') || {}).value || '';
            var classeDescricao = (document.getElementById('hidden-classe-descricao') || {}).value || '';

            if (regraId.trim() === '') {
                notify('error', 'Erro', 'Selecione uma regra válida.');
                return;
            }

            if (classeProcesso === 'Aguarde...') {
                notify('error', 'Erro', 'Aguarde a consulta ao DataJud terminar antes de cadastrar.');
                return;
            }

            if (numeroProcesso.trim() !== '' && window.App && App.CartorioCreateHelpers && typeof App.CartorioCreateHelpers.normalizeProcessValue === 'function') {
                var normalized = App.CartorioCreateHelpers.normalizeProcessValue(numeroProcesso, {
                    inputId: 'filter-processo',
                    feedback: function (message, type) {
                        notify(type || 'error', 'Erro', message);
                    },
                    requiredMessage: 'Informe o número do processo.',
                    invalidMessage: 'O número do processo está incompleto ou inválido (20 dígitos).'
                });
                if (!normalized.ok) {
                    return;
                }
                numeroProcesso = normalized.value;
            }

            btn.disabled = true;
            fetch('/api/produtividade/lancamentos/create-manual.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    regra_id: regraId,
                    quantidade: quantidade,
                    numero_processo: numeroProcesso,
                    classe_processo: (classeDescricao || classeProcesso || '').trim(),
                    observacoes: observacoes
                }),
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Falha ao salvar lançamento.');
                        return;
                    }
                    notify('success', '', 'Lançamento manual registrado com sucesso.');
                    window.location.reload();
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão ao registrar lançamento.');
                })
                .finally(function () {
                    syncCreateButtonState();
                });
        });
    }

    function setupAnulacao() {
        var buttons = document.querySelectorAll('[data-anular-id]');
        if (!buttons.length) {
            return;
        }

        var pending = {};
        function requireSecondClick(key, message) {
            var now = Date.now();
            if ((pending[key] || 0) > now) {
                delete pending[key];
                return true;
            }
            pending[key] = now + 5000;
            notify('info', 'Confirmação', message);
            return false;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var lancamentoId = btn.getAttribute('data-anular-id') || '';
                if (!lancamentoId) {
                    return;
                }

                if (!requireSecondClick(lancamentoId, 'Clique novamente em até 5 segundos para confirmar a anulação.')) {
                    return;
                }

                var motivo = window.prompt('Informe o motivo da anulação:');
                if (!motivo || motivo.trim() === '') {
                    notify('error', 'Erro', 'Motivo da anulação é obrigatório.');
                    return;
                }

                fetch('/api/produtividade/lancamentos/anular.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: lancamentoId, motivo: motivo.trim() }),
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Falha ao anular lançamento.');
                            return;
                        }
                        notify('success', '', 'Lançamento anulado com sucesso.');
                        window.location.reload();
                    })
                    .catch(function () {
                        notify('error', 'Erro', 'Erro de conexão ao anular lançamento.');
                    });
            });
        });
    }

    function setupFilterSubmit() {
        var form = document.getElementById('filtro-produtividade-form');
        if (!form) {
            return;
        }
        var dataInicio = document.getElementById('data_inicio');
        var dataFim = document.getElementById('data_fim');

        function parseDate(value) {
            var parts = String(value || '').split('-');
            if (parts.length !== 3) {
                return null;
            }
            var y = parseInt(parts[0], 10);
            var m = parseInt(parts[1], 10);
            var d = parseInt(parts[2], 10);
            if (isNaN(y) || isNaN(m) || isNaN(d)) {
                return null;
            }
            return new Date(y, m - 1, d);
        }

        function isValidRange() {
            if (!dataInicio || !dataFim) {
                return true;
            }
            var ini = (dataInicio.value || '').trim();
            var fim = (dataFim.value || '').trim();
            if (ini === '' || fim === '') {
                return true;
            }

            var dtIni = parseDate(ini);
            var dtFim = parseDate(fim);
            if (!dtIni || !dtFim) {
                notify('error', 'Erro', 'Período inválido. Use datas válidas para filtrar.');
                return false;
            }

            if (dtIni.getTime() > dtFim.getTime()) {
                notify('error', 'Erro', 'A data inicial não pode ser maior que a data final.');
                return false;
            }

            var diffMs = dtFim.getTime() - dtIni.getTime();
            var diffDays = Math.floor(diffMs / 86400000);
            if (diffDays > 60) {
                notify('error', 'Erro', 'O intervalo entre data inicial e data final não pode ultrapassar 60 dias.');
                return false;
            }
            return true;
        }

        form.addEventListener('submit', function (event) {
            if (!isValidRange()) {
                event.preventDefault();
            }
        });
    }

    function setupCreateButtonGate() {
        var btn = document.getElementById('salvar-lancamento-manual');
        var regraIdInput = document.getElementById('regra_id');
        var processoInput = document.getElementById('filter-processo');
        var quantidadeInput = document.getElementById('quantidade');
        if (!btn || !regraIdInput || !processoInput || !quantidadeInput) {
            return;
        }

        window.__produtividadeSyncCreateButtonState = function () {
            var hasRegra = (regraIdInput.value || '').trim() !== '';
            var hasProcesso = (processoInput.value || '').trim() !== '';
            var qtd = parseInt(String(quantidadeInput.value || '0'), 10);
            var hasQuantidade = !isNaN(qtd) && qtd > 0;

            btn.disabled = !(hasRegra && hasProcesso && hasQuantidade);
        };

        processoInput.addEventListener('input', syncCreateButtonState);
        processoInput.addEventListener('blur', syncCreateButtonState);
        quantidadeInput.addEventListener('input', syncCreateButtonState);
        quantidadeInput.addEventListener('change', syncCreateButtonState);

        syncCreateButtonState();
    }

    function setupRanking() {
        var section = document.getElementById('ranking-section');
        var loading = document.getElementById('ranking-loading');
        var content = document.getElementById('ranking-content');
        if (!section || !loading || !content) {
            return;
        }

        var url = section.getAttribute('data-ranking-url') || '';
        if (!url) {
            loading.textContent = 'Ranking indisponível.';
            return;
        }

        function renderSetor(setor) {
            var wrapper = document.createElement('div');
            wrapper.className = 'display-flex col col-full border-bottom padding-y-sm';

            var title = document.createElement('h3');
            title.textContent = (setor && setor.setor_nome ? setor.setor_nome : 'Sem setor');
            wrapper.appendChild(title);

            var rows = (setor && Array.isArray(setor.rows)) ? setor.rows : [];
            if (rows.length === 0) {
                var empty = document.createElement('span');
                empty.className = 'minor-info';
                empty.textContent = 'Sem dados para este setor no mês atual.';
                wrapper.appendChild(empty);
                return wrapper;
            }

            rows.forEach(function (item) {
                var line = document.createElement('div');
                line.className = 'display-flex row row-full justify-between padding-y-sm';

                var left = document.createElement('span');
                left.className = 'major-info';
                left.textContent = '#' + String(item.posicao || '-') + ' - ' + (item.usuario_nome || 'Usuário');

                var right = document.createElement('span');
                right.className = 'major-info';
                right.textContent = String(Number(item.pontos_total || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })) + ' pts';

                line.appendChild(left);
                line.appendChild(right);
                wrapper.appendChild(line);
            });

            return wrapper;
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    notify('error', 'Erro', result.data && result.data.error ? result.data.error : 'Falha ao carregar ranking.');
                    loading.textContent = 'Não foi possível carregar o ranking.';
                    return;
                }

                var setores = result.data && Array.isArray(result.data.setores) ? result.data.setores : [];
                content.innerHTML = '';
                if (setores.length === 0) {
                    loading.textContent = 'Sem dados de ranking no mês atual (Cartório/Gabinete).';
                    return;
                }

                setores.forEach(function (setor) {
                    content.appendChild(renderSetor(setor));
                });
                loading.style.display = 'none';
                content.style.display = '';
            })
            .catch(function () {
                loading.textContent = 'Erro de conexão ao carregar ranking.';
            });
    }

    setupCreateButtonGate();
    setupRuleSearch();
    setupDatajudProcessModel();
    setupCnjValidation();
    setupCreateManual();
    setupAnulacao();
    setupFilterSubmit();
    setupRanking();
})();

