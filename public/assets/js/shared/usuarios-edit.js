(function (global) {
    'use strict';

    const App = global.App || (global.App = {});
    const common = App.RecordEditCommon || null;

    function notify(type, title, message) {
        if (common) return common.notify(type, title, message);
        if (typeof global.showToast === 'function') {
            global.showToast({ type: type, variant: 'full', title: title, message: message });
        }
    }

    function notifyByKey(key, vars, fallbackType, fallbackTitle, fallbackMessage) {
        if (common && typeof common.notifyByKey === 'function') {
            common.notifyByKey(key, vars || {}, fallbackType, fallbackTitle, fallbackMessage);
            return;
        }
        notify(fallbackType, fallbackTitle, fallbackMessage);
    }

    function validarEmail(email) {
        return email && typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
    }

        function formatProper(el, max) {
            if (el && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                el.value = App.ProperNameHelper.formatProper(el.value || '', max || 100);
            } else if (el && typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                el.value = global.TextFormatter.formatProperName(el.value || '', max || 100);
            }
    }

    function initialize() {
        const form = global.document.getElementById('form-edit-usuario');
        const formExcluir = global.document.getElementById('form-excluir-usuario');
        const nome = global.document.getElementById('edit-nome');
        const sobrenome = global.document.getElementById('edit-sobrenome');
        const email = global.document.getElementById('edit-email');
        const telefone = global.document.getElementById('edit-telefone');
        const sexo = global.document.getElementById('edit-sexo');
        const dataNasc = global.document.getElementById('edit-data-nascimento');
        const status = global.document.getElementById('edit-status');
        const cargo = global.document.getElementById('edit-cargo');
        const setor = global.document.getElementById('edit-setor');
        const salvar = global.document.getElementById('salvar-btn');
        const voltar = global.document.getElementById('voltar-btn');
        const excluir = global.document.getElementById('excluir-btn');

        if (voltar) {
            voltar.addEventListener('click', function () { global.location.href = '/frame.php?page=usuarioslist'; });
        }

        function syncCargoToSetor() {
            if (!cargo || !setor) return;
            const cargoVal = (cargo.value || '').trim();
            if (cargoVal === 'Magistrada') {
                setor.value = 'Gabinete';
                setor.disabled = true;
                setor.classList.add('readonly');
            } else {
                setor.disabled = false;
                setor.classList.remove('readonly');
            }
        }

        const perfisInputs = global.document.querySelectorAll('input[name="perfil[]"]');

        function collectPerfis() {
            const list = [];
            perfisInputs.forEach(function (cb) {
                if (cb.checked) {
                    list.push((cb.value || '').trim().toUpperCase());
                }
            });
            return list.sort();
        }

        function getState() {
            return {
                nome: nome ? (nome.value || '').trim() : '',
                sobrenome: sobrenome ? (sobrenome.value || '').trim() : '',
                email: email ? (email.value || '').trim() : '',
                telefone: telefone ? (telefone.value || '').trim() : '',
                sexo: sexo ? (sexo.value || '').trim() : '',
                dataNascimento: dataNasc ? (dataNasc.value || '').trim() : '',
                status: status ? (status.value || '').trim() : '',
                cargo: cargo ? (cargo.value || '').trim() : '',
                setor: setor ? (setor.value || '').trim() : '',
                perfis: collectPerfis()
            };
        }

        const initialState = getState();

        function isValid() {
            const nomeVal = nome ? nome.value.trim() : '';
            const sobrenomeVal = sobrenome ? sobrenome.value.trim() : '';
            const emailVal = email ? email.value.trim() : '';
            const sexoVal = sexo ? sexo.value.trim() : '';
            if (!nomeVal || !sobrenomeVal) return false;
            if (!emailVal || !validarEmail(emailVal)) return false;
            if (sexoVal !== 'Feminino' && sexoVal !== 'Masculino' && sexoVal !== 'Outro') return false;
            return true;
        }

        function hasChanges() {
            return JSON.stringify(getState()) !== JSON.stringify(initialState);
        }

        function updateSalvar() {
            if (salvar) salvar.disabled = !isValid() || !hasChanges();
        }

        function showFieldError(key, message) {
            if (!message) return;
            notify('error', 'Validação', message);
        }

        if (cargo) cargo.addEventListener('change', function () { syncCargoToSetor(); updateSalvar(); });
        if (setor) setor.addEventListener('change', updateSalvar);
        if (status) status.addEventListener('change', updateSalvar);
        if (sexo) sexo.addEventListener('change', updateSalvar);
        if (telefone) {
            telefone.addEventListener('input', updateSalvar);
            telefone.addEventListener('change', updateSalvar);
        }
        if (dataNasc) {
            dataNasc.addEventListener('input', updateSalvar);
            dataNasc.addEventListener('change', updateSalvar);
        }
        perfisInputs.forEach(function (cb) { cb.addEventListener('change', updateSalvar); });

        [nome, sobrenome].forEach(function (el) {
            if (!el) return;
            if (App.ProperNameHelper) App.ProperNameHelper.applyOnBlur(el, 100);
            el.addEventListener('input', updateSalvar);
        });

        if (email) {
            email.addEventListener('blur', function () {
                const ok = !email.value || validarEmail(email.value);
                if (!ok) showFieldError('email', 'E-mail inválido.');
                updateSalvar();
            });
            email.addEventListener('input', updateSalvar);
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                formatProper(nome, 100);
                formatProper(sobrenome, 100);
                if (!isValid()) {
                    e.preventDefault();
                    notifyByKey(
                        'generic.validation.review.required',
                        {},
                        'error',
                        'Validação',
                        'Revise os campos obrigatórios e corrija os dados informados.'
                    );
                }
            });
        }

        if (excluir && formExcluir) {
            excluir.addEventListener('click', function () {
                formExcluir.submit();
            });
        }

        syncCargoToSetor();
        updateSalvar();
    }

    App.UsuariosEdit = { initialize: initialize };
})(window);
