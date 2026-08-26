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

    var pageTools = window.App && App.Page ? App.Page : null;
    var state = normalizeInitial(window.PAUTA_PRIORIDADES_INITIAL || window.PROCESSO_PRIORIDADES_INITIAL);

    function updatePrioridadeAddButtonState() {
        btnAdd.disabled = String(selectEl.value || '').trim() === '';
    }

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

    function codigosBloqueados() {
        if (!state || !Array.isArray(state.codigos_bloqueados_remocao)) {
            return [];
        }
        return state.codigos_bloqueados_remocao;
    }

    function isLockedCodigo(codigo) {
        return codigosBloqueados().indexOf(String(codigo || '')) >= 0;
    }

    /** Mesma regra de `sala-audiencias-pauta-list.php`: danger para réu preso, violência doméstica, criança/adolescente. */
    function dangerCodes() {
        return Array.isArray(window.PRIORIDADES_BADGE_DANGER_CODES)
            ? window.PRIORIDADES_BADGE_DANGER_CODES
            : ['reu_preso', 'violencia_domestica', 'medida_protetiva', 'crianca_adolescente'];
    }

    function resolveCanonicalCodigo(p) {
        var c = String(p.codigo || '').trim();
        var lab = String(p.label || '').trim();
        if (state && Array.isArray(state.catalogo)) {
            var i;
            for (i = 0; i < state.catalogo.length; i++) {
                var it = state.catalogo[i];
                if (!it) {
                    continue;
                }
                if (c && String(it.codigo || '') === c) {
                    return c;
                }
            }
            for (i = 0; i < state.catalogo.length; i++) {
                var it2 = state.catalogo[i];
                if (it2 && lab && String(it2.label || '') === lab && it2.codigo) {
                    return String(it2.codigo);
                }
            }
        }
        return c || lab;
    }

    function blackCodes() {
        return Array.isArray(window.PRIORIDADES_BADGE_BLACK_CODES)
            ? window.PRIORIDADES_BADGE_BLACK_CODES
            : ['sigiloso'];
    }

    function badgeClassForPrioridade(p) {
        var cod = resolveCanonicalCodigo(p);
        if (dangerCodes().indexOf(cod) >= 0) {
            return 'badge badge-danger';
        }
        if (blackCodes().indexOf(cod) >= 0) {
            return 'badge badge-black';
        }
        return 'badge badge-warning';
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
        var wrap = document.createElement('div');
        wrap.className = 'display-flex row row-full flex-wrap align-items-center';
        wrap.style.gap = '6px 8px';

        if (window.PAUTA_PROCESSO_SIGILOSO) {
            var sigMark = document.createElement('span');
            sigMark.className = 'badge badge-black';
            sigMark.title = 'Processo sigiloso';
            sigMark.textContent = 'S';
            wrap.appendChild(sigMark);
        }

        var hasLista = state && Array.isArray(state.prioridades) && state.prioridades.length > 0;
        if (!hasLista) {
            listEl.appendChild(wrap);
            return;
        }

        var sorted = state.prioridades.slice().sort(function (a, b) {
            return String(a.label || a.codigo).localeCompare(String(b.label || b.codigo), 'pt-BR');
        });

        sorted.forEach(function (p) {
            var codigo = String(p.codigo || '');
            var label = String(p.label || codigo);
            var badge = document.createElement('span');
            badge.className = badgeClassForPrioridade(p);
            badge.title = label;
            var badgeLabel = document.createElement('span');
            badgeLabel.textContent = label;
            badge.appendChild(badgeLabel);

            if (!isLockedCodigo(codigo)) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'badge-prioridade-remover';
                btn.setAttribute('data-prioridade-codigo-rem', codigo);
                btn.setAttribute('title', 'Remover prioridade');
                btn.setAttribute('aria-label', 'Remover prioridade');
                btn.textContent = '×';
                badge.appendChild(btn);
            }

            wrap.appendChild(badge);
        });

        listEl.appendChild(wrap);
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
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão ao carregar prioridades.');
            });
    }

    function postJson(url, body) {
        var common = window.App && window.App.RecordEditCommon ? window.App.RecordEditCommon : null;
        if (common && typeof common.postJson === 'function') {
            return common.postJson(url, body).then(function (result) {
                return { ok: !!result.ok, j: result.data || {} };
            });
        }
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
            notifyByKey('audiencias.prioridade.required.error', {}, 'error', 'Validacao', 'Selecione uma prioridade no catálogo.');
            return;
        }
        postJson('/api/processos/prioridades-add.php', {
            processo_id: processoId,
            codigo: val,
        })
            .then(function (res) {
                if (res.ok && res.j && res.j.success && res.j.state) {
                    applyState(res.j.state);
                    notifyByKey('audiencias.prioridade.add.success', {}, 'success', 'Sucesso', 'Prioridade registrada.');
                    selectEl.selectedIndex = 0;
                    updatePrioridadeAddButtonState();
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível adicionar.');
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
            });
    });

    selectEl.addEventListener('change', updatePrioridadeAddButtonState);

    listEl.addEventListener('click', function (ev) {
        var btn = ev.target && ev.target.closest ? ev.target.closest('[data-prioridade-codigo-rem]') : null;
        if (!btn) {
            return;
        }
        var codigo = String(btn.getAttribute('data-prioridade-codigo-rem') || '').trim();
        if (!codigo) {
            return;
        }
        postJson('/api/processos/prioridades-remove.php', {
            processo_id: processoId,
            codigo: codigo,
        })
            .then(function (res) {
                if (res.ok && res.j && res.j.success && res.j.state) {
                    applyState(res.j.state);
                    notifyByKey('audiencias.prioridade.remove.success', {}, 'success', 'Sucesso', 'Prioridade removida.');
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível remover.');
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
            });
    });

    if (state && state.prioridades) {
        applyState(state);
    } else {
        fetchState();
    }
    updatePrioridadeAddButtonState();
})();
