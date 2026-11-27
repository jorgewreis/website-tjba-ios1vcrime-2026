<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/CartasExpedidasPrioridade.php';
require_once __DIR__ . '/../../../helpers/DeleteAuthorization.php';

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$canDelete = DeleteAuthorization::usuarioPodeExcluir((string) ($_SESSION['id_usuario'] ?? ''), DeleteAuthorization::perfisAdminOuChefia());
$prioridadesPiloto = CartasExpedidasPrioridade::paraCartas($connection, array_column($rows, 'IdCarta'));
$groups = [
    'envio' => ['Aguardando envio', $statusCounts['aguardando_envio'], []],
    'devolucao' => ['Aguardando cumprimento', $statusCounts['aguardando_cumprimento'], []],
    'encerrada' => ['Finalizadas', $statusCounts['concluída'], []],
    'cancelada' => ['Canceladas', $statusCounts['cancelada'], []],
    'conferir' => ['Etapa a conferir', null, []],
];
foreach ($rows as $item) {
    $key = match ((string)($item['Status'] ?? '')) {
        'cancelada' => 'cancelada',
        'concluída','concluida','finalizada' => 'encerrada',
        default => (string)($item['EtapaEfetiva'] ?? ''),
    };
    if (!isset($groups[$key]) || !empty($item['FluxoInconsistente'])) $key = 'conferir';
    $groups[$key][2][] = $item;
}
$hrefPage = static fn(int $number): string => '?' . http_build_query(array_merge($baseQueryParams, ['page_number'=>$number]));
$css = '/assets/css/modules/cartorio/cartas-expedidas-piloto.css';
$js = '/assets/js/modules/cartorio/cartas-expedidas/piloto.js';
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cartas Expedidas (nova) | Cartório</title>
<link rel="stylesheet" href="<?= $css ?>?v=<?= (int)filemtime(dirname(__DIR__,4).'/public'.$css) ?>">
</head>
<body class="ce-piloto">
<main class="ce-workspace">
 <section class="ce-queue" aria-label="Fila de cartas expedidas">
  <header class="ce-header">
   <div><p class="ce-eyebrow">Cartório / Expedição <span class="ce-tag">Versão piloto</span></p><h1>Cartas Expedidas</h1><p>1ª Vara Crime · Cartas expedidas do cartório</p></div>
   <div class="ce-tools">
    <a class="ce-button" href="?page=cartasexpedidasrules&amp;piloto=1" data-ce-frame data-title="Regra de envio de cartas">Regra de envio</a>
    <a class="ce-button primary" href="?page=cartasexpedidascreate&amp;piloto=1" data-ce-frame data-title="Nova carta expedida">+ Novo cadastro</a>
   </div>
  </header>
  <form method="get" class="ce-toolbar">
    <input type="hidden" name="page" value="cartasexpedidasnova">
    <input type="search" name="processo" aria-label="Pesquisar por processo" placeholder="Pesquisar por processo" value="<?= $e($processoFilter) ?>">
    <select name="status" aria-label="Status da carta">
     <option value="">Todos os status</option>
     <?php foreach (['aguardando_envio'=>'Aguardando envio','aguardando_cumprimento'=>'Aguardando cumprimento','concluída'=>'Finalizadas','cancelada'=>'Canceladas'] as $key=>$name): ?>
     <option value="<?= $e($key) ?>" <?= $statusFilter===$key?'selected':'' ?>><?= $e($name) ?></option>
     <?php endforeach; ?>
    </select>
    <select name="finalidade" aria-label="Finalidade"><option value="">Todas as finalidades</option>
     <?php foreach ($finalidadeCounts as $key=>$count): ?><option value="<?= $e($key) ?>" <?= $finalidadeFilter===$key?'selected':'' ?>><?= $e($key) ?></option><?php endforeach; ?>
    </select>
    <button class="ce-button" type="submit">Filtrar</button>
    <details class="ce-dropdown"><summary>Exportar</summary><div><a href="<?= $e($exportPdfUrl) ?>">PDF</a><a href="<?= $e($exportCsvUrl) ?>">CSV</a><button type="button" data-ce-print>Imprimir</button></div></details>
  </form>
  <div class="ce-columns ce-grid" aria-hidden="true"><span>Processo</span><span>Destino</span><span>Finalidade</span><span>Prazo</span><span>Responsável</span><span>Prioridade</span><span>Ações</span></div>
  <?php if ($rows === []): ?><p class="ce-empty">Nenhuma carta encontrada com esses filtros.</p><?php endif; ?>
  <?php foreach ($groups as $groupKey=>[$label,$count,$items]): ?>
  <?php if ($items === []) continue; ?>
  <details class="ce-group <?= $e($groupKey) ?>" <?= in_array($groupKey,['encerrada','cancelada'],true)&&$statusFilter===''?'':'open' ?>>
    <summary><span class="ce-dot"></span><?= $e($label) ?> <span class="ce-tag" title="<?= $count===null?'Nesta página':'Total filtrado' ?>"><?= $e($count ?? count($items)) ?></span></summary>
    <?php foreach ($items as $row) require __DIR__ . '/cartas-expedidas-v2-row.php'; ?>
  </details>
  <?php endforeach; ?>
  <footer class="ce-pagination"><span><?= $e($startItem) ?> a <?= $e($endItem) ?> de <?= $e($totalDocuments) ?> cartas</span><nav aria-label="Paginação">
    <?php if ($currentPage>1): ?><a class="ce-button" href="<?= $e($hrefPage($currentPage-1)) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= $e($currentPage) ?> de <?= $e($totalPages) ?></span>
    <?php if ($currentPage<$totalPages): ?><a class="ce-button" href="<?= $e($hrefPage($currentPage+1)) ?>">Próxima</a><?php endif; ?>
  </nav></footer>
 </section>
 <aside id="ce-panel" class="ce-panel" aria-label="Painel da carta" hidden>
   <header><h2 id="ce-panel-title">Detalhes da carta</h2><button type="button" class="ce-close" data-ce-close aria-label="Fechar painel">×</button></header>
   <div id="ce-panel-content"></div>
 </aside>
 <div class="ce-toast" id="ce-feedback" aria-live="polite" role="status" hidden></div>
</main>
<script src="<?= $js ?>?v=<?= (int)filemtime(dirname(__DIR__,4).'/public'.$js) ?>" defer></script>
</body></html>
