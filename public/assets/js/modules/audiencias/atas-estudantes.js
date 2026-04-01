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

    document.querySelectorAll('.btn-inscrever-ata').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pauta = String(btn.getAttribute('data-pauta') || '').trim();
            if (!pauta || btn.disabled) return;
            btn.disabled = true;

            fetch('api/sala-audiencias-ata/inscrever.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ pauta: pauta })
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notify('success', 'Sucesso', 'Inscrição realizada.');
                        window.location.reload();
                        return;
                    }
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível inscrever.');
                    btn.disabled = false;
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    btn.disabled = false;
                });
        });
    });
})();
