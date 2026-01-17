<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';

if (Security::isAuthenticated()) {
    header('Location: /public/home.php');
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

    <!-- Reset CSS -->
    <link rel="stylesheet" href="/css/reset.css">

    <!-- Styles CSS -->
    <link rel="stylesheet" href="/css/color.css">
    <link rel="stylesheet" href="/css/login.css">
    <link rel="stylesheet" href="/css/form.css">
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
                        <p class="text">O lugar onde a verdade prevalece</p>
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

                        <div class="form-line">
                            <div class="form-block">
                                <a class="link-login" href="recupera_senha.php">Esqueci a senha.</a>
                            </div>
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

    <script src="/js/common.js" defer></script>
    <script defer>
        document.addEventListener("DOMContentLoaded", async () => {
            document.querySelector("#cpf").addEventListener("click", function(){
                document.querySelector("#cpf").value = "";
                document.querySelector("#senha").value = "";
                document.querySelector("#alert-login").style.display = "none";
            });
            await initCPF("#cpf");
            await initLogin("#loginForm", "login.php", "erro", "alert-login");
        });
    </script>


</body>
</html>
