(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function notify(type, title, message) {
        if (common) return common.notify(type, title, message);
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type || 'info', variant: 'full', title: title || 'Informação', message: message || '' });
        }
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (common && typeof common.notifyByKey === 'function') {
            common.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        notify(fallbackType, fallbackTitle, fallbackMessage);
    }

    function initialize(ctx) {
        const context = ctx || global.__ALTERAR_DADOS_CTX || {};
        const serverError = context.serverError || '';
        const serverSuccess = context.serverSuccess || '';
        if (serverError) notify('error', 'Erro', serverError);
        if (!serverError && serverSuccess) {
            notifyByKey(
                'generic.operation.success',
                {},
                'success',
                'Sucesso',
                serverSuccess
            );
        }

        const interno = !!context.interno;
        const avatarEntries = Array.isArray(context.avatarEntries) ? context.avatarEntries : [];
        const initial = context.initial || {};

        const voltar = global.document.getElementById('voltar-btn');
        const salvar = global.document.getElementById('salvar-btn');
        const avatarSelect = global.document.getElementById('edit-avatar-slug');

        function avatarEntryBySlug(slug) {
            const s = String(slug || '').trim();
            for (let i = 0; i < avatarEntries.length; i += 1) {
                if (avatarEntries[i].slug === s) return avatarEntries[i];
            }
            return null;
        }

        function syncAvatarPreview() {
            if (!interno || !avatarSelect) return;
            const img = global.document.getElementById('avatar-preview-img');
            const initialsEl = global.document.getElementById('avatar-preview-initials');
            if (!img || !initialsEl) return;
            const entry = avatarEntryBySlug(avatarSelect.value);
            if (!entry || entry.is_initial) {
                img.setAttribute('hidden', 'hidden');
                img.removeAttribute('src');
                initialsEl.removeAttribute('hidden');
            } else {
                initialsEl.setAttribute('hidden', 'hidden');
                img.src = entry.url;
                img.removeAttribute('hidden');
            }
        }

        function getCurrent() {
            const emailEl = global.document.getElementById('edit-email');
            const telEl = global.document.getElementById('edit-telefone');
            const sexoEl = global.document.getElementById('edit-sexo');
            const cur = {
                email: emailEl ? (emailEl.value || '').trim() : '',
                telefone: telEl ? (telEl.value || '').trim() : '',
                sexo: sexoEl ? (sexoEl.value || '').trim() : ''
            };
            if (interno && avatarSelect) {
                cur.avatarSlug = (avatarSelect.value || '').trim();
            }
            return cur;
        }

        function hasChanges() {
            const cur = getCurrent();
            if ((initial.email || '') !== (cur.email || '')) return true;
            if ((initial.telefone || '') !== (cur.telefone || '')) return true;
            if ((initial.sexo || '') !== (cur.sexo || '')) return true;
            if (interno) {
                if ((initial.avatarSlug || '') !== (cur.avatarSlug || '')) return true;
            }
            return false;
        }

        function updateSalvarState() {
            if (salvar) salvar.disabled = !hasChanges();
        }

        if (voltar) {
            voltar.addEventListener('click', function () {
                global.location.href = '/frame.php?page=dashboard';
            });
        }

        ['edit-email', 'edit-telefone', 'edit-sexo'].forEach(function (id) {
            const el = global.document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateSalvarState);
                el.addEventListener('change', updateSalvarState);
            }
        });

        if (avatarSelect) {
            avatarSelect.addEventListener('change', function () {
                syncAvatarPreview();
                updateSalvarState();
            });
            syncAvatarPreview();
        }

        updateSalvarState();
    }

    App.AlterarDados = { initialize: initialize };
})(window);
