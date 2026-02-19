<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';

Security::requireAuthentication();
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
</head>
<body>
    <div class="container-main">
        <div class="title-page">
            <span class="title" style="color: crimson;">Erro 404 - Página não encontrada</span>
            <span class="info-title">A página que você tentou acessar não existe, foi removida ou o endereço foi digitado incorretamente. Verifique o link e tente novamente. Se você chegou aqui a partir de um botão ou menu do sistema, atualize a página e retorne para a página inicial para continuar.</span>
        </div>
    </div>
</body>
</html>
