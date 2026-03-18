<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

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
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>
  
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">

    <!-- Ícones: fi-rs-* (regular-straight) -->
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-straight/css/uicons-regular-straight.css">
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
    
    <!-- PAGE DISPOSITIVOS INCOMPATIVEIS -->
    <div class="noview"></div>

    <!-- CONTEÚDO DO SITE -->
    <div class="view layout-wraper">

        <div class="layout-container">
            <div class="layout-page">

                <!-- LEFT - THEME -->
                <div class="page-left">
                    <div class="title-system">
                        <span class="title">PRIMEIRA VARA</span>
                        <span class="info-title">CRIMINAL</span>
                        <p class="text">A excelência nasce de um fluxo inteligente, padronização segura, responsabilidade clara e foco na efetividade jurisdicional.
                        </p>
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

                <!-- RIGHT - LOGIN -->
                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Bem vindo</span>
                        <span class="info-title">Sistema online: porque filas são coisa do passado</span>
                    </div>

                    <form class="form-login" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
                            <div class="col">
                                <label for="cpf">Login (CPF)</label>
                                <input class="width-500 white" type="text" id="cpf" name="cpf" required>
                            </div>
                        </div>

                        <div class="display-flex row row-full justify-start align-center padding-x-lg padding-y-md">
                            <div class="col">
                                <label for="senha">Senha</label>
                                <input class="width-400 white" type="password" id="senha" name="senha" required>
                            </div>
                        </div>

                        <div class="display-flex row row-full justify-between align-center">
                            <div class="col">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="lembrarUsuario" name="lembrar" value="1">
                                    <span>Lembrar-me</span>
                                </label>
                            </div>
                            <div class="col">
                                <label class="link-login">
                                    <a href="recupera_senha.php">Esqueceu a senha?</a>
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
                            <button class="btn btn-primary" type="submit">Entrar</button>
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
                                <button class="btn btn-primary-outline" onclick="window.location.href='cadastro_estudantes.php'">Cadastro de estudantes</button>
                                <label>Estudantes de Direito devem se cadastrar para acompanhamento de audiências.</label>
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
    <script src="/assets/js/shared/prefs.js" defer></script>
    <script src="/assets/js/shared/auth.js" defer></script>
    <script defer>
        document.addEventListener("DOMContentLoaded", async () => {
            const cpfInput = document.querySelector("#cpf");
            const alertBox = document.querySelector("#alert-login");
            const senhaInput = document.querySelector("#senha");
            const lembrarCheck = document.querySelector("#lembrarUsuario");
            const themeSelect = document.querySelector("#themeSelect");

            // Ao clicar no CPF: limpa CPF e senha e esconde alerta
            cpfInput?.addEventListener("click", () => {
                if (cpfInput) cpfInput.value = "";
                if (senhaInput) senhaInput.value = "";
                if (alertBox) alertBox.style.display = "none";
            });

            // Ao clicar na senha: limpa só a senha (facilita em caso de senha inválida)
            senhaInput?.addEventListener("click", () => {
                if (senhaInput) senhaInput.value = "";
                if (alertBox) alertBox.style.display = "none";
            });

            // Cookies: lembrar usuário e tema
            if (App.Prefs) {
                App.Prefs.applyThemeFromCookie();
                const savedCpf = App.Prefs.getRememberCpf();
                if (savedCpf && cpfInput) {
                    cpfInput.value = savedCpf;
                    if (lembrarCheck) lembrarCheck.checked = true;
                }
                const savedTheme = App.Prefs.getTheme();
                if (savedTheme && themeSelect) themeSelect.value = savedTheme;
                themeSelect?.addEventListener("change", () => {
                    App.Prefs.setTheme(themeSelect.value);
                    App.Prefs.applyTheme();
                });
            }

            await App.CPF.init("#cpf");
            await App.Auth.initLogin("#loginForm", "login.php", "erro", "alert-login");
        });
    </script>


</body>
</html>
