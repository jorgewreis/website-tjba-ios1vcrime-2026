// navigation.js
// LEGADO: preferir navigate-buttons.js (data-nav-href) para novos fluxos.
// Mantido apenas por compatibilidade com chamadas antigas de setupNavigationButton.

function setupNavigationButton(btnId, targetPage) {
    if (typeof document === 'undefined') {
        return;
    }
    const button = document.getElementById(btnId);
    if (!button) {
        return;
    }
    button.addEventListener('click', function (event) {
        event.preventDefault();
        window.location.href = targetPage;
    });
}

if (typeof window !== 'undefined') {
    window.setupNavigationButton = setupNavigationButton;
}
