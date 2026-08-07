<?php
declare(strict_types=1);
require_once __DIR__ . '/../../services/CejuscRegistrosService.php';
$connection = Conection::getInstance()->getConnection();
$q = trim((string) ($_GET['q'] ?? ''));
$mesEnvio = trim((string) ($_GET['mes_envio'] ?? ''));
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 10);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) $itemsPerPage = 10;
$currentPage = max(1, (int) ($_GET['page_number'] ?? 1));
$filters = ['q' => $q, 'mes_envio' => $mesEnvio];
try { $result = (new CejuscRegistrosService($connection))->list($filters, $itemsPerPage, ($currentPage - 1) * $itemsPerPage); } catch (Throwable $e) { $result = ['rows' => [], 'total' => 0]; }
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
$exportQuery = ['page' => 'cejusc', 'q' => $q, 'mes_envio' => $mesEnvio];
$listExportPage = 'cejusc';
$listExportQuery = $exportQuery;
function cejusc_url(array $extra = []): string { return '/frame.php?' . http_build_query(array_merge(['page' => 'cejusc'], $extra)); }
function cejusc_date(string $value): string { $t = strtotime($value); return $t === false ? $value : date('d/m/Y', $t); }
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CEJUSC - Primeira Vara Crime de Ilhéus</title><?php include __DIR__ . '/_internal_head_styles.php'; ?><?php include __DIR__ . '/_toast_script.php'; ?></head>
<body class="page-internal"><div class="spacing-top-page"></div>
<div class="header-page"><div class="location"><span class="all">Interno - Cartório -</span><span class="specific">Cejusc</span></div><h1 class="title-page">Cejusc</h1><p class="description-page">Controle dos encaminhamentos enviados ao Centro Judiciário de Solução de Conflitos.</p></div>
<div class="container-full card-page">
    <div class="card-title"><div><h2>Registros CEJUSC</h2><p class="description-page">Processo, parte e data de envio ao CEJUSC.</p></div><button class="btn btn-primary width-300" type="button" onclick="location.href='/frame.php?page=cejusccreate'">Novo cadastro</button></div>
    <form method="get" class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom gap-y-md" data-auto-submit-filters>
        <input type="hidden" name="page" value="cejusc"><div class="col filter"><label for="cejusc-q">Pesquisar processo ou parte</label><input class="width-400 height-md" type="search" id="cejusc-q" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>" placeholder="Pesquisar"></div>
        <div class="col filter"><label for="cejusc-month">Mês da data de envio</label><input class="width-250 height-md" type="month" id="cejusc-month" name="mes_envio" value="<?php echo htmlspecialchars($mesEnvio, ENT_QUOTES); ?>"></div>
        <div class="col filter"><label for="cejusc-per-page">Itens por página</label><select class="width-150 height-md" id="cejusc-per-page" name="items_per_page"><option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option><option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option><option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option><option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option></select></div>
        <div class="col filter"><label>Exportação mensal</label><?php include __DIR__ . '/_list_export_buttons.php'; ?></div>
    </form>
    <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom" style="background-color:var(--color-primary-bg-light);"><span class="text"><?php echo $total === 0 ? 'Nenhum registro encontrado.' : $total . ($total === 1 ? ' registro encontrado.' : ' registros encontrados.'); ?></span></div>
    <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Processo</th><th>Nome da parte</th><th>Data de envio ao CEJUSC</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?php echo htmlspecialchars((string) $row['numero_processo'], ENT_QUOTES); ?></td><td><?php echo htmlspecialchars((string) $row['nome_parte'], ENT_QUOTES); ?></td><td><?php echo htmlspecialchars(cejusc_date((string) $row['data_envio_cejusc']), ENT_QUOTES); ?></td><td><a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(cejusc_url(['page' => 'cejuscedit', 'id' => $row['id']]), ENT_QUOTES); ?>">Editar</a></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4">Nenhum encaminhamento encontrado para os filtros informados.</td></tr><?php endif; ?></tbody></table></div>
    <?php if ($totalPages > 1): ?><div class="display-flex row row-full justify-center padding-y-md gap-x-md"><?php for ($i = 1; $i <= $totalPages; $i++): ?><a class="btn <?php echo $i === $currentPage ? 'btn-primary' : 'btn-secondary'; ?>" href="<?php echo htmlspecialchars(cejusc_url(['q' => $q, 'mes_envio' => $mesEnvio, 'items_per_page' => $itemsPerPage, 'page_number' => $i]), ENT_QUOTES); ?>"><?php echo $i; ?></a><?php endfor; ?></div><?php endif; ?>
</div><script>
document.addEventListener('change', function (event) {
    var select = event.target && event.target.closest ? event.target.closest('select.list-export-select') : null;
    if (!select || document.getElementById('cejusc-month').value) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    select.selectedIndex = 0;
    if (window.App && App.Page) App.Page.notify('info', 'Exportação mensal', 'Selecione o mês da data de envio antes de exportar.');
}, true);
</script></body></html>
