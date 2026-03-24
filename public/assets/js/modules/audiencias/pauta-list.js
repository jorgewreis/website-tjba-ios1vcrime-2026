(function () {
    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function applyFilters() {
        notify('info', 'Filtros', 'Aplicacao de filtros sera conectada na API da pauta.');
    }

    function setupEditButtons() {
        document.querySelectorAll('.button-edit[data-parte-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idParte = String(btn.getAttribute('data-parte-edit') || '').trim();
                if (!idParte) return;
                window.location.href = '/frame.php?page=salapautaparteedit&id_parte=' + encodeURIComponent(idParte);
            });
        });

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

    setupEditButtons();
})();
