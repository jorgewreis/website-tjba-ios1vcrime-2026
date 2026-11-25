(function () {
    'use strict';

    const form = document.getElementById('process-responsible-sector-form');
    if (!form) return;

    const select = document.getElementById('process-responsible-sector-select');
    const reason = document.getElementById('process-responsible-sector-reason');
    const button = document.getElementById('process-responsible-sector-submit');
    const status = document.getElementById('process-responsible-sector-status');
    const current = document.getElementById('process-responsible-sector-current');
    const endpoint = '/api/processos/setor-responsavel.php';

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!select || !reason || !button || !status) return;
        const justification = reason.value.trim();
        if (justification === '') {
            status.textContent = 'Informe a justificativa para registrar a alteração.';
            reason.focus();
            return;
        }

        button.disabled = true;
        status.textContent = 'Registrando atribuição…';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                body: JSON.stringify({
                    processo_id: form.dataset.processId || '',
                    setor_id: select.value,
                    justificativa: justification,
                }),
            });
            const result = await response.json().catch(function () { return {}; });
            if (!response.ok || result.success !== true) {
                status.textContent = typeof result.error === 'string' ? result.error : 'Não foi possível registrar a atribuição.';
                return;
            }

            const chosen = select.options[select.selectedIndex];
            const sectorName = result.sector_id ? (chosen ? chosen.textContent.trim() : 'Setor atribuído') : '';
            if (current) {
                current.textContent = sectorName
                    ? 'Atribuição atual: ' + sectorName
                    : 'Sem unidade atribuída. Mutações protegidas por unidade permanecem bloqueadas.';
            }
            status.textContent = result.changed ? 'Atribuição auditada com sucesso.' : 'Nenhuma alteração necessária.';
            reason.value = '';
        } catch (error) {
            status.textContent = 'Falha de comunicação. A justificativa foi preservada; tente novamente.';
        } finally {
            button.disabled = false;
        }
    });
})();
