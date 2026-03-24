(function () {
    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function changePageSize() {
        var n = String(document.getElementById('items-per-page')?.value || '25').trim();
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'salaata');
        params.set('page_number', '1');
        params.set('items_per_page', n);
        window.location.href = '/frame.php?' + params.toString();
    }

    document.getElementById('items-per-page')?.addEventListener('change', changePageSize);

    document.querySelectorAll('.btn-upload-ata').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('[data-pauta]');
            var inp = wrap ? wrap.querySelector('input.ata-file-input') : null;
            if (inp) inp.click();
        });
    });

    document.querySelectorAll('input.ata-file-input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var file = inp.files && inp.files[0];
            var wrap = inp.closest('[data-pauta]');
            var pauta = wrap ? String(wrap.getAttribute('data-pauta') || '').trim() : '';
            inp.value = '';
            if (!file || !pauta) return;

            var fd = new FormData();
            fd.append('pauta', pauta);
            fd.append('arquivo', file, file.name);

            fetch('/api/sala-audiencias-ata/upload.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notify('success', 'Sucesso', 'Ata enviada com sucesso.');
                        window.location.reload();
                        return;
                    }
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao enviar o arquivo.');
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                });
        });
    });
})();
