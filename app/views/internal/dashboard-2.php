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
    <main id="dashboard2" class="dashboard2-shell" aria-label="Dashboard 2 da 1ª Vara Crime de Ilhéus">
      <p id="dashboard2-error" class="dashboard2-error" role="alert" hidden></p>
      <div class="dashboard2-welcome">
        <div><h1>Bom trabalho! <span aria-hidden="true">👋</span></h1><p>Seu espaço de trabalho na 1ª Vara Crime de Ilhéus.</p></div>
        <span class="dashboard2-brand">1ª Vara Crime de Ilhéus</span>
      </div>

      <div class="dashboard2-row dashboard2-row-primary">
        <section class="dashboard2-card dashboard2-overview" aria-labelledby="dashboard2-overview-title">
          <header><span class="dashboard2-icon" aria-hidden="true">▥</span><div><h2 id="dashboard2-overview-title">Visão geral da Vara</h2><p>Cadastros recentes e indicadores do resumo</p></div></header>
          <div class="dashboard2-stats">
            <article class="dashboard2-stat"><span>Cadastros de cartas expedidas · 30 dias</span><strong id="d2-letters-sent">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Cadastros de cartas recebidas · 30 dias</span><strong id="d2-letters-received">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Ofícios cadastrados · ano</span><strong id="d2-offices">Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Editais expedidos</span><strong>Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Medidas protetivas cadastradas</span><strong>Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Guias expedidas</span><strong>Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Audiências cadastradas</span><strong>Indisponível</strong></article>
            <article class="dashboard2-stat"><span>Pendências operacionais</span><strong>Indisponível</strong></article>
          </div>
          <p class="dashboard2-footnote">Cadastros não representam expedições. O corte segue o resumo legado e ainda não tem fuso Bahia validado.</p>
          <div class="dashboard2-chart-heading"><strong>Volume de cadastros · últimos 30 dias</strong><span>Fonte legada</span></div>
          <div class="dashboard2-chart-wrap">
            <canvas id="d2-volume-chart" aria-hidden="true" hidden></canvas>
            <p id="d2-volume-unavailable" class="dashboard2-chart-empty" role="status">Série diária indisponível até que rótulos e valores da fonte estejam completos.</p>
          </div>
          <table id="d2-volume-data" class="dashboard2-visually-hidden">
            <caption id="d2-volume-data-caption">Dados diários do gráfico de cadastros de cartas, últimos 30 dias</caption>
            <thead><tr><th scope="col">Dia</th><th scope="col">Expedidas cadastradas</th><th scope="col">Recebidas cadastradas</th></tr></thead>
            <tbody id="d2-volume-data-body"></tbody>
          </table>
          <p class="dashboard2-executable-total">Filas executáveis no resumo: <strong id="d2-queues">Indisponível</strong></p>
        </section>

        <section class="dashboard2-card dashboard2-work" aria-labelledby="dashboard2-work-title">
          <header><span class="dashboard2-icon" aria-hidden="true">♙</span><div><h2 id="dashboard2-work-title">Meu trabalho</h2><p>Eventos atribuídos ao autor, sem usar o responsável atual</p></div></header>
          <ul class="dashboard2-list" aria-label="Indicadores pessoais sem fonte auditável suficiente">
            <li><span>Cartas expedidas</span><strong>Indisponível</strong></li>
            <li><span>Ofícios expedidos</span><strong>Indisponível</strong></li>
            <li><span>Editais expedidos</span><strong>Indisponível</strong></li>
            <li><span>Medidas protetivas cadastradas</span><strong>Indisponível</strong></li>
            <li><span>Guias expedidas</span><strong>Indisponível</strong></li>
            <li><span>Pessoas cadastradas</span><strong>Indisponível</strong></li>
            <li><span>Audiências cadastradas</span><strong>Indisponível</strong></li>
            <li><span>Consultas realizadas</span><strong>Indisponível</strong></li>
          </ul>
          <p class="dashboard2-footnote">A trilha existente cobre conclusões parciais de subtarefas e não tem histórico retroativo.</p>
        </section>

        <section class="dashboard2-card dashboard2-flow" aria-labelledby="dashboard2-flow-title">
          <header><span class="dashboard2-icon" aria-hidden="true">▱</span><div><h2 id="dashboard2-flow-title">Fluxo operacional</h2><p>Etapas; sem contagens até validar o histórico de transições</p></div></header>
          <div class="dashboard2-flow-stages" aria-label="Cadastrado, aguardando expedição, expedido, enviado e cumprido">
            <span>Cadastrado</span><b aria-hidden="true">→</b><span>Aguardando expedição</span><b aria-hidden="true">→</b><span>Expedido</span><b aria-hidden="true">→</b><span>Enviado</span><b aria-hidden="true">→</b><span>Cumprido</span>
          </div>
          <p class="dashboard2-unavailable">Conversão, tempo por etapa e coorte indisponíveis: não há histórico completo de transições.</p>
        </section>
      </div>

      <div class="dashboard2-row dashboard2-row-secondary">
        <section class="dashboard2-card" aria-labelledby="d2-lotacao-title"><header><span class="dashboard2-icon" aria-hidden="true">▤</span><div><h2 id="d2-lotacao-title">Lotação</h2><p>Filas retornadas para as áreas autorizadas</p></div></header><div id="d2-lotacao" class="dashboard2-data-list" role="status" aria-live="polite"><span>Carregando…</span></div></section>
        <section class="dashboard2-card" aria-labelledby="d2-critical-title"><header><span class="dashboard2-icon" aria-hidden="true">◉</span><div><h2 id="d2-critical-title">Criticidade</h2><p>Classificação de processos e tarefas</p></div></header><div class="dashboard2-state-grid dashboard2-state-grid-three"><div><span>Alta</span><strong>Indisponível</strong></div><div><span>Média</span><strong>Indisponível</strong></div><div><span>Baixa</span><strong>Indisponível</strong></div></div><p class="dashboard2-unavailable">Níveis de processo e tarefa ainda não têm mapeamento comum.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-radar-title"><header><span class="dashboard2-icon" aria-hidden="true">◎</span><div><h2 id="d2-radar-title">Radar de prazos</h2><p>Prazos formalmente registrados</p></div></header><div class="dashboard2-state-list"><div><span>Vencidos</span><strong>Indisponível</strong></div><div><span>Hoje</span><strong>Indisponível</strong></div><div><span>Próximas 48 horas</span><strong>Indisponível</strong></div><div><span>De 3 a 7 dias</span><strong>Indisponível</strong></div></div><p class="dashboard2-unavailable">Falta cobertura unificada e regra validada por módulo.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-quality-title"><header><span class="dashboard2-icon" aria-hidden="true">♧</span><div><h2 id="d2-quality-title">Qualidade operacional</h2><p>Conformidade e eficiência</p></div></header><div class="dashboard2-state-list"><div><span>Atividades no prazo</span><strong>Indisponível</strong></div><div><span>Sem retrabalho</span><strong>Indisponível</strong></div><div><span>Registros completos</span><strong>Indisponível</strong></div></div><p class="dashboard2-unavailable">Histórico de prazos, reaberturas e checklist ainda incompleto.</p></section>
        <section class="dashboard2-card" aria-labelledby="d2-readiness-title"><header><span class="dashboard2-icon" aria-hidden="true">▣</span><div><h2 id="d2-readiness-title">Prontidão das audiências</h2><p>Expedientes, confirmações e acesso</p></div></header><div class="dashboard2-state-grid dashboard2-state-grid-five"><div><span>Expedientes emitidos</span><strong>Indisponível</strong></div><div><span>Intimações cumpridas</span><strong>Indisponível</strong></div><div><span>Intimações pendentes</span><strong>Indisponível</strong></div><div><span>Cadastro no AudIn</span><strong>Indisponível</strong></div><div><span>Link virtual</span><strong>Indisponível</strong></div></div><p class="dashboard2-unavailable">Confirmação e disponibilidade não foram comprovadas.</p></section>
      </div>

      <div class="dashboard2-row dashboard2-row-tertiary">
        <section class="dashboard2-card dashboard2-agenda" aria-labelledby="d2-agenda-title"><header><span class="dashboard2-icon" aria-hidden="true">▦</span><div><h2 id="d2-agenda-title">Agenda</h2><p>Próximos sete dias</p></div></header><p id="d2-agenda" class="dashboard2-metric" role="status">Indisponível</p><p class="dashboard2-unavailable">A fonte atual usa D0–D+7. Falta conciliar D0–D+6 em America/Bahia com o detalhe autorizado.</p></section>
        <section class="dashboard2-card dashboard2-next" aria-labelledby="d2-next-title"><header><span class="dashboard2-icon" aria-hidden="true">ϟ</span><div><h2 id="d2-next-title">Próxima ação</h2><p>Tarefas executáveis atribuídas</p></div></header><p class="dashboard2-unavailable">Indisponível: atribuição unificada e links de detalhe não foram comprovados.</p></section>
        <section class="dashboard2-card dashboard2-evolution" aria-labelledby="d2-evolution-title"><header><span class="dashboard2-icon" aria-hidden="true">▥</span><div><h2 id="d2-evolution-title">Evolução de produtividade</h2><p>Eventos com cobertura e privacidade adequadas</p></div></header><p class="dashboard2-unavailable">Indisponível: a trilha de conclusões é parcial e não suporta comparação entre servidores.</p></section>
        <section class="dashboard2-card dashboard2-alerts" aria-labelledby="d2-alerts-title"><header><span class="dashboard2-icon" aria-hidden="true">⚠</span><div><h2 id="d2-alerts-title">Alertas críticos</h2><p>Prazos e antiguidade de filas em grupos distintos</p></div></header><p class="dashboard2-scope-note">Cobertura atual limitada aos alertas do resumo de cartas.</p><div id="d2-alerts-list" class="dashboard2-data-list" role="status" aria-live="polite"><span>Carregando…</span></div></section>
      </div>
    </main>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/vendor-chart.umd.js"></script>
    <script src="/assets/js/dashboard-2.js?v=<?php echo (int) @filemtime($dashboard2Js); ?>"></script>
</body></html>
