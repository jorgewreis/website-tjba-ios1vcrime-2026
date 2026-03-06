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
                </div>

                <!-- RIGHT - LOGIN -->
                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Bem vindo</span>
                        <span class="info-title">Sistema online: porque filas são coisa do passado</span>
                    </div>

                    <form class="form-login" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-line">
                            <div class="form-block">
                                <label for="cpf">Login (CPF)</label>
                                <input class="form-control size-c" type="text" id="cpf" name="cpf" required>
                            </div>
                        </div>

                        <div class="form-line">
                            <div class="form-block">
                                <label for="senha">Senha</label>
                                <input class="form-control size-c" type="password" id="senha" name="senha" required>
                            </div>
                        </div>

                        <div class="form-line form-line-options">
                            <div class="form-block">
                                <a class="link-login" href="recupera_senha.php">Esqueci a senha.</a>
                            </div>
                            <div class="form-block form-block-remember">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="lembrarUsuario" name="lembrar" value="1">
                                    <span>Lembrar usuário</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-line form-line-theme">
                            <span class="theme-label">Tema:</span>
                            <select id="themeSelect" class="form-control form-control-theme" title="Preferência de tema">
                                <option value="auto">Auto</option>
                                <option value="claro">Claro</option>
                                <option value="escuro">Escuro</option>
                            </select>
                        </div>

                        <div class="form-line">
                            <button class="button bg-primary size-full" type="submit">Entrar</button>
                        </div>
                        <div class="form-line">
                            <div class="alert-heading danger size-full" id="alert-login">
                                <div class="alert-header">
                                    <span class="text">Erro</span>
                                    <i class="fi fi-rs-cross"></i>
                                </div>
                                <span class="alert-text" id="erro"></span>
                            </div>
                        </div>

                        <div class="form-line">
                            <div class="form-block">
                                <span class="text-login">Estudante de direito? <a class="link-login" href="#">Cadastre-se</a></span>
                            </div>
                        </div>
                    </form>

                </div>
            </div>                   
        </div>        

        <div class="layout-overlay"></div>
        
    </div>

    <script src="/assets/js/shared/cpf.js" defer></script>
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
