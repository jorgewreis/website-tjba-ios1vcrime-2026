/**
 * Threshold de badge: apenas dígitos (inteiro), sem máscara monetária / decimal.
 */
(function () {
    'use strict';

    function sanitize(el) {
        if (!el || el.tagName !== 'INPUT') {
            return;
        }
        var digits = String(el.value || '').replace(/\D/g, '');
        if (el.value !== digits) {
            el.value = digits;
        }
    }

    function bind(id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.addEventListener('input', function () {
            sanitize(el);
        });
        el.addEventListener('blur', function () {
            sanitize(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bind('valor_threshold');
        });
    } else {
        bind('valor_threshold');
    }
})();
