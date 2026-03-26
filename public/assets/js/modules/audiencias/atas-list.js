(function () {
    'use strict';
    var MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

    function getFramePhpHref() {
        var path = window.location.pathname || '/frame.php';
        var slash = path.lastIndexOf('/');
        var dir = slash >= 0 ? path.slice(0, slash + 1) : '/';
        return dir + 'frame.php';
    }

    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function parseJsonSafe(r) {
        return r.text().then(function (text) {
            try {
                return { ok: r.ok, j: text ? JSON.parse(text) : {} };
            } catch (e) {
                return { ok: false, j: { error: text ? text.slice(0, 200) : 'Resposta inválida do servidor.' } };
            }
        });
    }

    function changePageSize() {
        var el = document.getElementById('items-per-page');
        var n = el ? String(el.value || '25').trim() : '25';
        var params = new URLSearchParams();
        params.set('page', 'salaata');
        params.set('page_number', '1');
        params.set('items_per_page', n);
        window.location.href = getFramePhpHref() + '?' + params.toString();
    }

    var ipp = document.getElementById('items-per-page');
    if (ipp) ipp.addEventListener('change', changePageSize);

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

            if (file.type && file.type !== 'application/pdf') {
                notify('error', 'Erro', 'Selecione um arquivo PDF válido.');
                return;
            }
            if (file.size <= 0 || file.size > MAX_UPLOAD_BYTES) {
                notify('error', 'Erro', 'O arquivo deve ter no máximo 2 MB.');
                return;
            }

            var fd = new FormData();
            fd.append('pauta', pauta);
            fd.append('arquivo', file, file.name);

            var apiPath = '/api/sala-audiencias-ata/upload.php';
            fetch(apiPath, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            })
                .then(parseJsonSafe)
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
