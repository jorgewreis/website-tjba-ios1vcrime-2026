<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/bootstrap.php';

$csrfToken = Security::generateCSRFToken();

$cpfInicial = '';
if (isset($_COOKIE['tjba_1vcrime_remember_cpf'])) {
    $cpfInicial = trim((string) $_COOKIE['tjba_1vcrime_remember_cpf']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

    <script>
        (function() {
            var name = 'tjba_1vcrime_theme';
            var i = document.cookie.indexOf(name + '=');
            if (i === -1) return;
            i += name.length + 1;
            var end = document.cookie.indexOf(';', i);
            var t = decodeURIComponent((end === -1 ? document.cookie.slice(i) : document.cookie.slice(i, end)).trim());
            if (t === 'claro' || t === 'escuro' || t === 'auto') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: var(--color-background-elevated);
            overflow-x: hidden;
            overflow-y: auto;
        }
    </style>
</head>
<body>

    <div class="title-page" style="margin-top: 30px;">
        <span class="title">Recuperar senha</span>
        <span class="info-title">Informe o CPF e confirme o e-mail cadastrado para receber um link de redefinição.</span>
    </div>

    <form class="form-login" id="resetForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="cpf">CPF</label>
                <input class="width-400" type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cpfInicial, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="col">
                <label for="email">Confirmar e-mail cadastrado</label>
                <input class="width-600" type="email" id="email" name="email" placeholder="Digite o e-mail cadastrado" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
            <button class="btn btn-primary" type="submit">Recuperar senha</button>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
            <button type="button" class="btn btn-secondary width-300" data-nav-href="/index.php">Voltar</button>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
            <div class="padding-y-md alert-heading danger" id="alert-login">
                <div class="alert-header">
                    <span class="padding-x-lg text">Aviso</span>
                </div>
                <span class="padding-x-lg alert-text" id="erro"></span>
            </div>
        </div>
    </form>

    <script src="/assets/js/shared/navigate-buttons-iframe.js" defer></script>
    <script src="/assets/js/shared/cpf.js" defer></script>
    <script src="/assets/js/shared/toast-helper.js" defer></script>
    <script defer>
        // Shim para compatibilidade com libs que usam App.Toast
        window.App = window.App || {};
        App.Toast = { show: function(opts) { showToast(opts); } };

        document.addEventListener('DOMContentLoaded', async function () {
            await App.CPF.init('#cpf');

            const cpfInput      = document.querySelector('#cpf');
            const emailInput    = document.querySelector('#email');
            const form          = document.querySelector('#resetForm');
            const csrfInput     = form.querySelector('input[name="csrf_token"]');
            const alertBox      = document.getElementById('alert-login');
            const erroField     = document.getElementById('erro');

            function onlyDigits(v) {
                return (v || '').toString().replace(/\D+/g, '');
            }

            function setAlert(title, message) {
                if (erroField) erroField.textContent = message || '';
                if (alertBox) alertBox.style.display = 'block';
            }

            function clearAlert() {
                if (alertBox) alertBox.style.display = 'none';
                if (erroField) erroField.textContent = '';
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                clearAlert();

                const cpfDigits = onlyDigits(cpfInput.value);
                const email = (emailInput.value || '').trim();

                if (cpfDigits.length !== 11) {
                    showToast({ type: 'error', title: 'Erro', message: 'Informe um CPF válido.' });
                    return;
                }

                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showToast({ type: 'error', title: 'Erro', message: 'Informe um e-mail válido.' });
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const previousText = submitBtn ? submitBtn.textContent : '';

                try {
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Processando...'; }

                    const response = await fetch('/envia_mail.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify({
                            cpf: cpfDigits,
                            email: email,
                            csrf_token: csrfInput ? csrfInput.value : ''
                        })
                    });

                    const result = await response.json().catch(() => ({}));
                    const success   = result && result.success === true;
                    const emailSent = result && result.emailSent === true;
                    const message   = (result && result.message) || 'Não foi possível processar. Tente novamente.';

                    if (success) {
                        const toastType  = emailSent ? 'success' : 'info';
                        const toastTitle = emailSent ? 'Link enviado' : 'Solicitação registrada';
                        showToast({ type: toastType, title: toastTitle, message: message });
                        form.reset();
                        if (emailSent) {
                            setTimeout(function () {
                                if (window.parent !== window) {
                                    window.parent.location.replace('/index.php');
                                } else {
                                    window.location.replace('/index.php');
                                }
                            }, 2000);
                        }
                    } else {
                        showToast({ type: 'error', title: 'Erro', message: message });
                        setAlert('Erro', message);
                    }
                } catch (err) {
                    const msg = 'Erro de conexão. Tente novamente mais tarde.';
                    showToast({ type: 'error', title: 'Erro', message: msg });
                    setAlert('Erro', msg);
                } finally {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = previousText || 'Recuperar senha'; }
                }
            });
        });
    </script>

</body>
</html>
