(function () {
    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function applyFilters() {
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'salapauta');
        params.set('page_number', '1');
        params.set('status', String(document.getElementById('filter-status')?.value || '').trim());
        params.set('periodo', String(document.getElementById('filter-periodo')?.value || '').trim());
        params.set('tipo', String(document.getElementById('filter-tipo')?.value || '').trim());
        params.set('search', String(document.getElementById('search-input')?.value || '').trim());
        params.set('items_per_page', String(document.getElementById('items-per-page')?.value || '25').trim());
        window.location.href = '/frame.php?' + params.toString();
    }

    function postStatusPainel(idAudiencia, acao, successMessage) {
        return fetch('/api/sala-audiencias-pauta/set-status-painel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: idAudiencia, acao: acao })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    try {
                        sessionStorage.setItem('salapauta.postReloadToast', JSON.stringify([
                            { type: 'success', title: 'Sucesso', message: successMessage }
                        ]));
                    } catch (e) {}
                    window.location.reload();
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível atualizar o status.');
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
            });
    }

    function setupPainelButtons() {
        document.querySelectorAll('[data-audiencia-reiniciar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var id = String(btn.getAttribute('data-audiencia-reiniciar') || '').trim();
                if (!id) return;
                postStatusPainel(id, 'reiniciar', 'Status atualizado para Designada.');
            });
        });
        document.querySelectorAll('[data-audiencia-iniciar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var id = String(btn.getAttribute('data-audiencia-iniciar') || '').trim();
                if (!id) return;
                postStatusPainel(id, 'iniciar', 'Status atualizado para Em andamento.');
            });
        });
        document.querySelectorAll('[data-audiencia-encerrar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var id = String(btn.getAttribute('data-audiencia-encerrar') || '').trim();
                if (!id) return;
                postStatusPainel(id, 'encerrar', 'Status atualizado para Finalizada.');
            });
        });
    }

    function setupEditButtons() {
        document.querySelectorAll('[data-audiencia-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idAudiencia = String(btn.getAttribute('data-audiencia-edit') || '').trim();
                if (!idAudiencia) return;
                window.location.href = '/frame.php?page=salapautaedit&id=' + encodeURIComponent(idAudiencia);
            });
        });
    }

    document.getElementById('novo-cadastro-btn')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapautacreate';
    });
    document.getElementById('filter-periodo')?.addEventListener('change', applyFilters);
    document.getElementById('filter-tipo')?.addEventListener('change', applyFilters);
    document.getElementById('filter-status')?.addEventListener('change', applyFilters);
    document.getElementById('items-per-page')?.addEventListener('change', applyFilters);
    document.getElementById('search-input')?.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') applyFilters();
    });

    (function flushPostReloadToast() {
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

    setupEditButtons();
    setupPainelButtons();
})();
