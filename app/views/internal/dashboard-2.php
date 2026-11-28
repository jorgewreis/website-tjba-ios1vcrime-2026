<?php
declare(strict_types=1);
Security::requireAuthentication();
$dashboard2Js = dirname(__DIR__, 3) . '/public/assets/js/dashboard-2.js';
$dashboard2Css = dirname(__DIR__, 3) . '/public/assets/css/dashboard-2.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard 2 - Primeira Vara Crime de Ilhéus</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/dashboard-2.css?v=<?php echo (int) @filemtime($dashboard2Css); ?>">
</head>
<body class="page-internal dashboard2-page">
    <div class="spacing-top-page"></div>
    <header class="header-page"><div class="location"><span class="all">Interno -</span> <span class="specific">Dashboard 2</span></div><h1 class="title-page">Visão operacional</h1></header>
    <main id="dashboard2" class="dashboard2-shell" aria-label="Dashboard 2 da 1ª Vara Crime de Ilhéus">
      <p id="dashboard2-error" class="dashboard2-error" role="alert" hidden></p>
      <div class="dashboard2-welcome"><div><p>Seu espaço de trabalho na 1ª Vara Crime de Ilhéus.</p><h2>Bom trabalho! <span aria-hidden="true">👋</span></h2></div><span class="dashboard2-brand">1ª Vara Crime de Ilhéus</span></div>

      <div class="dashboard2-row dashboard2-row-primary">
        <section class="dashboard2-card dashboard2-overview" aria-labelledby="dashboard2-overview-title">
          <header><span class="dashboard2-icon" aria-hidden="true">▥</span><div><h2 id="dashboard2-overview-title">Visão geral</h2><p>Indicadores com fonte disponível</p></div></header>
          <div class="dashboard2-stats">
            <article class="dashboard2-stat"><span>Cartas cadastradas · 30 dias</span><strong id="d2-letters">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Ofícios cadastrados · ano</span><strong id="d2-offices">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Filas executáveis</span><strong id="d2-queues">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Pendências operacionais</span><strong>Indisponível</strong></article>
          </div>
          <p class="dashboard2-footnote">Cadastros e estoque não representam expedições ou produção.</p>
        </section>
        <section class="dashboard2-card dashboard2-work" aria-labelledby="dashboard2-work-title">
          <header><span class="dashboard2-icon" aria-hidden="true">♙</span><div><h2 id="dashboard2-work-title">Meu trabalho</h2><p>Eventos atribuíveis ao autor</p></div></header>
          <ul class="dashboard2-list">
            <li>Cartas expedidas <strong>Indisponível</strong></li><li>Ofícios expedidos <strong>Indisponível</strong></li>
            <li>Editais expedidos <strong>Indisponível</strong></li><li>Medidas protetivas cadastradas <strong>Indisponível</strong></li>
            <li>Guias expedidas <strong>Indisponível</strong></li><li>Pessoas cadastradas <strong>Indisponível</strong></li>
            <li>Audiências cadastradas <strong>Indisponível</strong></li><li>Consultas realizadas <strong>Indisponível</strong></li>
          </ul>
          <p class="dashboard2-footnote">A trilha existente cobre apenas conclusões parciais de subtarefas, sem histórico retroativo.</p>
        </section>
        <section class="dashboard2-card dashboard2-flow" aria-labelledby="dashboard2-flow-title">
          <header><span class="dashboard2-icon" aria-hidden="true">▱</span><div><h2 id="dashboard2-flow-title">Fluxo operacional</h2><p>Etapas com transições verificadas</p></div></header>
          <div class="dashboard2-flow-stages"><span>Cadastrado</span><b>→</b><span>Aguardando expedição</span><b>→</b><span>Expedido</span><b>→</b><span>Enviado</span><b>→</b><span>Cumprido</span></div>
          <p class="dashboard2-unavailable">Conversão e tempo por etapa indisponíveis: não há histórico completo de transições.</p>
        </section>
      </div>

      <div class="dashboard2-row dashboard2-row-secondary">
        <section class="dashboard2-card" aria-labelledby="d2-lotacao-title"><header><span class="dashboard2-icon">▤</span><div><h2 id="d2-lotacao-title">Lotação</h2><p>Filas autorizadas por área</p></div></header><div id="d2-lotacao" class="dashboard2-data-list"><span>Carregando…</span></div></section>
        <section class="dashboard2-card" aria-labelledby="d2-critical-title"><header><span class="dashboard2-icon">◉</span><div><h2 id="d2-critical-title">Criticidade</h2><p>Classificação consistente</p></div></header><p class="dashboard2-unavailable">Indisponível: níveis do processo e da tarefa ainda não têm mapeamento comum validado.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-radar-title"><header><span class="dashboard2-icon">◎</span><div><h2 id="d2-radar-title">Radar de prazos</h2><p>Prazos formalmente registrados</p></div></header><p class="dashboard2-unavailable">Indisponível: falta cobertura unificada de prazos e regras de cálculo por módulo.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-quality-title"><header><span class="dashboard2-icon">♧</span><div><h2 id="d2-quality-title">Qualidade operacional</h2><p>Indicadores com histórico íntegro</p></div></header><p class="dashboard2-unavailable">Indisponível: reabertura, retrabalho e checklist não têm histórico completo.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-readiness-title"><header><span class="dashboard2-icon">▣</span><div><h2 id="d2-readiness-title">Prontidão das audiências</h2><p>Expedientes, confirmações e acesso</p></div></header><p class="dashboard2-unavailable">Indisponível: confirmação no AudIn e disponibilidade de links não foram comprovadas.</p></section>
      </div>

      <div class="dashboard2-row dashboard2-row-tertiary">
        <section class="dashboard2-card" aria-labelledby="d2-agenda-title"><header><span class="dashboard2-icon">▦</span><div><h2 id="d2-agenda-title">Agenda</h2><p>Próximos sete dias</p></div></header><p id="d2-agenda" class="dashboard2-metric">Indisponível</p><p class="dashboard2-unavailable">Pauta ativa disponível no resumo; compromissos e links não têm cobertura unificada.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-next-title"><header><span class="dashboard2-icon">ϟ</span><div><h2 id="d2-next-title">Próxima ação</h2><p>Tarefas executáveis atribuídas</p></div></header><p class="dashboard2-unavailable">Indisponível: atribuição unificada por tarefa não foi comprovada.</p></section>
        <section class="dashboard2-card dashboard2-evolution" aria-labelledby="d2-evolution-title"><header><span class="dashboard2-icon">▥</span><div><h2 id="d2-evolution-title">Evolução de produtividade</h2><p>Eventos com cobertura e privacidade adequadas</p></div></header><p class="dashboard2-unavailable">Indisponível: a trilha de conclusões é parcial e não suporta comparação entre servidores.</p></section>
        <section class="dashboard2-card dashboard2-alerts" aria-labelledby="d2-alerts-title"><header><span class="dashboard2-icon">⚠</span><div><h2 id="d2-alerts-title">Alertas críticos</h2><p>Prazos e antiguidade de filas separados</p></div></header><div id="d2-alerts-list" class="dashboard2-data-list"><span>Carregando…</span></div></section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/dashboard-2.js?v=<?php echo (int) @filemtime($dashboard2Js); ?>"></script>
</body></html>
