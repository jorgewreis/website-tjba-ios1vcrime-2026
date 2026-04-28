<?php
declare(strict_types=1);

Security::requireAuthentication();

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';
require_once __DIR__ . '/../../helpers/DashboardInternoAlocacaoResumoHelper.php';
require_once __DIR__ . '/../../helpers/ProdutividadeRankingDisplayHelper.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

$prodError = '';
$resumo = [
    'month' => ['current' => 0.0, 'previous' => 0.0, 'variation_percent' => 0.0],
];
$rankingTop5 = [];
$allRankingRows = [];
$userRankingRow = null;
$rankingOculto = false;
$podeVerRanking = false;
$alocacaoCards = [];

$mesesPt = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mesRef = $mesesPt[(int) date('n')] . ' de ' . date('Y');

if ($userId !== '') {
    try {
        $resumo = $service->getResumoCards($userId, [
            'status' => 'ativo',
            'tipo_lancamento' => '',
        ]);
        $podeVerRanking = $service->usuarioPodeVerRankingProdutividade($userId);
        if ($podeVerRanking) {
            $rankingPayload = $service->listRanking($userId, ['status' => 'ativo'], 200);
            $rankingOculto = !empty($rankingPayload['ranking_oculto']);
            $allRankingRows = $rankingPayload['rows'] ?? [];
            if ($allRankingRows !== [] && !$rankingOculto) {
                $rankingTop5 = array_slice($allRankingRows, 0, 5);
                foreach ($allRankingRows as $row) {
                    if ((string) ($row['usuario_id'] ?? '') === $userId) {
                        $userRankingRow = $row;
                        break;
                    }
                }
            }
        }
        $alocacaoCards = DashboardInternoAlocacaoResumoHelper::cardsParaUsuario($connection, $userId);
    } catch (Throwable $e) {
        $prodError = $e->getMessage();
    }
}

$monthPts = (float) (($resumo['month']['current'] ?? 0));
$monthVar = (float) (($resumo['month']['variation_percent'] ?? 0));
$monthVarFmt = $monthVar > 0
    ? '+' . number_format($monthVar, 1, ',', '.') . '%'
    : number_format($monthVar, 1, ',', '.') . '%';
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }
        @media (max-width: 1100px) {
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
        .dashboard-ranking-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .dashboard-ranking-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        .dashboard-ranking-row.is-me {
            background: rgba(59, 130, 246, 0.12);
            outline: 1px solid rgba(59, 130, 246, 0.35);
        }
        .dashboard-ranking-pos {
            font-weight: 600;
            min-width: 2rem;
        }
        .dashboard-ranking-me {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.25);
            font-size: 0.95rem;
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
        <p class="description-page">Resumo de produtividade, ranking mensal e tarefas do cartório conforme suas regras de alocação.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Painel</h2>
        </div>
        <div class="padding-x-md padding-y-md">
            <?php if ($prodError !== ''): ?>
                <p class="status status-cancelled margin-bottom-md"><?php echo htmlspecialchars('Erro ao carregar o painel: ' . $prodError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <div class="dashboard-interno-grid">
                <div class="dashboard-interno-col">
                    <div class="dashboard-interno-card" id="produtividade-dashboard-ranking">
                        <div class="dashboard-interno-card-head">
                            <div>
                                <h2>Ranking mensal</h2>
                                <span class="minor-info"><?php echo htmlspecialchars($mesRef, ENT_QUOTES, 'UTF-8'); ?> · top 5 (Cartório e Gabinete)</span>
                            </div>
                            <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=produtividade">Abrir módulo</button>
                        </div>
                        <div class="dashboard-interno-card-body">
                            <?php if (!$podeVerRanking): ?>
                                <p class="minor-info">O ranking de produtividade não está disponível para o seu perfil de lotação (ex.: Sala de Audiências).</p>
                            <?php elseif ($rankingOculto): ?>
                                <p class="minor-info">Ranking indisponível para o seu perfil neste período.</p>
                            <?php elseif ($allRankingRows === []): ?>
                                <p class="minor-info">Ainda não há pontos registrados no ranking neste mês.</p>
                            <?php else: ?>
                                    <ul class="dashboard-ranking-list">
                                        <?php foreach ($rankingTop5 as $row): ?>
                                            <?php
                                            $isMe = (string) ($row['usuario_id'] ?? '') === $userId;
                                            $nome = ProdutividadeRankingDisplayHelper::doisPrimeirosNomes((string) ($row['usuario_nome'] ?? ''));
                                            $pts = (float) ($row['pontos_total'] ?? 0);
                                            $pos = (int) ($row['posicao'] ?? 0);
                                            ?>
                                            <li class="dashboard-ranking-row<?php echo $isMe ? ' is-me' : ''; ?>">
                                                <span><span class="dashboard-ranking-pos"><?php echo $pos; ?>.</span> <?php echo htmlspecialchars($nome !== '' ? $nome : 'Usuário', ENT_QUOTES, 'UTF-8'); ?><?php echo $isMe ? ' <span class="minor-info">(você)</span>' : ''; ?></span>
                                                <span><?php echo number_format($pts, 2, ',', '.'); ?> pts</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                <?php if ($userRankingRow !== null): ?>
                                    <div class="dashboard-ranking-me">
                                        <strong>Sua posição:</strong> <?php echo (int) ($userRankingRow['posicao'] ?? 0); ?>.
                                        · <?php echo number_format((float) ($userRankingRow['pontos_total'] ?? 0), 2, ',', '.'); ?> pts
                                    </div>
                                <?php elseif ($rankingTop5 !== []): ?>
                                    <p class="minor-info margin-top-md">Você ainda não possui pontos no ranking deste mês; sua posição aparecerá quando houver lançamentos válidos.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-interno-col">
                    <div class="dashboard-interno-card" id="produtividade-dashboard-widget">
                        <div class="dashboard-interno-card-head">
                            <div>
                                <h2>Pontos no mês</h2>
                                <span class="minor-info"><?php echo htmlspecialchars($mesRef, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=produtividade">Abrir módulo</button>
                        </div>
                        <div class="dashboard-interno-card-body">
                            <span class="minor-info">Total no mês civil</span>
                            <div class="major-info"><?php echo number_format($monthPts, 2, ',', '.'); ?> pts</div>
                            <p class="minor-info margin-top-sm">Variação vs. mês anterior: <?php echo htmlspecialchars($monthVarFmt, ENT_QUOTES, 'UTF-8'); ?></p>
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
    <script src="/assets/js/modules/produtividade/badge-popup.js"></script>
</body>
</html>
