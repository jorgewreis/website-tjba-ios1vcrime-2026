/**
 * Toast informativo quando produtividade automática interna regista ponto (catálogo toast_messages.php).
 * Usar em iframes internos: chave em produtividade_toast_key vinda do JSON ou PHP.
 */
(function (global) {
    'use strict';

    function showProdutividadeToastByCatalogKey(key) {
        if (!key) {
            return;
        }
        var catalog = global.App && global.App.ToastCatalog;
        if (catalog && typeof catalog.notifyByKey === 'function') {
            catalog.notifyByKey(String(key), {}).catch(function () {
                if (typeof global.showToast === 'function') {
                    global.showToast({
                        type: 'info',
                        variant: 'full',
                        title: 'Produtividade',
                        message: '+1 ponto de produtividade registado.'
                    });
                }
            });
            return;
        }
        if (typeof global.showToast === 'function') {
            global.showToast({
                type: 'info',
                variant: 'full',
                title: 'Produtividade',
                message: '+1 ponto de produtividade registado.'
            });
        }
    }

    global.showProdutividadeToastByCatalogKey = showProdutividadeToastByCatalogKey;
})(typeof window !== 'undefined' ? window : this);
