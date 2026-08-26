<?php
declare(strict_types=1);
require_once __DIR__ . '/../../services/CejuscRegistrosService.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';

$connection = Conection::getInstance()->getConnection();
$q = trim((string) ($_GET['q'] ?? ''));
$mesEnvio = trim((string) ($_GET['mes_envio'] ?? ''));
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 10);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) $itemsPerPage = 10;
$currentPage = max(1, (int) ($_GET['page_number'] ?? 1));
$filters = ['q' => $q, 'mes_envio' => $mesEnvio];
try {
    $result = (new CejuscRegistrosService($connection))->list($filters, $itemsPerPage, ($currentPage - 1) * $itemsPerPage);
} catch (Throwable $e) {
    $result = ['rows' => [], 'total' => 0];
}
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, is_array($result['rows'] ?? null) ? $result['rows'] : []);
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();
$perfisCejuscUsuario = ProfileNormalizer::normalize(UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) ($_SESSION['id_usuario'] ?? ''))));
$podeRemoverRegistroCejusc = in_array('CHEFIA', $perfisCejuscUsuario, true) || in_array('SERVIDOR', $perfisCejuscUsuario, true);
foreach ($perfisCejuscUsuario as $perfilCejuscUsuario) {
    if (strpos($perfilCejuscUsuario, 'SERVIDOR-') === 0) {
        $podeRemoverRegistroCejusc = true;
        break;
    }
}
$total = (int) ($result['total'] ?? 0);
$totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
if ($totalPages > 0 && $currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $itemsPerPage;
$startItem = $total > 0 ? $offset + 1 : 0;
$endItem = $total > 0 ? min($offset + count($rows), $total) : 0;
$exportQuery = ['page' => 'cejusc', 'q' => $q, 'mes_envio' => $mesEnvio, 'items_per_page' => $itemsPerPage];
$listExportPage = 'cejusc';
$listExportQuery = $exportQuery;
function cejusc_url(array $extra = []): string { return '/frame.php?' . http_build_query(array_merge(['page' => 'cejusc'], $extra)); }
function cejusc_date(string $value): string { $t = strtotime($value); return $t === false ? $value : date('d/m/Y', $t); }
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CEJUSC - Primeira Vara Crime de Ilhéus</title>
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
    <body class="page-internal">
        <div class="spacing-top-page"></div>

        <div class="header-page">
            <div class="location">
                <span class="all">Interno - Cartório - Cejusc -</span>
                <span class="specific">Lista</span>
            </div>
        </div>

        <main class="container-full card-page">
            <div class="card-title">
                <div class="display-flex col col-fit justify-start">
                    <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Cejusc</h1>
                    <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Controle dos encaminhamentos enviados ao Centro Judiciário de Solução de Conflitos.</p>
                </div>
                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">
                    <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search"><i class="fi fi-rr-search"></i></div>
                    <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters"><i class="fi fi-rr-filter"></i></div>
                    <div class="title-icon" title="Itens por página" role="button" tabindex="0" aria-label="Itens por página" data-list-section-trigger="items"><i class="fi fi-rr-list"></i></div>
                    <button class="btn btn-primary width-300" type="button" onclick="location.href='/frame.php?page=cejusccreate'">Novo cadastro</button>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm"><span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisar processo ou parte</span></div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center">
                    <form method="get" class="display-flex row row-fit gap-y-md align-center" data-auto-submit-filters>
                        <input type="hidden" name="page" value="cejusc">
                        <input type="hidden" name="mes_envio" value="<?php echo htmlspecialchars($mesEnvio, ENT_QUOTES); ?>">
                        <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
                        <input type="search" class="height-md" style="width: 295px;" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>" placeholder="Pesquisar" aria-label="Pesquisar processo ou parte">
                        <?php $listExportPage = 'cejusc'; $listExportQuery = $exportQuery; include __DIR__ . '/_list_export_buttons.php'; ?>
                    </form>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar pesquisa" data-list-section-hide="search"><i class="fi fi-rr-angle-small-up"></i></div>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm"><span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span></div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center">
                    <form method="get" class="display-flex row row-fit gap-y-md align-center" data-auto-submit-filters>
                        <input type="hidden" name="page" value="cejusc">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>">
                        <input type="hidden" name="items_per_page" value="<?php echo (int) $itemsPerPage; ?>">
                        <label class="sr-only" for="cejusc-month">Mês da data de envio</label>
                        <input class="width-250 height-md" type="month" id="cejusc-month" name="mes_envio" value="<?php echo htmlspecialchars($mesEnvio, ENT_QUOTES); ?>">
                    </form>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar filtros" data-list-section-hide="filters"><i class="fi fi-rr-angle-small-up"></i></div>
                </div>
            </div>

            <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="items" style="display: none;">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm"><span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Itens por página</span></div>
                <div class="display-flex row row-fit justify-end gap-y-md align-center">
                    <form method="get" class="display-flex row row-fit gap-y-md align-center" data-auto-submit-filters>
                        <input type="hidden" name="page" value="cejusc"><input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES); ?>"><input type="hidden" name="mes_envio" value="<?php echo htmlspecialchars($mesEnvio, ENT_QUOTES); ?>">
                        <select class="width-300 height-md" name="items_per_page" aria-label="Itens por página"><option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option><option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option><option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option><option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option></select>
                    </form>
                    <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar itens por página" data-list-section-hide="items"><i class="fi fi-rr-angle-small-up"></i></div>
                </div>
            </div>

            <div class="display-flex col col-full register">
                <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                    <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm"><span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;"><?php echo $total === 0 ? 'Nenhum registro encontrado' : htmlspecialchars((string) $total . ($total === 1 ? ' registro encontrado' : ' registros encontrados'), ENT_QUOTES); ?></span></div>
                </div>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'cejusc', $registroListViewerContext, $connection);
                    $registroMetadata['show_delete'] = $podeRemoverRegistroCejusc;
                    $registroMetadata['delete_endpoint'] = '/api/cartorio-cejusc/delete.php';
                    $registroMetadata['delete_label'] = 'Remover registro';
                    ?>
                    <div class="display-flex col col-full register register-with-metadata border-bottom" data-registro-id="<?php echo htmlspecialchars((string) $row['id'], ENT_QUOTES); ?>">
                        <div class="register-line" style="border-bottom: 0;">
                            <div class="register-line-left">
                                <div class="col width-500 col-process">
                                    <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                                </div>
                                <div class="col width-700" title="Parte encaminhada ao CEJUSC">
                                    <span class="minor-info text-ellipsis" style="font-weight: 600; color: var(--color-gray-900);">Parte: <?php echo htmlspecialchars((string) $row['nome_parte'], ENT_QUOTES); ?></span>
                                    <span class="major-info text-ellipsis">Data de envio ao CEJUSC — <?php echo htmlspecialchars(cejusc_date((string) $row['data_envio_cejusc']), ENT_QUOTES); ?></span>
                                </div>
                            </div>
                            <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                                <?php include __DIR__ . '/_registro_metadata.php'; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card-buttons">
                <span class="pagination-info">Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $total; ?> registros (<?php echo (int) $totalPages; ?> <?php echo $totalPages === 1 ? 'página' : 'páginas'; ?>, <?php echo (int) $itemsPerPage; ?> itens por página)</span>
                <?php if ($totalPages > 0): ?><div class="pagination-controls"><a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url(array_merge($exportQuery, ['page_number' => max(1, $currentPage - 1)])), ENT_QUOTES); ?>" aria-label="Página anterior"><i class="fi fi-rr-angle-small-left"></i></a><span class="pagination-number active"><?php echo (int) $currentPage; ?></span><a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="<?php echo htmlspecialchars(cejusc_url(array_merge($exportQuery, ['page_number' => min($totalPages, $currentPage + 1)])), ENT_QUOTES); ?>" aria-label="Próxima página"><i class="fi fi-rr-angle-small-right"></i></a></div><?php endif; ?>
            </div>
        </main>
        <div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
        <script>
            (function () {
                function toggleSection(name) {
                    var section = document.querySelector('[data-list-section="' + name + '"]');
                    if (!section) return;
                    var isVisible = section.style.display !== 'none';
                    document.querySelectorAll('[data-list-section]').forEach(function (item) {
                        item.style.display = 'none';
                    });
                    if (!isVisible) section.style.display = 'flex';
                }
                document.querySelectorAll('[data-list-section-trigger]').forEach(function (trigger) {
                    trigger.addEventListener('click', function () {
                        toggleSection(trigger.getAttribute('data-list-section-trigger'));
                    });
                    trigger.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            toggleSection(trigger.getAttribute('data-list-section-trigger'));
                        }
                    });
                });
                document.querySelectorAll('[data-list-section-hide]').forEach(function (trigger) {
                    trigger.addEventListener('click', function () {
                        var section = trigger.closest('[data-list-section]');
                        if (section) section.style.display = 'none';
                    });
                });
                if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                    App.RegistroMetadataList.bind({
                        deleteEndpoint: '/api/cartorio-cejusc/delete.php',
                        deleteFallback: {
                            type: 'success',
                            title: 'Sucesso',
                            message: 'Registro CEJUSC removido.'
                        }
                    });
                }
            })();
        </script>
    </body>
</html>
