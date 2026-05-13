<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../../helpers/ClasseProcessualListagemHelper.php';

$service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
$routePage = trim((string) ($_GET['page'] ?? 'salaata'));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} else {
    $currentPage = 1;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$result = $service->listRealizadasParaUpload($itemsPerPage, $offset);
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);

if ($total === 0) {
    $totalPages = 0;
    $currentPage = 1;
    $offset = 0;
} else {
    $totalPages = (int) ceil($total / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $itemsPerPage;
        $result = $service->listRealizadasParaUpload($itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
    }
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $total === 1 ? 'audiência' : 'audiências';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($currentPage - 2, $totalPages - 4));
        $windowEnd = min($totalPages, $windowStart + 4);
        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $paginationButtons[] = $page;
        }
        if ($windowEnd < $totalPages) {
            $paginationEllipsis = true;
            $extraStart = max($windowEnd + 1, $totalPages - 1);
            for ($page = $extraStart; $page <= $totalPages; $page++) {
                $trailingPages[] = $page;
            }
        }
    }
}

/**
 * Texto da coluna "Ata para download" com data limite e dias restantes (calendário).
 */
function sala_audiencias_ata_label_prazo_download(string $expiresAt): string
{
    $expiresAt = trim($expiresAt);
    if ($expiresAt === '') {
        return 'Disponível para download';
    }
    try {
        $exp = new DateTimeImmutable($expiresAt);
    } catch (Throwable $e) {
        return 'Disponível para download';
    }
    $today = new DateTimeImmutable('today');
    $expDay = $exp->setTime(0, 0, 0);
    $diff = $today->diff($expDay);
    if ($diff->invert === 1) {
        return 'Prazo encerrado';
    }
    $dataLimite = $exp->format('d/m/Y');
    $n = (int) $diff->days;
    $diaWord = $n === 1 ? 'dia' : 'dias';

    return 'Disponível até ' . $dataLimite . ' (' . $n . ' ' . $diaWord . ')';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Atas de Audiências - Lista</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Sala de Audiências - Atas -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Atas de Audiências</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Envio do PDF da ata para audiências realizadas. O arquivo fica disponível aos estudantes inscritos por 30 dias.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Exportação</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = [];
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="items" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Itens por página</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-300 height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="items">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex col col-full register">

            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $totalAtas = (int) ($total ?? 0);
                        if ($totalAtas === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            $trAtas = $totalAtas === 1 ? 'registro encontrado' : 'registros encontrados';
                            echo htmlspecialchars((string) $totalAtas, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($trAtas, ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                    </span>
                </div>
            </div>

        <?php foreach ($rows as $row):
            $idPauta = htmlspecialchars((string) ($row['id_pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $arquivoRelRow = trim((string) ($row['arquivo_relativo'] ?? ''));
            $temAta = !empty($row['id_ata']) && $arquivoRelRow !== '';
            $ataRetencaoEncerrada = !empty($row['id_ata']) && $arquivoRelRow === '';
            $labelAtaDownload = $temAta
                ? sala_audiencias_ata_label_prazo_download((string) ($row['expires_at'] ?? ''))
                : '';
            $inicioEfetivoRaw = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
            $inicioEfetivoTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            $horaExibicao = $inicioEfetivoTs !== false ? date('H:i', $inicioEfetivoTs) : substr((string) ($row['hora_audiencia'] ?? ''), 0, 5);
            ?>
        <div class="display-flex col col-full register border-bottom" data-pauta="<?php echo $idPauta; ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . $horaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-400 col-process process-info" title="Clique para copiar">
                        <?php
                            $classeRawAtasList = (string) ($row['classe_nome'] ?? '');
                            $classeAudiencia = ClasseProcessualListagemHelper::textoListagem($classeRawAtasList);
                            $classeAudienciaClass = ClasseProcessualListagemHelper::classesSpanMinor($classeRawAtasList);
                        ?>
                        <span class="<?php echo $classeAudienciaClass; ?>" title="<?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500">
                        <span class="minor-info">Tipo de audiência</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500">
                        <span class="minor-info">Ata para download</span>
                        <?php if ($temAta): ?>
                        <span class="major-info text-ellipsis ata-status-text"><?php echo htmlspecialchars($labelAtaDownload, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php elseif ($ataRetencaoEncerrada): ?>
                        <span class="major-info text-ellipsis ata-status-text">Prazo de download encerrado. O PDF foi removido após 30 dias; novo envio não é permitido.</span>
                        <?php else: ?>
                        <span class="major-info text-ellipsis ata-status-text">Nenhum arquivo enviado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <?php if (!$ataRetencaoEncerrada): ?>
                    <input type="file" class="ata-file-input visually-hidden" accept="application/pdf,.pdf" aria-label="Selecionar PDF da ata">
                    <button type="button" class="btn btn-primary btn-upload-ata" data-pauta="<?php echo $idPauta; ?>"><?php echo $temAta ? 'Substituir PDF' : 'Enviar PDF'; ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        </div>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $prevHref = '?' . http_build_query($prevParams);
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
                    $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>
    <script src="/assets/js/modules/audiencias/atas-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/atas-list.js') ?: time(); ?>"></script>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>
</body>
</html>
