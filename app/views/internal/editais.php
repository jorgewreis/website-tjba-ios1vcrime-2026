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

    <link rel= stylesheet href=/assets/css/shared/reset.css>

    <link rel=stylesheet href=/assets/css/shared/variables.css>

    <link rel=stylesheet href=/assets/css/shared/base.css>

    <link rel=stylesheet href=/assets/css/shared/components.css>

    <link rel=stylesheet href=/assets/css/shared/forms.css>

</head>
<body>
    <div class="container-main">
        <div class="title-page">
            <span class="title">Editais</span>
            <span class="info-title">Espaço destinado a garantir que o edital foi publicado, o prazo correu, a certificação foi lançada e, sobretudo, que ninguém alegue desconhecimento post factum</span>
        </div>
    </div>
</body>
</html>

