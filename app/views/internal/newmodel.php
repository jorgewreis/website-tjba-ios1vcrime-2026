<?php
declare(strict_types=1);

Security::requireAuthentication();
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartas Recebidas - Primeira Vara Crime de Ilhéus</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/components.css">
    <link rel="stylesheet" href="/assets/css/shared/forms.css">
    <link rel="stylesheet" href="/assets/css/shared/pages.css">
    <link rel="stylesheet" href="/assets/css/shared/style.css">

<!-- Flaticon -->
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.6.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

    <style>
        /* ===== VARIÁVEIS ===== */
        :root {
            --primary: #7367F0;
            --primary-light: #E8E6FC;
            --success: #28C76F;
            --success-light: #D4F4E2;
            --warning: #FF9F43;
            --warning-light: #FFF0E1;
            --danger: #EA5455;
            --danger-light: #FCE5E6;
            --info: #00CFE8;
            --info-light: #D9F8FC;
            --bg-light: #F8F7FA;
            --text-primary: #5D596C;
            --text-secondary: #A5A3AE;
            --border-color: #DBDADE;
            --white: #FFFFFF;
            --shadow: 0 2px 6px rgba(47, 43, 61, 0.14);
            --radius: 8px;
        }

        /* ===== CONTAINER PRINCIPAL ===== */
        .container-main {
            padding: 24px;
            background: var(--bg-light);
            min-height: 100vh;
        }

        /* ===== TÍTULO DA PÁGINA ===== */
        .title-page {
            margin-bottom: 24px;
        }

        .title-page .title {
            display: block;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .title-page .info-title {
            display: block;
            font-size: 14px;
            color: var(--text-secondary);
            font-style: italic;
        }

        /* ===== HEADER AÇÕES ===== */
        .page-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: var(--white);
            padding: 10px 20px;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #5A50E0;
            box-shadow: 0 4px 12px rgba(115, 103, 240, 0.4);
            transform: translateY(-1px);
        }

        .btn-primary i {
            font-size: 16px;
        }

        /* ===== CARDS ESTATÍSTICAS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(47, 43, 61, 0.18);
        }

        .stat-content .stat-label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .stat-content .stat-value {
            display: block;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-content .stat-info {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }

        .stat-info.positive {
            color: var(--success);
        }

        .stat-info.negative {
            color: var(--danger);
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 20px;
        }

        .stat-icon.primary {
            background: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.success {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-icon.warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .stat-icon.danger {
            background: var(--danger-light);
            color: var(--danger);
        }

        /* ===== CARD TABELA ===== */
        .table-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        /* ===== FILTROS ===== */
        .filter-section {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .filter-toggle i {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            background: var(--white);
            transition: border-color 0.2s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .filter-actions {
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .btn-filter {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-filter.primary {
            background: var(--primary);
            color: var(--white);
            border: none;
        }

        .btn-filter.primary:hover {
            background: #5A50E0;
        }

        .btn-filter.outline {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-filter.outline:hover {
            border-color: var(--text-secondary);
        }

        /* ===== TABELA ===== */
        .table-wrapper {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: var(--bg-light);
        }

        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .data-table td {
            padding: 14px 16px;
            font-size: 14px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background 0.15s ease;
        }

        .data-table tbody tr:hover {
            background: var(--bg-light);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Processo */
        .processo-link {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .processo-link:hover {
            color: #5A50E0;
            text-decoration: underline;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .status-badge.pendente {
            background: var(--warning-light);
            color: #B26A00;
        }

        .status-badge.pendente::before {
            background: var(--warning);
        }

        .status-badge.cumprido {
            background: var(--success-light);
            color: #1B8A4B;
        }

        .status-badge.cumprido::before {
            background: var(--success);
        }

        .status-badge.vencido {
            background: var(--danger-light);
            color: #C43A3B;
        }

        .status-badge.vencido::before {
            background: var(--danger);
        }

        .status-badge.em-andamento {
            background: var(--info-light);
            color: #008B9E;
        }

        .status-badge.em-andamento::before {
            background: var(--info);
        }

        /* Prazo */
        .prazo-urgente {
            color: var(--danger);
            font-weight: 500;
        }

        .prazo-normal {
            color: var(--text-primary);
        }

        /* Usuário */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .user-name {
            font-size: 14px;
            color: var(--text-primary);
        }

        /* Ações */
        .actions-cell {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-action:hover {
            background: var(--bg-light);
            color: var(--primary);
        }

        .btn-action i {
            font-size: 16px;
        }

        /* ===== PAGINAÇÃO ===== */
        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
        }

        .table-info {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .pagination-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid var(--border-color);
            background: var(--white);
            border-radius: 6px;
            font-size: 13px;
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(.active):not(:disabled) {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: var(--white);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ===== RESPONSIVO ===== */
        @media (max-width: 768px) {
            .container-main {
                padding: 16px;
            }

            .page-header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .table-footer {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <!-- Título da Página -->
        <div class="title-page">
            <span class="title">Cartas Recebidas</span>
            <span class="info-title">Espaço onde a jurisdição alheia é recebida, organizada e cumprida, para que o pedido não fique sem resposta.</span>
        </div>

        <!-- Header com Botão de Cadastro -->
        <div class="page-header-actions">
            <div></div>
            <a href="/cartas/cadastrar" class="btn-primary">
                <i class="fi fi-rr-plus"></i>
                Nova Carta
            </a>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Total de Cartas</span>
                    <span class="stat-value">248</span>
                    <span class="stat-info positive">
                        <i class="fi fi-rr-arrow-trend-up"></i>
                        +12 este mês
                    </span>
                </div>
                <div class="stat-icon primary">
                    <i class="fi fi-rr-envelope"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Pendentes</span>
                    <span class="stat-value">45</span>
                    <span class="stat-info">Aguardando cumprimento</span>
                </div>
                <div class="stat-icon warning">
                    <i class="fi fi-rr-clock"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Cumpridas</span>
                    <span class="stat-value">186</span>
                    <span class="stat-info positive">
                        <i class="fi fi-rr-check"></i>
                        75% do total
                    </span>
                </div>
                <div class="stat-icon success">
                    <i class="fi fi-rr-check-circle"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Vencidas</span>
                    <span class="stat-value">17</span>
                    <span class="stat-info negative">
                        <i class="fi fi-rr-triangle-warning"></i>
                        Atenção necessária
                    </span>
                </div>
                <div class="stat-icon danger">
                    <i class="fi fi-rr-alarm-exclamation"></i>
                </div>
            </div>
        </div>

        <!-- Card da Tabela -->
        <div class="table-card">
            <!-- Seção de Filtros -->
            <div class="filter-section">
                <button class="filter-toggle" onclick="toggleFilter()">
                    <i class="fi fi-rr-filter"></i>
                    Filtrar
                </button>

                <div class="filter-form" id="filterForm" style="display: none;">
                    <div class="filter-group">
                        <label for="filterProcesso">Processo</label>
                        <input type="text" id="filterProcesso" placeholder="Nº do processo">
                    </div>

                    <div class="filter-group">
                        <label for="filterFinalidade">Finalidade</label>
                        <select id="filterFinalidade">
                            <option value="">Todas</option>
                            <option value="citacao">Citação</option>
                            <option value="intimacao">Intimação</option>
                            <option value="penhora">Penhora</option>
                            <option value="avaliacao">Avaliação</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filterComarca">Comarca</label>
                        <input type="text" id="filterComarca" placeholder="Comarca de origem">
                    </div>

                    <div class="filter-group">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus">
                            <option value="">Todos</option>
                            <option value="pendente">Pendente</option>
                            <option value="em-andamento">Em Andamento</option>
                            <option value="cumprido">Cumprido</option>
                            <option value="vencido">Vencido</option>
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button class="btn-filter primary">Filtrar</button>
                        <button class="btn-filter outline" onclick="clearFilters()">Limpar</button>
                    </div>
                </div>
            </div>

            <!-- Tabela -->
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Processo</th>
                            <th>Finalidade</th>
                            <th>Prazo</th>
                            <th>Comarca (Origem)</th>
                            <th>Status</th>
                            <th>Usuário</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a href="#" class="processo-link">0001234-56.2024.8.05.0103</a></td>
                            <td>Citação</td>
                            <td class="prazo-urgente">15/01/2025</td>
                            <td>Salvador - BA</td>
                            <td><span class="status-badge pendente">Pendente</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">MS</div>
                                    <span class="user-name">Maria Silva</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><a href="#" class="processo-link">0002345-67.2024.8.05.0103</a></td>
                            <td>Intimação</td>
                            <td class="prazo-normal">28/01/2025</td>
                            <td>Itabuna - BA</td>
                            <td><span class="status-badge em-andamento">Em Andamento</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">JO</div>
                                    <span class="user-name">João Oliveira</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><a href="#" class="processo-link">0003456-78.2024.8.05.0103</a></td>
                            <td>Penhora</td>
                            <td class="prazo-normal">05/02/2025</td>
                            <td>Feira de Santana - BA</td>
                            <td><span class="status-badge cumprido">Cumprido</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">AC</div>
                                    <span class="user-name">Ana Costa</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><a href="#" class="processo-link">0004567-89.2024.8.05.0103</a></td>
                            <td>Avaliação</td>
                            <td class="prazo-urgente">10/01/2025</td>
                            <td>Vitória da Conquista - BA</td>
                            <td><span class="status-badge vencido">Vencido</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">PS</div>
                                    <span class="user-name">Pedro Santos</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><a href="#" class="processo-link">0005678-90.2024.8.05.0103</a></td>
                            <td>Citação</td>
                            <td class="prazo-normal">20/02/2025</td>
                            <td>Jequié - BA</td>
                            <td><span class="status-badge pendente">Pendente</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">LF</div>
                                    <span class="user-name">Lucas Ferreira</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><a href="#" class="processo-link">0006789-01.2024.8.05.0103</a></td>
                            <td>Intimação</td>
                            <td class="prazo-normal">12/02/2025</td>
                            <td>Camaçari - BA</td>
                            <td><span class="status-badge cumprido">Cumprido</span></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">MS</div>
                                    <span class="user-name">Maria Silva</span>
                                </div>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <button class="btn-action" title="Editar">
                                        <i class="fi fi-rr-pencil"></i>
                                    </button>
                                    <button class="btn-action" title="Menu Rápido">
                                        <i class="fi fi-rr-menu-dots-vertical"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Rodapé da Tabela / Paginação -->
            <div class="table-footer">
                <div class="table-info">
                    Mostrando <strong>1-6</strong> de <strong>248</strong> registros
                </div>
                <div class="pagination">
                    <button class="pagination-btn" disabled>
                        <i class="fi fi-rr-angle-left"></i>
                    </button>
                    <button class="pagination-btn active">1</button>
                    <button class="pagination-btn">2</button>
                    <button class="pagination-btn">3</button>
                    <button class="pagination-btn">...</button>
                    <button class="pagination-btn">42</button>
                    <button class="pagination-btn">
                        <i class="fi fi-rr-angle-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleFilter() {
            const filterForm = document.getElementById('filterForm');
            if (filterForm.style.display === 'none') {
                filterForm.style.display = 'grid';
            } else {
                filterForm.style.display = 'none';
            }
        }

        function clearFilters() {
            document.getElementById('filterProcesso').value = '';
            document.getElementById('filterFinalidade').value = '';
            document.getElementById('filterComarca').value = '';
            document.getElementById('filterStatus').value = '';
        }
    </script>
</body>
</html>