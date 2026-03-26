(function () {
    'use strict';

    if (!window.__IS_ESTAGIARIO_PROFILE) {
        return;
    }

    var page = new URLSearchParams(window.location.search || '').get('page') || '';
    if (!/edit$/i.test(page)) {
        return;
    }

    var destructiveTextRe = /\b(excluir|remover|deletar|apagar)\b/i;
    var knownDeleteSelector = [
        'button[id*="excluir"]',
        'button[id*="delete"]',
        'button[class*="excluir"]',
        'button[class*="delete"]',
        'button[data-action*="excluir"]',
        'button[data-action*="delete"]',
        '[data-parte-remove]',
        '.btn-del-end',
        '.btn-del-tel',
        '.btn-del-setor'
    ].join(',');

    function textFrom(el) {
        var text = [
            el.textContent || '',
            el.getAttribute('title') || '',
            el.getAttribute('aria-label') || '',
            el.getAttribute('id') || '',
            el.className || ''
        ].join(' ').trim();
        return text;
    }

    function isDeleteActionElement(el) {
        if (!el || typeof el.matches !== 'function') {
            return false;
        }

        if (el.matches(knownDeleteSelector)) {
            return true;
        }

        return destructiveTextRe.test(textFrom(el));
    }

    function hideDeleteElement(el) {
        if (!el || el.dataset.deleteHiddenByEstagiario === '1') {
            return;
        }
        el.dataset.deleteHiddenByEstagiario = '1';
        el.style.display = 'none';
        el.hidden = true;
        el.setAttribute('aria-hidden', 'true');
        el.setAttribute('tabindex', '-1');
    }

    function hideAllDeleteActions() {
        document.querySelectorAll('button, a, [role="button"]').forEach(function (el) {
            if (isDeleteActionElement(el)) {
                hideDeleteElement(el);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest
            ? event.target.closest('button, a, [role="button"]')
            : null;
        if (!trigger || !isDeleteActionElement(trigger)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        hideDeleteElement(trigger);
    }, true);

    var observer = new MutationObserver(function () {
        hideAllDeleteActions();
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideAllDeleteActions);
    } else {
        hideAllDeleteActions();
    }
})();
