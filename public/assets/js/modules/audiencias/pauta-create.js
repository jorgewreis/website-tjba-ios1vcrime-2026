(function () {
    function notify(type, title, message) {
        if (typeof showToast === 'function') {
            showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function clearForm() {
        var form = document.getElementById('audiencia-create-form');
        if (!form) return;
        form.reset();
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

    document.getElementById('btn-audiencia-limpar')?.addEventListener('click', clearForm);

    document.getElementById('btn-audiencia-registrar')?.addEventListener('click', function () {
        var processo = requiredValue('audiencia-processo', 'Numero do processo');
        if (!processo) return;
        var tipo = requiredValue('audiencia-tipo', 'Tipo de audiencia');
        if (!tipo) return;
        var data = requiredValue('audiencia-data', 'Data');
        if (!data) return;
        var hora = requiredValue('audiencia-hora', 'Hora');
        if (!hora) return;
        var sala = requiredValue('audiencia-sala', 'Localizacao');
        if (!sala) return;

        notify('success', 'Sucesso', 'Audiencia validada para cadastro. Integracao com API pendente.');
    });
})();
