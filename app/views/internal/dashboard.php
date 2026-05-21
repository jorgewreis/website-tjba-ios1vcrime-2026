<?php
declare(strict_types=1);

Security::requireAuthentication();

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/DashboardInternoAlocacaoResumoHelper.php';

$connection = Conection::getInstance()->getConnection();
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

$dashboardError = '';
$alocacaoCards = [];

if ($userId !== '') {
    try {
        $alocacaoCards = DashboardInternoAlocacaoResumoHelper::cardsParaUsuario($connection, $userId);
        $alocacaoCards = array_values(array_filter(
            $alocacaoCards,
            static function (array $card): bool {
                $blocos = $card['status_blocos'] ?? [];
                $pendente = 0;
                $emAndamento = 0;
                foreach ($blocos as $bloco) {
                    $label = mb_strtolower(trim((string) ($bloco['label'] ?? '')), 'UTF-8');
                    $count = (int) ($bloco['count'] ?? 0);
                    if ($label === 'pendente') {
                        $pendente += $count;
                    } elseif ($label === 'em andamento') {
                        $emAndamento += $count;
                    }
                }

                return ($pendente + $emAndamento) > 0;
            }
        ));
    } catch (Throwable $e) {
        $dashboardError = $e->getMessage();
    }
}
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

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <style>
        .dashboard-interno-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .dashboard-interno-grid {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-interno-col {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .dashboard-interno-card {
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px;
            overflow: hidden;
            background: var(--card-bg, #fff);
        }
        .dashboard-interno-card-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }
        .dashboard-interno-card-head h2 {
            margin: 0;
            font-size: 1.1rem;
        }
        .dashboard-interno-card-body {
            padding: 12px 16px 16px;
        }
        .dashboard-aloc-card {
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 8px;
            padding: 12px 14px;
            background: var(--card-bg, #fff);
        }
        .dashboard-aloc-card h3 {
            margin: 0 0 8px;
            font-size: 1rem;
        }
        .dashboard-aloc-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 6px;
        }
        .dashboard-aloc-status {
            padding: 6px 8px;
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.04);
            font-size: 0.8rem;
            line-height: 1.3;
        }
        .dashboard-aloc-status .count {
            display: block;
            font-weight: 600;
            font-size: 1rem;
        }
        .dashboard-metas-card-header {
            display: grid;
            grid-template-columns: 1fr;
            gap: 6px;
        }
        .dashboard-metas-card-ident {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 12px;
            border-bottom: 1px dashed var(--border-color, #e2e8f0);
            padding-bottom: 6px;
        }
        .dashboard-metas-card-ident strong {
            font-weight: 600;
            color: var(--color-gray-700, #374151);
        }
        .dashboard-metas-card-table {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .dashboard-metas-row {
            display: block;
            font-size: 12px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            padding-bottom: 5px;
        }
        .dashboard-metas-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .dashboard-metas-row .meta-line-1-row {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            min-width: 0;
        }
        .dashboard-metas-row .meta-line-1-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        .dashboard-metas-row .meta-line-1-meta {
            font-weight: 600;
            font-size: 11px;
            line-height: 1.25;
            color: var(--color-gray-700, #374151);
            cursor: help;
        }
        .dashboard-metas-row .meta-line-1-alvo {
            font-size: 10.5px;
            line-height: 1.25;
            color: var(--color-gray-600, #4b5563);
        }
        .dashboard-metas-row .dashboard-metas-cumpr-badge {
            flex-shrink: 0;
            margin: 0;
            align-self: center;
            text-transform: none;
            font-variant-numeric: tabular-nums;
            max-width: 42%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .badge-success {
            color: var(--color-gray-800);
        }
        .badge-danger {
            color: var(--color-gray-100);
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
        <p class="description-page">Evolução das metas do cartório e tarefas conforme suas regras de alocação.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Painel</h2>
        </div>
        <div class="padding-x-md padding-y-md">
            <?php if ($dashboardError !== ''): ?>
                <p class="status status-cancelled margin-bottom-md"><?php echo htmlspecialchars('Erro ao carregar o painel: ' . $dashboardError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="dashboard-interno-grid">
                <div class="dashboard-interno-col">
                    <div class="dashboard-interno-card" id="dashboard-metas-cartorio-card">
                        <div class="dashboard-interno-card-head">
                            <div>
                                <h2>Evolução das metas do cartório</h2>
                                <span class="minor-info" id="dashboard-metas-ano">Carregando referência...</span>
                            </div>
                        </div>
                        <div class="dashboard-interno-card-body">
                            <div class="dashboard-metas-card-header">
                                <div class="dashboard-metas-card-ident">
                                    <strong>Comarca</strong>
                                    <span id="dashboard-metas-comarca">Carregando...</span>
                                </div>
                                <div class="dashboard-metas-card-ident">
                                    <strong>Vara</strong>
                                    <span id="dashboard-metas-vara">Carregando...</span>
                                </div>
                            </div>
                            <div class="dashboard-metas-card-table" id="dashboard-metas-table">
                                <div class="dashboard-metas-row">
                                    <span class="meta-name">Aguardando dados da API</span>
                                    <span class="meta-value">...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-interno-col">
                    <?php if ($alocacaoCards === []): ?>
                        <div class="dashboard-interno-card">
                            <div class="dashboard-interno-card-head">
                                <h2>Regras de alocação</h2>
                            </div>
                            <div class="dashboard-interno-card-body">
                                <p class="minor-info">Nenhuma regra de alocação do cartório está atribuída a você no momento.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alocacaoCards as $card): ?>
                            <?php
                            $titulo = (string) ($card['titulo'] ?? '');
                            $blocos = $card['status_blocos'] ?? [];
                            $blocosFiltrados = [];
                            foreach ($blocos as $bloco) {
                                $label = mb_strtolower(trim((string) ($bloco['label'] ?? '')), 'UTF-8');
                                if ($label === 'pendente' || $label === 'em andamento') {
                                    $blocosFiltrados[] = $bloco;
                                }
                            }
                            ?>
                            <div class="dashboard-aloc-card">
                                <div class="display-flex row row-full align-center gap-sm margin-bottom-sm">
                                    <h3><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h3>
                                </div>
                                <span class="minor-info">Por status</span>
                                <div class="dashboard-aloc-status-grid margin-top-sm">
                                    <?php foreach ($blocosFiltrados as $bloco): ?>
                                        <?php
                                        $bLabel = (string) ($bloco['label'] ?? '');
                                        $bCount = (int) ($bloco['count'] ?? 0);
                                        $bTitle = (string) ($bloco['title'] ?? '');
                                        ?>
                                        <div class="dashboard-aloc-status" title="<?php echo htmlspecialchars($bTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="count"><?php echo $bCount; ?></span>
                                            <span><?php echo htmlspecialchars($bLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($blocosFiltrados === []): ?>
                                        <div class="dashboard-aloc-status" title="Sem tarefas pendentes ou em andamento neste módulo.">
                                            <span class="count">0</span>
                                            <span>Sem pendências</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/modules/dashboard/metas-cartorio-card.js"></script>
</body>
</html>
