<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';

$currentUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$isGestao = false;
if ($currentUserId !== '') {
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'])) > 0;
}

$pdo = Conection::getInstance()->getConnection();
$service = new SalaAudienciasPautaService($pdo);
$hoje = date('Y-m-d');
$idEmAndamentoHoje = $service->findIdEmAndamentoNaData($hoje);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter === 'agendada') {
    $statusFilter = 'designada';
}
if ($statusFilter === 'adiada') {
    $statusFilter = 'redesignada';
}
$periodoFilter = trim((string) ($_GET['periodo'] ?? ''));
$tipoFilter = trim((string) ($_GET['tipo'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$routePage = trim((string) ($_GET['page'] ?? 'salapauta'));
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $pageNumber = max(1, (int) $pageNumberParam);
} else {
    $pageNumber = 1;
}

$listFilters = [
    'status' => $statusFilter,
    'periodo' => $periodoFilter,
    'tipo' => $tipoFilter,
    'search' => $search,
];
$offset = ($pageNumber - 1) * $itemsPerPage;
$result = $service->list($listFilters, $itemsPerPage, $offset);
$tipos = $service->listTiposAtivos();
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$statusCounts = $result['statusCounts'] ?? ['designada' => 0, 'realizada' => 0, 'redesignada' => 0, 'cancelada' => 0, 'em_andamento' => 0, 'finalizada' => 0];

if ($total === 0) {
    $totalPages = 0;
    $pageNumber = 1;
    $offset = 0;
} else {
    $totalPages = (int) ceil($total / $itemsPerPage);
    if ($pageNumber > $totalPages) {
        $pageNumber = $totalPages;
        $offset = ($pageNumber - 1) * $itemsPerPage;
        $result = $service->list($listFilters, $itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
        $statusCounts = $result['statusCounts'] ?? $statusCounts;
    }
}
$registroListViewerContext = RegistroMetadataHelper::viewerContextFromSession();

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
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($periodoFilter !== '') {
    $baseQueryParams['periodo'] = $periodoFilter;
}
if ($tipoFilter !== '') {
    $baseQueryParams['tipo'] = $tipoFilter;
}
if ($search !== '') {
    $baseQueryParams['search'] = $search;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

$paginationButtons = [];
$paginationEllipsis = false;
$trailingPages = [];
if ($totalPages > 0) {
    if ($totalPages <= 5) {
        $paginationButtons = range(1, $totalPages);
    } else {
        $windowStart = max(1, min($pageNumber - 2, $totalPages - 4));
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

$periodoFilterLabelsPauta = ['hoje' => 'Hoje', 'semana' => 'Próximos 7 dias', 'mes' => 'Este mês'];
$statusFilterLabelsPauta = [
    'designada' => 'Designada',
    'em_andamento' => 'Em andamento',
    'finalizada' => 'Finalizada',
    'realizada' => 'Realizada',
    'redesignada' => 'Redesignada',
    'cancelada' => 'Cancelada',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Lista</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <!-- HEADER PAGE -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <!-- CARD PAGE -->
    <div class="container-full card-page">

        <!-- TITLE CARD -->
        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active); font-size: 36px;">Pauta de Audiências</h1>
                <p class="description-page" style="color: var(--color-primary-active); margin-top: -15px;">Lista de audiências cadastradas, com visão por data, tipo, sala e situação processual.</p>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">
                <div class="title-icon" title="Pesquisar" role="button" tabindex="0" aria-label="Pesquisar" data-list-section-trigger="search">
                    <i class="fi fi-rr-search"></i>
                </div>
                <div class="title-icon" title="Filtrar" role="button" tabindex="0" aria-label="Filtrar" data-list-section-trigger="filters">
                    <i class="fi fi-rr-filter"></i>
                </div>
                <div class="title-icon" title="Listar" role="button" tabindex="0" aria-label="Listar" data-list-section-trigger="items">
                    <i class="fi fi-rr-list"></i>
                </div>
                <button type="button" class="btn btn-primary width-300" id="novo-cadastro-btn">Novo cadastro</button>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="search" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Pesquisa e cadastro</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <input type="text" class="height-md" id="search-input" placeholder="Pesquisar por processo, parte ou sala..." autocomplete="off" style="width: 290px;" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                <?php
                $listExportPage = $routePage;
                $listExportQuery = array_filter([
                    'status' => $statusFilter,
                    'periodo' => $periodoFilter,
                    'tipo' => $tipoFilter,
                    'search' => $search,
                ], static fn ($v) => $v !== null && $v !== '');
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="search">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - FILTERS -->
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" data-list-section="filters" style="display: none;">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center flex-wrap">
                <select class="width-500 height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="designada"<?php echo $statusFilter === 'designada' ? ' selected' : ''; ?>>Designada (<?php echo (int) ($statusCounts['designada'] ?? 0); ?>)</option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                    <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                    <option value="realizada"<?php echo $statusFilter === 'realizada' ? ' selected' : ''; ?>>Realizada (<?php echo (int) ($statusCounts['realizada'] ?? 0); ?>)</option>
                    <option value="redesignada"<?php echo $statusFilter === 'redesignada' ? ' selected' : ''; ?>>Redesignada (<?php echo (int) ($statusCounts['redesignada'] ?? 0); ?>)</option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                </select>
                <select class="width-500 height-md" id="filter-periodo">
                    <option value=""<?php echo $periodoFilter === '' ? ' selected' : ''; ?>>Todo o período</option>
                    <option value="hoje"<?php echo $periodoFilter === 'hoje' ? ' selected' : ''; ?>>Hoje</option>
                    <option value="semana"<?php echo $periodoFilter === 'semana' ? ' selected' : ''; ?>>Próximos 7 dias</option>
                    <option value="mes"<?php echo $periodoFilter === 'mes' ? ' selected' : ''; ?>>Este mês</option>
                </select>
                <select class="width-700 height-md" id="filter-tipo">
                    <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Todos os tipos de audiência</option>
                    <?php foreach ($tipos as $tp): $tpNome = (string) ($tp['nome'] ?? ''); ?>
                        <option value="<?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?>"<?php echo $tipoFilter === $tpNome ? ' selected' : ''; ?>><?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="title-icon" title="Ocultar" role="button" tabindex="0" aria-label="Ocultar" data-list-section-hide="filters">
                    <i class="fi fi-rr-angle-small-up"></i>
                </div>
            </div>
        </div>

        <!-- SUBTITLE SECTION CARD - ITEMS PER PAGE -->
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

        <!-- REGISTERS LIST -->
        <div class="display-flex col col-full register">

            <!-- SUBTITLE SECTION CARD - FILTERS INFO -->
            <?php
            $activeFiltersPauta = [];
            if ($statusFilter !== '') {
                $activeFiltersPauta[] = 'Status [' . htmlspecialchars($statusFilterLabelsPauta[$statusFilter] ?? $statusFilter, ENT_QUOTES) . ']';
            }
            if ($periodoFilter !== '') {
                $activeFiltersPauta[] = 'Período [' . htmlspecialchars($periodoFilterLabelsPauta[$periodoFilter] ?? $periodoFilter, ENT_QUOTES) . ']';
            }
            if ($tipoFilter !== '') {
                $activeFiltersPauta[] = 'Tipo [' . htmlspecialchars($tipoFilter, ENT_QUOTES) . ']';
            }
            if ($search !== '') {
                $activeFiltersPauta[] = 'Pesquisa [' . htmlspecialchars($search, ENT_QUOTES) . ']';
            }
            if (!empty($activeFiltersPauta)) {
                echo '
                    <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);">
                        <div class="display-flex row row-fit justify-start align-center padding-x-sm padding-y-sm gap-y-sm">
                            <span class="text text-ellipsis" style="font-weight: 400; color: var(--color-primary-active); font-size: 12px;">
                                Lista com os seguintes filtros:
                            </span>
                            <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                                ' . implode(' + ', $activeFiltersPauta) . '
                            </span>
                        </div>
                    </div>';
            }
            ?>

            <!-- Informa a quantidade de registros encontrados (x ou nenhum) -->
            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom" style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $totalRegistrosPauta = (int) ($total ?? 0);
                        if ($totalRegistrosPauta === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            $trTxt = $totalRegistrosPauta === 1 ? 'registro encontrado' : 'registros encontrados';
                            echo htmlspecialchars((string) $totalRegistrosPauta, ENT_QUOTES) . ' ' . htmlspecialchars($trTxt, ENT_QUOTES);
                        }
                        ?>
                    </span>
                </div>
            </div>

        <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $row): ?>
        <?php $registroMetadata = RegistroMetadataHelper::fromListRow($row, 'sala_pauta', $registroListViewerContext, $pdo); ?>
        <div class="display-flex col col-full register register-with-metadata border-bottom" data-audiencia-id="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . substr((string) $row['hora_audiencia'], 0, 5), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-500 col-process">
                        <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Tipo de audiência</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Intimações</span>
                        <span class="major-info">
                            <?php
                                $partesStatus = is_array($row['partes_cumprimento'] ?? null) ? $row['partes_cumprimento'] : [];
                                $ordemTipos = [
                                    'reu' => 0,
                                    'vitima' => 1,
                                    'testemunha_denuncia' => 2,
                                    'testemunha_defesa' => 3,
                                ];
                                usort($partesStatus, static function ($a, $b) use ($ordemTipos): int {
                                    $tipoKey = static function ($x): string {
                                        if (!is_array($x)) {
                                            return '';
                                        }
                                        $tp = (string) (($x['tipo_parte'] ?? '') === '' ? ($x['tipo'] ?? '') : ($x['tipo_parte'] ?? ''));

                                        return $tp;
                                    };
                                    $ta = $tipoKey($a);
                                    $tb = $tipoKey($b);
                                    $ia = $ordemTipos[$ta] ?? 4;
                                    $ib = $ordemTipos[$tb] ?? 4;
                                    if ($ia === $ib) {
                                        return 0;
                                    }

                                    return $ia <=> $ib;
                                });

                                $linkAudinRow = trim((string) ($row['link_audiencia'] ?? ''));
                                $localizacaoRow = trim((string) ($row['localizacao'] ?? ''));
                                if ($localizacaoRow === 'audin' && $linkAudinRow !== ''):
                            ?>
                                <span
                                    class="mini-status status-audin"
                                    role="button"
                                    tabindex="0"
                                    data-nav-href="<?php echo htmlspecialchars($linkAudinRow, ENT_QUOTES); ?>"
                                    data-nav-target="_blank"
                                    title="Audiência cadastrada no Audin"
                                    aria-label="Abrir link da audiência no Audin"
                                ></span>
                            <?php
                                endif;

                                foreach ($partesStatus as $parteStatus):
                                    $cumprimentoFallback = '';
                                    if (is_scalar($parteStatus) || $parteStatus === null) {
                                        $cumprimentoFallback = (string) ($parteStatus ?? '');
                                    }
                                    $cp = is_array($parteStatus)
                                        ? $parteStatus
                                        : ['nome_parte' => '', 'tipo_parte' => '', 'expedicao' => 'Não expedido', 'cumprimento' => $cumprimentoFallback];

                                    $nomeParte = trim((string) ($cp['nome_parte'] ?? ''));
                                    $expVal = trim((string) ($cp['expedicao'] ?? 'Não expedido'));
                                    $cumVal = trim((string) ($cp['cumprimento'] ?? ''));

                                    // Metade esquerda = expedição. "Não expedido" fica em status pendente; qualquer outro em concluído.
                                    $leftColor = ($expVal === '' || $expVal === 'Não expedido')
                                        ? 'var(--color-secondary-bg-light)'
                                        : 'var(--color-success)';

                                    // Metade direita = cumprimento (mantém as mesmas cores do mini-status original).
                                    $rightColor = $cumVal === 'cumprido'
                                        ? 'var(--color-success)'
                                        : ($cumVal === 'nao_cumprido' ? 'var(--color-danger)' : 'var(--color-secondary-bg-light)');

                                    $labelCumprimento = ucfirst(str_replace('_', ' ', $cumVal !== '' ? $cumVal : 'pendente'));
                                    $labelExpedicao = $expVal !== '' ? $expVal : 'Não expedido';
                                    $titleIntimacao = ($nomeParte !== '' ? $nomeParte : 'Parte')
                                        . ' — Expedição: ' . $labelExpedicao
                                        . ' | Cumprimento: ' . $labelCumprimento;
                            ?>
                                <div
                                    class="mini-status mini-status-dual"
                                    style="--left-color: <?php echo htmlspecialchars($leftColor, ENT_QUOTES); ?>; --right-color: <?php echo htmlspecialchars($rightColor, ENT_QUOTES); ?>"
                                    title="<?php echo htmlspecialchars($titleIntimacao, ENT_QUOTES); ?>"
                                ></div>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center padding-y-sm">
                        <?php
                            $idPautaRow = (string) ($row['id_pauta'] ?? '');
                            $dataAudRow = substr((string) ($row['data_audiencia'] ?? ''), 0, 10);
                            $stRaw = strtolower(preg_replace('/[\s-]+/', '_', trim((string) ($row['status'] ?? ''))));
                            $mostrarPainelAcao = ($dataAudRow === $hoje) && ($stRaw !== 'finalizada');
                            $bloqueioOutroEmAndamento = ($idEmAndamentoHoje !== null && $idEmAndamentoHoje !== $idPautaRow);
                            $stRow = $stRaw;
                            if ($stRow === 'agendada') {
                                $stRow = 'designada';
                            } elseif ($stRow === 'adiada') {
                                $stRow = 'redesignada';
                            }
                            $labelsStatus = [
                                'designada' => 'Designada',
                                'em_andamento' => 'Em andamento',
                                'finalizada' => 'Finalizada',
                                'realizada' => 'Realizada',
                                'redesignada' => 'Redesignada',
                                'cancelada' => 'Cancelada',
                            ];
                            $textoStatus = $labelsStatus[$stRow] ?? ucfirst(str_replace('_', ' ', $stRow));
                            $classeStatus = 'status-pending';
                            if ($stRow === 'cancelada' || $stRow === 'redesignada') {
                                $classeStatus = 'status-cancelled';
                            } elseif ($stRow === 'em_andamento') {
                                $classeStatus = 'status-current';
                            } elseif (in_array($stRow, ['realizada', 'finalizada'], true)) {
                                $classeStatus = 'status-finished';
                            }
                            $titBloqueio = 'Já existe uma audiência em andamento hoje. Conclua ou reinicie-a antes de movimentar outras.';
                            $titSoEmAndamento = 'Só disponível quando a audiência estiver em andamento.';
                            $disIniciar = true;
                            $disReiniciar = true;
                            $disEncerrar = true;
                            if ($mostrarPainelAcao) {
                                if ($bloqueioOutroEmAndamento) {
                                    $disIniciar = $disReiniciar = $disEncerrar = true;
                                } else {
                                    // Iniciar: só audiência designada (ainda não começou).
                                    if ($stRow === 'designada') {
                                        $disIniciar = false;
                                    }
                                    // Reiniciar e encerrar: só quando já estiver em andamento.
                                    if ($stRow === 'em_andamento') {
                                        $disReiniciar = false;
                                        $disEncerrar = false;
                                    }
                                }
                            }
                        ?>
                        <?php if ($mostrarPainelAcao): ?>
                        <?php
                            $titleReiniciar = $bloqueioOutroEmAndamento
                                ? $titBloqueio
                                : ($stRow === 'em_andamento' ? 'Reiniciar (volta para Designada)' : $titSoEmAndamento);
                            $titleIniciar = $bloqueioOutroEmAndamento
                                ? $titBloqueio
                                : ($stRow === 'designada' ? 'Iniciar (Em andamento)' : 'Só disponível quando a audiência estiver designada.');
                            $titleEncerrar = $bloqueioOutroEmAndamento
                                ? $titBloqueio
                                : ($stRow === 'em_andamento' ? 'Encerrar (Finalizada)' : $titSoEmAndamento);
                        ?>
                        <button type="button" class="btn-editar reload" data-audiencia-reiniciar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleReiniciar, ENT_QUOTES); ?>"<?php echo $disReiniciar ? ' disabled' : ''; ?>>
                            <i class="fi fi-rr-rotate-square"></i>
                        </button>
                        <button type="button" class="btn-editar start" data-audiencia-iniciar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleIniciar, ENT_QUOTES); ?>"<?php echo $disIniciar ? ' disabled' : ''; ?>>
                            <i class="fi fi-rr-arrow-square-right"></i>
                        </button>
                        <button type="button" class="btn-editar stop" data-audiencia-encerrar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleEncerrar, ENT_QUOTES); ?>"<?php echo $disEncerrar ? ' disabled' : ''; ?>>
                            <i class="fi fi-rr-stop-square"></i>
                        </button>
                        <?php endif; ?>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <div class="display-flex row row-fit justify-end gap-y-sm allocate-chips-row allocate-users">
                        <?php
                            $alocados = $row['Alocados'] ?? [];
                            foreach ($alocados as $alocado) {
                                $tipo = $alocado['tipo'] ?? 'specific';
                                $iniciais = htmlspecialchars($alocado['iniciais'] ?? '—', ENT_QUOTES);
                                $nomeCompleto = $alocado['nome_completo'] ?? '';
                                $nomeLabel = $nomeCompleto !== '' ? $nomeCompleto : ($alocado['iniciais'] ?? '—');
                                if ($tipo === 'rule') {
                                    $chipTitle = 'Servidor responsável (regra): ' . $nomeLabel;
                                } elseif ($tipo === 'all') {
                                    $chipTitle = 'Responsável pelo cadastro: ' . $nomeLabel;
                                } else {
                                    $chipTitle = 'Alocado: ' . $nomeLabel;
                                }
                                $classeTipo = in_array($tipo, ['rule', 'specific', 'all'], true) ? ' ' . $tipo : '';
                                $alocadoIdStr = htmlspecialchars($alocado['alocado_id'] ?? '', ENT_QUOTES);
                                $usuarioIdStr = $alocado['usuario_id'] ?? '';
                                $podeRemover = $isGestao || ($tipo === 'specific' && $usuarioIdStr === $currentUserId);
                                ?>
                                <span class="user-allocate-wrap">
                                    <span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($chipTitle, ENT_QUOTES); ?>"><?php echo $iniciais; ?></span>
                                    <?php if ($podeRemover): ?>
                                    <button type="button"
                                        class="user-allocate-remove js-desalocar"
                                        data-alocado-id="<?php echo $alocadoIdStr; ?>"
                                        data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"
                                        data-endpoint="/api/sala-audiencias-pauta/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">×</button>
                                    <?php endif; ?>
                                </span>
                                <?php
                            }
                            ?>
                        </div>
                        </div>
                    <div class="col">
                        <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($classeStatus, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($textoStatus, ENT_QUOTES); ?>
                        </div>
                    </div>
                    <?php include __DIR__ . '/_registro_metadata.php'; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        </div>

        <!-- PAGINAÇÃO -->
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
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
                    <button type="button" class="pagination-symbol<?php echo $pageNumber <= 1 ? ' disabled' : ''; ?>" <?php if ($pageNumber > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $pageNumber ? ' active' : ''; ?>" <?php if ($page !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams; $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $pageNumber ? ' active' : ''; ?>" <?php if ($page !== $pageNumber): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $pageNumber >= $totalPages ? ' disabled' : ''; ?>" <?php if ($pageNumber < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
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

    <script>
        (function () {
            function initializeListSectionToggles() {
                const sections = document.querySelectorAll('[data-list-section]');

                function setSectionVisibility(sectionName, isVisible) {
                    const section = document.querySelector('[data-list-section="' + sectionName + '"]');
                    if (!section) {
                        return;
                    }

                    section.style.display = isVisible ? 'flex' : 'none';
                }

                function handleToggleClick(event, isVisible) {
                    const sectionName = event.currentTarget.getAttribute(isVisible ? 'data-list-section-trigger' : 'data-list-section-hide');
                    if (!sectionName) {
                        return;
                    }

                    setSectionVisibility(sectionName, isVisible);
                }

                function handleToggleKeydown(event, isVisible) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    handleToggleClick(event, isVisible);
                }

                sections.forEach(function (section) {
                    section.style.display = 'none';
                });

                document.querySelectorAll('[data-list-section-trigger]').forEach(function (trigger) {
                    trigger.addEventListener('click', function (event) {
                        handleToggleClick(event, true);
                    });
                    trigger.addEventListener('keydown', function (event) {
                        handleToggleKeydown(event, true);
                    });
                });

                document.querySelectorAll('[data-list-section-hide]').forEach(function (trigger) {
                    trigger.addEventListener('click', function (event) {
                        handleToggleClick(event, false);
                    });
                    trigger.addEventListener('keydown', function (event) {
                        handleToggleKeydown(event, false);
                    });
                });
            }

            initializeListSectionToggles();
        })();
    </script>
    <script src="/assets/js/modules/audiencias/pauta-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list.js') ?: time(); ?>"></script>
    <script>
        (function () {
            if (window.App && App.RegistroMetadataList && typeof App.RegistroMetadataList.bind === 'function') {
                App.RegistroMetadataList.bind({
                    storageKey: 'salapauta.postReloadToast',
                    cancelEndpoint: '/api/sala-audiencias-pauta/cancel.php',
                    cancelToastKey: 'sala.pauta.cancel.success',
                    cancelFallback: { type: 'success', title: 'Sucesso', message: 'Audiência cancelada.' }
                });
            }
            if (!window.App || !App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
                return;
            }
            App.CartorioTaskList.initialize({
                storageKey: 'salapauta.postReloadToast',
                enableDesalocar: true
            });
        })();
    </script>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</body>
</html>
