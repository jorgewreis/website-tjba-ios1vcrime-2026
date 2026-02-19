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

    <link rel=stylesheet href=/assets/css/shared/reset.css>
    <link rel=stylesheet href=/assets/css/shared/variables.css>
    <link rel=stylesheet href=/assets/css/shared/base.css>
    <link rel=stylesheet href=/assets/css/shared/components.css>
    <link rel=stylesheet href=/assets/css/shared/forms.css>
</head>
<body>
    <div class="container-main">
        <div class="title-page">
            <span class="title">Réus Presos</span>
            <span class="info-title">Painel dedicado aos processos com réus presos, acompanhamento das audiências e movimentações urgentes.</span>
        </div>
    </div>

    <section class="content-section">
        <div class="info-card">
            <span class="card-title">Réus em audiência</span>
            <span class="card-value">12</span>
        </div>
        <div class="info-card">
            <span class="card-title">Réus aguardando custódia</span>
            <span class="card-value">4</span>
        </div>
    </section>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Processo</th>
                    <th>Réu</th>
                    <th>Status</th>
                    <th>Unidade</th>
                    <th>Última movimentação</th>
                </tr>
            </thead>
            <tbody>
                <!-- Linhas de réus presos serão renderizadas dinamicamente -->
            </tbody>
        </table>
    </div>
</body>
</html>
