/**
 * Copia o número do processo para a área de transferência ao clicar em .process-info .major-info
 * e exibe toast de sucesso. Usado nas páginas de listagem (Guias, Cartas Expedidas, Cartas Recebidas, Cejusc).
 */
(function () {
    'use strict';

    function showSuccessToast(text) {
        if (typeof window.showToast === 'function') {
            window.showToast({
                type: 'success',
                variant: 'full',
                title: 'Sucesso',
                message: 'O número ' + text + ' foi copiado com sucesso.'
            });
        }
    }

    function copyToClipboard(text, done) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                fallbackCopy(text, done);
            });
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, done) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (done) done();
        } catch (err) {
            if (done) done();
        }
        document.body.removeChild(textarea);
    }

    document.addEventListener('click', function (e) {
        var container = e.target && e.target.closest ? e.target.closest('.process-info') : null;
        if (!container) return;
        var major = container.querySelector('.major-info');
        if (!major || !major.contains(e.target)) return;
        var text = (major.textContent || '').trim();
        if (!text) return;
        copyToClipboard(text, function () {
            showSuccessToast(text);
        });
    });
})();
