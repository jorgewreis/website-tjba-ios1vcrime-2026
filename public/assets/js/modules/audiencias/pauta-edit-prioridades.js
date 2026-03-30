(function () {
    'use strict';

    var wrap = document.getElementById('prioridades-processuais-wrap');
    if (!wrap) {
        return;
    }

    var idPauta = String(window.PAUTA_EDIT_ID || '').trim();
    if (!idPauta) {
        return;
    }

    var selectEl = document.getElementById('prioridade-catalog-select');
    var btnAdd = document.getElementById('btn-prioridade-adicionar');
    var listEl = document.getElementById('prioridades-processuais-list');
    if (!selectEl || !btnAdd || !listEl) {
        return;
    }

    var pendingConfirmations = {};
    var state = window.PAUTA_PRIORIDADES_INITIAL;

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

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isLocked(p) {
        if (!state) {
            return false;
        }
        if (p === state.prioridade_bloqueada_se_menor && state.has_parte_menor) {
            return true;
        }
        if (p === state.prioridade_bloqueada_se_reu_preso && state.has_reu_preso) {
            return true;
        }
        return false;
    }

    function populateSelect() {
        if (!state || !catalogoLista().length) {
            return;
        }
        var current = state.prioridades || [];
        var set = {};
        current.forEach(function (x) {
            set[String(x)] = true;
        });
        selectEl.innerHTML = '<option value="" selected disabled>Selecione...</option>';
        catalogoLista().forEach(function (label) {
            if (set[label]) {
                return;
            }
            var opt = document.createElement('option');
            opt.value = label;
            opt.textContent = label;
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
            return String(a).localeCompare(String(b), 'pt-BR');
        });

        sorted.forEach(function (p) {
            var row = document.createElement('div');
            row.className = 'display-flex row row-full align-center justify-between padding-y-xs';
            row.style.borderBottom = '1px solid rgba(0,0,0,.06)';

            var left = document.createElement('div');
            left.className = 'display-flex row row-fit align-center gap-y-sm flex-wrap';
            var badge = document.createElement('span');
            badge.className = 'badge badge-primary';
            badge.textContent = p;
            left.appendChild(badge);
            if (p === state.prioridade_bloqueada_se_menor && state.has_parte_menor) {
                var hintMenor = document.createElement('span');
                hintMenor.className = 'label-hint-automatizado';
                hintMenor.textContent = '*automática (parte menor)';
                left.appendChild(hintMenor);
            } else if (p === state.prioridade_bloqueada_se_reu_preso && state.has_reu_preso) {
                var hintReu = document.createElement('span');
                hintReu.className = 'label-hint-automatizado';
                hintReu.textContent = '*automática (réu preso)';
                left.appendChild(hintReu);
            }

            row.appendChild(left);

            if (!isLocked(p)) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-danger-outline height-sm';
                btn.setAttribute('data-prioridade-rem', p);
                btn.setAttribute('title', 'Remover prioridade');
                btn.textContent = 'Remover';
                row.appendChild(btn);
            }

            listEl.appendChild(row);
        });
    }

    function applyState(next) {
        state = next;
        populateSelect();
        renderList();
    }

    function fetchState() {
        return fetch('/api/sala-audiencias-pauta/prioridades-list.php?id_pauta=' + encodeURIComponent(idPauta), {
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
        postJson('/api/sala-audiencias-pauta/prioridades-add.php', {
            id_pauta: idPauta,
            prioridade: val,
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
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-prioridade-rem]') : null;
        if (!btn) {
            return;
        }
        var p = String(btn.getAttribute('data-prioridade-rem') || '').trim();
        if (!p) {
            return;
        }
        var ck = 'prioridade-rem-' + idPauta + '-' + p;
        if (!requireSecondClick(ck, 'Clique novamente em Remover em ate 5 segundos para confirmar.')) {
            return;
        }
        postJson('/api/sala-audiencias-pauta/prioridades-remove.php', {
            id_pauta: idPauta,
            prioridade: p,
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
