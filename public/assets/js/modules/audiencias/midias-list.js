(function () {
    function notify(type, title, message) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: type, title: title, message: message });
        }
    }

    var lockMap = {};
    function requireSecondClick(key, message) {
        var now = Date.now();
        if (lockMap[key] && lockMap[key] > now) {
            delete lockMap[key];
            return true;
        }
        lockMap[key] = now + 5000;
        notify('info', 'Confirmação', message);
        return false;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-midias-concluir-subtarefa');
        if (!button) {
            return;
        }
        var subtarefaId = String(button.getAttribute('data-subtarefa-id') || '').trim();
        if (!subtarefaId) {
            return;
        }
        if (!requireSecondClick('midias-sub-' + subtarefaId, 'Clique novamente para confirmar a conclusão da subtarefa.')) {
            return;
        }
        button.disabled = true;
        fetch('/api/sala-midias/subtarefa-status.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                subtarefa_id: subtarefaId,
                status: 'concluida'
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.j || !res.j.success) {
                    button.disabled = false;
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao concluir subtarefa.');
                    return;
                }
                notify('success', 'Sucesso', 'Subtarefa concluída com sucesso.');
                window.setTimeout(function () {
                    window.location.reload();
                }, 450);
            })
            .catch(function () {
                button.disabled = false;
                notify('error', 'Erro', 'Erro de conexão ao concluir subtarefa.');
            });
    });
})();
