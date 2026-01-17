<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="css/color.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/pages.css">
    <link rel="stylesheet" href="css/form.css">
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
                <input type="hidden" name="csrf_token" id="csrf_token">

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
                        <a class="link-login" href="index.php">Voltar para login</a>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<script src="/js/common.js" defer></script>
<script src="/js/login.js" defer></script>

<script defer>
document.addEventListener("DOMContentLoaded", async () => {

    await initCPF("#cpf");

    // CSRF vindo do backend (ex.: window.CSRF_TOKEN)
    if (window.CSRF_TOKEN) {
        document.querySelector("#csrf_token").value = window.CSRF_TOKEN;
    }

    await initRecuperaSenha("#resetForm");
});
</script>

</body>
</html>
