(function () {
    var pageTools = window.App && App.Page ? App.Page : null;

    function notify(type, title, message) {
        if (pageTools && typeof pageTools.notify === 'function') {
            pageTools.notify(type, title, message);
            return;
        }
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function applyFilters() {
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'estudapauta');
        params.set('page_number', '1');
        params.set('status', String(document.getElementById('filter-status')?.value || '').trim());
        params.set('periodo', String(document.getElementById('filter-periodo')?.value || '').trim());
        params.set('tipo', String(document.getElementById('filter-tipo')?.value || '').trim());
        params.set('search', String(document.getElementById('search-input')?.value || '').trim());
        params.set('items_per_page', '15');
        window.location.href = '/frame.php?' + params.toString();
    }

    document.getElementById('filter-periodo')?.addEventListener('change', applyFilters);
    document.getElementById('filter-tipo')?.addEventListener('change', applyFilters);
    document.getElementById('filter-status')?.addEventListener('change', applyFilters);
    document.getElementById('search-input')?.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') applyFilters();
    });

    (function flushPostReloadToast() {
        if (pageTools && typeof pageTools.flushPostReloadToast === 'function') {
            pageTools.flushPostReloadToast('estudapauta.postReloadToast', { type: 'success', title: 'Sucesso' });
            return;
        }
        try {
            var raw = sessionStorage.getItem('estudapauta.postReloadToast');
            if (!raw) return;
            sessionStorage.removeItem('estudapauta.postReloadToast');
            var data = JSON.parse(raw);
            (Array.isArray(data) ? data : []).forEach(function (item) {
                if (item && item.message) notify(item.type || 'success', item.title || 'Sucesso', item.message);
            });
        } catch (e) {}
    })();
})();
