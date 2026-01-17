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
            <span class="title">Ofícios (Gabinete)</span>
            <span class="info-title">Aqui o Gabinete se manifesta de modo oficial, com numeração própria e registro histórico, para que cada ato tenha existência, contexto e lastro institucional.</span>
        </div>
    </div>
</body>
</html>