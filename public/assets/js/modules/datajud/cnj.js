// cnj.js
// Padronização de número do processo CNJ
// Regra: se receber 13 dígitos (ou 15 chars mascarados equivalentes), completa com ".8.05.0103",
// valida e, se inválido, limpa o campo e exibe toast de erro.

const CNJ_SUFFIX = '.8.05.0103';
const CNJ_SUFFIX_DIGITS = '8050103';
const CNJ_MAX_DIGITS = 20;

function onlyDigits(value) {
    return String(value || '').replace(/\D/g, '');
}

function modulo97(valor) {
    let resto = 0;
    for (let i = 0; i < valor.length; i += 1) {
        const digito = valor.charCodeAt(i) - 48;
        if (digito < 0 || digito > 9) continue;
        resto = (resto * 10 + digito) % 97;
    }
    return resto;
}

function calcularDigitoVerificadorCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) return null;
    const base = digits.slice(0, 7) + digits.slice(9);
    const resto = modulo97(base + '00');
    const dvCalc = 98 - resto;
    return dvCalc.toString().padStart(2, '0');
}

function validarCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) return false;
    const dv = digits.slice(7, 9);
    const dvCalc = calcularDigitoVerificadorCNJ(digits);
    return dvCalc !== null && dv === dvCalc;
}

function formatarCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) return valor;
    return `${digits.slice(0, 7)}-${digits.slice(7, 9)}.${digits.slice(9, 13)}.${digits.slice(13, 14)}.${digits.slice(14, 16)}.${digits.slice(16)}`;
}

function formatarCNJParcial(valor) {
    const digits = onlyDigits(valor).slice(0, CNJ_MAX_DIGITS);
    let out = '';
    for (let i = 0; i < digits.length; i += 1) {
        out += digits[i];
        if (i === 6 && digits.length > 7) out += '-';
        if (i === 8 && digits.length > 9) out += '.';
        if (i === 12 && digits.length > 13) out += '.';
        if (i === 13 && digits.length > 14) out += '.';
        if (i === 15 && digits.length > 16) out += '.';
    }
    return out;
}

function completarComSufixoSeElegivel(rawValue) {
    const raw = String(rawValue || '').trim();
    let digits = onlyDigits(raw);
    // 13 dígitos puros ou 15 caracteres com máscara parcial (equivalente a 13 dígitos)
    const elegivel = digits.length === 13 || (raw.length === 15 && digits.length === 13);
    if (elegivel) {
        digits += CNJ_SUFFIX_DIGITS;
    }
    return digits.slice(0, CNJ_MAX_DIGITS);
}

function showInvalidToast() {
    if (typeof showToast === 'function') {
        showToast({
            type: 'error',
            variant: 'full',
            title: 'Erro',
            message: 'Número do processo incorreto. Verifique e tente novamente.',
        });
    }
}

function normalizeAndValidateCNJValue(rawValue, options) {
    const opts = Object.assign({ clearOnInvalid: false, toastOnInvalid: false }, options || {});
    const raw = String(rawValue || '').trim();
    if (raw === '') {
        return { ok: true, empty: true, digits: '', formatted: '' };
    }

    const digits = completarComSufixoSeElegivel(raw);
    const valid = digits.length === CNJ_MAX_DIGITS && validarCNJ(digits);
    const formatted = valid ? formatarCNJ(digits) : '';

    if (!valid && opts.toastOnInvalid) {
        showInvalidToast();
    }

    return { ok: valid, empty: false, digits, formatted };
}

function dispatchCnjValidated(valor) {
    if (!valor || typeof window === 'undefined') return;
    const digits = onlyDigits(valor);
    window.dispatchEvent(new CustomEvent('cnjValidated', {
        detail: { numero: valor, digits },
    }));
}

function dispatchCnjInvalid(inputId) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent('cnjInvalid', {
        detail: { inputId: inputId || '' },
    }));
}

function bindProcessoInput(input) {
    if (!input || input.readOnly || input.disabled) return;
    if (input.dataset.cnjBound === '1') return;
    input.dataset.cnjBound = '1';

    input.addEventListener('input', function () {
        this.value = formatarCNJParcial(this.value);
        const result = normalizeAndValidateCNJValue(this.value, { toastOnInvalid: false });
        if (!result.ok || result.empty) {
            if (!result.empty && onlyDigits(this.value).length === CNJ_MAX_DIGITS) {
                dispatchCnjInvalid(this.id || '');
            }
            if (onlyDigits(this.value).length < 20) {
                delete this.dataset.lastCnjDispatchedDigits;
            }
            return;
        }
        const prev = this.dataset.lastCnjDispatchedDigits || '';
        if (prev === result.digits) {
            return;
        }
        this.dataset.lastCnjDispatchedDigits = result.digits;
        this.value = result.formatted;
        dispatchCnjValidated(result.formatted);
    });

    input.addEventListener('blur', function () {
        const result = normalizeAndValidateCNJValue(this.value, { toastOnInvalid: true });
        if (result.empty) {
            this.value = '';
            delete this.dataset.lastCnjDispatchedDigits;
            return;
        }
        if (!result.ok) {
            dispatchCnjInvalid(this.id || '');
            this.value = '';
            delete this.dataset.lastCnjDispatchedDigits;
            return;
        }
        this.value = result.formatted;
        const prev = this.dataset.lastCnjDispatchedDigits || '';
        if (prev === result.digits) {
            return;
        }
        this.dataset.lastCnjDispatchedDigits = result.digits;
        dispatchCnjValidated(result.formatted);
    });
}

function bindFormSubmitValidation(input) {
    const form = input && input.form ? input.form : null;
    if (!form) return;
    if (form.dataset.cnjBound === '1') return;

    form.addEventListener('submit', function (event) {
        const cnjInputs = form.querySelectorAll('input[id="filter-processo"], input[id="gab-mon-processo"], input[id="filter-numero"], input[id="audiencia-processo"], input[data-cnj-input="true"]');
        for (let i = 0; i < cnjInputs.length; i += 1) {
            const input = cnjInputs[i];
            if (input.readOnly || input.disabled) continue;
            const result = normalizeAndValidateCNJValue(input.value, { toastOnInvalid: true });
            if (result.empty) continue;
            if (!result.ok) {
                input.value = '';
                event.preventDefault();
                input.focus();
                return;
            }
            input.value = result.formatted;
        }
    });

    form.dataset.cnjBound = '1';
}

function bindAllCNJInputs() {
    const inputs = document.querySelectorAll('input[id="filter-processo"], input[id="gab-mon-processo"], input[id="filter-numero"], input[id="audiencia-processo"], input[data-cnj-input="true"]');
    inputs.forEach(function (input) {
        bindProcessoInput(input);
        bindFormSubmitValidation(input);
    });
}

if (typeof window !== 'undefined') {
    window.normalizeAndValidateCNJValue = normalizeAndValidateCNJValue;
    window.validarCNJ = validarCNJ;
    window.formatarCNJ = formatarCNJ;
    window.onlyDigitsCNJ = onlyDigits;

    window.addEventListener('DOMContentLoaded', function () {
        bindAllCNJInputs();
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        calcularDigitoVerificadorCNJ,
        validarCNJ,
        formatarCNJ,
        onlyDigits,
        normalizeAndValidateCNJValue,
    };
}
