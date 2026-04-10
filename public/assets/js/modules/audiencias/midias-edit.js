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

    var backBtn = document.getElementById('midias-edit-voltar');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            window.location.href = '/frame.php?page=salamidias';
        });
    }

    var btnSalvar = document.getElementById('btn-midias-salvar');
    var id = String(document.getElementById('midias-edit-id') && document.getElementById('midias-edit-id').value || '').trim();
    if (btnSalvar) {
        btnSalvar.addEventListener('click', function () {
            if (!id) {
                notify('error', 'Erro', 'Registro inválido para atualização.');
                return;
            }
            var qtd = parseInt(String(document.getElementById('midias-edit-qtd') && document.getElementById('midias-edit-qtd').value || '1'), 10);
            var status = String(document.getElementById('midias-edit-status') && document.getElementById('midias-edit-status').value || '').trim();
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
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-midias-edit-concluir-subtarefa');
        if (!button) {
            return;
        }
        var subtarefaId = String(button.getAttribute('data-subtarefa-id') || '').trim();
        if (!subtarefaId) {
            return;
        }
        if (!requireSecondClick('midias-edit-sub-' + subtarefaId, 'Clique novamente para confirmar a conclusão da subtarefa.')) {
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
