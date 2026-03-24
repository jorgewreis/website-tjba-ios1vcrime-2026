(function () {
    var pendingConfirmations = {};

    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function requireSecondClick(key, message) {
        var now = Date.now();
        var validUntil = Number(pendingConfirmations[key] || 0);
        if (validUntil > now) {
            delete pendingConfirmations[key];
            return true;
        }
        pendingConfirmations[key] = now + 5000;
        notify('info', 'Confirmacao', message);
        return false;
    }

    function requiredValue(id, label) {
        var el = document.getElementById(id);
        var value = String(el && el.value ? el.value : '').trim();
        if (!value) {
            notify('error', 'Validacao', 'Informe: ' + label + '.');
            return '';
        }
        return value;
    }

    document.getElementById('btn-parte-edit-voltar')?.addEventListener('click', function () {
        history.back();
    });

    document.getElementById('btn-parte-edit-remover')?.addEventListener('click', function () {
        if (!requireSecondClick('parte-edit-remover', 'Clique novamente em Remover parte em ate 5 segundos para confirmar.')) return;
        var idParte = String(document.getElementById('parte-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/partes-delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id_parte: idParte })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    notify('success', 'Sucesso', 'Parte removida.');
                    setTimeout(function () { history.back(); }, 300);
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao remover parte.');
                }
            })
            .catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
    });

    document.getElementById('btn-parte-edit-salvar')?.addEventListener('click', function () {
        var nome = requiredValue('parte-edit-nome', 'Nome da parte');
        if (!nome) return;
        var tipo = requiredValue('parte-edit-tipo', 'Tipo de parte');
        if (!tipo) return;
        var maioridade = requiredValue('parte-edit-maioridade', 'Maioridade');
        if (!maioridade) return;
        var expedicao = requiredValue('parte-edit-expedicao', 'Expedicao');
        if (!expedicao) return;
        var cumprimento = requiredValue('parte-edit-cumprimento', 'Cumprimento');
        if (!cumprimento) return;

        var idParte = String(document.getElementById('parte-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/partes-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                id_parte: idParte,
                nome_parte: nome,
                tipo_parte: tipo,
                maioridade: maioridade,
                expedicao: expedicao,
                cumprimento: cumprimento,
                id_documento_expedido: String(document.getElementById('parte-edit-id-expedido')?.value || '').trim(),
                id_documento_cumprido: String(document.getElementById('parte-edit-id-cumprido')?.value || '').trim()
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.success) {
                    notify('success', 'Sucesso', 'Parte atualizada.');
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao atualizar parte.');
                }
            })
            .catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
    });
})();
