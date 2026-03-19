<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

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
    <title>Esqueci a senha - Primeira Vara Crime de Ilhéus</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
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
</head>
<body>

    <div class="noview"></div>

    <div class="view layout-wraper">
        <div class="layout-container">
            <div class="layout-page">

                <!-- LEFT - mesmo esquema visual do index -->
                <div class="page-left">
                    <div class="title-system">
                        <span class="title">PRIMEIRA VARA</span>
                        <span class="info-title">CRIMINAL</span>
                        <p class="text">A excelência nasce de um fluxo inteligente, padronização segura, responsabilidade clara e foco na efetividade jurisdicional.</p>
                    </div>
                    <!-- FOOTER - SYSTEM -->
                    <div class="footer-page" style="position: absolute; bottom: 0;">
                        <div class="info-page">
                            <span class="info-line">Rua Dr. Hygino Filho, bloco C, 1º andar, sala 132 - Jardim Atlântico, Ilhéus - BA</span>
                            <span class="info-line">Tel.: 73 3234-3428 - Whatsapp: 71 99610-8241 - Email: ilheus1vcriminal@tjba.jus.br</span>
                        </div>
                        <div class="info-app">Sistema desenvolvido integralmente por Jorge Reis Developer - 2026 - Todos os direitos reservados</div>
                    </div>
                </div>

                <!-- RIGHT - formulário -->
                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Recuperar senha</span>
                        <span class="info-title">
                            Informe o CPF e confirme o e-mail cadastrado.
                        </span>
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

                        <div class="display-flex row row-full justify-start align-center padding-x-md padding-y-md" id="emailHintLine" style="display: none;">
                            <span class="status status-pending padding-x-md row-full" id="emailHint" style="margin-bottom: 15px; color: var(--color-gray-600);"></label>
                        </div>                      

                        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
                            <button class="btn btn-primary" type="submit">Recuperar senha</button>
                        </div>
                        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
                            <button class="btn btn-secondary width-300" type="button" onclick="window.location.href = '/index.php';">Voltar</button>
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
                </div>
            </div>
        </div>
        <div class="layout-overlay"></div>
        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/cpf.js" defer></script>
    <script src="/assets/js/shared/toast.js" defer></script>
    <script src="/assets/js/shared/auth.js" defer></script>
    <script defer>
        document.addEventListener("DOMContentLoaded", async () => {
            await App.CPF.init("#cpf");

            const cpfInput = document.querySelector("#cpf");
            const emailHintLine = document.getElementById("emailHintLine");
            const emailHint = document.getElementById("emailHint");

            function onlyDigits(v) {
                return (v || "").toString().replace(/\D+/g, "");
            }

            async function fetchEmailHint() {
                const digits = onlyDigits(cpfInput.value);
                if (digits.length !== 11) {
                    emailHintLine.style.display = "none";
                    return;
                }
                try {
                    const r = await fetch("/api/email_hint.php?cpf=" + encodeURIComponent(digits));
                    const data = await r.json();
                    if (data && data.hint) {
                        emailHint.textContent = "E-mail cadastrado : " + data.hint;
                        emailHintLine.style.display = "flex";
                    } else {
                        emailHintLine.style.display = "none";
                    }
                } catch (e) {
                    emailHintLine.style.display = "none";
                }
            }

            cpfInput.addEventListener("blur", fetchEmailHint);

            await App.Auth.initRecuperaSenha("#resetForm", "#cpf", "#email", "/envia_mail.php");
        });
    </script>
</body>
</html>
