(function (window) {
    'use strict';

    var App = window.App || (window.App = {});
    var bound = false;

    function notify(success) {
        var payload = {
            type: success ? 'success' : 'error',
            title: success ? 'Sucesso' : 'Erro',
            message: success ? 'Texto para WhatsApp copiado com sucesso.' : 'Não foi possível copiar o texto para WhatsApp.'
        };
        if (typeof window.showToast === 'function') window.showToast(payload);
        else if (App.Page && typeof App.Page.notify === 'function') App.Page.notify(payload.type, payload.title, payload.message);
    }

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        var focused = document.activeElement;
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        var copied = false;
        try { copied = document.execCommand('copy'); } catch (error) { /* exibe erro abaixo */ }
        textarea.remove();
        if (focused && typeof focused.focus === 'function') focused.focus();
        notify(copied);
    }

    function bind() {
        if (bound) return;
        bound = true;
        document.addEventListener('click', function (event) {
            var button = event.target.closest('.js-v2-copy-whatsapp');
            if (!button || button.disabled) return;
            event.preventDefault();
            var text = String(button.getAttribute('data-whatsapp-text') || '').trim();
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { notify(true); }).catch(function () { fallbackCopy(text); });
            } else fallbackCopy(text);
        });
    }

    App.WhatsappCopy = { bind: bind };
})(window);
