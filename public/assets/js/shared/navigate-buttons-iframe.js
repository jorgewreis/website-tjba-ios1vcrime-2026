/**
 * Versão de navigate-buttons.js para uso em páginas carregadas em iframe.
 * Toda navegação _self usa window.parent.location.href em vez de window.location.href.
 * Links _blank abrem normalmente no próprio click.
 */
(function () {
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-nav-href]');
        if (!el) return;
        if (el.tagName !== 'BUTTON' && el.getAttribute('role') !== 'button') return;
        if (el.disabled || el.classList.contains('disabled') || el.getAttribute('aria-disabled') === 'true') return;

        var href   = String(el.getAttribute('data-nav-href') || '').trim();
        if (href === '' || href === '#') return;

        e.preventDefault();
        var target = String(el.getAttribute('data-nav-target') || '_self').trim();
        if (target === '_blank') {
            window.open(href, '_blank', 'noopener,noreferrer');
        } else {
            if (window.parent !== window) {
                window.parent.location.href = href;
            } else {
                window.location.href = href;
            }
        }
    });
})();
