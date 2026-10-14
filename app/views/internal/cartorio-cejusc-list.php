<?php
declare(strict_types=1);
require_once __DIR__ . '/../../services/CejuscRegistrosService.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$connection = Conection::getInstance()->getConnection();
$itemsPerPage = 15;
$currentPage = max(1, (int) ($_GET['page_number'] ?? 1));
$queryFilter = trim((string) ($_GET['q'] ?? ''));
$monthFilter = trim((string) ($_GET['mes_envio'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthFilter)) $monthFilter = '';
$filters = ['q' => $queryFilter, 'mes_envio' => $monthFilter];
try {
    $result = (new CejuscRegistrosService($connection))->list($filters, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
} catch (Throwable $e) {
    $result = ['rows' => [], 'total' => 0];
}
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, is_array($result['rows'] ?? null) ? $result['rows'] : []);
$processoIdsCejusc = [];
foreach ($rows as $rowCejusc) {
    $processoIdCejusc = trim((string) ($rowCejusc['bloco_processo_id_resolvido'] ?? ''));
    if ($processoIdCejusc !== '') $processoIdsCejusc[] = $processoIdCejusc;
}
$cejuscVitimasPorProcesso = (new ProcessosPartesRepository($connection))->listNomesVitimasByProcessoIds($processoIdsCejusc);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$total = (int) ($result['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $itemsPerPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$startItem = $total > 0 ? (($currentPage - 1) * $itemsPerPage) + 1 : 0;
$endItem = $total > 0 ? min((($currentPage - 1) * $itemsPerPage) + count($rows), $total) : 0;
$paginationButtons = $totalPages <= 5 ? range(1, $totalPages) : range(max(1, min($currentPage - 2, $totalPages - 4)), min($totalPages, max(5, $currentPage + 2)));
$exportMonths = [];
$monthCursor = new DateTimeImmutable('first day of this month');
$monthNames = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
for ($i = 0; $i < 3; $i++) {
    $monthDate = $monthCursor->modify('-' . $i . ' months');
    $monthValue = $monthDate->format('Y-m');
    $exportMonths[] = ['value' => $monthValue, 'label' => ($monthNames[(int) $monthDate->format('n')] ?? $monthDate->format('m')) . '/' . $monthDate->format('Y'), 'href' => '/api/internal-list-export/pdf.php?' . http_build_query(['page' => 'cejusc', 'mes_envio' => $monthValue])];
}
function cejusc_url(array $extra = []): string { return '/frame.php?' . http_build_query(array_merge(['page' => 'cejusc'], $extra)); }
function cejusc_date(string $value): string { $t = strtotime($value); return $t === false ? $value : date('d/m/Y', $t); }
function cejusc_resumo_nomes(array $nomes, string $singularOutro = 'outro', string $pluralOutros = 'outros'): string
{
    $nomes = array_values(array_filter(array_map(static fn($nome): string => trim((string) $nome), $nomes), static fn(string $nome): bool => $nome !== ''));
    $total = count($nomes);
    if ($total === 0) return '';
    if ($total === 1) return $nomes[0];
    if ($total === 2) return $nomes[0] . ' e ' . $singularOutro;
    return $nomes[0] . ' e ' . $pluralOutros . ' (' . ($total - 1) . ')';
}
$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$cartasExpedidasRowsCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cartas-expedidas-rows.css') ?: time();
$cejuscCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cejusc-v2.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cejusc - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cartas-expedidas-rows.css?v=<?php echo (int) $cartasExpedidasRowsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cejusc-v2.css?v=<?php echo (int) $cejuscCssV; ?>">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime($assetsRoot . '/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</head>
<body class="page-internal cejusc-v2-page cejusc-list-page">
    <div class="spacing-top-page"></div>
    <div class="header-page"><div class="location"><span class="all">Interno - Cartório - Cejusc -</span><span class="specific">Lista</span></div></div>
    <main class="container-full card-page">
        <div class="card-title">
            <h1 class="title-page">Cejusc</h1>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap cejusc-header-actions">
                <input type="search" class="height-md list-search-input" id="cejusc-search" value="<?php echo htmlspecialchars($queryFilter, ENT_QUOTES); ?>" placeholder="Pesquisar processo ou parte" aria-label="Pesquisar processo ou parte">
                <select id="cejusc-export-pdf" class="list-export-select width-300 height-md white" aria-label="Gerar relatório CEJUSC em PDF">
                    <option value="" selected disabled>Gerar relatório PDF</option>
                    <?php foreach ($exportMonths as $exportMonth): ?><option value="pdf-<?php echo htmlspecialchars($exportMonth['value'], ENT_QUOTES); ?>" data-export-href="<?php echo htmlspecialchars($exportMonth['href'], ENT_QUOTES); ?>">PDF - <?php echo htmlspecialchars($exportMonth['label'], ENT_QUOTES); ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-primary width-300" type="button" onclick="location.href='/frame.php?page=cejusccreate'">Novo cadastro</button>
            </div>
        </div>
        <div class="cejusc-filters">
            <span class="cejusc-filters-title">Filtros</span>
            <form class="cejusc-filter-controls" method="get" action="/frame.php">
                <input type="hidden" name="page" value="cejusc"><input type="hidden" name="q" value="<?php echo htmlspecialchars($queryFilter, ENT_QUOTES); ?>">
                <label for="cejusc-month">Mês da data de envio</label><input type="month" id="cejusc-month" name="mes_envio" value="<?php echo htmlspecialchars($monthFilter, ENT_QUOTES); ?>">
                <button class="btn btn-primary-outline" type="submit">Filtrar</button>
                <?php if ($monthFilter !== '' || $queryFilter !== ''): ?><a class="btn btn-primary-outline" href="/frame.php?page=cejusc">Limpar</a><?php endif; ?>
            </form>
        </div>
        <div class="cejusc-list-count" role="status"><?php echo $total === 0 ? 'Nenhum registro encontrado' : htmlspecialchars((string) $total . ($total === 1 ? ' registro encontrado' : ' registros encontrados'), ENT_QUOTES); ?></div>
        <div class="cejusc-list-results" role="list" aria-label="Resultados do Cejusc">
            <?php foreach ($rows as $row): ?>
                <?php
                $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'cejusc', $registroListViewerContext, $connection);
                $processoIdBlocoCejusc = trim((string) ($row['bloco_processo_id_resolvido'] ?? ''));
                $historicoLinhasCejusc = is_array($registroMetadata['historico_linhas'] ?? null) ? $registroMetadata['historico_linhas'] : [];
                $vitimaResumoCejusc = cejusc_resumo_nomes($cejuscVitimasPorProcesso[$processoIdBlocoCejusc] ?? [], 'outra', 'outras');
                $dataEnvioRaw = trim((string) ($row['data_envio_cejusc'] ?? ''));
                $dataEnvioTexto = $dataEnvioRaw !== '' ? cejusc_date($dataEnvioRaw) : 'Data não informada';
                $cejuscStatusLabel = $dataEnvioRaw !== '' ? 'Enviado ao Cejusc' : 'Aguardando envio';
                $cejuscStatusClass = $dataEnvioRaw !== '' ? 'status-pending' : 'status-current';
                $historicoRecente = $historicoLinhasCejusc !== [] ? $historicoLinhasCejusc[count($historicoLinhasCejusc) - 1] : null;
                $historicoTexto = is_array($historicoRecente) ? trim((string) ($historicoRecente['data_hora'] ?? '')) : '';
                $historicoAutor = is_array($historicoRecente) ? trim((string) ($historicoRecente['autor'] ?? '')) : '';
                if ($historicoAutor !== '') $historicoTexto .= ($historicoTexto !== '' ? ' | ' : '') . $historicoAutor;
                ?>
                <article class="register-line carta-expedida-v2-row cejusc-record" role="listitem" data-registro-id="<?php echo htmlspecialchars((string) $row['id'], ENT_QUOTES); ?>"<?php echo $processoIdBlocoCejusc !== '' ? ' data-processo-id="' . htmlspecialchars($processoIdBlocoCejusc, ENT_QUOTES) . '"' : ''; ?> id="<?php echo htmlspecialchars((string) $row['id'], ENT_QUOTES); ?>">
                    <div class="left carta-expedida-left">
                        <?php include __DIR__ . '/partials/processo-v2.php'; ?>
                    </div>
                    <div class="right carta-expedida-right">
                        <div class="register-block cejusc-party-column">
                            <div class="register-block-content"><strong class="register-block-content-item-value"><?php echo htmlspecialchars((string) $row['nome_parte'], ENT_QUOTES); ?></strong></div>
                            <div class="register-block-content cejusc-date-line"><?php echo htmlspecialchars($dataEnvioTexto . ' - Enviado ao Cejusc', ENT_QUOTES); ?></div>
                            <?php if ($vitimaResumoCejusc !== ''): ?><div class="register-block-content cejusc-victim">Vítima: <?php echo htmlspecialchars($vitimaResumoCejusc, ENT_QUOTES); ?></div><?php endif; ?>
                        </div>
                        <div class="carta-expedida-status-group">
                            <div class="register-block-status">
                                <div class="badge-status-primary <?php echo htmlspecialchars($cejuscStatusClass, ENT_QUOTES); ?>"><span class="badge-status-text"><?php echo htmlspecialchars($cejuscStatusLabel, ENT_QUOTES); ?></span></div>
                                <?php if (!empty($registroMetadata['show_edit']) && !empty($registroMetadata['edit_href'])): ?><button type="button" class="carta-row-action" data-action="editar-registro" data-edit-href="<?php echo htmlspecialchars($registroMetadata['edit_href'], ENT_QUOTES); ?>" title="Editar cadastro" aria-label="Editar cadastro"><img src="/assets/icons/Eject-Button--Streamline-Ultimate.png" alt=""></button><?php endif; ?>
                            </div>
                            <div class="container-historical container-sm">
                                <div class="container-line"><div class="line-content double-line">
                                    <span class="content-classe">Última movimentação</span>
                                    <span class="content-assuntos<?php echo $historicoTexto === '' ? ' over' : ''; ?>"><?php echo htmlspecialchars($historicoTexto !== '' ? $historicoTexto : 'Nenhuma movimentação registrada', ENT_QUOTES); ?></span>
                                </div></div>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><div class="cejusc-list-empty"><strong>Nenhum registro CEJUSC encontrado</strong><span>Ajuste os filtros ou a pesquisa para ver outros resultados.</span></div><?php endif; ?>
        </div>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-md cejusc-list-pagination">
            <span class="pagination-info">Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $total; ?> registros (<?php echo (int) $totalPages; ?> <?php echo $totalPages === 1 ? 'página' : 'páginas'; ?>, <?php echo (int) $itemsPerPage; ?> itens por página)</span>
            <?php if ($total > 0): ?><div class="pagination-controls">
                <?php $paginationParams = ['q' => $queryFilter, 'mes_envio' => $monthFilter, 'items_per_page' => $itemsPerPage]; ?>
                <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url($paginationParams + ['page_number' => max(1, $currentPage - 1)]), ENT_QUOTES); ?>" aria-label="Página anterior"><i class="fi fi-rr-angle-small-left"></i></a>
                <?php foreach ($paginationButtons as $pageButton): ?><?php if ($pageButton === $currentPage): ?><span class="pagination-number active" aria-current="page"><?php echo (int) $pageButton; ?></span><?php else: ?><a class="pagination-number" href="<?php echo htmlspecialchars(cejusc_url($paginationParams + ['page_number' => $pageButton]), ENT_QUOTES); ?>"><?php echo (int) $pageButton; ?></a><?php endif; ?><?php endforeach; ?>
                <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url($paginationParams + ['page_number' => min($totalPages, $currentPage + 1)]), ENT_QUOTES); ?>" aria-label="Próxima página"><i class="fi fi-rr-angle-small-right"></i></a>
            </div><?php endif; ?>
        </div>
    </main>
    <div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
    <script>
    (function () {
        if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') App.RegistroMetadataList.bind({deleteEndpoint:'/api/cartorio-cejusc/delete.php',deleteFallback:{type:'success',title:'Sucesso',message:'Registro CEJUSC removido.'}});
        if(window.App&&App.ListSearch&&typeof App.ListSearch.initializeProcessSearch==='function')App.ListSearch.initializeProcessSearch({inputId:'cejusc-search',searchParamKey:'q',initialProcessFilter:<?php echo json_encode($queryFilter, JSON_UNESCAPED_UNICODE); ?>,minSearchDigits:1,debounceDelay:750,filters:[{id:'cejusc-month',param:'mes_envio'}]});
        var exportSelect=document.getElementById('cejusc-export-pdf');
        if(exportSelect)exportSelect.addEventListener('change',function(){var option=exportSelect.options[exportSelect.selectedIndex],href=option&&option.getAttribute('data-export-href');if(href)window.location.href=href;});
    })();
    </script>
</body>
</html>
