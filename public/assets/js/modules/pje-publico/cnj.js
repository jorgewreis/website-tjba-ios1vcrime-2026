(function (global) {
    'use strict';

    function onlyDigits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function mod97(value) {
        var remainder = 0;
        for (var i = 0; i < value.length; i += 1) {
            remainder = (remainder * 10 + Number(value.charAt(i))) % 97;
        }
        return remainder;
    }

    function validarCNJ(value) {
        var digits = onlyDigits(value);
        if (digits.length !== 20) return false;

        var sequential = digits.slice(0, 7);
        var verifier = digits.slice(7, 9);
        var year = digits.slice(9, 13);
        var segment = digits.slice(13, 14);
        var court = digits.slice(14, 16);
        var origin = digits.slice(16, 20);
        var base = sequential + year + segment + court + origin + '00';
        var expected = String(98 - mod97(base)).padStart(2, '0');

        return verifier === expected;
    }

    function formatarCNJ(value) {
        var digits = onlyDigits(value).slice(0, 20);
        var parts = [
            digits.slice(0, 7),
            digits.slice(7, 9),
            digits.slice(9, 13),
            digits.slice(13, 14),
            digits.slice(14, 16),
            digits.slice(16, 20)
        ];

        var output = parts[0];
        if (parts[1]) output += '-' + parts[1];
        if (parts[2]) output += '.' + parts[2];
        if (parts[3]) output += '.' + parts[3];
        if (parts[4]) output += '.' + parts[4];
        if (parts[5]) output += '.' + parts[5];
        return output;
    }

    function bindInput(input) {
        if (!input) return;
        input.addEventListener('input', function () {
            input.value = formatarCNJ(input.value);
        });
    }

    global.PjePublicoCNJ = {
        onlyDigits: onlyDigits,
        validarCNJ: validarCNJ,
        formatarCNJ: formatarCNJ,
        bindInput: bindInput
    };
})(typeof window !== 'undefined' ? window : this);
