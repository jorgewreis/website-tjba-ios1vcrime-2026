/**
 * Copia o número do processo para a área de transferência:
 * - clique em .process-info .major-info (listagens legadas);
 * - clique em [data-copy-numero-processo] (ex.: bloco processo em sala de mídias).
 * Toast de sucesso via showToast (iframe) ou App.Toast quando disponível.
 */
(function () {
    'use strict';

    function showSuccessToast(text) {
        var payload = {
            type: 'success',
            variant: 'full',
            title: 'Sucesso',
            message: 'O número ' + text + ' foi copiado com sucesso.'
        };
        if (typeof window.showToast === 'function') {
            window.showToast(payload);
            return;
        }
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({ type: 'APP_TOAST', payload: payload }, '*');
            } catch (err) {
                /* ignore */
            }
            return;
        }
        if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
            window.App.Toast.show(payload);
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
        var copyBtn = e.target && e.target.closest ? e.target.closest('[data-copy-numero-processo]') : null;
        if (copyBtn) {
            if (copyBtn.disabled || copyBtn.getAttribute('aria-disabled') === 'true') {
                return;
            }
            var raw = String(copyBtn.getAttribute('data-copy-numero-processo') || '').trim();
            if (!raw) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            copyToClipboard(raw, function () {
                showSuccessToast(raw);
            });
            return;
        }

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
