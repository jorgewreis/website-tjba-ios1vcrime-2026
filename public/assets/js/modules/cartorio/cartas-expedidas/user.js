function hasPermissionToChangeRule(perfis) {
    if (window.App && App.User && typeof App.User.canChangeRule === 'function') {
        return App.User.canChangeRule(perfis);
    }
    return false;
}

function hasPermission() {
    // Retrocompatibilidade: se já houver perfis em memória, usa.
    if (window.__userPerfisCartorio && hasPermissionToChangeRule(window.__userPerfisCartorio)) {
        return true;
    }
    return false;
}

function showRuleButton(btnId) {
    if (window.App && App.User && typeof App.User.setupRuleButton === 'function') {
        return App.User.setupRuleButton({
            buttonId: btnId,
            targetPage: 'cartasexpedidasrules',
            endpoint: typeof window.CARTAS_EXPEDIDAS_PERFIS_ENDPOINT !== 'undefined'
                ? window.CARTAS_EXPEDIDAS_PERFIS_ENDPOINT
                : '/admin/api/getPerfis.php'
        }).then(function (allowed) {
            if (!allowed || !window.App || !App.User || typeof App.User.fetchPerfis !== 'function') {
                return allowed;
            }
            return App.User.fetchPerfis().then(function (perfis) {
                window.__userPerfisCartorio = perfis;
                return allowed;
            });
        });
    }
    return Promise.resolve(false);
}

// Inicialização automática
window.addEventListener('DOMContentLoaded', function () {
    showRuleButton('alterar-regra-btn');
});

function getCurrentUser() {
    if (window.App && App.User && typeof App.User.getCurrentUser === 'function') {
        return App.User.getCurrentUser();
    }
    return null;
}

function getCurrentUserName() {
    if (window.App && App.User && typeof App.User.getCurrentUserName === 'function') {
        return App.User.getCurrentUserName();
    }
    return null;
}

if (typeof window !== 'undefined') {
    window.hasPermission = hasPermission;
    window.getCurrentUser = getCurrentUser;
    window.getCurrentUserName = getCurrentUserName;
}
