<?php
declare(strict_types=1);

Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Dashboard - Primeira Vara Crime de Ilhéus</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <style>
        .dashboard-interno-columns {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-start;
        }

        .dashboard-interno-column {
            flex: 1 1 320px;
            min-width: 0;
        }
    </style>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno -</span>
            <span class="specific">Dashboard</span>
        </div>
        <h1 class="title-page">Dashboard</h1>
    </div>

    <main class="container-full padding-x-md padding-y-md" aria-label="Conteúdo do dashboard">
        <div class="dashboard-interno-columns"></div>
    </main>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
</body>
</html>
