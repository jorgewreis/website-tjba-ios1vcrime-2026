// cnj.js
// Padronização de número do processo CNJ
// Regra: se receber 13 dígitos (ou 15 chars mascarados equivalentes), completa com ".8.05.0103",
// valida DV e, se inválido com 20 dígitos, limpa o campo e dispara cnjInvalid (sem toast no fluxo DataJud).

const CNJ_SUFFIX = '.8.05.0103';
const CNJ_SUFFIX_DIGITS = '8050103';
const CNJ_MAX_DIGITS = 20;

const CNJ_INPUT_SELECTOR = 'input[id="filter-processo"], input[id="gab-mon-processo"], input[id="filter-numero"], input[id="audiencia-processo"], input[id="audiencia-edit-processo"], input[id="midias-edit-processo"], input[data-cnj-input="true"]';

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
    const elegivel = digits.length === 13 || (raw.length === 15 && digits.length === 13);
    if (elegivel) {
        digits += CNJ_SUFFIX_DIGITS;
    }
    return digits.slice(0, CNJ_MAX_DIGITS);
}

function showInvalidToast() {
    // Fluxo DataJud silencioso por decisão de produto.
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
        detail: { numero: valor, digits, inputId: '' },
    }));
}

function dispatchCnjValidatedFromInput(input, formatted, digits) {
    if (typeof window === 'undefined' || !input) return;
    window.dispatchEvent(new CustomEvent('cnjValidated', {
        detail: {
            numero: formatted,
            digits: digits,
            inputId: input.id || '',
        },
    }));
}

function dispatchCnjInvalid(inputId) {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent('cnjInvalid', {
        detail: { inputId: inputId || '' },
    }));
}

/**
 * Valida CNJ completo no input: limpa se DV inválido; formata e dispara cnjValidated se válido.
 * @returns {{ ok: boolean, empty?: boolean, digits: string, formatted: string }}
 */
function applyCnjValidationToInput(input, options) {
    const opts = Object.assign({ dispatchValidated: true }, options || {});
    if (!input || input.readOnly || input.disabled) {
        return { ok: false, empty: true, digits: '', formatted: '' };
    }

    const result = normalizeAndValidateCNJValue(input.value, { toastOnInvalid: false });
    if (result.empty) {
        input.value = '';
        delete input.dataset.lastCnjDispatchedDigits;
        return result;
    }

    if (!result.ok) {
        const digitCount = onlyDigits(input.value).length;
        input.value = '';
        delete input.dataset.lastCnjDispatchedDigits;
        if (digitCount >= CNJ_MAX_DIGITS) {
            dispatchCnjInvalid(input.id || '');
        }
        return result;
    }

    input.value = result.formatted;
    const prev = input.dataset.lastCnjDispatchedDigits || '';
    if (prev !== result.digits) {
        input.dataset.lastCnjDispatchedDigits = result.digits;
        if (opts.dispatchValidated) {
            dispatchCnjValidatedFromInput(input, result.formatted, result.digits);
        }
    }
    return result;
}

function bindProcessoInput(input) {
    if (!input || input.readOnly || input.disabled) return;
    if (input.dataset.cnjBound === '1') return;
    input.dataset.cnjBound = '1';

    input.addEventListener('input', function () {
        this.value = formatarCNJParcial(this.value);
        const digits = onlyDigits(this.value);
        if (digits.length < CNJ_MAX_DIGITS) {
            delete this.dataset.lastCnjDispatchedDigits;
            return;
        }
        applyCnjValidationToInput(this);
    });

    input.addEventListener('blur', function () {
        const digits = onlyDigits(this.value);
        if (digits.length === 0) {
            this.value = '';
            delete this.dataset.lastCnjDispatchedDigits;
            return;
        }
        applyCnjValidationToInput(this);
    });
}

function bindFormSubmitValidation(input) {
    const form = input && input.form ? input.form : null;
    if (!form) return;
    if (form.dataset.cnjBound === '1') return;

    form.addEventListener('submit', function (event) {
        const cnjInputs = form.querySelectorAll(CNJ_INPUT_SELECTOR);
        for (let i = 0; i < cnjInputs.length; i += 1) {
            const el = cnjInputs[i];
            if (el.readOnly || el.disabled) continue;
            const result = normalizeAndValidateCNJValue(el.value, { toastOnInvalid: false });
            if (result.empty) continue;
            if (!result.ok) {
                el.value = '';
                delete el.dataset.lastCnjDispatchedDigits;
                dispatchCnjInvalid(el.id || '');
                event.preventDefault();
                el.focus();
                return;
            }
            el.value = result.formatted;
            el.dataset.lastCnjDispatchedDigits = result.digits;
        }
    });

    form.dataset.cnjBound = '1';
}

function bindAllCNJInputs(root) {
    const scope = root && root.querySelectorAll ? root : document;
    const inputs = scope.querySelectorAll(CNJ_INPUT_SELECTOR);
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
    window.bindAllCNJInputs = bindAllCNJInputs;
    window.applyCnjValidationToInput = applyCnjValidationToInput;

    window.addEventListener('DOMContentLoaded', function () {
        bindAllCNJInputs(document);
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        calcularDigitoVerificadorCNJ,
        validarCNJ,
        formatarCNJ,
        onlyDigits,
        normalizeAndValidateCNJValue,
        bindAllCNJInputs,
        applyCnjValidationToInput,
    };
}
