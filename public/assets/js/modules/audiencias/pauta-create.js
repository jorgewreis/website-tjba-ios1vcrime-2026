(function () {
    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
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

    document.getElementById('btn-audiencia-voltar')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapauta';
    });

    document.getElementById('btn-audiencia-cadastrar')?.addEventListener('click', function () {
        var processo = requiredValue('audiencia-processo', 'Numero do processo');
        if (!processo) return;
        var tipoRaw = requiredValue('audiencia-tipo', 'Tipo de audiencia');
        if (!tipoRaw) return;
        var tipoSelect = document.getElementById('audiencia-tipo');
        var tipo = tipoSelect && tipoSelect.options && tipoSelect.selectedIndex >= 0
            ? String(tipoSelect.options[tipoSelect.selectedIndex].text || '').trim()
            : tipoRaw;
        if (!tipo) return;
        var data = requiredValue('audiencia-data', 'Data');
        if (!data) return;
        var hora = requiredValue('audiencia-hora', 'Hora');
        if (!hora) return;
        var sala = requiredValue('audiencia-sala', 'Localizacao');
        if (!sala) return;
        var status = String(document.getElementById('audiencia-status')?.value || 'designada').trim();
        var classeDescricao = String(document.getElementById('audiencia-classe')?.value || '').trim();

        fetch('/api/sala-audiencias-pauta/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                numero_processo: processo,
                tipo_audiencia: tipo,
                data_audiencia: data,
                hora_audiencia: hora,
                localizacao: sala.charAt(0).toUpperCase() + sala.slice(1),
                status: status,
                classe_descricao: classeDescricao
            })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j && res.j.id) {
                    try {
                        sessionStorage.setItem('salapauta.postReloadToast', JSON.stringify([{ type: 'success', title: 'Sucesso', message: 'Audiência cadastrada.' }]));
                    } catch (e) {}
                    window.location.href = '/frame.php?page=salapauta';
                } else {
                    notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao cadastrar audiência.');
                }
            })
            .catch(function () {
                notify('error', 'Erro', 'Erro de conexão.');
            });
    });
})();
