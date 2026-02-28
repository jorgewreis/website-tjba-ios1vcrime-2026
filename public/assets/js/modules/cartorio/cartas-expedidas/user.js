// user.js
// Funções relacionadas ao usuário e permissões.
// Perfis para alterar regra de alocação: obtidos da tabela usuario_perfil via /admin/api/getPerfis.php

var PERFIS_ALTERAR_REGRA = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

function hasPermissionToChangeRule(perfis) {
    if (!Array.isArray(perfis) || perfis.length === 0) return false;
    const allowed = PERFIS_ALTERAR_REGRA.map(function (p) { return p.toUpperCase(); });
    return perfis.some(function (perfil) {
        var p = (perfil && String(perfil)).toUpperCase().trim();
        return allowed.indexOf(p) !== -1;
    });
}

function hasPermission() {
    // Retrocompatibilidade: se já houver perfis em memória, usa.
    if (window.__userPerfisCartorio && hasPermissionToChangeRule(window.__userPerfisCartorio)) {
        return true;
    }
    return false;
}

function showRuleButton(btnId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;

    var endpoint = typeof window.CARTAS_EXPEDIDAS_PERFIS_ENDPOINT !== 'undefined'
        ? window.CARTAS_EXPEDIDAS_PERFIS_ENDPOINT
        : '/admin/api/getPerfis.php';

    fetch(endpoint, { credentials: 'same-origin' })
        .then(function (response) {
            if (!response.ok) throw new Error('getPerfis');
            return response.json();
        })
        .then(function (data) {
            var perfis = (data && data.perfis) ? data.perfis : [];
            if (typeof window.__userPerfisCartorio === 'undefined') {
                window.__userPerfisCartorio = perfis;
            }
            if (hasPermissionToChangeRule(perfis)) {
                btn.style.display = '';
                btn.addEventListener('click', function () {
                    window.location.href = '/frame.php?page=cartasexpedidasrules';
                });
            } else {
                btn.style.display = 'none';
            }
        })
        .catch(function () {
            btn.style.display = 'none';
        });
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
