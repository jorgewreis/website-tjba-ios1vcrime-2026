(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
        } else if (typeof global.showToast === 'function') {
            global.showToast({ type: type, variant: 'full', title: title, message: message });
        } else {
            global.alert(message);
        }
    }

    function normalizeProcess(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (digits.length !== 20) {
            throw new Error('O número do processo deve ter 20 dígitos no formato CNJ.');
        }
        let rest = 0;
        const base = digits.slice(0, 7) + digits.slice(9) + '00';
        for (const char of base) rest = (rest * 10 + Number(char)) % 97;
        const expected = String(98 - rest).padStart(2, '0');
        if (digits.slice(7, 9) !== expected) {
            throw new Error('Número do processo inválido. Verifique o dígito verificador.');
        }
        return digits.slice(0, 7) + '-' + digits.slice(7, 9) + '.' + digits.slice(9, 13) + '.' + digits.slice(13, 14) + '.' + digits.slice(14, 16) + '.' + digits.slice(16);
    }

    function validate(form) {
        const processInput = form.querySelector('[name="numero_processo"]');
        const partInput = form.querySelector('[name="nome_parte"]');
        const dateInput = form.querySelector('[name="data_envio_cejusc"]');
        const numeroProcesso = normalizeProcess(processInput.value);
        const nomeParte = partInput.value.trim();
        const dataEnvio = dateInput.value.trim();
        if (!nomeParte) throw new Error('O nome da parte é obrigatório.');
        if (nomeParte.length > 180) throw new Error('O nome da parte deve ter no máximo 180 caracteres.');
        if (!/^\d{4}-\d{2}-\d{2}$/.test(dataEnvio) || Number.isNaN(Date.parse(dataEnvio))) throw new Error('Informe uma data de envio válida.');
        return { numero_processo: numeroProcesso, nome_parte: nomeParte, data_envio_cejusc: dataEnvio };
    }

    async function send(endpoint, payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        let data = {};
        try { data = await response.json(); } catch (error) { /* resposta inválida tratada abaixo */ }
        if (!response.ok) throw new Error(data.error || 'Não foi possível concluir a operação.');
        return data;
    }

    function setBusy(button, busy, label) {
        if (!button) return;
        button.disabled = busy;
        if (busy) {
            button.dataset.initialLabel = button.textContent.trim();
            button.textContent = label;
        } else if (button.dataset.initialLabel) {
            button.textContent = button.dataset.initialLabel;
        }
    }

    function bindCreate(form) {
        const button = form.querySelector('[type="submit"]');
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (button && button.disabled) return;
            try {
                const payload = validate(form);
                setBusy(button, true, 'Cadastrando...');
                const data = await send('/api/cartorio-cejusc/create.php', payload);
                if (!data.id) throw new Error('O cadastro não foi confirmado pelo servidor.');
                if (App.Page && typeof App.Page.queuePostReloadToast === 'function') App.Page.queuePostReloadToast('success', 'Sucesso', 'Registro CEJUSC cadastrado.', 'cartorio.postReloadToast');
                global.location.href = '/frame.php?page=cejusc';
            } catch (error) {
                setBusy(button, false);
                notify('error', 'Validação', error.message || 'Não foi possível cadastrar o registro.');
            }
        });
    }

    function bindEdit(form) {
        const saveButton = form.querySelector('[type="submit"]');
        const deleteButton = form.querySelector('[data-cejusc-delete]');
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (saveButton && saveButton.disabled) return;
            try {
                const payload = validate(form);
                payload.id = form.querySelector('[name="id"]').value;
                setBusy(saveButton, true, 'Salvando...');
                const data = await send('/api/cartorio-cejusc/update.php', payload);
                if (!data.success) throw new Error(data.error || 'O registro não foi atualizado.');
                if (App.Page && typeof App.Page.queuePostReloadToast === 'function') App.Page.queuePostReloadToast('success', 'Sucesso', 'Alterações salvas.', 'cartorio.postReloadToast');
                global.location.href = '/frame.php?page=cejusc';
            } catch (error) {
                setBusy(saveButton, false);
                notify('error', 'Validação', error.message || 'Não foi possível salvar as alterações.');
            }
        });
        if (deleteButton) deleteButton.addEventListener('click', async function () {
            if (deleteButton.disabled || !global.confirm('Excluir este registro CEJUSC?')) return;
            setBusy(deleteButton, true, 'Excluindo...');
            try {
                const data = await send('/api/cartorio-cejusc/delete.php', { id: form.querySelector('[name="id"]').value });
                if (!data.deleted) throw new Error(data.error || 'O registro não foi excluído.');
                if (App.Page && typeof App.Page.queuePostReloadToast === 'function') App.Page.queuePostReloadToast('success', 'Sucesso', 'Registro CEJUSC excluído.', 'cartorio.postReloadToast');
                global.location.href = '/frame.php?page=cejusc';
            } catch (error) {
                setBusy(deleteButton, false);
                notify('error', 'Erro', error.message || 'Não foi possível excluir o registro.');
            }
        });
    }

    App.CartorioCejusc = { bindCreate: bindCreate, bindEdit: bindEdit };
})(window);
