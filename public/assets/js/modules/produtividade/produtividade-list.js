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
        if (!input || !hidden || !list) {
            return;
        }

        var timer = null;
        function render(rows) {
            list.innerHTML = '';
            if (!rows || rows.length === 0) {
                var empty = document.createElement('button');
                empty.type = 'button';
                empty.className = 'btn btn-secondary-outline height-md';
                empty.textContent = 'Nenhuma tarefa encontrada';
                empty.disabled = true;
                list.appendChild(empty);
                return;
            }
            rows.forEach(function (row) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-secondary-outline height-md';
                btn.style.width = '100%';
                btn.style.textAlign = 'left';
                btn.textContent = row.label + ' | ' + row.pontos_por_ato + ' x ' + row.pontos_multiplicador;
                btn.addEventListener('click', function () {
                    hidden.value = row.id || '';
                    input.value = row.label || '';
                    list.innerHTML = '';
                    syncCreateButtonState();
                });
                list.appendChild(btn);
            });
        }

        input.addEventListener('input', function () {
            hidden.value = '';
            syncCreateButtonState();
            var q = input.value.trim();
            if (timer) {
                clearTimeout(timer);
            }
            if (q.length < 2) {
                list.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch('/api/produtividade/regras/search.php?q=' + encodeURIComponent(q) + '&limit=8', {
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
                            return;
                        }
                        render((result.data && result.data.rows) ? result.data.rows : []);
                    })
                    .catch(function () {
                        list.innerHTML = '';
                    });
            }, 300);
        });
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
        var perPage = document.getElementById('items_per_page');
        if (perPage) {
            perPage.addEventListener('change', function () {
                form.submit();
            });
        }
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

    setupCreateButtonGate();
    setupRuleSearch();
    setupDatajudProcessModel();
    setupCnjValidation();
    setupCreateManual();
    setupAnulacao();
    setupFilterSubmit();
})();

