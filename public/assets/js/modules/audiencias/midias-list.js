(function () {
    var pageCtx = window.SALA_MIDIAS_LIST || {};
    var routePage = String(pageCtx.routePage || 'salamidias');
    var filters = pageCtx.filters || {};

    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
        }
    }

    var lockMap = {};
    function requireSecondClick(key, message) {
        var now = Date.now();
        if (lockMap[key] && lockMap[key] > now) {
            delete lockMap[key];
            return true;
        }
        lockMap[key] = now + 5000;
        notify('info', 'Confirmação', message);
        return false;
    }

    function buildListUrl(overrides) {
        var query = {
            page: routePage,
            search: String(filters.search || ''),
            status: String(filters.status || ''),
            data_audiencia: String(filters.data_audiencia || ''),
            items_per_page: parseInt(String(filters.items_per_page || 25), 10) || 25
        };
        overrides = overrides || {};
        Object.keys(overrides).forEach(function (k) {
            query[k] = overrides[k];
        });

        var params = new URLSearchParams();
        Object.keys(query).forEach(function (k) {
            var val = query[k];
            if (val !== null && val !== undefined && String(val) !== '') {
                params.set(k, String(val));
            }
        });

        return '/frame.php?' + params.toString();
    }

    function bindFiltersAndActions() {
        var searchInput = document.getElementById('search-input');
        var filterStatus = document.getElementById('filter-status');
        var filterData = document.getElementById('filter-data-audiencia');
        var itemsPerPage = document.getElementById('items-per-page');
        var novoBtn = document.getElementById('novo-cadastro-btn');
        var regrasBtn = document.getElementById('regras-btn');

        if (novoBtn) {
            novoBtn.addEventListener('click', function () {
                window.location.href = '/frame.php?page=salamidiascreate';
            });
        }
        if (regrasBtn) {
            regrasBtn.addEventListener('click', function () {
                window.location.href = '/frame.php?page=salamidiasrules';
            });
        }
        if (searchInput) {
            searchInput.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }
                event.preventDefault();
                filters.search = String(searchInput.value || '').trim();
                window.location.href = buildListUrl({ search: filters.search, page_number: 1 });
            });
            searchInput.addEventListener('blur', function () {
                var next = String(searchInput.value || '').trim();
                if (next === String(filters.search || '').trim()) {
                    return;
                }
                filters.search = next;
                window.location.href = buildListUrl({ search: next, page_number: 1 });
            });
        }
        if (filterStatus) {
            filterStatus.addEventListener('change', function () {
                var value = String(filterStatus.value || '').trim();
                filters.status = value;
                window.location.href = buildListUrl({ status: value, page_number: 1 });
            });
        }
        if (filterData) {
            filterData.addEventListener('change', function () {
                var value = String(filterData.value || '').trim();
                filters.data_audiencia = value;
                window.location.href = buildListUrl({ data_audiencia: value, page_number: 1 });
            });
        }
        if (itemsPerPage) {
            itemsPerPage.addEventListener('change', function () {
                var value = String(itemsPerPage.value || '25');
                filters.items_per_page = value;
                window.location.href = buildListUrl({ items_per_page: value, page_number: 1 });
            });
        }
    }

    function bindSubtaskToggle() {
        var radios = document.querySelectorAll('input[name="open-subtask-midias"]');
        if (!radios || radios.length === 0) {
            return;
        }
        Array.prototype.forEach.call(radios, function (radio) {
            radio.addEventListener('change', function () {
                var targetId = String(radio.getAttribute('aria-controls') || '').trim();
                Array.prototype.forEach.call(radios, function (r) {
                    var tid = String(r.getAttribute('aria-controls') || '').trim();
                    var panel = tid ? document.getElementById(tid) : null;
                    if (panel) {
                        panel.classList.add('subtask-collapsed');
                        panel.setAttribute('aria-hidden', 'true');
                    }
                    r.setAttribute('aria-expanded', 'false');
                });
                if (!targetId) {
                    return;
                }
                var target = document.getElementById(targetId);
                if (!target) {
                    return;
                }
                target.classList.remove('subtask-collapsed');
                target.setAttribute('aria-hidden', 'false');
                radio.setAttribute('aria-expanded', 'true');
            });
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-midias-concluir-subtarefa');
        if (button) {
            var subtarefaId = String(button.getAttribute('data-subtarefa-id') || '').trim();
            if (!subtarefaId) {
                return;
            }
            var row = button.closest('[data-subtarefa-id]');
            var subtarefaCodigo = String((row && row.getAttribute('data-subtarefa-codigo')) || '').trim();
            var qtdInput = row ? row.querySelector('.js-midias-qtd-midias') : null;
            var dados = {};
            if (subtarefaCodigo === 'download_midia') {
                var qtd = parseInt(String(qtdInput && qtdInput.value ? qtdInput.value : '0'), 10);
                if (!qtd || qtd < 1) {
                    notify('error', 'Validação', 'Informe uma quantidade de mídias válida para o download.');
                    return;
                }
                dados.qtd_midias = qtd;
            } else if ((subtarefaCodigo === 'sincronizacao_midia' || subtarefaCodigo === 'upload_midia') && qtdInput) {
                var qtdReadonly = parseInt(String(qtdInput.value || '0'), 10);
                if (qtdReadonly > 0) {
                    dados.qtd_midias = qtdReadonly;
                }
            }
            button.disabled = true;
            fetch('/api/sala-midias/subtarefa-status.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    subtarefa_id: subtarefaId,
                    status: 'concluida',
                    dados: dados
                })
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j || !res.j.success) {
                        button.disabled = false;
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao concluir subtarefa.');
                        return;
                    }
                    notify('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 450);
                })
                .catch(function () {
                    button.disabled = false;
                    notify('error', 'Erro', 'Erro de conexão ao concluir subtarefa.');
                });
            return;
        }

        var rm = event.target.closest('.js-desalocar');
        if (!rm) {
            return;
        }
        var alocadoId = String(rm.getAttribute('data-alocado-id') || '').trim();
        var endpoint = String(rm.getAttribute('data-endpoint') || '').trim();
        if (!alocadoId || !endpoint) {
            return;
        }
        if (!requireSecondClick('midias-desalocar-' + alocadoId, 'Clique novamente para confirmar a remoção da alocação.')) {
            return;
        }
        rm.disabled = true;
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                alocado_id: alocadoId
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !res.j.deleted) {
                    rm.disabled = false;
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao remover alocação.');
                    return;
                }
                notify('success', 'Sucesso', 'Alocação removida.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 400);
            })
            .catch(function () {
                rm.disabled = false;
                notify('error', 'Erro', 'Erro de conexão ao remover alocação.');
            });
    });

    bindFiltersAndActions();
    bindSubtaskToggle();
})();
