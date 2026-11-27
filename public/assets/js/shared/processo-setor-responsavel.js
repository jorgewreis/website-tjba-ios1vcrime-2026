(function () {
    'use strict';

    const form = document.getElementById('process-responsible-sector-form');
    if (!form) return;

    const select = document.getElementById('process-responsible-sector-select');
    const button = document.getElementById('process-responsible-sector-submit');
    const status = document.getElementById('process-responsible-sector-status');
    const current = document.getElementById('process-responsible-sector-current');
    const endpoint = '/api/processos/setor-responsavel.php';
    const requiresDestination = form.dataset.explicitDestination === 'true';
    let submitting = false;
    function refreshSubmitState() {
        if (requiresDestination && select && button) button.disabled = submitting || select.value === '';
    }
    if (requiresDestination && select) {
        select.addEventListener('change', refreshSubmitState);
        refreshSubmitState();
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!select || !button || !status) return;
        if (button.disabled || submitting || (requiresDestination && select.value === '')) return;
        const sectorId = select.value;
        const chosen = select.options[select.selectedIndex];

        submitting = true;
        button.disabled = true;
        status.textContent = 'Registrando atribuição…';
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json; charset=UTF-8' },
                body: JSON.stringify({
                    processo_id: form.dataset.processId || '',
                    setor_id: sectorId,
                }),
            });
            const result = await response.json().catch(function () { return {}; });
            if (!response.ok || result.success !== true) {
                status.textContent = typeof result.error === 'string' ? result.error : 'Não foi possível registrar a atribuição.';
                return;
            }

            const sectorName = result.sector_id ? (chosen ? chosen.textContent.trim() : 'Setor atribuído') : '';
            if (current) {
                current.textContent = sectorName
                    ? (result.is_default ? 'Unidade padrão: Cartório' : 'Atribuição atual: ' + sectorName)
                    : 'Sem unidade atribuída. Mutações protegidas por unidade permanecem bloqueadas.';
            }
            status.textContent = result.changed ? 'Atribuição auditada com sucesso.' : 'Nenhuma alteração necessária.';
        } catch (error) {
            status.textContent = 'Falha de comunicação. Tente novamente.';
        } finally {
            submitting = false;
            button.disabled = false;
            refreshSubmitState();
        }
    });
})();
