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

    const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{10,}$/;
    function validarSenha(v) {
        return !!(v && senhaRegex.test(v));
    }
    function validarEmail(email) {
        return email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
    }
    function formatCPF(value) {
        const digits = String(value || '').replace(/\D/g, '');
        if (digits.length <= 3) return digits;
        if (digits.length <= 6) return digits.slice(0, 3) + '.' + digits.slice(3);
        if (digits.length <= 9) return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
        return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9, 11);
    }

    function validarCPFLocal(cpf) {
        const digits = String(cpf || '').replace(/\D/g, '');
        if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false;
        let sum = 0;
        for (let i = 0; i < 9; i += 1) sum += parseInt(digits[i], 10) * (10 - i);
        let d1 = (sum * 10) % 11;
        if (d1 === 10) d1 = 0;
        if (parseInt(digits[9], 10) !== d1) return false;
        sum = 0;
        for (let j = 0; j < 10; j += 1) sum += parseInt(digits[j], 10) * (11 - j);
        let d2 = (sum * 10) % 11;
        if (d2 === 10) d2 = 0;
        return parseInt(digits[10], 10) === d2;
    }

    function init() {
        const form = global.document.getElementById('form-create-usuario');
        const nome = global.document.getElementById('create-nome');
        const sobrenome = global.document.getElementById('create-sobrenome');
        const cpf = global.document.getElementById('create-cpf');
        const email = global.document.getElementById('create-email');
        const senha = global.document.getElementById('create-senha');
        const senhaConfirm = global.document.getElementById('create-senha-confirm');
        const sexo = global.document.getElementById('create-sexo');
        const tipo = global.document.getElementById('create-tipo');
        const internoFields = global.document.getElementById('create-interno-fields');
        const externoFields = global.document.getElementById('create-externo-fields');
        const perfilExterno = global.document.getElementById('create-perfil-externo');
        const cargo = global.document.getElementById('create-cargo');
        const setor = global.document.getElementById('create-setor');
        const dataNasc = global.document.getElementById('create-data-nascimento');
        const telefone = global.document.getElementById('create-telefone');
        const cadastrar = global.document.getElementById('cadastrar-btn');
        const voltar = global.document.getElementById('voltar-btn');

        const CARGOS_OPERACIONAIS = { 'Estagiário': true, 'Técnico Judiciário': true, 'Analista Judiciário': true };
        const lastFieldToast = {};

        function showFieldError(key, msg) {
            if (lastFieldToast[key] === msg) return;
            lastFieldToast[key] = msg;
            if (msg) notify('error', 'Validação', msg);
        }

        function isExterno() {
            return tipo && tipo.value.trim() === 'externo';
        }

        function syncTipoFields() {
            const externo = isExterno();
            if (internoFields) internoFields.style.display = externo ? 'none' : '';
            if (externoFields) externoFields.style.display = externo ? '' : 'none';
            if (externo && cargo) cargo.value = '';
            if (externo && setor) setor.value = '';
        }

        function syncCargoToSetor() {
            if (!cargo || !setor) return;
            if (isExterno()) return;
            const c = cargo.value.trim();
            if (c === 'Chefia') {
                setor.value = 'Gabinete';
            }
            setor.disabled = false;
            setor.classList.remove('readonly');
        }

        function formatProper(el, max) {
            if (el && App.ProperNameHelper && typeof App.ProperNameHelper.formatProper === 'function') {
                el.value = App.ProperNameHelper.formatProper(el.value || '', max || 100);
            } else if (el && typeof global.TextFormatter !== 'undefined' && global.TextFormatter.formatProperName) {
                el.value = global.TextFormatter.formatProperName(el.value || '', max || 100);
            }
        }

        function validarCPFInput() {
            if (!cpf) return true;
            const raw = cpf.value.replace(/\D/g, '');
            if (raw.length !== 11) {
                showFieldError('cpf', raw.length > 0 ? 'CPF deve ter 11 dígitos.' : '');
                return false;
            }
            const ok = (App.CPF && App.CPF.validar) ? App.CPF.validar(raw) : validarCPFLocal(raw);
            showFieldError('cpf', ok ? '' : 'CPF inválido.');
            return ok;
        }

        function validarEmailInput() {
            if (!email) return true;
            const v = email.value.trim();
            const ok = !v || validarEmail(v);
            showFieldError('email', ok ? '' : 'E-mail inválido.');
            return ok;
        }

        function validarSenhaInput(exibirErro) {
            if (!senha) return true;
            const v = senha.value;
            const notificar = exibirErro !== false;
            if (!v) { if (notificar) showFieldError('senha', ''); return false; }
            if (v.length < 10) { if (notificar) showFieldError('senha', 'Mínimo 10 caracteres.'); return false; }
            const ok = validarSenha(v);
            if (notificar) showFieldError('senha', ok ? '' : 'Use 1 maiúscula, 1 minúscula, 1 número e 1 símbolo.');
            return ok;
        }

        function isFormValid() {
            const nomeVal = nome?.value.trim() || '';
            const sobrenomeVal = sobrenome?.value.trim() || '';
            const emailVal = email?.value.trim() || '';
            const sexoVal = sexo?.value.trim() || '';
            const cpfDigits = cpf ? cpf.value.replace(/\D/g, '') : '';
            if (!nomeVal || !sobrenomeVal) return false;
            if (cpfDigits.length !== 11) return false;
            if (!validarCPFInput()) return false;
            if (!emailVal || !validarEmail(emailVal)) return false;
            if (!['Feminino', 'Masculino', 'Outro'].includes(sexoVal)) return false;
            if (!validarSenhaInput(false)) return false;
            if (senhaConfirm && senha.value !== senhaConfirm.value) return false;
            if (!isExterno()) {
                const cargoVal = cargo?.value.trim() || '';
                if (CARGOS_OPERACIONAIS[cargoVal] && !(setor && setor.value.trim())) {
                    return false;
                }
            } else {
                if (perfilExterno && !perfilExterno.value) return false;
            }
            return true;
        }

        function updateCadastrar() {
            if (cadastrar) cadastrar.disabled = !isFormValid();
        }

        if (voltar) {
            voltar.addEventListener('click', function () { global.location.href = '/frame.php?page=usuarioslist'; });
        }

        if (tipo) tipo.addEventListener('change', function () { syncTipoFields(); updateCadastrar(); });
        if (cargo) cargo.addEventListener('change', function () { syncCargoToSetor(); updateCadastrar(); });
        if (setor) setor.addEventListener('change', updateCadastrar);
        if (sexo) sexo.addEventListener('change', updateCadastrar);
        if (dataNasc) {
            dataNasc.addEventListener('input', updateCadastrar);
            dataNasc.addEventListener('change', updateCadastrar);
        }

        [nome, sobrenome].forEach(function (el) {
            if (!el) return;
            if (App.ProperNameHelper) App.ProperNameHelper.applyOnBlur(el, 100);
            el.addEventListener('input', updateCadastrar);
        });

        if (cpf) {
            cpf.addEventListener('blur', function () {
                cpf.value = formatCPF(cpf.value);
                validarCPFInput();
                updateCadastrar();
            });
            if (App.CPF && typeof App.CPF.init === 'function') {
                App.CPF.init('#create-cpf');
            }
            cpf.addEventListener('input', updateCadastrar);
        }

        if (email) {
            email.addEventListener('blur', function () { validarEmailInput(); updateCadastrar(); });
            email.addEventListener('input', updateCadastrar);
        }
        if (senha) {
            senha.addEventListener('blur', function () { validarSenhaInput(); updateCadastrar(); });
            senha.addEventListener('input', updateCadastrar);
        }
        if (senhaConfirm) {
            senhaConfirm.addEventListener('blur', function () {
                const msg = senhaConfirm.value ? (senha && senha.value === senhaConfirm.value ? '' : 'As senhas não coincidem.') : '';
                showFieldError('senha-confirm', msg);
                updateCadastrar();
            });
            senhaConfirm.addEventListener('input', updateCadastrar);
        }
        if (telefone) {
            telefone.addEventListener('input', updateCadastrar);
            telefone.addEventListener('change', updateCadastrar);
        }
        if (perfilExterno) {
            perfilExterno.addEventListener('change', updateCadastrar);
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                [nome, sobrenome].forEach(function (el) { formatProper(el, 100); });
                if (!isFormValid()) {
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

        syncTipoFields();
        syncCargoToSetor();
        updateCadastrar();
        notifyByKey(
            'generic.user.create.pending.info',
            {},
            'info',
            'Informação',
            'O cadastro será criado com status Pendente.'
        );
    }

    App.UsuariosCreate = { initialize: init };
})(window);
