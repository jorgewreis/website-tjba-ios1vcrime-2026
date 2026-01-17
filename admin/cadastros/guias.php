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

    <!-- Reset CSS -->
    <link rel="stylesheet" href="/css/reset.css">

    <!-- Styles CSS -->
    <link rel="stylesheet" href="/css/color.css">
    <link rel="stylesheet" href="/css/pages.css">
    <link rel="stylesheet" href="/css/form.css">
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