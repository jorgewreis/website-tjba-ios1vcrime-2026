<?php
declare(strict_types=1);

Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notificações - Sistema de Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body>
<div class="container py-4">
    <h1 class="h4 mb-3">Notificações</h1>
    <p class="text-muted">Lista de notificações do usuário e ação rápida para marcar como lido.</p>

    <div id="notifications-list" class="list-group">
        <div class="list-group-item">Nenhuma notificação encontrada.</div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

