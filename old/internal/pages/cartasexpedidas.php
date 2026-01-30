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
            <span class="title">Cartas Expedidas</span>
            <span class="info-title">Página dedicada a acompanhar o percurso das cartas precatórias, assegurando que o pedido jurisdicional tenha início, trânsito e retorno.</span>
        </div>
    </div>

    <!-- BARRA DE BOTÕES - NOVO CADASTRO -->
    <div class="page-header-actions">
        <button class="btn-primary">
            <i class="fi fi-rr-plus"></i>
            Nova Carta Expedida
        </button>
    </div>

    <!-- BARRA DE 4 CARDS INFORMATIVOS -->
    <div class="info-cards-row">
        <div class="info-card">
            <span class="card-title">Total de Cartas Expedidas</span>
            <span class="card-value">150</span>
        </div>
        <div class="info-card">
            <span class="card-title">Cartas aguardando envio</span>
            <span class="card-value">5</span>
        </div>
        <div class="info-card">
            <span class="card-title">Cartas aguardando cumprimento</span>
            <span class="card-value">50</span>
        </div>
        <div class="info-card">
            <span class="card-title">Cartas devolvidas</span>
            <span class="card-value">25</span>
        </div>
    </div>

    <!-- TABELA COM LISTA DE CARTAS EXPEDIDAS -->
    <!-- colunas: Processo, Finalidade, Prazo, Comarca destino, Usuário, Menu Rápido -->
    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Processo</th>
                    <th>Finalidade</th>
                    <th>Prazo</th>
                    <th>Comarca destino</th>
                    <th>Usuário</th>
                    <th>Menu Rápido</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dados das cartas expedidas serão inseridos aqui dinamicamente -->
            </tbody>
        </table>
    </div>
</body>
</html>
