<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/UserAccessHelper.php';
require_once __DIR__ . '/../../helpers/ClasseProcessualListagemHelper.php';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';

$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$usuarioModel = new Usuario();
$perfilsUsuario = $userId !== '' ? $usuarioModel->listarPerfisUsuario($userId) : [];
if (!is_array($perfilsUsuario)) {
    $perfilsUsuario = [];
}
$normPerfis = ProfileNormalizer::normalize($perfilsUsuario);
$podeInscricaoComoEstudante = UserAccessHelper::isUsuarioExterno($perfilsUsuario) && ProfileNormalizer::hasEstudante($normPerfis);

$service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
$minhasRows = array_values(array_filter($minhasRows, static function (array $row): bool {
    return !SalaAudienciasAtaService::ocultarDaListagemAtasPorPrazoSemDownload($row);
}));
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$total = count($minhasRows);
$totalPages = $total > 0 ? (int) ceil($total / $itemsPerPage) : 0;
if ($totalPages > 0 && $pageNumber > $totalPages) {
    $pageNumber = $totalPages;
}
if ($total === 0) {
    $pageNumber = 1;
}
$offset = ($pageNumber - 1) * $itemsPerPage;
$rowsPage = array_slice($minhasRows, $offset, $itemsPerPage);
$displayedCount = count($rowsPage);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $total === 1 ? 'audiência' : 'audiências';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$baseQueryParams = [
    'page' => 'estudaata',
    'items_per_page' => $itemsPerPage,
];

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($pageNumber - 2, $totalPages - 4));
        $windowEnd = min($totalPages, $windowStart + 4);
        for ($p = $windowStart; $p <= $windowEnd; $p++) {
            $paginationButtons[] = $p;
        }
        if ($windowEnd < $totalPages) {
            $paginationEllipsis = true;
            $extraStart = max($windowEnd + 1, $totalPages - 1);
            for ($p = $extraStart; $p <= $totalPages; $p++) {
                $trailingPages[] = $p;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Atas de Audiências - Estudantes</title>
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
            <span class="all">Estudantes - Audiências -</span>
            <span class="specific">Atas de audiências</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Atas de Audiências</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Lista das audiências em que você se inscreveu, com situação de disponibilidade da ata para download.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters">
                    <i class="fi fi-rr-filter"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-600); font-size: 13px;">Não há busca nesta lista.</span>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-600); font-size: 13px;">Não há filtros adicionais.</span>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
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

        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $total; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>

        <?php if (count($rowsPage) === 0): ?>
        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="major-info">Nenhuma inscrição encontrada.</span>
            </div>
        </div>
        <?php endif; ?>

        <?php foreach ($rowsPage as $m):
            $idPauta = htmlspecialchars((string) ($m['pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $arquivoRelEst = trim((string) ($m['arquivo_relativo'] ?? ''));
            $expira = trim((string) ($m['expires_at'] ?? ''));
            $expiraTs = $expira !== '' ? strtotime($expira) : false;
            $downloadAtaOk = $arquivoRelEst !== '' && !empty($m['id_ata']) && $expiraTs !== false && $expiraTs > time();
            $ataPrazoEncerrado = !empty($m['id_ata']) && $arquivoRelEst === '';
            $classeRawAtasEst = (string) ($m['classe_nome'] ?? '');
            $classeAudiencia = ClasseProcessualListagemHelper::textoListagem($classeRawAtasEst);
            $classeAudienciaClass = ClasseProcessualListagemHelper::classesSpanMinor($classeRawAtasEst);
            $expiraFmt = $expira !== '' ? date('d/m', strtotime($expira)) : '';
            $inicioEfetivoRaw = trim((string) ($m['data_hora_inicio_efetivo'] ?? ''));
            $inicioEfetivoTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            $horaExibicao = $inicioEfetivoTs !== false ? date('H:i', $inicioEfetivoTs) : substr((string) ($m['hora_audiencia'] ?? ''), 0, 5);
            ?>
        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div class="col width-300">
                        <span class="minor-info">Data</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . $horaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500 col-process process-info" title="Clique para copiar">
                        <span class="<?php echo htmlspecialchars($classeAudienciaClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($m['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Ata para download</span>
                        <span class="major-info">
                            <?php
                            if ($downloadAtaOk) {
                                echo 'Disponível até ' . htmlspecialchars($expiraFmt, ENT_QUOTES, 'UTF-8');
                            } elseif ($ataPrazoEncerrado) {
                                echo 'Prazo de download encerrado.';
                            } else {
                                echo 'Ainda não disponível';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end padding-x-md">
                    <?php if ($downloadAtaOk && $podeInscricaoComoEstudante): ?>
                    <button type="button" class="btn btn-primary width-300" data-nav-href="/api/sala-audiencias-ata/download.php?pauta=<?php echo urlencode((string) $m['pauta']); ?>" data-nav-target="_blank">Baixar Ata (PDF)</button>
                    <?php elseif ($downloadAtaOk): ?>
                    <span class="minor-info">Download exclusivo para estudantes.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $total; ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $pageNumber > 1 ? $pageNumber - 1 : 1;
                    $prevParams = $baseQueryParams;
                    $prevParams['page_number'] = $prevPage;
                    $prevHref = '?' . http_build_query($prevParams);
                    $nextPage = $pageNumber < $totalPages ? $pageNumber + 1 : $pageNumber;
                    $nextParams = $baseQueryParams;
                    $nextParams['page_number'] = $nextPage;
                    $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $pageNumber <= 1 ? ' disabled' : ''; ?>" <?php if ($pageNumber > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                    <?php foreach ($paginationButtons as $pBtn): ?>
                        <?php $pageParams = $baseQueryParams;
                        $pageParams['page_number'] = $pBtn; ?>
                        <button type="button" class="pagination-number<?php echo $pBtn === $pageNumber ? ' active' : ''; ?>" <?php if ($pBtn !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $pBtn): ?>
                            <?php $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $pBtn; ?>
                            <button type="button" class="pagination-number<?php echo $pBtn === $pageNumber ? ' active' : ''; ?>" <?php if ($pBtn !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $pageNumber >= $totalPages ? ' disabled' : ''; ?>" <?php if ($pageNumber < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
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

    <script>
    (function () {
        var el = document.getElementById('items-per-page');
        if (!el) return;
        function getFramePhpHref() {
            var path = window.location.pathname || '/frame.php';
            var slash = path.lastIndexOf('/');
            var dir = slash >= 0 ? path.slice(0, slash + 1) : '/';
            return dir + 'frame.php';
        }
        el.addEventListener('change', function () {
            var n = String(el.value || '25').trim();
            var params = new URLSearchParams();
            params.set('page', 'estudaata');
            params.set('page_number', '1');
            params.set('items_per_page', n);
            window.location.href = getFramePhpHref() + '?' + params.toString();
        });
    })();
    </script>
    <?php include __DIR__ . '/_list_section_toggle_script.php'; ?>

</body>
</html>
