<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
require_once __DIR__ . '/../../../config/security.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Novo usuário - Primeira Vara Crime de Ilhéus</title>
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno -</span><span class="specific">Novo usuário</span></div>
        <h1 class="title-page">Novo usuário</h1>
        <p class="description-page">Cadastro de novo usuário (em construção).</p>
    </div>
    <div class="card-page">
        <div class="row table between padding-tb-lg padding-lr-md">
            <p class="major-info">Esta página está em construção. Use o botão abaixo para voltar à listagem.</p>
            <a href="/frame.php?page=usuarioslist" class="btn btn-primary">Voltar à lista de usuários</a>
        </div>
    </div>
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
</body>
</html>
