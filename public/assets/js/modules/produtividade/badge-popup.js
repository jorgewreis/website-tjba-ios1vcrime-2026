(function () {
    'use strict';

    function notifySuccess(message) {
        var catalog = window.App && window.App.ToastCatalog ? window.App.ToastCatalog : null;
        if (catalog && typeof catalog.notifyByKey === 'function') {
            catalog.notifyByKey(
                'produtividade.badge.unlock.success',
                { badge_nome: String(message || '').replace(/^Badge desbloqueado:\s*/i, '') },
                'success',
                'Sucesso',
                message
            ).catch(function () {
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', message: message });
                    return;
                }
                if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
                    window.App.Toast.show({ type: 'success', message: message });
                }
            });
            return;
        }
        if (typeof window.showToast === 'function') {
            window.showToast({ type: 'success', message: message });
            return;
        }
        if (window.App && window.App.Toast && typeof window.App.Toast.show === 'function') {
            window.App.Toast.show({ type: 'success', message: message });
        }
    }

    function ensureHost() {
        var host = document.getElementById('badge-popup-host');
        if (host) {
            return host;
        }
        host = document.createElement('div');
        host.id = 'badge-popup-host';
        host.style.position = 'fixed';
        host.style.right = '16px';
        host.style.bottom = '16px';
        host.style.display = 'flex';
        host.style.flexDirection = 'column-reverse';
        host.style.gap = '8px';
        host.style.zIndex = '9999';
        document.body.appendChild(host);
        return host;
    }

    function renderPopup(item) {
        var host = ensureHost();
        var card = document.createElement('div');
        card.style.width = '320px';
        card.style.maxWidth = 'calc(100vw - 32px)';
        card.style.background = '#101828';
        card.style.color = '#fff';
        card.style.padding = '12px 14px';
        card.style.borderRadius = '10px';
        card.style.boxShadow = '0 8px 24px rgba(16,24,40,.35)';
        card.style.transform = 'translateY(20px)';
        card.style.opacity = '0';
        card.style.transition = 'all .28s ease';
        card.innerHTML = ''
            + '<div style="font-size:12px;opacity:.85">Nova conquista</div>'
            + '<div style="font-size:16px;font-weight:700;margin-top:2px">' + String(item.nome || 'Badge desbloqueado') + '</div>'
            + '<div style="font-size:13px;opacity:.9;margin-top:4px">' + String(item.descricao || 'Parabéns pelo desempenho!') + '</div>';

        host.appendChild(card);
        window.setTimeout(function () {
            card.style.transform = 'translateY(0)';
            card.style.opacity = '1';
        }, 20);
        window.setTimeout(function () {
            card.style.transform = 'translateX(120%)';
            card.style.opacity = '0';
        }, 4200);
        window.setTimeout(function () {
            if (card.parentNode) {
                card.parentNode.removeChild(card);
            }
        }, 4700);
    }

    function markNotified(url, concedidoId) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ concedido_id: concedidoId })
        }).catch(function () {
            return null;
        });
    }

    function init() {
        var pendingUrl = '/api/produtividade/badges/pendentes.php?limit=5';
        var markUrl = '/api/produtividade/badges/marcar-notificado.php';

        fetch(pendingUrl, { credentials: 'same-origin' })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    return;
                }
                var rows = result.data && Array.isArray(result.data.rows) ? result.data.rows : [];
                rows.forEach(function (item, idx) {
                    window.setTimeout(function () {
                        renderPopup(item);
                        notifySuccess('Badge desbloqueado: ' + String(item.nome || 'Nova conquista'));
                        if (item.concedido_id) {
                            markNotified(markUrl, String(item.concedido_id));
                        }
                    }, idx * 650);
                });
            })
            .catch(function () {
                return null;
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
