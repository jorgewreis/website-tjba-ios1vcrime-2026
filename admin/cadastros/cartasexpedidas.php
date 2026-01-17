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
            <span class="title">Cartas Expedidas</span>
            <span class="info-title">Página dedicada a acompanhar o percurso das cartas precatórias, assegurando que o pedido jurisdicional tenha início, trânsito e retorno.</span>
        </div>
    </div>
</body>
</html>