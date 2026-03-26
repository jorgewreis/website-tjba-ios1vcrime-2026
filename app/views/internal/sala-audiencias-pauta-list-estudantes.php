<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';

$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
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
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$offset = ($pageNumber - 1) * $itemsPerPage;
$result = $service->list([
    'status' => $statusFilter,
    'periodo' => $periodoFilter,
    'tipo' => $tipoFilter,
    'search' => $search,
], $itemsPerPage, $offset);
$tipos = $service->listTiposAtivos();
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$statusCounts = $result['statusCounts'] ?? ['designada' => 0, 'realizada' => 0, 'redesignada' => 0, 'cancelada' => 0, 'em_andamento' => 0, 'finalizada' => 0];
$totalPages = max(1, (int) ceil(max(1, $total) / $itemsPerPage));
$startItem = $total > 0 ? $offset + 1 : 0;
$endItem = min($offset + count($rows), $total);
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Estudantes</title>
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
            <span class="specific">Pauta de audiências</span>
        </div>
        <h1 class="title-page">Pauta de Audiências</h1>
        <p class="description-page">Lista de audiências (somente leitura). Use os filtros para localizar por data, tipo ou situação.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start"><h2>Lista de audiências</h2></div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <input type="text" class="width-400 height-md" id="search-input" placeholder="Pesquisar por processo, parte ou sala..." autocomplete="off" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                <select class="width-300 height-md" id="export-type" disabled>
                    <option value="" selected>Exportar lista</option>
                    <option value="pdf">Gerar PDF</option>
                    <option value="csv">Gerar CSV</option>
                    <option value="print">Imprimir</option>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                <div class="col">
                    <label for="filter-status">Status</label>
                    <select class="width-400 height-md" id="filter-status">
                        <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <option value="designada"<?php echo $statusFilter === 'designada' ? ' selected' : ''; ?>>Designada (<?php echo (int) ($statusCounts['designada'] ?? 0); ?>)</option>
                        <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>Em andamento (<?php echo (int) ($statusCounts['em_andamento'] ?? 0); ?>)</option>
                        <option value="finalizada"<?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>Finalizada (<?php echo (int) ($statusCounts['finalizada'] ?? 0); ?>)</option>
                        <option value="realizada"<?php echo $statusFilter === 'realizada' ? ' selected' : ''; ?>>Realizada (<?php echo (int) ($statusCounts['realizada'] ?? 0); ?>)</option>
                        <option value="redesignada"<?php echo $statusFilter === 'redesignada' ? ' selected' : ''; ?>>Redesignada (<?php echo (int) ($statusCounts['redesignada'] ?? 0); ?>)</option>
                        <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>Cancelada (<?php echo (int) ($statusCounts['cancelada'] ?? 0); ?>)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-periodo">Período</label>
                    <select class="width-500 height-md" id="filter-periodo">
                        <option value=""<?php echo $periodoFilter === '' ? ' selected' : ''; ?>>Todo o período</option>
                        <option value="hoje"<?php echo $periodoFilter === 'hoje' ? ' selected' : ''; ?>>Hoje</option>
                        <option value="semana"<?php echo $periodoFilter === 'semana' ? ' selected' : ''; ?>>Próximos 7 dias</option>
                        <option value="mes"<?php echo $periodoFilter === 'mes' ? ' selected' : ''; ?>>Este mês</option>
                    </select>
                </div>
                <div class="col">
                    <label for="filter-tipo">Tipo de audiência</label>
                    <select class="width-500 height-md" id="filter-tipo">
                        <option value=""<?php echo $tipoFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                        <?php foreach ($tipos as $tp): $tpNome = (string) ($tp['nome'] ?? ''); ?>
                            <option value="<?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?>"<?php echo $tipoFilter === $tpNome ? ' selected' : ''; ?>><?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        <?php foreach ($rows as $row): ?>
        <div class="display-flex col col-full register border-bottom" data-audiencia-id="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . substr((string) $row['hora_audiencia'], 0, 5), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400 col-process process-info" title="clique para copiar">
                        <?php
                            $classeAudiencia = !empty($row['classe_nome']) ? $row['classe_nome'] : 'Classe não informada';
                            $classeStyleAudiencia = $classeAudiencia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info text-ellipsis" <?php echo $classeStyleAudiencia; ?>><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Tipo de audiência</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Intimações</span>
                        <span class="major-info">
                            <?php
                                $cumprimentos = is_array($row['partes_cumprimento'] ?? null) ? $row['partes_cumprimento'] : [];
                                foreach ($cumprimentos as $cumprimentoParte):
                                    $cp = is_array($cumprimentoParte)
                                        ? $cumprimentoParte
                                        : ['nome_parte' => '', 'cumprimento' => (string) $cumprimentoParte];
                                    $statusCumpr = (string) ($cp['cumprimento'] ?? '');
                                    $miniClass = $statusCumpr === 'cumprido'
                                        ? 'status-finished'
                                        : ($statusCumpr === 'nao_cumprido' ? 'status-cancelled' : 'status-pending');
                            ?>
                                <div class="mini-status <?php echo $miniClass; ?>"></div>
                            <?php endforeach; ?>
                        </span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <div class="col">
                        <?php
                            $stRow = (string) ($row['status'] ?? '');
                            if ($stRow === 'agendada') {
                                $stRow = 'designada';
                            } elseif ($stRow === 'adiada') {
                                $stRow = 'redesignada';
                            }
                            $labelsStatusEst = [
                                'designada' => 'Designada',
                                'em_andamento' => 'Em andamento',
                                'finalizada' => 'Finalizada',
                                'realizada' => 'Realizada',
                                'redesignada' => 'Redesignada',
                                'cancelada' => 'Cancelada',
                            ];
                            $textoStatusEst = $labelsStatusEst[$stRow] ?? ucfirst(str_replace('_', ' ', $stRow));
                            $classeStatusEst = 'status-pending';
                            if ($stRow === 'cancelada') {
                                $classeStatusEst = 'status-cancelled';
                            } elseif ($stRow === 'em_andamento') {
                                $classeStatusEst = 'status-current';
                            } elseif (in_array($stRow, ['realizada', 'finalizada'], true)) {
                                $classeStatusEst = 'status-finished';
                            }
                        ?>
                        <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($classeStatusEst, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($textoStatusEst, ENT_QUOTES); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> audiências (<?php echo $totalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)</span>
            <div class="pagination-controls">
                <?php $prev = max(1, $pageNumber - 1); $next = min($totalPages, $pageNumber + 1); ?>
                <?php
                $qPrevE = $pageNumber > 1 ? '?page=estudapauta&page_number=' . $prev . '&items_per_page=' . $itemsPerPage . '&status=' . urlencode($statusFilter) . '&periodo=' . urlencode($periodoFilter) . '&tipo=' . urlencode($tipoFilter) . '&search=' . urlencode($search) : '';
                $qNextE = $pageNumber < $totalPages ? '?page=estudapauta&page_number=' . $next . '&items_per_page=' . $itemsPerPage . '&status=' . urlencode($statusFilter) . '&periodo=' . urlencode($periodoFilter) . '&tipo=' . urlencode($tipoFilter) . '&search=' . urlencode($search) : '';
                ?>
                <button type="button" class="pagination-symbol<?php echo $pageNumber <= 1 ? ' disabled' : ''; ?>" <?php echo $qPrevE !== '' ? 'data-nav-href="' . htmlspecialchars($qPrevE, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                <button type="button" class="pagination-number active" disabled><?php echo $pageNumber; ?></button>
                <button type="button" class="pagination-symbol<?php echo $pageNumber >= $totalPages ? ' disabled' : ''; ?>" <?php echo $qNextE !== '' ? 'data-nav-href="' . htmlspecialchars($qNextE, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/audiencias/pauta-list-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list-estudantes.js') ?: time(); ?>"></script>
</body>
</html>
