<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/variables.css">
    <link rel="stylesheet" href="/assets/css/shared/base.css">
    <link rel="stylesheet" href="/assets/css/shared/components.css">
    <link rel="stylesheet" href="/assets/css/shared/forms.css">
    <link rel="stylesheet" href="/assets/css/modules/public/login.css">
</head>

<body style="background-color: #f5f5f9;">

<div class="noview"></div>

<div class="view layout-wraper">
    <div class="layout-container">

        <div class="card-login">
            <div class="title-page">
                <span class="title">Resetar senha</span>
                <span class="info-title">
                    Informe CPF e e-mail cadastrados.  
                    Se os dados estiverem corretos, você receberá instruções por e-mail.
                </span>
            </div>

            <form class="form-login" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-line">
                    <div class="form-block">
                        <label for="cpf">CPF</label>
                        <input class="form-control size-c" type="text" id="cpf" name="cpf" required>
                    </div>
                </div>

                <div class="form-line">
                    <div class="form-block" style="width: 100%;">
                        <label for="email">E-mail</label>
                        <input class="form-control size-full" type="email" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-line">
                    <button class="button bg-primary size-full" type="submit">
                        Solicitar reset
                    </button>
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

<script src="/assets/js/shared/cpf.js" defer></script>
<script src="/assets/js/shared/auth.js" defer></script>

<script defer>
document.addEventListener("DOMContentLoaded", async () => {

    await App.CPF.init("#cpf");

    await App.Auth.initRecuperaSenha("#resetForm", "#cpf", "#email", "/envia_mail.php");
});
</script>

</body>
</html>
