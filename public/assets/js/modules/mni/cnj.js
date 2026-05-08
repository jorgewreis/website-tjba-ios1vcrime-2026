(function (global) {
    'use strict';

    var CNJ_MAX_DIGITS = 20;

    function onlyDigits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function modulo97(valor) {
        var resto = 0;
        for (var i = 0; i < valor.length; i += 1) {
            var digito = valor.charCodeAt(i) - 48;
            if (digito < 0 || digito > 9) continue;
            resto = (resto * 10 + digito) % 97;
        }
        return resto;
    }

    function calcularDvCNJ(digits) {
        if (digits.length !== CNJ_MAX_DIGITS) return null;
        var base = digits.slice(0, 7) + digits.slice(9);
        var resto = modulo97(base + '00');
        var dvCalc = 98 - resto;
        return String(dvCalc).padStart(2, '0');
    }

    function validarCNJ(valor) {
        var digits = onlyDigits(valor);
        if (digits.length !== CNJ_MAX_DIGITS) return false;
        var dv = digits.slice(7, 9);
        var calc = calcularDvCNJ(digits);
        return calc !== null && dv === calc;
    }

    function formatarCNJ(digitsOrValue) {
        var digits = onlyDigits(digitsOrValue);
        if (digits.length !== CNJ_MAX_DIGITS) return String(digitsOrValue || '');
        return digits.slice(0, 7) + '-' + digits.slice(7, 9) + '.'
            + digits.slice(9, 13) + '.' + digits.slice(13, 14) + '.'
            + digits.slice(14, 16) + '.' + digits.slice(16);
    }

    function formatarCNJParcial(valor) {
        var digits = onlyDigits(valor).slice(0, CNJ_MAX_DIGITS);
        var out = '';
        for (var i = 0; i < digits.length; i += 1) {
            out += digits[i];
            if (i === 6 && digits.length > 7) out += '-';
            if (i === 8 && digits.length > 9) out += '.';
            if (i === 12 && digits.length > 13) out += '.';
            if (i === 13 && digits.length > 14) out += '.';
            if (i === 15 && digits.length > 16) out += '.';
        }
        return out;
    }

    function bindInput(input) {
        if (!input) return;
        input.addEventListener('input', function () {
            input.value = formatarCNJParcial(input.value);
        });
    }

    global.MniCNJ = {
        onlyDigits: onlyDigits,
        validarCNJ: validarCNJ,
        formatarCNJ: formatarCNJ,
        bindInput: bindInput
    };
})(typeof window !== 'undefined' ? window : this);
