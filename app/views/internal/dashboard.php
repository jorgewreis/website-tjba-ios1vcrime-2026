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
          <div class="dash-kpi-grid">
            <article class="dash-card dash-kpi-card dash-kpi-purple"><span class="dash-kpi-label">Cartas Expedidas</span><strong class="dash-kpi-value" id="dash-registered-expedidas">—</strong><div class="dash-kpi-foot"><span>cadastradas</span><canvas id="dash-spark-expedidas" aria-label="Cadastros de cartas expedidas na semana"></canvas></div></article>
            <article class="dash-card dash-kpi-card dash-kpi-mint"><span class="dash-kpi-label">Cartas Recebidas</span><strong class="dash-kpi-value" id="dash-registered-recebidas">—</strong><div class="dash-kpi-foot"><span>cadastradas</span><canvas id="dash-spark-recebidas" aria-label="Cadastros de cartas recebidas na semana"></canvas></div></article>
          </div>
          <article class="dash-card dash-weekly-card"><h3>Cadastros · últimos 7 dias</h3><canvas id="dash-chart-cadastros-semana" aria-label="Evolução semanal de cartas expedidas e recebidas cadastradas"></canvas><div class="dash-chart-legend"><span><i class="legend-dot legend-purple"></i>Expedidas</span><span><i class="legend-dot legend-mint"></i>Recebidas</span></div></article>
        </section>
        <section class="dashboard-column dashboard-sector" aria-labelledby="dash-sector-title">
          <header><span class="column-kicker">02 / SETOR</span><h2 id="dash-sector-title">Minha área</h2><p>Pendências de cartas no setor autorizado</p></header>
          <label class="dash-filter" for="dash-sector-filter">Filtrar setor</label><select id="dash-sector-filter" class="dashboard-sector-filter"><option value="">Todas as áreas autorizadas</option></select>
          <article class="dash-card dash-sector-queue">
            <div class="dash-sector-heading"><div><span class="dash-kpi-label">FILA DO CARTÓRIO</span><strong id="dash-areas">Áreas autorizadas</strong></div><span class="dash-sector-mark" aria-hidden="true">C</span></div>
            <div class="dash-sector-metric"><span>Cartas Expedidas<br>aguardando envio</span><strong id="dash-queue-expedidas">—</strong></div>
            <div class="dash-sector-metric"><span>Cartas Recebidas<br>aguardando devolução</span><strong id="dash-queue-recebidas">—</strong></div>
            <small class="dash-sector-footnote">Pendências na etapa atual do setor.</small>
          </article>
        </section>
        <section class="dashboard-column dashboard-work" aria-labelledby="dash-work-title">
          <header><span class="column-kicker">03 / PRODUTIVIDADE</span><h2 id="dash-work-title">Meu trabalho</h2><p>Movimentações feitas pelo usuário nas duas páginas</p></header>
          <article class="dash-card dash-production-card"><div><span class="dash-kpi-label">ÚLTIMOS 30 DIAS</span><strong class="dash-production-number" id="dash-done-expedidas">—</strong><span class="dash-production-label">Cartas Expedidas movimentadas</span></div><canvas id="dash-spark-user-expedidas" aria-label="Movimentações de cartas expedidas pelo usuário na semana"></canvas></article>
          <article class="dash-card dash-production-card dash-production-mint"><div><span class="dash-kpi-label">ÚLTIMOS 30 DIAS</span><strong class="dash-production-number" id="dash-done-recebidas">—</strong><span class="dash-production-label">Cartas Recebidas movimentadas</span></div><canvas id="dash-spark-user-recebidas" aria-label="Movimentações de cartas recebidas pelo usuário na semana"></canvas></article>
          <article class="dash-card dash-note-card"><span class="dash-note">Conta movimentações realizadas pelo usuário autenticado. O histórico começa no registro implantado.</span></article>
        </section>
        <section class="dashboard-column dashboard-agenda" aria-labelledby="dash-agenda-title">
          <header><span class="column-kicker">04 / AGENDA</span><h2 id="dash-agenda-title">Agenda e alertas</h2><p>Janelas próximas e referência de fila</p></header>
          <article class="dash-card dash-alert-card"><span class="dash-kpi-label">FILA MAIS ANTIGA</span><strong class="dash-alert-number" id="dash-oldest-days">—</strong><span class="dash-alert-detail" id="dash-oldest-label">Idade da etapa atual</span></article>
          <article class="dash-card dash-alert-card dash-alert-warm"><span class="dash-kpi-label">PRAZOS PRÓXIMOS</span><strong class="dash-alert-number" id="dash-deadlines-soon">—</strong><span class="dash-alert-detail">Cartas com prazo nos próximos 7 dias</span></article>
          <article class="dash-card dash-alert-card dash-alert-mint"><span class="dash-kpi-label">DEVOLUÇÕES AGUARDANDO</span><strong class="dash-alert-number" id="dash-waiting-returns">—</strong><span class="dash-alert-detail">Cartas Recebidas na etapa de devolução</span></article>
        </section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/vendor-chart.umd.js"></script>
    <script src="/assets/js/dashboard-interno.js?v=<?php echo (int)@filemtime($dashboardJs); ?>"></script>
</body></html>
