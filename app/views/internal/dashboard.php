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
      <div class="dashboard-meta"><span>Indicadores consultados ao abrir esta página</span><span id="dashboard-atualizado">Carregando…</span></div>
      <div id="dashboard-erro" class="dashboard-error" hidden></div>
      <div class="dashboard-interno-columns">
        <section class="dashboard-column dashboard-system" aria-labelledby="dash-system-title">
          <header><span class="column-kicker">01 / SISTEMA</span><h2 id="dash-system-title">Fluxo geral</h2><p>Pendências atuais em cinco fluxos operacionais do Cartório</p></header>
          <article class="dash-card dash-hero"><span class="dash-label">Tarefas em andamento</span><strong class="dash-number" id="dash-total-geral">—</strong><small>Subtarefas na etapa atual</small></article>
          <article class="dash-card"><h3>Fila por módulo</h3><div id="dash-filas-geral" class="dash-queue"></div><canvas id="dash-chart-geral" aria-label="Gráfico de pendências por módulo"></canvas></article>
          <article class="dash-card"><h3>Cadastros nos últimos seis meses</h3><canvas id="dash-chart-cadastros" aria-label="Cadastros mensais dos módulos conectados"></canvas></article>
          <article class="dash-card"><h3>Etapas concluídas · últimos 7 dias</h3><canvas id="dash-chart-conclusoes" aria-label="Conclusões de etapas nos últimos sete dias"></canvas></article>
          <a class="dash-link" href="/frame.php?page=cartasexpedidas">Abrir fluxos do Cartório <span aria-hidden="true">↗</span></a>
        </section>
        <section class="dashboard-column dashboard-sector" aria-labelledby="dash-sector-title">
          <header><span class="column-kicker">02 / SETOR</span><h2 id="dash-sector-title">Minha área</h2><p>Filas operacionais da área autorizada selecionada</p></header>
          <label class="dash-filter" for="dash-sector-filter">Filtrar setor</label><select id="dash-sector-filter" class="dashboard-sector-filter"><option value="">Todas as áreas autorizadas</option></select>
          <article class="dash-card dash-hero"><span class="dash-label">Tarefas em andamento</span><strong class="dash-number" id="dash-total-setor">—</strong><small id="dash-areas">Áreas autorizadas</small></article>
          <article class="dash-card"><h3>Etapas executáveis</h3><div id="dash-filas-setor" class="dash-queue"></div><canvas id="dash-chart-sector" aria-label="Comparação das filas do setor"></canvas></article>
          <article class="dash-card"><h3>Cadastros dos fluxos conectados · seis meses</h3><canvas id="dash-chart-sector-month" aria-label="Evolução mensal dos cadastros do setor"></canvas></article>
        </section>
        <section class="dashboard-column dashboard-work" aria-labelledby="dash-work-title">
          <header><span class="column-kicker">03 / PRODUTIVIDADE</span><h2 id="dash-work-title">Meu trabalho</h2><p>Conclusões registradas a partir da implantação</p></header>
          <div class="dash-stat-grid"><article class="dash-card dash-stat"><span>Últimos 7 dias</span><strong class="dash-number" id="dash-done-7">—</strong></article><article class="dash-card dash-stat"><span>Últimos 30 dias</span><strong class="dash-number" id="dash-done-30">—</strong></article></div>
          <article class="dash-card"><h3>Conclusões na semana</h3><canvas id="dash-chart-work" aria-label="Conclusões diárias na última semana"></canvas></article>
          <article class="dash-card"><h3>Por módulo · últimos 30 dias</h3><canvas id="dash-chart-modules" aria-label="Distribuição das conclusões por módulo"></canvas><p class="dash-note">Conta etapas concluídas pelo seu usuário; não atribui tarefas por cadastro ou edição.</p></article>
        </section>
        <section class="dashboard-column dashboard-agenda" aria-labelledby="dash-agenda-title">
          <header><span class="column-kicker">04 / AGENDA</span><h2 id="dash-agenda-title">Agenda e alertas</h2><p>Janelas próximas e referência de fila</p></header>
          <article class="dash-card dash-hero"><span class="dash-label">Audiências · próximos 7 dias</span><strong class="dash-number" id="dash-audiencias">—</strong><a class="dash-link" href="/frame.php?page=salapauta">Ver pauta <span aria-hidden="true">↗</span></a></article>
          <article class="dash-card"><span class="dash-label">Dias não úteis · próximos 30 dias</span><strong class="dash-number" id="dash-nao-uteis">—</strong><small>Fins de semana, feriados e recessos ativos</small></article>
          <article class="dash-card"><span class="dash-label">Item executável mais antigo</span><strong class="dash-date" id="dash-oldest">—</strong><small>Idade da fila; não representa vencimento</small></article>
          <div id="dash-missing" class="dash-note"></div>
        </section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/vendor-chart.umd.js"></script>
    <script src="/assets/js/dashboard-interno.js?v=<?php echo (int)@filemtime($dashboardJs); ?>"></script>
</body></html>
