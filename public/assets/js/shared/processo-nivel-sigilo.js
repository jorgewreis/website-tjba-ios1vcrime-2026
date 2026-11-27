(function () {
    'use strict';
    const form = document.getElementById('processo-nivel-sigilo-form');
    if (!form) return;
    const select = document.getElementById('processo-nivel-sigilo');
    const status = document.getElementById('processo-nivel-sigilo-status');
    const button = form.querySelector('button[type=submit]');
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (button.disabled || !/^[1-5]$/.test(select.value)) return;
        button.disabled = true;
        select.disabled = true;
        status.textContent = 'Salvando…';
        try {
            const response = await fetch('/api/processos/nivel-sigilo.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ processo_id: form.dataset.processId, nivel_sigilo: select.value }),
            });
            const data = await response.json();
            status.textContent = response.ok && data.success
                ? (data.alterado ? 'Nível registrado.' : 'Nível já registrado.')
                : (data.error || 'Não foi possível salvar.');
        } catch {
            status.textContent = 'Não foi possível salvar. Tente novamente.';
        } finally {
            button.disabled = false;
            select.disabled = false;
        }
    });
})();
