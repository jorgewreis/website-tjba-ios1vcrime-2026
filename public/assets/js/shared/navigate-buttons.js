/**
 * Navegação via botões com data-nav-href (substitui uso de <a href> em ações de UI).
 * - data-nav-href: URL absoluta ou relativa
 * - data-nav-target="_blank": abre em nova aba (noopener)
 */
(function () {
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-nav-href]');
        if (!el) {
            return;
        }
        if (el.tagName !== 'BUTTON' && el.getAttribute('role') !== 'button') {
            return;
        }
        if (el.disabled || el.classList.contains('disabled') || el.getAttribute('aria-disabled') === 'true') {
            return;
        }
        var href = String(el.getAttribute('data-nav-href') || '').trim();
        if (href === '' || href === '#') {
            return;
        }
        e.preventDefault();
        var target = String(el.getAttribute('data-nav-target') || '_self').trim();
        if (target === '_blank') {
            window.open(href, '_blank', 'noopener,noreferrer');
        } else {
            window.location.href = href;
        }
    });
})();
