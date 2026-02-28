// navigation.js
// Funções de navegação para páginas

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
