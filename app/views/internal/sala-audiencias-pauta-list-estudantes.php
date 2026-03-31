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
$timelineItemsHoje = [];
$hoje = date('Y-m-d');
foreach ($rows as $rowTimeline) {
    $dataRow = substr((string) ($rowTimeline['data_audiencia'] ?? ''), 0, 10);
    if ($dataRow !== $hoje) {
        continue;
    }
    $horaRow = substr((string) ($rowTimeline['hora_audiencia'] ?? '00:00'), 0, 5);
    $durPrev = max(5, (int) ($rowTimeline['duracao_estimada_minutos'] ?? 0));
    $timelineItemsHoje[] = [
        'id' => (string) ($rowTimeline['id_pauta'] ?? ''),
        'data_audiencia' => $dataRow,
        'hora_audiencia' => $horaRow,
        'duracao_estimada_minutos' => $durPrev,
        'data_hora_inicio_efetivo' => (string) ($rowTimeline['data_hora_inicio_efetivo'] ?? ''),
        'data_hora_fim_efetivo' => (string) ($rowTimeline['data_hora_fim_efetivo'] ?? ''),
    ];
}
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
        <style>
            .timeline-day-ruler { padding: 8px 0 4px; }
            .timeline-day-ruler-track { position: relative; height: 26px; border-radius: 999px; background: #eef2f6; overflow: hidden; }
            .timeline-day-ruler-hour-tick { position: absolute; top: 0; width: 1px; height: 100%; background: rgba(71,84,103,.22); }
            .timeline-day-ruler-now { position: absolute; top: 0; width: 1px; height: 100%; background: rgba(17,24,39,.45); z-index: 4; }
            .timeline-day-ruler-bar-prev { position: absolute; top: 5px; height: 12px; border-radius: 999px; background: #8db8ff; opacity: .7; }
            .timeline-day-ruler-bar-real { position: absolute; top: 8px; height: 8px; border-radius: 999px; background: #2b7a4b; opacity: .95; z-index: 3; }
            .timeline-day-ruler-bar-real.late { background: #d14343; }
            .timeline-day-ruler-bar-real.early { background: #7c5cff; }
            .timeline-day-ruler-labels { margin-top: 6px; display: grid; grid-template-columns: repeat(11, 1fr); font-size: 11px; color: #667085; font-family: monospace; }
            .timeline-day-ruler-labels span { text-align: center; }
            .timeline-day-ruler-legend { margin-top: 6px; display: flex; gap: 12px; align-items: center; font-size: 11px; color: #667085; }
            .timeline-day-ruler-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
            .timeline-day-ruler-empty { font-size: 12px; color: #667085; }
        </style>
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
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Lista de audiências</h2></div>
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
        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <div id="timeline-hoje-pauta-estudante" class="timeline-day-ruler row row-full"></div>
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
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-lg">
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

    <script>
        (function () {
            const items = <?php echo json_encode($timelineItemsHoje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
            const root = document.getElementById('timeline-hoje-pauta-estudante');
            if (!root) return;
            const dayStart = 8 * 60;
            const dayEnd = 18 * 60;
            const range = dayEnd - dayStart;
            const toTs = (v) => {
                const s = String(v || '').trim();
                if (!s) return NaN;
                const t = Date.parse(s.replace(' ', 'T'));
                return Number.isNaN(t) ? NaN : t;
            };
            const toMin = (ts) => {
                const d = new Date(ts);
                if (Number.isNaN(d.getTime())) return NaN;
                return d.getHours() * 60 + d.getMinutes();
            };
            const parseSched = (it) => toTs((it.data_audiencia || '') + ' ' + (it.hora_audiencia || ''));
            const clamp = (m) => Math.max(dayStart, Math.min(dayEnd, m));
            const pct = (m) => ((clamp(m) - dayStart) / range) * 100;
            const barsPrev = [];
            const barsReal = [];
            const fmtHM = (m) => {
                const hh = String(Math.floor(m / 60)).padStart(2, '0');
                const mm = String(m % 60).padStart(2, '0');
                return `${hh}:${mm}`;
            };
            items.forEach((it) => {
                const sTs = parseSched(it);
                if (Number.isNaN(sTs)) return;
                const sMin = toMin(sTs);
                const prevDur = Math.max(5, parseInt(it.duracao_estimada_minutos || 0, 10) || 5);
                const prevEnd = sMin + prevDur;
                barsPrev.push({
                    l: pct(sMin),
                    w: Math.max(0.4, pct(prevEnd) - pct(sMin)),
                    title: `Previsto: ${fmtHM(sMin)} - ${fmtHM(prevEnd)}`
                });
                const realStartTs = toTs(it.data_hora_inicio_efetivo || '');
                const realEndTs = toTs(it.data_hora_fim_efetivo || '');
                if (!Number.isNaN(realStartTs)) {
                    const rs = toMin(realStartTs);
                    const re = !Number.isNaN(realEndTs) ? toMin(realEndTs) : (rs + prevDur);
                    const delta = rs - sMin;
                    const cls = delta > 5 ? 'late' : (delta < -5 ? 'early' : '');
                    barsReal.push({
                        l: pct(rs),
                        w: Math.max(0.4, pct(re) - pct(rs)),
                        cls,
                        title: `Real: ${fmtHM(rs)} - ${fmtHM(re)} | Previsto: ${fmtHM(sMin)} - ${fmtHM(prevEnd)}`
                    });
                }
            });
            if (barsPrev.length === 0 && barsReal.length === 0) {
                root.innerHTML = '<span class="timeline-day-ruler-empty">Régua do tempo (08:00–18:00) indisponível para os filtros atuais.</span>';
                return;
            }
            const prevHtml = barsPrev.map((b) => `<span class="timeline-day-ruler-bar-prev" title="${b.title}" style="left:${b.l}%;width:${b.w}%"></span>`).join('');
            const realHtml = barsReal.map((b) => `<span class="timeline-day-ruler-bar-real ${b.cls || ''}" title="${b.title}" style="left:${b.l}%;width:${b.w}%"></span>`).join('');
            const tickHtml = Array.from({ length: 11 }, (_, i) => {
                const left = (i / 10) * 100;
                return `<span class="timeline-day-ruler-hour-tick" style="left:${left}%"></span>`;
            }).join('');
            const labelsHtml = Array.from({ length: 11 }, (_, i) => {
                const hour = String(8 + i).padStart(2, '0');
                return `<span>${hour}:00</span>`;
            }).join('');
            const now = new Date();
            const nowMin = now.getHours() * 60 + now.getMinutes();
            const nowHtml = (nowMin >= dayStart && nowMin <= dayEnd)
                ? `<span class="timeline-day-ruler-now" title="Agora: ${fmtHM(nowMin)}" style="left:${pct(nowMin)}%"></span>`
                : '';
            root.innerHTML =
                `<div class="timeline-day-ruler-track">${tickHtml}${prevHtml}${realHtml}${nowHtml}</div>
                 <div class="timeline-day-ruler-labels">${labelsHtml}</div>
                 <div class="timeline-day-ruler-legend">
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#8db8ff"></i> Previsto</span>
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#2b7a4b"></i> Real no horário</span>
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#d14343"></i> Atraso</span>
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#7c5cff"></i> Adiantamento</span>
                 </div>`;
        })();
    </script>
    <script src="/assets/js/modules/audiencias/pauta-list-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list-estudantes.js') ?: time(); ?>"></script>
</body>
</html>
