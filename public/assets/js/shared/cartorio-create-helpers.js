(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function notify(message, type, customTitle) {
        const kind = type === 'error' ? 'error' : (type === 'warning' ? 'warning' : (type === 'info' ? 'info' : 'success'));
        const title = customTitle || (kind === 'error' ? 'Erro' : (kind === 'warning' ? 'Atenção' : (kind === 'info' ? 'Informação' : 'Sucesso')));

        if (App.Page && typeof App.Page.notify === 'function') {
            App.Page.notify(kind, title, message || '');
            return;
        }

        if (typeof global.showToast === 'function') {
            global.showToast({
                type: kind,
                variant: 'full',
                title: title,
                message: message || ''
            });
        }
    }

    function getValue(id) {
        return global.document.getElementById(id)?.value?.trim() ?? '';
    }

    function formatProperName(value, maxLength) {
        if (App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
            return App.ProperNameHelper.formatProper(value, maxLength || 255);
        }
        if (typeof global.TextFormatter !== 'undefined' && typeof global.TextFormatter.formatProperName === 'function') {
            return global.TextFormatter.formatProperName(value, maxLength || 255);
        }
        return value || '';
    }

    function buildClassePayload(options) {
        const settings = options || {};
        const payload = {};
        const classeCodigo = getValue(settings.codigoFieldId || 'hidden-classe-codigo');
        const classeDescricao = getValue(settings.descricaoFieldId || 'hidden-classe-descricao');
        const classeVal = String(settings.classeValue || '').trim();

        if (classeCodigo) {
            payload.classe_codigo = classeCodigo;
        }

        if (classeDescricao) {
            payload.classe_descricao = classeDescricao;
            return payload;
        }

        if (classeVal && classeVal !== 'Classe não informada' && classeVal !== 'Aguarde...') {
            payload.classe_descricao = classeVal;
            if (!payload.classe_codigo) {
                payload.classe_codigo = classeVal.replace(/\s+/g, '').substring(0, 20);
            }
        }

        return payload;
    }

    function onlyDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function normalizeProcessValue(rawValue, options) {
        const settings = options || {};
        const input = settings.inputId ? global.document.getElementById(settings.inputId) : null;
        const feedback = typeof settings.feedback === 'function' ? settings.feedback : function () {};
        const requiredMessage = settings.requiredMessage || 'Informe o número do processo.';
        const invalidMessage = settings.invalidMessage || 'O número do processo está incompleto ou inválido (20 dígitos).';
        const raw = String(rawValue || '').trim();

        if (!raw) {
            feedback(requiredMessage, 'error');
            return { ok: false, value: '' };
        }

        if (input && typeof global.normalizeAndValidateCNJValue === 'function') {
            const normalized = global.normalizeAndValidateCNJValue(raw, { toastOnInvalid: true });
            if (!normalized.ok) {
                input.value = '';
                input.focus();
                return { ok: false, value: '' };
            }
            input.value = normalized.formatted;
            return { ok: true, value: normalized.formatted };
        }

        const digits = onlyDigits(raw);
        if (digits.length !== 20) {
            feedback(invalidMessage, 'error');
            if (input) {
                input.focus();
            }
            return { ok: false, value: '' };
        }

        return { ok: true, value: raw };
    }

    App.CartorioCreateHelpers = {
        notify: notify,
        getValue: getValue,
        formatProperName: formatProperName,
        buildClassePayload: buildClassePayload,
        onlyDigits: onlyDigits,
        normalizeProcessValue: normalizeProcessValue
    };
})(window);
