(function () {
    'use strict';

    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
            return;
        }
        if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
            window.App.Toast.show({ type: type, title: title, message: message });
        }
    }

    function setupModal() {
        var modal = document.getElementById('produtividade-modal');
        var openBtn = document.getElementById('abrir-lancamento-manual');
        var closeBtns = document.querySelectorAll('[data-close-modal]');
        if (!modal || !openBtn) {
            return;
        }

        function open() {
            modal.style.display = 'flex';
        }

        function close() {
            modal.style.display = 'none';
        }

        openBtn.addEventListener('click', open);
        closeBtns.forEach(function (btn) {
            btn.addEventListener('click', close);
        });
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                close();
            }
        });
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
                empty.textContent = 'Nenhuma regra ativa encontrada';
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
                });
                list.appendChild(btn);
            });
        }

        input.addEventListener('input', function () {
            hidden.value = '';
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
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        render(data.rows || []);
                    })
                    .catch(function () {
                        list.innerHTML = '';
                    });
            }, 300);
        });
    }

    function setupClasseByProcesso() {
        var processo = document.getElementById('numero_processo');
        var classe = document.getElementById('classe_processo');
        if (!processo || !classe) {
            return;
        }

        processo.addEventListener('blur', function () {
            var digits = (processo.value || '').replace(/\D/g, '');
            if (digits.length !== 20) {
                return;
            }
            fetch('/api/datajud/search-processo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ numero: digits }),
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    var desc = '';
                    if (data && data.data && data.data.classe) {
                        desc = (data.data.classe.descricao || data.data.classe.codigo || '').trim();
                    }
                    if (desc !== '') {
                        classe.value = desc;
                    }
                })
                .catch(function () {
                    // silencioso
                });
        });
    }

    function setupCreateManual() {
        var btn = document.getElementById('salvar-lancamento-manual');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function () {
            var regraId = (document.getElementById('regra_id') || {}).value || '';
            var quantidade = (document.getElementById('quantidade') || {}).value || '';
            var numeroProcesso = (document.getElementById('numero_processo') || {}).value || '';
            var classeProcesso = (document.getElementById('classe_processo') || {}).value || '';
            var dataHoraAto = (document.getElementById('data_hora_ato') || {}).value || '';
            var observacoes = (document.getElementById('observacoes') || {}).value || '';

            if (regraId.trim() === '') {
                notify('error', 'Erro', 'Selecione uma regra válida.');
                return;
            }

            btn.disabled = true;
            fetch('/api/produtividade/lancamentos/create-manual.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    regra_id: regraId,
                    quantidade: quantidade,
                    numero_processo: numeroProcesso,
                    classe_processo: classeProcesso,
                    data_hora_ato: dataHoraAto,
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
                    btn.disabled = false;
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

    setupModal();
    setupRuleSearch();
    setupClasseByProcesso();
    setupCreateManual();
    setupAnulacao();
    setupFilterSubmit();
})();

