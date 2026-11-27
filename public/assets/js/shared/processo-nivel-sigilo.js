(function () {
    'use strict';
    const form = document.getElementById('processo-nivel-sigilo-form');
    if (!form) return;
    const select = document.getElementById('processo-nivel-sigilo');
    const status = document.getElementById('processo-nivel-sigilo-status');
    const aviso = document.getElementById('processo-sigilo-confirmacao');
    const confirmei = document.getElementById('processo-sigilo-confirmei-pje');
    const button = form.querySelector('button[type=submit]');
    let salvando = false;
    function atualizar() {
        const alterado = form.dataset.inicial !== select.value;
        const tornandoPublico = alterado && select.value === 'publico';
        aviso.hidden = !tornandoPublico;
        confirmei.required = tornandoPublico;
        if (!tornandoPublico) confirmei.checked = false;
        button.disabled = salvando || !alterado || !/^(publico|[1-5])$/.test(select.value)
            || (tornandoPublico && !confirmei.checked);
    }
    select.addEventListener('change', atualizar);
    confirmei.addEventListener('change', atualizar);
    atualizar();
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        atualizar();
        if (button.disabled) return;
        const nivel = select.value;
        const conferencia = nivel === 'publico' && confirmei.checked;
        salvando = true;
        select.disabled = confirmei.disabled = true;
        atualizar();
        status.textContent = 'Salvando…';
        try {
            const response = await fetch('/api/processos/nivel-sigilo.php', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    processo_id: form.dataset.processId, nivel_sigilo: nivel, confirmei_pje: conferencia
                }),
            });
            const data = await response.json();
            if (response.ok && data.success === true) {
                location.reload();
                return;
            }
            status.textContent = data.error || 'Não foi possível salvar.';
        } catch {
            status.textContent = 'Não foi possível confirmar a alteração. Confira a classificação e tente novamente.';
        }
        salvando = false;
        select.disabled = confirmei.disabled = false;
        atualizar();
    });
})();
