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
    'month' => [
        'current' => 0.0,
        'current_to_date' => 0.0,
        'previous' => 0.0,
        'variation_percent' => 0.0,
        'variation_percent_proportional' => 0.0,
        'expected_to_date' => 0.0,
        'delta_points' => 0.0,
        'prev_business_days' => 0,
        'current_business_days_total' => 0,
        'current_business_days_full_elapsed' => 0,
        'current_business_days_effective_elapsed' => 0.0,
        'current_business_days_remaining' => 0,
        'today_fraction' => 0.0,
        'today_is_business_day' => false,
    ],
    'projection' => [
        'projected_points' => 0.0,
        'current_points' => 0.0,
        'business_days_total' => 0,
        'business_days_with_launch' => 0,
        'business_days_effective_elapsed' => 0.0,
        'business_days_remaining' => 0,
    ],
];
$rankingTop10 = [];
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
                $rankingTop10 = array_slice($allRankingRows, 0, 10);
                foreach ($allRankingRows as $row) {
                    $rankingUserId = trim((string) ($row['usuario_id'] ?? $row['id_usuario'] ?? ''));
                    if ($rankingUserId !== '' && $rankingUserId === $userId) {
                        $userRankingRow = $row;
                        break;
                    }
                }
            }
        }
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
        $prodError = $e->getMessage();
    }
}

$monthPts = (float) (($resumo['month']['current_to_date'] ?? $resumo['month']['current'] ?? 0));
$monthPrev = (float) ($resumo['month']['previous'] ?? 0);
$monthVpa = (float) (($resumo['month']['variation_percent_proportional'] ?? 0));
$deltaPts = (float) ($resumo['month']['delta_points'] ?? 0);
$expectedPts = (float) ($resumo['month']['expected_to_date'] ?? 0);
$bizPrev = (int) ($resumo['month']['prev_business_days'] ?? 0);
$bizTotal = (int) ($resumo['month']['current_business_days_total'] ?? 0);
$bizFullElap = (int) ($resumo['month']['current_business_days_full_elapsed'] ?? 0);
$bizRemaining = (int) ($resumo['month']['current_business_days_remaining'] ?? 0);
$dayFrac = (float) ($resumo['month']['today_fraction'] ?? 0);
$bizEff = (float) ($resumo['month']['current_business_days_effective_elapsed'] ?? ($resumo['projection']['business_days_effective_elapsed'] ?? 0));
$projPts = (float) ($resumo['projection']['projected_points'] ?? 0);
$bizComLanc = (int) ($resumo['projection']['business_days_with_launch'] ?? 0);

$deltaPtsFmt = ($deltaPts >= 0 ? '+' : '−') . number_format(abs($deltaPts), 2, ',', '.');

$bizElapsedDisplay = $bizFullElap + (($dayFrac > 0.0) ? 1 : 0);
$bizElapsedDisplay = max(0, min($bizTotal, $bizElapsedDisplay));
$diasUteisLabel = $bizElapsedDisplay === 1 ? 'dia útil' : 'dias úteis';
$diasRestantesLabel = $bizRemaining === 1 ? 'restante' : 'restantes';
$cardSubtitle = sprintf(
    '%s · %d/%d %s · %d %s',
    $mesRef,
    $bizElapsedDisplay,
    $bizTotal,
    $diasUteisLabel,
    $bizRemaining,
    $diasRestantesLabel
);

$monthVpaFmt = $monthVpa > 0
    ? '+' . number_format($monthVpa, 1, ',', '.') . '%'
    : number_format($monthVpa, 1, ',', '.') . '%';
$vpaAbs = abs($monthVpa);
if ($vpaAbs <= 10.0) {
    $vpaBadgeClass = 'badge badge-primary dashboard-vpa-badge';
    $vpaIconClass = 'fi fi-bs-arrow-alt-right';
} elseif ($monthVpa > 10.0) {
    $vpaBadgeClass = 'badge badge-success dashboard-vpa-badge';
    $vpaIconClass = 'fi fi-bs-arrow-trend-up';
} else {
    $vpaBadgeClass = 'badge badge-danger dashboard-vpa-badge';
    $vpaIconClass = 'fi fi-bs-arrow-trend-down';
}

