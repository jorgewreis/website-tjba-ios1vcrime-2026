<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';

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
            <span class="title">Guias</span>
            <span class="info-title">Página dedicada ao controle dos atos formais que conduzem o sentenciado da decisão ao cumprimento, garantindo que cada passo da execução esteja documentado ad solemnitatem.</span>
        </div>
    </div>
</body>
</html>
