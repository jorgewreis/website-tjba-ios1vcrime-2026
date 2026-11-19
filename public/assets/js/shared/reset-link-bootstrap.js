(function () {
    'use strict';

    var frame = document.getElementById('login-iframe');
    var revealFrame = function () {
        if (frame) frame.style.visibility = 'visible';
    };
    var hash = window.location.hash;
    if (!hash || !frame) {
        revealFrame();
        return;
    }

    var token = new URLSearchParams(hash.slice(1)).get('token');
    if (!token) {
        revealFrame();
        return;
    }

    var csrfMeta = document.querySelector('meta[name="reset-bootstrap-csrf"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';
    if (!csrfToken) {
        showRetry();
        return;
    }

    function showRetry() {
        var status = document.getElementById('reset-bootstrap-status');
        if (!status) {
            status = document.createElement('div');
            status.id = 'reset-bootstrap-status';
            status.setAttribute('role', 'alert');
            status.setAttribute('aria-live', 'assertive');
            var parent = frame && frame.parentNode ? frame.parentNode : document.body;
            parent.insertBefore(status, frame || null);
        }
        status.textContent = 'Não foi possível validar o link. Ele continua disponível nesta aba; recarregue a página para tentar novamente.';
        if (frame) frame.style.visibility = 'visible';
    }

    var body = new URLSearchParams();
    body.set('reset_action', 'stage_token');
    body.set('csrf_token', csrfToken);
    body.set('token', token);

    window.fetch(window.location.pathname, {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
    }).then(function (response) {
        if (!response.ok) throw new Error('reset link rejected');
        token = '';
        window.history.replaceState(null, '', window.location.pathname + window.location.search);
        window.location.replace(window.location.pathname);
    }).catch(function () {
        showRetry();
    });
})();
