<?php
declare(strict_types=1);
Security::requireAuthentication();
$dashboardJs = dirname(__DIR__, 3) . '/public/assets/js/dashboard-interno.js';
$dashboardCss = dirname(__DIR__, 3) . '/public/assets/css/dashboard-interno.css';
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
          <header><span class="column-kicker">01 / SISTEMA</span><h2 id="dash-system-title">Fluxo geral</h2><p>Cartas Expedidas e Cartas Recebidas da Vara</p></header>
          <article class="dash-card dash-letters-card" aria-label="Cartas ativas e cadastros dos últimos sete dias">
            <div class="dash-letters-summary">
              <div class="dash-letters-stat"><span class="dash-letters-label"><i class="legend-dot legend-purple" aria-hidden="true"></i>Cartas Expedidas</span><div><strong id="dash-registered-expedidas">—</strong></div><small id="dash-week-expedidas">Últimos 7 dias</small></div>
              <div class="dash-letters-stat dash-letters-mint"><span class="dash-letters-label"><i class="legend-dot legend-mint" aria-hidden="true"></i>Cartas Recebidas</span><div><strong id="dash-registered-recebidas">—</strong></div><small id="dash-week-recebidas">Últimos 7 dias</small></div>
            </div>
            <div class="dash-weekly-chart"><canvas id="dash-chart-cadastros-semana" aria-label="Todas as cartas cadastradas por dia nos últimos sete dias: expedidas em roxo e recebidas em verde"></canvas></div>
          </article>
        </section>
        <section class="dashboard-column dashboard-sector" aria-labelledby="dash-sector-title">
          <header><span class="column-kicker">02 / SETOR</span><h2 id="dash-sector-title">Minha área</h2><p>Pendências no setor autorizado</p></header>
          <article id="dash-cartorio-queue" class="dash-card dash-sector-queue" hidden>
            <div class="dash-sector-metric" id="dash-queue-expedidas-row"><span><b>Cartas Expedidas</b><br>aguardando envio</span><strong id="dash-queue-expedidas">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-recebidas-row"><span><b>Cartas Recebidas</b><br>aguardando devolução</span><strong id="dash-queue-recebidas">—</strong></div>
            <div class="dash-sector-metric" id="dash-queue-editais-row" hidden><span><b>Editais</b><br>aguardando publicação</span><strong id="dash-queue-editais">—</strong></div>
            <div class="dash-sector-metric dash-sector-overdue" id="dash-overdue-expedidas-row" hidden><span><b>Cartas Expedidas</b><br>com prazo vencido</span><strong id="dash-overdue-expedidas">—</strong></div>
            <div class="dash-sector-metric dash-sector-overdue" id="dash-overdue-recebidas-row" hidden><span><b>Cartas Recebidas</b><br>com prazo vencido</span><strong id="dash-overdue-recebidas">—</strong></div>
          </article>
        </section>
        <section class="dashboard-column dashboard-work" aria-labelledby="dash-work-title">
          <header><span class="column-kicker">03 / PRODUTIVIDADE</span><h2 id="dash-work-title">Meu trabalho</h2><p>Movimentações feitas pelo usuário nas duas páginas</p></header>
          <article class="dash-card dash-production-summary" aria-label="Movimentações do usuário nos últimos 30 dias">
            <div class="dash-production-heading"><strong>Movimentações</strong><span class="dash-production-period">Últimos 30 dias</span></div>
            <div class="dash-production-grid">
              <div class="dash-production-metric"><span class="dash-production-label"><i class="legend-dot legend-purple" aria-hidden="true"></i>Cartas Expedidas</span><div class="dash-production-value"><strong class="dash-production-number" id="dash-done-expedidas">—</strong><span>movimentadas</span></div><div class="dash-mini-chart" hidden><canvas id="dash-spark-user-expedidas" aria-label="Movimentações diárias de cartas expedidas pelo usuário nos últimos sete dias"></canvas></div></div>
              <div class="dash-production-metric dash-production-mint"><span class="dash-production-label"><i class="legend-dot legend-mint" aria-hidden="true"></i>Cartas Recebidas</span><div class="dash-production-value"><strong class="dash-production-number" id="dash-done-recebidas">—</strong><span>movimentadas</span></div><div class="dash-mini-chart" hidden><canvas id="dash-spark-user-recebidas" aria-label="Movimentações diárias de cartas recebidas pelo usuário nos últimos sete dias"></canvas></div></div>
            </div>
          </article>
        </section>
        <section class="dashboard-column dashboard-agenda" aria-labelledby="dash-agenda-title">
          <header><span class="column-kicker">04 / AGENDA</span><h2 id="dash-agenda-title">Agenda e alertas</h2><p>Janelas próximas e referência de fila</p></header>
          <div id="dash-alerts" class="dash-alert-list"></div>
        </section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/vendor-chart.umd.js"></script>
    <script src="/assets/js/dashboard-interno.js?v=<?php echo (int)@filemtime($dashboardJs); ?>"></script>
</body></html>
