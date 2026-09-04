(function () {
    'use strict';
    var pageTools = window.App && App.Page ? App.Page : null;

    function getFramePhpHref() {
        if (pageTools && typeof pageTools.getFramePhpHref === 'function') {
            return pageTools.getFramePhpHref();
        }
        var path = window.location.pathname || '/frame.php';
        var slash = path.lastIndexOf('/');
        var dir = slash >= 0 ? path.slice(0, slash + 1) : '/';
        return dir + 'frame.php';
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

    function val(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function applyFilters() {
        var params = new URLSearchParams();
        params.set('page', 'salapauta');
        params.set('page_number', '1');
        params.set('status', val('filter-status'));
        params.set('periodo', val('filter-periodo'));
        params.set('tipo', val('filter-tipo'));
        params.set('search', val('search-input'));
        params.set('items_per_page', val('items-per-page') || '25');
        window.location.href = getFramePhpHref() + '?' + params.toString();
    }

    function postStatusPainel(idAudiencia, acao, successMessage) {
        var apiPath = 'api/sala-audiencias-pauta/set-status-painel.php';
        return fetch(apiPath, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: idAudiencia, acao: acao })
        })
            .then(function (r) {
                return r.json().then(function (j) {
                    return { ok: r.ok, j: j };
                });
            })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    var successKey = 'audiencias.pauta.status.reiniciar.success';
                    if (acao === 'iniciar') successKey = 'audiencias.pauta.status.iniciar.success';
                    if (acao === 'encerrar') successKey = 'audiencias.pauta.status.encerrar.success';
                    if (acao === 'redesignar') successKey = 'audiencias.pauta.status.redesignar.success';
                    queuePostReloadToastByKey(successKey, {}, 'success', 'Sucesso', successMessage, 'salapauta.postReloadToast');
                    window.location.reload();
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível atualizar o status.');
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
            });
    }

    function setupDelegatedClicks() {
        document.addEventListener('click', function (ev) {
            var target = ev.target;
            if (!target || !target.closest) return;

            var editBtn = target.closest('[data-audiencia-edit]');
            if (editBtn) {
                if (editBtn.disabled) return;
                var idEdit = String(editBtn.getAttribute('data-audiencia-edit') || '').trim();
                if (!idEdit) return;
                ev.preventDefault();
                var q = new URLSearchParams();
                q.set('page', 'salapautaedit');
                q.set('id', idEdit);
                window.location.href = getFramePhpHref() + '?' + q.toString();
                return;
            }

            var rein = target.closest('[data-audiencia-reiniciar]');
            if (rein) {
                if (rein.disabled) return;
                var idR = String(rein.getAttribute('data-audiencia-reiniciar') || '').trim();
                if (!idR) return;
                ev.preventDefault();
                postStatusPainel(idR, 'reiniciar', 'Status atualizado para Designada.');
                return;
            }

            var ini = target.closest('[data-audiencia-iniciar]');
            if (ini) {
                if (ini.disabled) return;
                var idI = String(ini.getAttribute('data-audiencia-iniciar') || '').trim();
                if (!idI) return;
                ev.preventDefault();
                postStatusPainel(idI, 'iniciar', 'Status atualizado para Em andamento.');
                return;
            }

            var enc = target.closest('[data-audiencia-encerrar]');
            if (enc) {
                if (enc.disabled) return;
                var idE = String(enc.getAttribute('data-audiencia-encerrar') || '').trim();
                if (!idE) return;
                ev.preventDefault();
                postStatusPainel(idE, 'encerrar', 'Status atualizado para Finalizada.');
                return;
            }

            var red = target.closest('[data-audiencia-redesignar]');
            if (red) {
                if (red.disabled) return;
                var idRed = String(red.getAttribute('data-audiencia-redesignar') || '').trim();
                if (!idRed) return;
                ev.preventDefault();
                postStatusPainel(idRed, 'redesignar', 'Status atualizado para Redesignada.');
            }
        });
    }

    var novo = document.getElementById('novo-cadastro-btn');
    if (novo) {
        novo.addEventListener('click', function () {
            window.location.href = getFramePhpHref() + '?page=salapautacreate';
        });
    }

    var fp = document.getElementById('filter-periodo');
    if (fp) fp.addEventListener('change', applyFilters);
    var ft = document.getElementById('filter-tipo');
    if (ft) ft.addEventListener('change', applyFilters);
    var fs = document.getElementById('filter-status');
    if (fs) fs.addEventListener('change', applyFilters);
    var ipp = document.getElementById('items-per-page');
    if (ipp) ipp.addEventListener('change', applyFilters);
    var searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') applyFilters();
        });
    }

    (function flushPostReloadToast() {
        if (pageTools && typeof pageTools.flushPostReloadToast === 'function') {
            pageTools.flushPostReloadToast('salapauta.postReloadToast', { type: 'success', title: 'Sucesso' });
            return;
        }
        try {
            var raw = sessionStorage.getItem('salapauta.postReloadToast');
            if (!raw) return;
            sessionStorage.removeItem('salapauta.postReloadToast');
            var data = JSON.parse(raw);
            (Array.isArray(data) ? data : []).forEach(function (item) {
                if (item && item.message) notify(item.type || 'success', item.title || 'Sucesso', item.message);
            });
        } catch (e) {}
    })();

    setupDelegatedClicks();
})();
