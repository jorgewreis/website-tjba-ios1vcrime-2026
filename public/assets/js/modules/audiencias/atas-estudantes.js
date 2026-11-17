(function () {
    var pageTools = window.App && App.Page ? App.Page : null;
    var pendingByPauta = Object.create(null);

    function setPendingUI(wrapper, pauta, pending) {
        if (pending) {
            pendingByPauta[pauta] = true;
            if (wrapper) wrapper.setAttribute('data-inscricao-pendente', '1');
            return;
        }

        delete pendingByPauta[pauta];
        if (!wrapper) return;
        wrapper.removeAttribute('data-inscricao-pendente');
        var currentButton = wrapper.querySelector('.btn-inscrever-ata');
        if (currentButton) {
            currentButton.setAttribute('aria-disabled', 'false');
            currentButton.setAttribute('aria-busy', 'false');
            currentButton.removeAttribute('aria-live');
            currentButton.textContent = 'Inscrever-me';
        }
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
        if (!pauta || btn.disabled || pendingByPauta[pauta]) {
            return;
        }
        var wrapper = typeof btn.closest === 'function' ? btn.closest('[data-inscricao-ui]') : null;
        if (wrapper && wrapper.getAttribute('data-inscricao-pendente') === '1') {
            return;
        }
        setPendingUI(wrapper, pauta, true);
        btn.setAttribute('aria-disabled', 'true');
        btn.setAttribute('aria-busy', 'true');
        btn.setAttribute('aria-live', 'polite');
        btn.textContent = 'Inscrição em andamento…';

        fetch('/api/sala-audiencias-ata/inscrever.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ pauta: pauta })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    notifyByKey('audiencias.ata.subscribe.success', {}, 'success', 'Sucesso', 'Inscrição realizada.');
                    window.location.reload();
                    return;
                }
                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Não foi possível inscrever.');
                setPendingUI(wrapper, pauta, false);
            })
            .catch(function () {
                notifyByKey('generic.error.connection', {}, 'error', 'Erro', 'Erro de conexão.');
                setPendingUI(wrapper, pauta, false);
            });
    });
})();
