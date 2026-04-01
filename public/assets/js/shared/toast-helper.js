/**
 * Helper para exibir toasts a partir de páginas carregadas em iframe (frame.php).
 * Se a página estiver no iframe, envia postMessage para o parent (home) exibir o toast.
 * Caso contrário, usa App.Toast diretamente.
 * Uso: showToast({ type, message, title?, variant? }) — variant é só dica; toast.js aplica política (success→simple, error→full, etc.).
 */
(function (global) {
    'use strict';

    function showToast(options) {
        var opts = typeof options === 'string' ? { message: options } : (options || {});
        if (global.parent !== global) {
            global.parent.postMessage({ type: 'APP_TOAST', payload: opts }, '*');
        } else if (global.App && global.App.Toast && global.App.Toast.show) {
            global.App.Toast.show(opts);
        }
    }

    global.showToast = showToast;
})(typeof window !== 'undefined' ? window : this);