$titleVpaBadge = sprintf(
    'Variação em relação ao esperado até o momento: referência %s pts (ritmo do mês anterior aplicado a %s dia(s) útil(is) efetivo(s), turno 8h–18h no dia corrente).',
    number_format($expectedPts, 2, ',', '.'),
    number_format($bizEff, 1, ',', '.')
);
$titleAnteriorStat = sprintf(
    'Total do mês anterior em %d dia(s) útil(eis) (segunda a sexta, excluindo feriados cadastrados).',
    $bizPrev
);
$titleDeltaStat = sprintf(
    'Diferença entre o realizado (%s pts) e o esperado (%s pts) no mesmo critério.',
    number_format($monthPts, 2, ',', '.'),
    number_format($expectedPts, 2, ',', '.')
);
$titleProjStat = sprintf(
    'Estimativa para o fim do mês: ritmo de pontos por dia útil efetivo decorrido aplicado aos %d dia(s) úteis do mês (inclui %d dia(s) com lançamento até hoje).',
    $bizTotal,
    $bizComLanc
);
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
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-bold-straight/css/uicons-bold-straight.css">
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
        .badge.dashboard-vpa-badge {
            text-transform: none;
            font-variant-numeric: tabular-nums;
            height: auto;
            min-height: 22px;
            padding: 0.2em 0.65em;
            gap: 0.35em;
        }
        .badge.dashboard-vpa-badge > i.fi {
            font-size: 0.95em;
            margin-bottom: -3px;
            line-height: 1;
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
            padding: 4px 10px;
            border-radius: 3px;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }
        .dashboard-ranking-row.is-me {
            background: var(--color-success);
            outline: 1px solid var(--color-success-active);
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
        .dashboard-points-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 8px;
        }
        .dashboard-points-head .major-info {
            margin: 0;
        }
        .dashboard-points-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            border-top: 1px solid var(--border-color, #e2e8f0);
            padding-top: 10px;
            margin-top: 10px;
        }
        .dashboard-points-stat {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .dashboard-points-value {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dashboard-points-value.is-positive { color: var(--color-success, #16a34a); }
        .dashboard-points-value.is-negative { color: var(--color-danger, #dc2626); }
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
        @media (max-width: 480px) {
            .dashboard-points-grid { gap: 6px; }
            .dashboard-points-value { font-size: 0.9rem; }
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
                                <span class="minor-info"><?php echo htmlspecialchars($mesRef, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
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
                                        <?php foreach ($rankingTop10 as $row): ?>
                                            <?php
                                            $isMe = (string) ($row['usuario_id'] ?? '') === $userId;
                                            $nome = ProdutividadeRankingDisplayHelper::doisPrimeirosNomes((string) ($row['usuario_nome'] ?? ''));
                                            $pts = (float) ($row['pontos_total'] ?? 0);
                                            $pos = (int) ($row['posicao'] ?? 0);
                                            ?>
                                            <li class="dashboard-ranking-row<?php echo $isMe ? ' is-me' : ''; ?>">
                                                <span style="font-size: 12px;" style="<?php echo $isMe ? 'font-weight: 700;' : ''; ?>"><span class="dashboard-ranking-pos" style="font-family: monospace;"><?php echo $pos; ?>.</span> <?php echo htmlspecialchars($nome !== '' ? $nome : 'Usuário', ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span style="font-family: monospace; font-size: 12px;"><?php echo number_format($pts, 2, ',', '.'); ?> pts</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>

                                <?php if ($userRankingRow !== null && (int) ($userRankingRow['posicao'] ?? 0) > 10): ?>
                                    <div class="dashboard-ranking-me">
                                        <strong>Sua posição:</strong> <?php echo (int) ($userRankingRow['posicao'] ?? 0); ?>.
                                        · <?php echo number_format((float) ($userRankingRow['pontos_total'] ?? 0), 2, ',', '.'); ?> pts
                                    </div>
                                <?php elseif ($userRankingRow === null && $rankingTop10 !== []): ?>
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
                                <h2>Produtividade geral</h2>
                                <span class="minor-info"><?php echo htmlspecialchars($cardSubtitle, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <div class="dashboard-interno-card-body">
                            <span style="font-size: 11px;">Pontuação no período atual</span>
                            <div class="dashboard-points-head margin-top-sm">
                                <div class="major-info" style="font-size: 14px; font-weight: 600;"><?php echo number_format($monthPts, 2, ',', '.'); ?> pts</div>
                                <span class="<?php echo htmlspecialchars($vpaBadgeClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($titleVpaBadge, ENT_QUOTES, 'UTF-8'); ?>"><i class="<?php echo htmlspecialchars($vpaIconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i><?php echo htmlspecialchars($monthVpaFmt, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="dashboard-points-grid display-flex row row-full align-center justify-between">
                                <div class="dashboard-points-stat display-flex col col-fit align-left justify-center" style="align-items: start;" title="<?php echo htmlspecialchars($titleAnteriorStat, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span style="font-size: 11px;">Período anterior</span>
                                    <span class="dashboard-points-value" style="font-size: 12px;"><?php echo number_format($monthPrev, 2, ',', '.'); ?></span>
                                </div>
                                <div class="dashboard-points-stat display-flex col col-fit align-center justify-center" style="align-items: center;" title="<?php echo htmlspecialchars($titleDeltaStat, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span style="font-size: 11px;">Diferença</span>
                                    <span class="dashboard-points-value<?php echo $deltaPts >= 0 ? ' is-positive' : ' is-negative'; ?>" style="font-size: 12px;"><?php echo htmlspecialchars($deltaPtsFmt, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="dashboard-points-stat display-flex col col-fit align-right justify-center" style="align-items: end;"title="<?php echo htmlspecialchars($titleProjStat, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span style="font-size: 11px;">Projeção esperada</span>
                                    <span class="dashboard-points-value" style="font-size: 12px;"><?php echo number_format($projPts, 2, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

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
    <script src="/assets/js/modules/produtividade/badge-popup.js"></script>
    <script src="/assets/js/modules/dashboard/metas-cartorio-card.js"></script>
</body>
</html>
