(function () {
    'use strict';

    var wrap = document.getElementById('prioridades-processuais-wrap');
    if (!wrap) {
        return;
    }

    var processoId = String(window.PAUTA_PROCESSO_ID || window.PROCESSO_PRIORIDADES_PROCESSO_ID || '').trim();
    if (!processoId) {
        return;
    }

    var selectEl = document.getElementById('prioridade-catalog-select');
    var btnAdd = document.getElementById('btn-prioridade-adicionar');
    var listEl = document.getElementById('prioridades-processuais-list');
    if (!selectEl || !btnAdd || !listEl) {
        return;
    }

    var pendingConfirmations = {};
    var state = normalizeInitial(window.PAUTA_PRIORIDADES_INITIAL || window.PROCESSO_PRIORIDADES_INITIAL);

    function normalizeInitial(s) {
        if (!s || typeof s !== 'object') {
            return s;
        }
        var out = Object.assign({}, s);
        if (Array.isArray(out.prioridades) && out.prioridades.length > 0 && typeof out.prioridades[0] === 'string') {
            out.prioridades = out.prioridades.map(function (label) {
                return { codigo: label, label: label, origem: '' };
            });
        }
        if (Array.isArray(out.catalogo) && out.catalogo.length > 0 && typeof out.catalogo[0] === 'string') {
            out.catalogo = out.catalogo.map(function (label) {
                return { codigo: label, label: label };
            });
        }
        return out;
    }

    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function requireSecondClick(key, message) {
        var now = Date.now();
        var validUntil = Number(pendingConfirmations[key] || 0);
        if (validUntil > now) {
            delete pendingConfirmations[key];
            return true;
        }
        pendingConfirmations[key] = now + 5000;
        notify('info', 'Confirmacao', message);
        return false;
    }

    function codigosBloqueados() {
        if (!state || !Array.isArray(state.codigos_bloqueados_remocao)) {
            return [];
        }
        return state.codigos_bloqueados_remocao;
    }

    function isLockedCodigo(codigo) {
        return codigosBloqueados().indexOf(String(codigo || '')) >= 0;
    }

    function hintForCodigo(codigo) {
        var c = String(codigo || '');
        if (state.has_parte_menor && c === state.codigo_bloqueado_menor) {
            return '*automática (parte menor)';
        }
        if (state.has_reu_preso && c === state.codigo_bloqueado_reu_preso) {
            return '*automática (réu preso)';
        }
        return '';
    }

    function populateSelect() {
        if (!state) {
            return;
        }
        var current = {};
        (state.prioridades || []).forEach(function (x) {
            if (x && x.codigo) {
                current[String(x.codigo)] = true;
            }
        });
        selectEl.innerHTML = '<option value="" selected disabled>Selecione...</option>';
        catalogoLista().forEach(function (item) {
            var cod = String(item.codigo || '');
            var lab = String(item.label || cod);
            if (!cod || current[cod]) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = cod;
            opt.textContent = lab;
            selectEl.appendChild(opt);
        });
    }

    function catalogoLista() {
        if (!state || !Array.isArray(state.catalogo)) {
            return [];
        }
        return state.catalogo.slice();
    }

    function renderList() {
        listEl.innerHTML = '';
        if (!state || !Array.isArray(state.prioridades) || state.prioridades.length === 0) {
            var empty = document.createElement('span');
            empty.className = 'minor-info';
            empty.textContent = 'Nenhuma prioridade registrada para este processo.';
            listEl.appendChild(empty);
            return;
        }

        var sorted = state.prioridades.slice().sort(function (a, b) {
            return String(a.label || a.codigo).localeCompare(String(b.label || b.codigo), 'pt-BR');
        });

        sorted.forEach(function (p) {
            var codigo = String(p.codigo || '');
            var label = String(p.label || codigo);
            var row = document.createElement('div');
            row.className = 'display-flex row row-full align-center justify-between padding-y-xs';
            row.style.borderBottom = '1px solid rgba(0,0,0,.06)';

            var left = document.createElement('div');
            left.className = 'display-flex row row-fit align-center gap-y-sm flex-wrap';
            var badge = document.createElement('span');
            badge.className = 'badge badge-primary';
            badge.textContent = label;
            left.appendChild(badge);
            var hintText = hintForCodigo(codigo);
            if (hintText) {
                var hint = document.createElement('span');
                hint.className = 'label-hint-automatizado';
                hint.textContent = hintText;
                left.appendChild(hint);
            }

            row.appendChild(left);

            if (!isLockedCodigo(codigo)) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-danger-outline height-sm';
                btn.setAttribute('data-prioridade-codigo-rem', codigo);
                btn.setAttribute('title', 'Remover prioridade');
                btn.textContent = 'Remover';
                row.appendChild(btn);
            }

            listEl.appendChild(row);
        });
    }

    function applyState(next) {
        state = normalizeInitial(next);
        populateSelect();
        renderList();
    }

    function fetchState() {
        return fetch('/api/processos/prioridades-list.php?processo_id=' + encodeURIComponent(processoId), {
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, j: j };
                });
            })
            .then(function (res) {
                if (res.ok && res.j && res.j.success && res.j.state) {
                    applyState(res.j.state);
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível carregar prioridades.');
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão ao carregar prioridades.');
            });
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then(function (r) {
            return r.json().then(function (j) {
                return { ok: r.ok, j: j };
            });
        });
    }

    btnAdd.addEventListener('click', function () {
        var val = String(selectEl.value || '').trim();
        if (!val) {
            notify('error', 'Validacao', 'Selecione uma prioridade no catálogo.');
            return;
        }
        postJson('/api/processos/prioridades-add.php', {
            processo_id: processoId,
            codigo: val,
        })
            .then(function (res) {
                if (res.ok && res.j && res.j.success && res.j.state) {
                    applyState(res.j.state);
                    notify('success', 'Sucesso', 'Prioridade registrada.');
                    selectEl.selectedIndex = 0;
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível adicionar.');
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
            });
    });

    listEl.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-prioridade-codigo-rem]') : null;
        if (!btn) {
            return;
        }
        var codigo = String(btn.getAttribute('data-prioridade-codigo-rem') || '').trim();
        if (!codigo) {
            return;
        }
        var ck = 'prioridade-rem-' + processoId + '-' + codigo;
        if (!requireSecondClick(ck, 'Clique novamente em Remover em ate 5 segundos para confirmar.')) {
            return;
        }
        postJson('/api/processos/prioridades-remove.php', {
            processo_id: processoId,
            codigo: codigo,
        })
            .then(function (res) {
                if (res.ok && res.j && res.j.success && res.j.state) {
                    applyState(res.j.state);
                    notify('success', 'Sucesso', 'Prioridade removida.');
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível remover.');
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
            });
    });

    if (state && state.prioridades) {
        applyState(state);
    } else {
        fetchState();
    }
})();
