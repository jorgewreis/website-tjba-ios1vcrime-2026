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

    <!-- Reset CSS (Fundação) -->
    <link rel="stylesheet" href="/css/reset.css">
    <link rel="stylesheet" href="/css/color.css">

    <!-- Layout e Navegação -->
    <link rel="stylesheet" href="/css/layout.css">
    <link rel="stylesheet" href="/css/menu-style.css">

    <!-- Componentes Reutilizáveis -->
    <link rel="stylesheet" href="/css/components.css">

    <!-- Páginas Internas -->
    <link rel="stylesheet" href="/css/pages.css">
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
