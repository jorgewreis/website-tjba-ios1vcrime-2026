(function (global) {
    const App = global.App || (global.App = {});

    function somenteDigitos(value) {
        return (value || '').replace(/\D+/g, '');
    }

    function formatar(digitos) {
        return digitos.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
    }

    function aplicarMascaraParcial(valor) {
        const digits = somenteDigitos(valor).slice(0, 14);
        const parts = [
            digits.slice(0, 2),
            digits.slice(2, 5),
            digits.slice(5, 8),
            digits.slice(8, 12),
            digits.slice(12, 14)
        ];
        let out = parts[0];
        if (parts[1]) out += '.' + parts[1];
        if (parts[2]) out += '.' + parts[2];
        if (parts[3]) out += '/' + parts[3];
        if (parts[4]) out += '-' + parts[4];
        return out;
    }

    function validar(digitos14) {
        if (!/^\d{14}$/.test(digitos14)) return false;
        if (/^(\d)\1{13}$/.test(digitos14)) return false;

        var pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        var soma = 0;
        for (var i = 0; i < 12; i++) soma += Number(digitos14[i]) * pesos1[i];
        var resto = soma % 11;
        var dv1 = resto < 2 ? 0 : 11 - resto;
        if (Number(digitos14[12]) !== dv1) return false;

        var pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        soma = 0;
        for (i = 0; i < 13; i++) soma += Number(digitos14[i]) * pesos2[i];
        resto = soma % 11;
        var dv2 = resto < 2 ? 0 : 11 - resto;
        return Number(digitos14[13]) === dv2;
    }

    function verifica(selector) {
        var input = document.querySelector(selector);
        if (!input) return false;
        var digits = somenteDigitos(input.value);
        if (digits.length !== 14 || !validar(digits)) {
            input.value = '';
            return false;
        }
        input.value = formatar(digits);
        return true;
    }

    function init(selector) {
        if (typeof selector === 'undefined') selector = '#cnpj';
        var input = document.querySelector(selector);
        if (!input) return;

        input.addEventListener('input', function () {
            input.value = aplicarMascaraParcial(input.value);
        });

        ['change', 'blur'].forEach(function (event) {
            input.addEventListener(event, function () {
                verifica(selector);
            });
        });
    }

    App.CNPJ = {
        somenteDigitos: somenteDigitos,
        formatar: formatar,
        aplicarMascaraParcial: aplicarMascaraParcial,
        validar: validar,
        init: init,
        verifica: verifica
    };
})(window);
