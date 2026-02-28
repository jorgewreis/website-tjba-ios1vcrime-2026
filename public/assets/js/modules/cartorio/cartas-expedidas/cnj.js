// cnj.js
// Validação e formatação do número do processo CNJ

const CNJ_SUFFIX = '.8.05.0103';
const CNJ_MAX_DIGITS = 20;

function onlyDigits(value) {
    return (value || '').replace(/\D/g, '');
}

function modulo97(valor) {
    let resto = 0;
    for (let i = 0; i < valor.length; i += 1) {
        const digito = valor.charCodeAt(i) - 48; // '0' = 48
        if (digito < 0 || digito > 9) {
            continue;
        }
        resto = (resto * 10 + digito) % 97;
    }
    return resto;
}

function calcularDigitoVerificadorCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) {
        return null;
    }
    const base = digits.slice(0, 7) + digits.slice(9);
    const resto = modulo97(base + '00');
    const dvCalc = 98 - resto;
    return dvCalc.toString().padStart(2, '0');
}

function completarCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length === 11) {
        return `${digits.substring(0, 7)}-${digits.substring(7, 9)}.${digits.substring(9, 13)}${CNJ_SUFFIX}`;
    }
    if (digits.length < CNJ_MAX_DIGITS) {
        const missing = CNJ_MAX_DIGITS - digits.length;
        const suffixDigits = CNJ_SUFFIX.replace(/\D/g, '');
        const adicional = suffixDigits.slice(-missing);
        return digits + adicional;
    }
    return digits;
}

function validarCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) {
        return false;
    }
    const dv = digits.slice(7, 9);
    const dvCalc = calcularDigitoVerificadorCNJ(digits);
    return dvCalc !== null && dv === dvCalc;
}

function formatarCNJ(valor) {
    const digits = onlyDigits(valor);
    if (digits.length !== CNJ_MAX_DIGITS) {
        return valor;
    }
    return `${digits.slice(0, 7)}-${digits.slice(7, 9)}.${digits.slice(9, 13)}.${digits.slice(13, 14)}.${digits.slice(14, 16)}.${digits.slice(16)}`;
}

if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', function () {
        const processoInput = document.getElementById('filter-processo');
        if (processoInput) {
            processoInput.addEventListener('blur', function () {
                const rawValue = processoInput.value.trim();
                let digits = onlyDigits(rawValue);

                if (digits.length === 0) {
                    processoInput.value = '';
                    return;
                }

                if (digits.length < CNJ_MAX_DIGITS) {
                    if (digits.length === 11) {
                        processoInput.value = completarCNJ(digits);
                    }
                    return;
                }

                digits = digits.slice(0, CNJ_MAX_DIGITS);
                if (!validarCNJ(digits)) {
                    processoInput.value = '';
                    return;
                }

                const formatado = formatarCNJ(digits);
                processoInput.value = formatado;
                dispatchCnjValidated(formatado);
            });
        }

        document.querySelectorAll('input[id*="processo"], input[id*="process"]').forEach((input) => {
            if (input.id === 'filter-processo') return;
            attachProcessInputBehavior(input);
        });
    });
}

function attachProcessInputBehavior(input) {
    if (!input) return;
    let lastValue = '';

    const safeSelect = (el, position) => {
        if (typeof el.setSelectionRange === 'function') {
            try {
                el.setSelectionRange(position, position);
            } catch (err) {
                // ignore
            }
        }
    };

    const scheduleValidation = (el) => {
        if (!el) return;
        const validators = window.FormUtils;
        if (!validators || typeof validators.validateInput !== 'function') return;
        setTimeout(() => {
            const result = validators.validateInput(el);
            if (typeof validators.applyValidationFeedback === 'function') {
                validators.applyValidationFeedback(el, result);
            }
        }, 100);
    };

    input.addEventListener('input', function () {
        const cursorPosition = (typeof this.selectionStart === 'number') ? this.selectionStart : this.value.length;
        const oldValue = lastValue;
        const newValue = this.value;
        const apenasNumeros = newValue.replace(/\D/g, '');
        const shouldAutoComplete = apenasNumeros.length === 13 && !oldValue.endsWith(CNJ_SUFFIX);

        if (shouldAutoComplete) {
            this.value = formatarCNJ(newValue, true);
            safeSelect(this, this.value.length);
            scheduleValidation(this);
        } else {
            const oldLength = this.value.length;
            this.value = formatarCNJ(this.value, false);
            const diff = this.value.length - oldLength;
            safeSelect(this, cursorPosition + diff);
            scheduleValidation(this);
        }

        lastValue = this.value;
    });

    input.addEventListener('keydown', function (e) {
        const apenasNumeros = this.value.replace(/\D/g, '');
        if (e.key === '.' && apenasNumeros.length === 13) {
            e.preventDefault();
            this.value = formatarCNJ(this.value + '.', true);
            scheduleValidation(this);
        }
    });
}

function dispatchCnjValidated(valor) {
    if (!valor || typeof window === 'undefined') {
        return;
    }
    const digits = onlyDigits(valor);
    window.dispatchEvent(new CustomEvent('cnjValidated', {
        detail: {
            numero: valor,
            digits,
        },
    }));
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        calcularDigitoVerificadorCNJ,
        validarCNJ,
        formatarCNJ,
        completarCNJ,
        onlyDigits,
    };
}
