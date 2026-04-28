(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(type, title, message) {
        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(type, title, message);
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: type || 'info',
                variant: 'full',
                title: title || 'Informação',
                message: message || ''
            });
        }
    }

    function queuePostReloadToast(type, title, message, storageKey) {
        const key = storageKey || 'cartorio.postReloadToast';
        if (App.Page && typeof App.Page.queuePostReloadToast === 'function') {
            App.Page.queuePostReloadToast(type, title, message, key);
            return;
        }

        try {
            const raw = global.sessionStorage.getItem(key);
            const current = raw ? JSON.parse(raw) : [];
            const list = Array.isArray(current) ? current : ((current && current.message) ? [current] : []);
            list.push({ type: type, title: title, message: message });
            global.sessionStorage.setItem(key, JSON.stringify(list));
        } catch (error) {
            // noop
        }
    }

    function createSecondClickGuard() {
        if (App.Page && typeof App.Page.createSecondClickGuard === 'function') {
            return App.Page.createSecondClickGuard();
        }

        const pending = {};
        return function requireSecondClick(key, message) {
            const now = Date.now();
            const validUntil = Number(pending[key] || 0);
            if (validUntil > now) {
                delete pending[key];
                return true;
            }
            pending[key] = now + 5000;
            notify('info', 'Confirmação', message);
            return false;
        };
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.notifyByKey !== 'function') {
            notify(fallbackType, fallbackTitle, fallbackMessage);
            return;
        }

        catalog.notifyByKey(key, vars || {}).catch(function () {
            notify(fallbackType, fallbackTitle, fallbackMessage);
        });
    }

    function resolveToastMessageByKey(key, vars, fallbackMessage) {
        const catalog = App.ToastCatalog;
        if (!catalog || typeof catalog.resolve !== 'function') {
            return Promise.resolve(fallbackMessage);
        }

        return catalog.resolve(key, vars || {}).then(function (toast) {
            return (toast && toast.message) ? toast.message : fallbackMessage;
        }).catch(function () {
            return fallbackMessage;
        });
    }

    function initialize(config) {
        const settings = Object.assign({
            storageKey: 'cartorio.postReloadToast'
        }, config || {});

        const idGuia = settings.entityId;
        global.document.getElementById(settings.backButtonId || 'voltar-btn')?.addEventListener('click', function () {
            global.location.href = settings.backHref || '/frame.php?page=guias';
        });

        const dataFato = global.document.getElementById(settings.dateFieldId || 'edit-data-fato');
        if (dataFato) {
            const today = new Date();
            today.setDate(today.getDate() - 1);
            dataFato.setAttribute('max', today.toISOString().split('T')[0]);
        }

        if (!idGuia) {
            return;
        }

        const saveButton = global.document.getElementById(settings.saveButtonId || 'salvar-btn');
        const initialData = settings.initialData || {};

        function getCurrentState() {
            const tipoEl = global.document.getElementById('edit-tipo');
            const regimeEl = global.document.getElementById('edit-regime');
            const dataFatoEl = global.document.getElementById('edit-data-fato');
            const diasMultaEl = global.document.getElementById('edit-dias-multa');
            const observacoesEl = global.document.getElementById('edit-observacoes');
            let diasVal = (diasMultaEl && diasMultaEl.value !== '') ? parseInt(diasMultaEl.value, 10) : '';
            if (diasVal !== '' && isNaN(diasVal)) {
                diasVal = '';
            }
            return {
                tipo: tipoEl ? (tipoEl.value || '').trim() : '',
                regime: regimeEl ? (regimeEl.value || '').trim() : '',
                data_fato: dataFatoEl ? (dataFatoEl.value || '').trim() : '',
                dias_multa: diasVal,
                observacoes: observacoesEl ? (observacoesEl.value || '').trim() : ''
            };
        }

        function hasChanges() {
            const current = getCurrentState();
            if ((initialData.tipo || '') !== (current.tipo || '')) return true;
            if ((initialData.regime || '') !== (current.regime || '')) return true;
            if ((initialData.data_fato || '') !== (current.data_fato || '')) return true;
            if (String(initialData.dias_multa ?? '') !== String(current.dias_multa ?? '')) return true;
            if ((initialData.observacoes || '') !== (current.observacoes || '')) return true;
            return false;
        }

        function updateButtonState() {
            if (saveButton) {
                saveButton.disabled = !hasChanges();
            }
        }

        ['edit-tipo', 'edit-regime', 'edit-data-fato', 'edit-dias-multa', 'edit-observacoes'].forEach(function (id) {
            const element = global.document.getElementById(id);
            if (element) {
                element.addEventListener('input', updateButtonState);
                element.addEventListener('change', updateButtonState);
            }
        });
        updateButtonState();

        saveButton?.addEventListener('click', function () {
            const regimeVal = global.document.getElementById('edit-regime')?.value?.trim() || '';
            const dataFatoVal = global.document.getElementById('edit-data-fato')?.value || '';
            if (regimeVal && ['Aberto', 'Semiaberto', 'Fechado'].indexOf(regimeVal) === -1) {
                notifyByKey('cartorio.guias.validation.regime.invalid', {}, 'error', 'Erro', 'Regime inválido. Selecione Aberto, Semiaberto ou Fechado.');
                return;
            }
            if (dataFatoVal) {
                const hoje = new Date();
                hoje.setHours(0, 0, 0, 0);
                if (new Date(dataFatoVal) >= hoje) {
                    notifyByKey('cartorio.guias.validation.data_fato.before.today', {}, 'error', 'Erro', 'A data do fato deve ser anterior à data de hoje.');
                    return;
                }
            }

            const diasMultaRaw = (global.document.getElementById('edit-dias-multa')?.value || '').trim();
            let diasMultaPayload = null;
            if (diasMultaRaw !== '') {
                const parsed = parseInt(diasMultaRaw, 10);
                if (Number.isNaN(parsed) || parsed < 0) {
                    notifyByKey('cartorio.guias.validation.dias_multa.invalid', {}, 'error', 'Erro', 'Informe um número válido (≥ 0) para dias de multa ou deixe em branco.');
                    return;
                }
                diasMultaPayload = parsed;
            }
            const payload = {
                id: idGuia,
                numero_processo: global.document.getElementById('edit-processo')?.value?.trim() || '',
                denunciado: global.document.getElementById('edit-denunciado')?.value?.trim() || '',
                tipo: global.document.getElementById('edit-tipo')?.value || 'Guia - Tipo não informado',
                regime: regimeVal,
                data_fato: dataFatoVal,
                dias_multa: diasMultaPayload,
                observacoes: global.document.getElementById('edit-observacoes')?.value?.trim() || ''
            };

            saveButton.disabled = true;
            fetch(settings.updateEndpoint || '/api/cartorio-guias/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error) {
                        notify('error', 'Erro', data.error);
                        updateButtonState();
                        return;
                    }
                    notifyByKey(
                        'cartorio.guias.edit.save.success',
                        {},
                        'success',
                        'Sucesso',
                        'Alterações salvas.'
                    );
                    if (data.valor_multa_calculado) {
                        notify('success', 'Cálculo automático', 'Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + data.valor_multa_calculado + '.');
                    }
                    global.location.href = settings.backHref || '/frame.php?page=guias';
                })
                .catch(function () {
                    notifyByKey('generic.error.save', {}, 'error', 'Erro', 'Erro ao salvar.');
                    updateButtonState();
                });
        });

        global.document.getElementById(settings.cancelButtonId || 'cancelar-tarefa-btn')?.addEventListener('click', function () {
            const button = this;
            button.disabled = true;
            fetch(settings.cancelEndpoint || '/api/cartorio-guias/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGuia })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error || data.deleted === false) {
                        notify('error', 'Erro', data.error || 'Erro ao excluir.');
                        button.disabled = false;
                        return;
                    }
                    notifyByKey(
                        'cartorio.guias.edit.cancel.success',
                        {},
                        'success',
                        'Sucesso',
                        'Guia cancelada.'
                    );
                    global.location.href = settings.backHref || '/frame.php?page=guias';
                })
                .catch(function () {
                    notifyByKey('generic.error.cancel', {}, 'error', 'Erro', 'Erro ao cancelar.');
                    button.disabled = false;
                });
        });

        const requireSecondClick = createSecondClickGuard();
        let deleteConfirmMessage = settings.deleteConfirmMessage || 'Clique novamente em Excluir para confirmar a exclusão.';
        resolveToastMessageByKey('cartorio.guias.delete.confirm', {}, deleteConfirmMessage).then(function (message) {
            deleteConfirmMessage = message;
        });
        global.document.getElementById(settings.deleteButtonId || 'excluir-btn')?.addEventListener('click', function () {
            if (!requireSecondClick(settings.deleteConfirmKey || 'excluir-guia', deleteConfirmMessage)) {
                return;
            }

            const button = this;
            button.disabled = true;
            fetch(settings.deleteEndpoint || '/api/cartorio-guias/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: idGuia })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error) {
                        notify('error', 'Erro', data.error);
                        button.disabled = false;
                        return;
                    }
                    (Array.isArray(data.subtarefas_toasts) ? data.subtarefas_toasts : []).forEach(function (message) {
                        queuePostReloadToast('info', 'Subtarefa excluída', message, settings.storageKey);
                    });
                    if (data.tarefa_toast) {
                        queuePostReloadToast('success', 'Sucesso', data.tarefa_toast, settings.storageKey);
                    }
                    global.location.href = settings.backHref || '/frame.php?page=guias';
                })
                .catch(function () {
                    notifyByKey('generic.error.delete', {}, 'error', 'Erro', 'Erro ao excluir.');
                    button.disabled = false;
                });
        });
    }

    App.CartorioGuiasEdit = {
        initialize: initialize
    };
})(window);
