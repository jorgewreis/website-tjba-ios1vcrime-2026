<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/bootstrap.php';

if (Security::isAuthenticated()) {
    header('Location: /home.php');
    exit;
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-straight/css/uicons-regular-straight.css">

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
        <span class="title">Bem vindo</span>
        <span class="info-title">Sistema online: porque filas são coisa do passado</span>
    </div>

    <form class="form-login" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
            <div class="col">
                <label for="cpf">Login (CPF)</label>
                <input class="width-500" type="text" id="cpf" name="cpf" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
            <div class="col">
                <label for="senha">Senha</label>
                <input class="width-400" type="password" id="senha" name="senha" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-between align-center padding-x-sm">
            <div class="col">
                <label class="checkbox-label">
                    <input type="checkbox" id="lembrarUsuario" name="lembrar" value="1">
                    <span>Lembrar-me</span>
                </label>
            </div>
            <div class="col">
                <label class="link-login">
                    <button type="button" class="link-like" data-nav-href="/recupera_senha.php">Esqueceu a senha?</button>
                </label>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
            <select id="themeSelect" class="width-400 height-sm white" title="Preferência de tema">
                <option value="claro" selected>Tema claro</option>
                <option value="auto">Tema automático</option>
                <option value="escuro">Tema escuro</option>
            </select>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md border-bottom">
            <button class="btn btn-primary width-300" type="submit">Entrar</button>
        </div>

        <div class="display-flex row row-full justify-start align-center">
            <div class="alert-heading danger" id="alert-login">
                <div class="alert-header">
                    <span class="text">Erro</span>
                    <i class="fi fi-rs-cross"></i>
                </div>
                <span class="alert-text" id="erro"></span>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
            <div class="col">
                <button type="button" class="btn btn-primary-outline" data-nav-href="/cadastro_estudantes.php">Cadastro de estudantes</button>
                <label>Estudantes de Direito devem se cadastrar para acompanhamento de audiências.</label>
            </div>
        </div>
    </form>

    <script src="/assets/js/shared/navigate-buttons-iframe.js" defer></script>
    <script src="/assets/js/shared/cpf.js" defer></script>
    <script src="/assets/js/shared/prefs.js" defer></script>
    <script src="/assets/js/shared/toast-helper.js" defer></script>
    <script defer>
        // Shim para compatibilidade com libs que usam App.Toast
        window.App = window.App || {};
        App.Toast = { show: function(opts) { showToast(opts); } };

        document.addEventListener('DOMContentLoaded', async function () {
            const cpfInput   = document.querySelector('#cpf');
            const senhaInput = document.querySelector('#senha');
            const alertBox   = document.querySelector('#alert-login');
            const themeSelect = document.querySelector('#themeSelect');
            const lembrarCheck = document.querySelector('#lembrarUsuario');

            cpfInput?.addEventListener('click', function () {
                if (cpfInput) cpfInput.value = '';
                if (senhaInput) senhaInput.value = '';
                if (alertBox) alertBox.style.display = 'none';
            });

            senhaInput?.addEventListener('click', function () {
                if (senhaInput) senhaInput.value = '';
                if (alertBox) alertBox.style.display = 'none';
            });

            if (App.Prefs) {
                App.Prefs.applyThemeFromCookie();
                const savedCpf = App.Prefs.getRememberCpf();
                if (savedCpf && cpfInput) {
                    cpfInput.value = savedCpf;
                    if (lembrarCheck) lembrarCheck.checked = true;
                }
                const savedTheme = App.Prefs.getTheme();
                if (savedTheme && themeSelect) themeSelect.value = savedTheme;
                themeSelect?.addEventListener('change', function () {
                    App.Prefs.setTheme(themeSelect.value);
                    App.Prefs.applyTheme();
                    // Sincronizar tema no parent também
                    if (window.parent !== window) {
                        window.parent.postMessage({ type: 'APP_THEME', theme: themeSelect.value }, '*');
                    }
                });
            }

            await App.CPF.init('#cpf');

            const form = document.getElementById('loginForm');
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const erroElement = document.getElementById('erro');
                if (alertBox) alertBox.style.display = 'none';

                const formData = new FormData(form);
                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn ? btn.textContent : '';

                try {
                    if (btn) { btn.disabled = true; btn.textContent = 'Entrando...'; }

                    const response = await fetch('/login.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });
                    const result = await response.json();

                    if (response.ok && result && result.success === true) {
                        if (App.Prefs) {
                            const cpfVal = (formData.get('cpf') || '').replace(/\D/g, '');
                            if (lembrarCheck && lembrarCheck.checked && cpfVal.length === 11) {
                                App.Prefs.setRememberCpf(cpfInput.value.trim());
                            } else {
                                App.Prefs.clearRememberCpf();
                            }
                        }
                        // Navegar a janela principal para home
                        var dest = '/home.php';
                        if (window.parent !== window) {
                            window.parent.location.replace(dest);
                        } else {
                            window.location.replace(dest);
                        }
                        return;
                    }

                    const message = (result && result.message) ? result.message : 'Erro ao entrar.';
                    showToast({ type: 'error', title: 'Erro', message: message });
                    if (erroElement) erroElement.textContent = message;
                    if (alertBox) alertBox.style.display = 'flex';

                } catch (err) {
                    const msg = 'Erro de conexão. Tente novamente mais tarde.';
                    showToast({ type: 'error', title: 'Erro', message: msg });
                    if (erroElement) erroElement.textContent = msg;
                    if (alertBox) alertBox.style.display = 'flex';
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                }
            });
        });
    </script>

</body>
</html>
