<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/bootstrap.php';

Security::requireAuthentication();

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar senha</title>

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
        <span class="title">Alterar senha</span>
        <span class="info-title">Informe a senha atual e defina uma nova senha com <?php echo htmlspecialchars(Security::passwordPolicySummary(), ENT_QUOTES, 'UTF-8'); ?>.</span>
    </div>

    <form class="form-login" id="formAlterarSenha">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="senha_atual">Senha atual</label>
                <input class="width-400" type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="nova_senha">Nova senha</label>
                <input class="width-400" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="confirmar_senha">Confirmar nova senha</label>
                <input class="width-400" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
            <button class="btn btn-primary" type="submit">Alterar senha</button>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
            <button type="button" class="btn btn-secondary" data-nav-href="/home.php">Voltar</button>
        </div>
    </form>

    <script src="/assets/js/shared/navigate-buttons-iframe.js" defer></script>
    <script src="/assets/js/shared/toast-helper.js" defer></script>
    <script defer>
        // Shim para compatibilidade com libs que usam App.Toast
        window.App = window.App || {};
        App.Toast = { show: function(opts) { showToast(opts); } };

        document.addEventListener('DOMContentLoaded', function () {
            const form    = document.getElementById('formAlterarSenha');
            const csrfInput = form.querySelector('input[name="csrf_token"]');
            const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{<?php echo PASSWORD_MIN_LENGTH; ?>,<?php echo PASSWORD_MAX_LENGTH; ?>}$/;

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const nova      = document.getElementById('nova_senha').value;
                const conf      = document.getElementById('confirmar_senha').value;
                const senhaAtual = document.getElementById('senha_atual').value;

                if (nova !== conf) {
                    showToast({ type: 'error', title: 'Erro', message: 'A nova senha e a confirmação não coincidem.' });
                    return;
                }

                if (!senhaRegex.test(nova)) {
                    showToast({ type: 'error', title: 'Erro', message: 'A senha deve seguir a política: <?php echo addslashes(Security::passwordPolicySummary()); ?>.' });
                    return;
                }

                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn ? btn.textContent : '';
                if (btn) { btn.disabled = true; btn.textContent = 'Alterando...'; }

                try {
                    const response = await fetch('/api/alterar_senha.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({
                            csrf_token:      csrfInput ? csrfInput.value : '',
                            senha_atual:     senhaAtual,
                            nova_senha:      nova,
                            confirmar_senha: conf,
                        })
                    });

                    const raw = await response.text();
                    let data = {};
                    try {
                        data = raw ? JSON.parse(raw) : {};
                    } catch (parseErr) {
                        showToast({
                            type: 'error',
                            title: 'Erro',
                            message: 'Resposta inválida do servidor (HTTP ' + response.status + '). Confirme se o deploy inclui config.php atualizado ou contacte o suporte.'
                        });
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                        return;
                    }

                    if (data.success) {
                        showToast({
                            type: 'success',
                            message: data.message || 'Senha alterada com sucesso.',
                            onClose: function () {
                                if (window.parent !== window) {
                                    window.parent.location.href = '/home.php';
                                } else {
                                    window.location.href = '/home.php';
                                }
                            }
                        });
                    } else {
                        showToast({ type: 'error', title: 'Erro', message: data.message || 'Não foi possível alterar a senha.' });
                        if (btn) { btn.disabled = false; btn.textContent = originalText; }
                    }
                } catch (err) {
                    var detail = (err && err.message) ? err.message : '';
                    showToast({
                        type: 'error',
                        title: 'Erro',
                        message: detail ? ('Falha na comunicação: ' + detail) : 'Erro de conexão. Tente novamente.'
                    });
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                }
            });
        });
    </script>

</body>
</html>
