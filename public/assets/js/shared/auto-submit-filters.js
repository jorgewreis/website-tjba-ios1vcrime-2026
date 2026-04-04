/**
 * Submete formulários GET de listagem ao alterar filtros (select, data, texto com debounce).
 * Marque o <form> com data-auto-submit-filters.
 */
(function (global) {
    'use strict';

    var DEBOUNCE_MS = 550;

    function submitFilterForm(form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    }

    function bindForm(form) {
        var textTimer = null;

        function scheduleTextSubmit() {
            if (textTimer !== null) {
                global.clearTimeout(textTimer);
            }
            textTimer = global.setTimeout(function () {
                textTimer = null;
                submitFilterForm(form);
            }, DEBOUNCE_MS);
        }

        form.querySelectorAll('select').forEach(function (el) {
            el.addEventListener('change', function () {
                submitFilterForm(form);
            });
        });

        form.querySelectorAll('input[type="date"],input[type="datetime-local"],input[type="month"],input[type="week"]').forEach(function (el) {
            el.addEventListener('change', function () {
                submitFilterForm(form);
            });
        });

        form.querySelectorAll('input[type="text"],input[type="search"],input[type="tel"],textarea').forEach(function (el) {
            el.addEventListener('input', scheduleTextSubmit);
            el.addEventListener('change', function () {
                if (textTimer !== null) {
                    global.clearTimeout(textTimer);
                    textTimer = null;
                }
                submitFilterForm(form);
            });
            el.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    if (textTimer !== null) {
                        global.clearTimeout(textTimer);
                        textTimer = null;
                    }
                    submitFilterForm(form);
                }
            });
        });
    }

    function init() {
        global.document.querySelectorAll('form[data-auto-submit-filters][method="get"]').forEach(bindForm);
    }

    if (global.document.readyState === 'loading') {
        global.document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
