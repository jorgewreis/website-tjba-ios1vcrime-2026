/**
 * Helper para exibir toasts a partir de páginas carregadas em iframe (frame.php).
 * Se a página estiver no iframe, envia postMessage para o parent (home) exibir o toast.
 * Caso contrário, usa App.Toast diretamente.
 * Uso: showToast({ type, message, title?, variant? }) — variant é só dica; toast.js aplica política (success→simple, error→full, etc.).
 */
(function (global) {
    'use strict';

    function getToastTargetWindow() {
        if (global.top && global.top !== global) {
            return global.top;
        }
        if (global.parent && global.parent !== global) {
            return global.parent;
        }
        return null;
    }

    function showToast(options) {
        var opts = typeof options === 'string' ? { message: options } : (options || {});
        var targetWin = getToastTargetWindow();
        if (targetWin) {
            try {
                targetWin.postMessage({ type: 'APP_TOAST', payload: opts }, '*');
                return;
            } catch (err) {
                /* janela alvo inacessível; tenta toast no próprio documento */
            }
        }
        if (global.App && global.App.Toast && typeof global.App.Toast.show === 'function') {
            global.App.Toast.show(opts);
        }
    }

    global.showToast = showToast;
})(typeof window !== 'undefined' ? window : this);
