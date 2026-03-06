<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

$csrfToken = Security::generateCSRFToken();

// CPF salvo no cookie "lembrar usuário" para pré-preencher
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

    <link rel="stylesheet" href="/assets/css/legacy/reset.css">
    <link rel="stylesheet" href="/assets/css/legacy/color.css">
    <link rel="stylesheet" href="/assets/css/legacy/components.css">
    <link rel="stylesheet" href="/assets/css/legacy/form.css">
    <link rel="stylesheet" href="/assets/css/legacy/layout.css">
    <link rel="stylesheet" href="/assets/css/legacy/login.css">
    <link rel="stylesheet" href="/assets/css/legacy/menu-style.css">
    <link rel="stylesheet" href="/assets/css/legacy/pages.css">
    <link rel="stylesheet" href="/assets/css/legacy/style.css">
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
                </div>

                <!-- RIGHT - formulário -->
                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Esqueci a senha</span>
                        <span class="info-title">
                            Informe o CPF e confirme o e-mail cadastrado. Uma nova senha de 10 caracteres (letras maiúsculas e números) será gerada, alterada no sistema e enviada por e-mail. Altere-a no próximo login.
                        </span>
                    </div>

                    <form class="form-login" id="resetForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-line">
                            <div class="form-block">
                                <label for="cpf">CPF</label>
                                <input class="form-control size-c" type="text" id="cpf" name="cpf" value="<?php echo htmlspecialchars($cpfInicial, ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                        </div>

                        <div class="form-line" id="emailHintLine" style="display: none;">
                            <div class="form-block">
                                <span class="email-hint" id="emailHint"></span>
                            </div>
                        </div>

                        <div class="form-line">
                            <div class="form-block" style="width: 100%;">
                                <label for="email">Confirmar e-mail cadastrado</label>
                                <input class="form-control size-full" type="email" id="email" name="email" placeholder="Digite o e-mail completo" required>
                            </div>
                        </div>

                        <div class="form-line">
                            <button class="button bg-primary size-full" type="submit">Gerar nova senha e enviar por e-mail</button>
                        </div>

                        <div class="form-line">
                            <div class="alert-heading size-full" id="alert-login">
                                <div class="alert-header">
                                    <span class="text">Aviso</span>
                                </div>
                                <span class="alert-text" id="erro"></span>
                            </div>
                        </div>

                        <div class="form-line">
                            <div class="form-block">
                                <a class="link-login" href="/index.php">Voltar para login</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layout-overlay"></div>
    </div>

    <script src="/assets/js/shared/cpf.js" defer></script>
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
                        emailHint.textContent = "E-mail cadastrado (parcial): " + data.hint;
                        emailHintLine.style.display = "block";
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
