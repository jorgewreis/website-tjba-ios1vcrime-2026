<?php
if (!isset($_SESSION['id_usuario'])) {
    die("Acesso negado");
}
?>

<!DOCTYPE html>
<html lang="en">
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
            <span class="title">Editais</span>
            <span class="info-title">Espaço destinado a garantir que o edital foi publicado, o prazo correu, a certificação foi lançada e, sobretudo, que ninguém alegue desconhecimento post factum</span>
        </div>
    </div>
</body>
</html>

