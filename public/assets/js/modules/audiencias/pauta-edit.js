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

    function readPartPayload() {
        return {
            nome: String(document.getElementById('parte-nome')?.value || '').trim(),
            tipo: String(document.getElementById('parte-tipo')?.value || '').trim(),
            maioridade: String(document.getElementById('parte-maioridade')?.value || '').trim(),
            expedicao: String(document.getElementById('parte-expedicao')?.value || '').trim(),
            cumprimento: String(document.getElementById('parte-cumprimento')?.value || '').trim(),
            idDocumentoExpedido: String(document.getElementById('parte-id-doc-expedido')?.value || '').trim(),
            idDocumentoCumprido: String(document.getElementById('parte-id-doc-cumprido')?.value || '').trim()
        };
    }

    function validatePart(payload) {
        if (!payload.nome) return 'Nome da parte';
        if (!payload.tipo) return 'Tipo de parte';
        if (!payload.maioridade) return 'Maioridade';
        if (!payload.expedicao) return 'Expedicao';
        if (!payload.cumprimento) return 'Cumprimento';
        return '';
    }

    function clearPartForm() {
        [
            'parte-nome',
            'parte-tipo',
            'parte-maioridade',
            'parte-expedicao',
            'parte-cumprimento',
            'parte-id-doc-expedido',
            'parte-id-doc-cumprido'
        ].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.tagName === 'SELECT') {
                el.selectedIndex = 0;
            } else {
                el.value = '';
            }
        });
    }

    document.getElementById('btn-audiencia-edit-voltar')?.addEventListener('click', function () {
        window.location.href = '/frame.php?page=salapauta';
    });

    document.getElementById('btn-parte-limpar')?.addEventListener('click', clearPartForm);

    document.getElementById('btn-parte-adicionar')?.addEventListener('click', function () {
        var payload = readPartPayload();
        var missing = validatePart(payload);
        if (missing) {
            notify('error', 'Validacao', 'Informe: ' + missing + '.');
            return;
        }
        notify('success', 'Sucesso', 'Parte validada para inclusao. Integracao com API pendente.');
    });

    document.querySelectorAll('.button-edit[data-parte-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idParte = String(btn.getAttribute('data-parte-edit') || '').trim();
            if (!idParte) return;
            window.location.href = '/frame.php?page=salapautaparteedit&id_parte=' + encodeURIComponent(idParte);
        });
    });

    document.querySelectorAll('.button-table[data-parte-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!requireSecondClick('pauta-remover-parte-' + btn.getAttribute('data-parte-remove'), 'Clique novamente em Remover parte em ate 5 segundos para confirmar.')) return;
            notify('success', 'Sucesso', 'Remocao da parte confirmada. Integracao com API pendente.');
        });
    });

    document.getElementById('btn-audiencia-edit-cancelar')?.addEventListener('click', function () {
        if (!requireSecondClick('pauta-cancelar-audiencia', 'Clique novamente em Cancelar audiencia em ate 5 segundos para confirmar.')) return;
        notify('success', 'Sucesso', 'Cancelamento confirmado. Integracao com API pendente.');
    });

    document.getElementById('btn-audiencia-edit-excluir')?.addEventListener('click', function () {
        if (!requireSecondClick('pauta-excluir-audiencia', 'Clique novamente em Excluir audiencia em ate 5 segundos para confirmar.')) return;
        notify('success', 'Sucesso', 'Exclusao confirmada. Integracao com API pendente.');
    });

    document.getElementById('btn-audiencia-edit-salvar')?.addEventListener('click', function () {
        notify('success', 'Sucesso', 'Alteracoes validadas para salvamento. Integracao com API pendente.');
    });
})();
