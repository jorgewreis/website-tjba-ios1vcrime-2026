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

    function setupEditButtons() {
        document.querySelectorAll('.button-edit[data-audiencia-edit]').forEach(function (btn) {
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
})();
