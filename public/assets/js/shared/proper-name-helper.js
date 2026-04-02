(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    function formatProper(value, maxLen) {
        const max = typeof maxLen === 'number' && maxLen > 0 ? maxLen : 255;
        const v = String(value || '');
        if (global.TextFormatter && typeof global.TextFormatter.formatProperName === 'function') {
            return global.TextFormatter.formatProperName(v, max);
        }
        // Fallback simples: trim e capitalização básica
        return v.trim().split(/\s+/).map(function (word) {
            if (!word) return '';
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }).join(' ').slice(0, max);
    }

    function applyOnBlur(element, maxLen) {
        if (!element || typeof element.addEventListener !== 'function') return;
        element.addEventListener('blur', function () {
            element.value = formatProper(element.value, maxLen);
        });
    }

    function applyOnSelector(selector, maxLen) {
        if (!selector || !global.document) return;
        const nodes = global.document.querySelectorAll(selector);
        nodes.forEach(function (el) { applyOnBlur(el, maxLen); });
    }

    App.ProperNameHelper = {
        formatProper: formatProper,
        applyOnBlur: applyOnBlur,
        applyOnSelector: applyOnSelector
    };
})(window);
