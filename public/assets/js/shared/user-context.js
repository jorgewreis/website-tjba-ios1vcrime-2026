(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const DEFAULT_RULE_PERFIS = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
    const perfisPromiseByEndpoint = {};

    function getAppContext() {
        return global.__APP_CONTEXT && typeof global.__APP_CONTEXT === 'object'
            ? global.__APP_CONTEXT
            : {};
    }

    function normalizePerfis(perfis) {
        const out = [];
        const seen = new Set();
        (Array.isArray(perfis) ? perfis : []).forEach((raw) => {
            let perfil = String(raw || '').trim().toUpperCase();
            if (perfil === 'DIRETORA' || perfil === 'MAGISTRADA') {
                perfil = 'CHEFIA';
            }
            if (!perfil || seen.has(perfil)) {
                return;
            }
            seen.add(perfil);
            out.push(perfil);
        });
        return out;
    }

    function getBootstrapPerfis(options) {
        const ctx = getAppContext();
        return normalizePerfis([]
            .concat(ctx.initialPerfis || [])
            .concat(ctx.fallbackPerfis || [])
            .concat((options && options.initialPerfis) || [])
            .concat((options && options.fallbackPerfis) || []));
    }

    function clearPerfisCache(endpoint) {
        const key = String(endpoint || '/admin/api/getPerfis.php');
        delete perfisPromiseByEndpoint[key];
    }

    async function fetchPerfis(endpoint = '/admin/api/getPerfis.php', options) {
        const opts = options || {};
        const cacheKey = String(endpoint || '/admin/api/getPerfis.php');
        if (!opts.refresh && perfisPromiseByEndpoint[cacheKey]) {
            return perfisPromiseByEndpoint[cacheKey];
        }

        const promise = (async function () {
            try {
                const response = await fetch(cacheKey, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
                if (response.ok) {
                    const payload = await response.json();
                    if (payload && Array.isArray(payload.perfis)) {
                        return normalizePerfis([].concat(payload.perfis).concat(opts.extraPerfis || []));
                    }
                }
            } catch (error) {
                // ignora; usa bootstrap abaixo
            }

            return normalizePerfis([]
                .concat(getBootstrapPerfis(opts))
                .concat(opts.extraPerfis || []));
        })();

        if (!opts.refresh) {
            perfisPromiseByEndpoint[cacheKey] = promise;
        }

        return promise;
    }

    function hasAnyPerfil(perfis, allowedPerfis) {
        const normalizedPerfis = normalizePerfis(perfis);
        const normalizedAllowed = normalizePerfis(allowedPerfis);
        if (normalizedPerfis.length === 0 || normalizedAllowed.length === 0) {
            return false;
        }
        return normalizedAllowed.some((perfil) => normalizedPerfis.includes(perfil));
    }

    function canChangeRule(perfis, allowedPerfis) {
        const ctx = getAppContext();
        const allowed = Array.isArray(allowedPerfis) && allowedPerfis.length > 0
            ? allowedPerfis
            : (ctx.allowedRulePerfis || DEFAULT_RULE_PERFIS);
        return hasAnyPerfil(perfis, allowed);
    }

    async function setupRuleButton(options) {
        const opts = options || {};
        const buttonId = String(opts.buttonId || opts.btnId || '').trim();
        const targetPage = String(opts.targetPage || '').trim();
        if (!buttonId || !targetPage) {
            return false;
        }

        const btn = global.document.getElementById(buttonId);
        if (!btn) {
            return false;
        }

        btn.style.display = 'none';

        try {
            const perfis = await fetchPerfis(opts.endpoint || '/admin/api/getPerfis.php', {
                initialPerfis: opts.initialPerfis,
                fallbackPerfis: opts.fallbackPerfis,
                refresh: opts.refresh === true
            });

            if (!canChangeRule(perfis, opts.allowedPerfis)) {
                btn.style.display = 'none';
                return false;
            }

            btn.style.display = '';
            if (!btn.dataset.ruleButtonBound) {
                btn.dataset.ruleButtonBound = '1';
                btn.addEventListener('click', function () {
                    global.location.href = '/frame.php?page=' + encodeURIComponent(targetPage);
                });
            }
            return true;
        } catch (error) {
            btn.style.display = 'none';
            return false;
        }
    }

    function parseLegacySessionUser() {
        try {
            const raw = global.sessionStorage.getItem('UserLogin');
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                return parsed[0] || null;
            }
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (error) {
            return null;
        }
    }

    function getCurrentUser() {
        const ctx = getAppContext();
        if (ctx.currentUser && typeof ctx.currentUser === 'object') {
            return ctx.currentUser;
        }
        return parseLegacySessionUser();
    }

    function getCurrentUserName() {
        const current = getCurrentUser();
        if (!current) {
            return null;
        }
        return current.usuario || current.nome || current.name || null;
    }

    async function validateSessionToken(endpoint = '/admin/api/checkToken.php') {
        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const result = await response.json();
            return result && (result.valid === true || result.success === true);
        } catch (error) {
            console.error('Erro ao validar token:', error);
            return false;
        }
    }

    App.User = {
        normalizePerfis,
        clearPerfisCache,
        fetchPerfis,
        hasAnyPerfil,
        canChangeRule,
        setupRuleButton,
        getCurrentUser,
        getCurrentUserName,
        validateSessionToken
    };
})(window);
