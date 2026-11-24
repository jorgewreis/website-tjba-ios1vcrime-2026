<?php
declare(strict_types=1);
Security::requireAuthentication();
$dashboardJs = dirname(__DIR__, 3) . '/public/assets/js/dashboard-interno.js';
$dashboardCss = dirname(__DIR__, 3) . '/public/assets/css/dashboard-interno.css';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Primeira Vara Crime de Ilhéus</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/dashboard-interno.css?v=<?php echo (int)@filemtime($dashboardCss); ?>">
</head>
<body class="page-internal dashboard-interno">
    <div class="spacing-top-page"></div>
    <header class="header-page"><div class="location"><span class="all">Interno -</span> <span class="specific">Dashboard</span></div><h1 class="title-page">Visão operacional</h1></header>
    <main id="dashboard-interno" class="dashboard-shell" aria-label="Visão operacional do sistema" aria-live="polite">
      <div id="dashboard-erro" class="dashboard-error" hidden></div>
      <div class="dashboard-interno-columns">
        <section class="dashboard-column dashboard-system" aria-labelledby="dash-system-title">
          <header><span class="column-kicker">SISTEMA</span><h2 id="dash-system-title">Fluxo geral</h2></header>
          <article class="dash-card dash-letters-card" aria-label="Cartas ativas e cadastros dos últimos sete dias">
            <div class="dash-summary-heading"><strong>Cartas ativas</strong><span>Total atual</span></div>
            <div class="dash-summary-stats">
              <div><span><i class="legend-dot legend-purple" aria-hidden="true"></i>Expedidas</span><strong id="dash-registered-expedidas">—</strong></div>
              <div><span><i class="legend-dot legend-mint" aria-hidden="true"></i>Recebidas</span><strong id="dash-registered-recebidas">—</strong></div>
            </div>
            <small class="dash-summary-period">Cadastros por dia · últimos 7 dias</small>
            <div class="dash-letters-chart"><canvas id="dash-chart-cadastros-semana" aria-label="Todas as cartas cadastradas por dia nos últimos sete dias: expedidas em roxo e recebidas em verde"></canvas></div>
          </article>
          <article class="dash-card dash-offices-card" aria-label="Ofícios cadastrados no ano corrente e nos últimos sete dias">
            <div class="dash-summary-heading"><strong>Ofícios cadastrados</strong><span>Ano corrente</span></div>
            <div class="dash-summary-stats">
              <div><span><i class="legend-dot legend-purple" aria-hidden="true"></i>Cartório</span><strong id="dash-offices-year-cartorio">—</strong></div>
              <div><span><i class="legend-dot legend-mint" aria-hidden="true"></i>Gabinete</span><strong id="dash-offices-year-gabinete">—</strong></div>
            </div>
            <small class="dash-summary-period">Cadastros por dia · últimos 7 dias</small>
            <div class="dash-weekly-chart dash-offices-chart"><canvas id="dash-chart-oficios-semana" aria-label="Quantidade de ofícios cadastrados por dia nos últimos sete dias, no Cartório e no Gabinete"></canvas></div>
          </article>
        </section>
        <section class="dashboard-column dashboard-sector" aria-labelledby="dash-sector-title">
          <header><span class="column-kicker">SETOR</span><h2 id="dash-sector-title">Minha área</h2></header>
          <article id="dash-cartorio-queue" class="dash-card dash-sector-queue" hidden>
            <div class="dash-sector-metric" id="dash-queue-expedidas-row"><span><b>Cartas Expedidas</b><br>aguardando envio</span><strong id="dash-queue-expedidas">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-recebidas-row"><span><b>Cartas Recebidas</b><br>aguardando devolução</span><strong id="dash-queue-recebidas">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-oficios-row"><span><b>Ofícios do Cartório</b><br>aguardando envio</span><strong id="dash-queue-oficios">0</strong></div>
            <div class="dash-sector-metric" id="dash-queue-editais-row" hidden><span><b>Editais</b><br>aguardando publicação</span><strong id="dash-queue-editais">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-guias-expedicao-row" hidden><span><b>Guias aguardando expedição</b></span><strong id="dash-queue-guias-expedicao">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-guias-envio-row" hidden><span><b>Guias aguardando envio</b></span><strong id="dash-queue-guias-envio">—</strong></div>
            <div class="dash-sector-metric dash-sector-overdue" id="dash-overdue-expedidas-row" hidden><span><b>Cartas Expedidas</b><br>com prazo vencido</span><strong id="dash-overdue-expedidas">—</strong></div>
            <div class="dash-sector-metric dash-sector-overdue" id="dash-overdue-recebidas-row" hidden><span><b>Cartas Recebidas</b><br>com prazo vencido</span><strong id="dash-overdue-recebidas">—</strong></div>
          </article>
        </section>
        <section class="dashboard-column dashboard-work" aria-labelledby="dash-work-title">
          <header><span class="column-kicker">PRODUTIVIDADE</span><h2 id="dash-work-title">Meu trabalho</h2></header>
          <article class="dash-card dash-production-summary" aria-label="Movimentações do usuário nos últimos 30 dias">
            <div class="dash-summary-heading"><strong>Movimentações</strong><span class="dash-production-period">Últimos 30 dias</span></div>
            <div class="dash-production-grid">
              <div class="dash-production-metric"><span class="dash-production-label"><i class="legend-dot legend-purple" aria-hidden="true"></i>Cartas Expedidas</span><strong class="dash-production-number" id="dash-done-expedidas">—</strong></div>
              <div class="dash-production-metric"><span class="dash-production-label"><i class="legend-dot legend-mint" aria-hidden="true"></i>Cartas Recebidas</span><strong class="dash-production-number dash-production-green" id="dash-done-recebidas">—</strong></div>
              <div class="dash-production-metric"><span class="dash-production-label"><i class="legend-dot legend-purple" aria-hidden="true"></i>Ofícios Expedidos</span><strong class="dash-production-number" id="dash-done-oficios">—</strong></div>
              <div class="dash-production-metric"><span class="dash-production-label"><i class="legend-dot legend-mint" aria-hidden="true"></i>Medidas Protetivas Expedidas</span><strong class="dash-production-number dash-production-green" id="dash-done-medidas-protetivas">—</strong></div>
            </div>
          </article>
        </section>
        <section class="dashboard-column dashboard-agenda" aria-labelledby="dash-agenda-title">
          <header><span class="column-kicker">AGENDA</span><h2 id="dash-agenda-title">Agenda e alertas</h2></header>
          <div id="dash-alerts" class="dash-alert-list"></div>
        </section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/vendor-chart.umd.js"></script>
    <script src="/assets/js/dashboard-interno.js?v=<?php echo (int)@filemtime($dashboardJs); ?>"></script>
</body></html>
