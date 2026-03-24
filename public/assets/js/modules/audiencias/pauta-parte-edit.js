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

    function toggleDefensorFields() {
        var tipoParte = String(document.getElementById('parte-edit-tipo')?.value || '').trim();
        var wrapper = document.getElementById('parte-edit-defensor-wrapper');
        var tipoDefensor = document.getElementById('parte-edit-tipo-defensor');
        var publicoWrap = document.getElementById('parte-edit-defensor-publico-wrapper');
        var privadoWrap = document.getElementById('parte-edit-defensor-privado-wrapper');
        var outroWrap = document.getElementById('parte-edit-defensor-privado-outro-wrapper');
        var defensorPublico = document.getElementById('parte-edit-defensor-publico');
        var defensorPrivado = document.getElementById('parte-edit-defensor-privado');
        var defensorOutro = document.getElementById('parte-edit-defensor-privado-outro');

        if (!wrapper || !tipoDefensor || !publicoWrap || !privadoWrap || !outroWrap) return;

        if (tipoParte === 'reu') {
            wrapper.style.display = '';
        } else {
            wrapper.style.display = 'none';
            tipoDefensor.value = '';
            if (defensorPublico) defensorPublico.value = '';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
            publicoWrap.style.display = 'none';
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            return;
        }

        var td = String(tipoDefensor.value || '').trim();
        if (td === 'publico') {
            publicoWrap.style.display = '';
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
        } else if (td === 'privado') {
            publicoWrap.style.display = 'none';
            privadoWrap.style.display = '';
            var valPrivado = String(defensorPrivado?.value || '').trim();
            outroWrap.style.display = valPrivado === 'outro' ? '' : 'none';
            if (defensorPublico) defensorPublico.value = '';
        } else {
            publicoWrap.style.display = 'none';
            privadoWrap.style.display = 'none';
            outroWrap.style.display = 'none';
            if (defensorPublico) defensorPublico.value = '';
            if (defensorPrivado) defensorPrivado.value = '';
            if (defensorOutro) defensorOutro.value = '';
        }
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

        var tipoParte = String(document.getElementById('parte-edit-tipo')?.value || '').trim();
        if (tipoParte === 'reu') {
            var tipoDefensor = requiredValue('parte-edit-tipo-defensor', 'Tipo de defensor');
            if (!tipoDefensor) return;
            if (tipoDefensor === 'publico') {
                var defensorPublico = requiredValue('parte-edit-defensor-publico', 'Defensor Público');
                if (!defensorPublico) return;
            } else if (tipoDefensor === 'privado') {
                var defensorPrivado = requiredValue('parte-edit-defensor-privado', 'Defensor');
                if (!defensorPrivado) return;
                if (defensorPrivado === 'outro') {
                    var defensorOutro = requiredValue('parte-edit-defensor-privado-outro', 'Outro defensor');
                    if (!defensorOutro) return;
                }
            }
        }

        var idParte = String(document.getElementById('parte-edit-id')?.value || '').trim();
        fetch('/api/sala-audiencias-pauta/partes-update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                id_parte: idParte,
                parte_id_global: String(document.getElementById('parte-edit-parte-global-id')?.value || '').trim(),
                processo_parte_id: String(document.getElementById('parte-edit-processo-parte-id')?.value || '').trim(),
                nome_parte: nome,
                tipo_parte: tipo,
                tipo_defensor: String(document.getElementById('parte-edit-tipo-defensor')?.value || '').trim(),
                defensor_publico_id: String(document.getElementById('parte-edit-defensor-publico')?.value || '').trim(),
                defensor_privado_id: String(document.getElementById('parte-edit-defensor-privado')?.value || '').trim(),
                defensor_privado_outro: String(document.getElementById('parte-edit-defensor-privado-outro')?.value || '').trim(),
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

    document.getElementById('parte-edit-tipo')?.addEventListener('change', toggleDefensorFields);
    document.getElementById('parte-edit-tipo-defensor')?.addEventListener('change', toggleDefensorFields);
    document.getElementById('parte-edit-defensor-privado')?.addEventListener('change', toggleDefensorFields);
    toggleDefensorFields();
})();
