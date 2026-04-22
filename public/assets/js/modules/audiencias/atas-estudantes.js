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

    /*
     * Delegacao no document para capturar cliques de btn-inscrever-ata mesmo quando
     * o botao e recriado pelo polling (renderInscricaoUI no painel-estudantes
     * atualiza innerHTML do wrapper, o que destroi listeners ligados diretamente
     * ao botao).
     */
    document.addEventListener('click', function (event) {
        var btn = event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.btn-inscrever-ata')
            : null;
        if (!btn) {
            return;
        }
        var pauta = String(btn.getAttribute('data-pauta') || '').trim();
        if (!pauta || btn.disabled) {
            return;
        }
        btn.disabled = true;

        fetch('/api/sala-audiencias-ata/inscrever.php', {
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
})();
