// user.js
// Funções relacionadas ao usuário e permissões


function hasPermission() {
    // Recupera array de usuários do sessionStorage
    const userData = sessionStorage.getItem('UserLogin');
    if (!userData) return false;
    let users = [];
    try {
        users = JSON.parse(userData);
    } catch (e) {
        return false;
    }
    const allowed = ['admin', 'diretora', 'magistrada'];
    // Verifica se algum usuário tem nucleo permitido
    return users.some(user => {
        if (!user.nucleo) return false;
        return allowed.some(perfil => user.nucleo.toLowerCase().includes(perfil));
    });
}

function showRuleButton(btnId) {
    const btn = document.getElementById(btnId);
    if (btn) {
        if (!hasPermission()) {
            btn.style.display = 'none';
        } else {
            btn.style.display = '';
            btn.addEventListener('click', function () {
                window.location.href = 'cartorio-cartas-expedidas-rules.php';
            });
        }
    }
}

// Inicialização automática
window.addEventListener('DOMContentLoaded', function () {
    showRuleButton('alterar-regra-btn');
});

function parseUserLogin() {
    const raw = sessionStorage.getItem('UserLogin');
    if (!raw) {
        return null;
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function getCurrentUser() {
    const userLogin = parseUserLogin();
    if (!userLogin) {
        return null;
    }
    if (Array.isArray(userLogin)) {
        return userLogin[0] ?? null;
    }
    return typeof userLogin === 'object' ? userLogin : null;
}

function getCurrentUserName() {
    const current = getCurrentUser();
    if (!current) {
        return null;
    }
    return current.usuario ?? current.nome ?? null;
}

if (typeof window !== 'undefined') {
    window.hasPermission = hasPermission;
    window.getCurrentUser = getCurrentUser;
    window.getCurrentUserName = getCurrentUserName;
}
