(function () {
    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
        }
    }

    var btnSalvar = document.getElementById('btn-midias-salvar');
    if (!btnSalvar) {
        return;
    }

    btnSalvar.addEventListener('click', function () {
        var id = String(document.getElementById('midias-edit-id') && document.getElementById('midias-edit-id').value || '').trim();
        var qtd = parseInt(String(document.getElementById('midias-edit-qtd') && document.getElementById('midias-edit-qtd').value || '1'), 10);
        var status = String(document.getElementById('midias-edit-status') && document.getElementById('midias-edit-status').value || '').trim();
        if (!id) {
            notify('error', 'Erro', 'Registro inválido para atualização.');
            return;
        }
        if (!qtd || qtd < 1) {
            qtd = 1;
        }
        fetch('/api/sala-midias/update.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                qtd_midias: qtd,
                status: status
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !res.j.success) {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao salvar alterações.');
                    return;
                }
                notify('success', 'Sucesso', 'Alterações salvas com sucesso.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 450);
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão ao salvar alterações.');
            });
    });
})();
