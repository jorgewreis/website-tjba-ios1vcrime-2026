(function () {
    'use strict';
    var pageTools = window.App && App.Page ? App.Page : null;
    var MAX_UPLOAD_BYTES = 2 * 1024 * 1024;

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

    function parseJsonSafe(r) {
        return r.text().then(function (text) {
            try {
                return { ok: r.ok, status: r.status, j: text ? JSON.parse(text) : {} };
            } catch (e) {
                return { ok: false, status: r.status, j: { error: text ? text.slice(0, 200) : 'Resposta inválida do servidor.' } };
            }
        });
    }

    function openFilePicker(inp) {
        if (!inp) return false;
        try {
            if (typeof inp.showPicker === 'function') {
                inp.showPicker();
                return true;
            }
        } catch (e) {
            // fallback para click()
        }
        try {
            inp.click();
            return true;
        } catch (e2) {
            return false;
        }
    }

    document.querySelectorAll('.btn-upload-ata').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            var wrap = btn.closest('.register[data-pauta]');
            if (!wrap && btn.parentElement) {
                wrap = btn.parentElement.closest('[data-pauta]');
            }
            var inp = wrap ? wrap.querySelector('input.ata-file-input') : null;
            if (!openFilePicker(inp)) {
                notifyByKey('audiencias.ata.filepicker.open.error', {}, 'error', 'Erro', 'Não foi possível abrir o seletor de arquivos.');
            }
        });
    });

    document.querySelectorAll('input.ata-file-input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            var file = inp.files && inp.files[0];
            var wrap = inp.closest('[data-pauta]');
            var pauta = wrap ? String(wrap.getAttribute('data-pauta') || '').trim() : '';
            if (!file || !pauta) return;

            var fileName = String(file.name || '').trim();
            var isPdfMime = !file.type || file.type === 'application/pdf';
            var isPdfName = /\.pdf$/i.test(fileName);
            if (!isPdfMime && !isPdfName) {
                notifyByKey('audiencias.ata.file.invalid.error', {}, 'error', 'Erro', 'Selecione um arquivo PDF válido.');
                inp.value = '';
                return;
            }
            if (file.size <= 0 || file.size > MAX_UPLOAD_BYTES) {
                notifyByKey('audiencias.ata.file.size.error', {}, 'error', 'Erro', 'O arquivo deve ter no máximo 2 MB.');
                inp.value = '';
                return;
            }

            var fd = new FormData();
            fd.append('pauta', pauta);
            fd.append('arquivo', file, file.name);
            inp.value = '';

            var apiPath = '/api/sala-audiencias-ata/upload.php';
            fetch(apiPath, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(parseJsonSafe)
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notifyByKey('audiencias.ata.upload.success', {}, 'success', 'Sucesso', 'Ata enviada com sucesso.');
                        window.location.reload();
                        return;
                    }
                    if (!res.ok && (!res.j || !res.j.error)) {
                        notifyByKey(
                            'audiencias.ata.upload.http.error',
                            { status: String(res.status || '') },
                            'error',
                            'Erro',
                            'Falha no envio (HTTP ' + String(res.status || '') + ').'
                        );
                        return;
                    }
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao enviar o arquivo.');
                })
                .catch(function () {
                    notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                });
        });
    });
})();
