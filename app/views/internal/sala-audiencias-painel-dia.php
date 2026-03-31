<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../../services/ProcessoManager.php';

$pdo = Conection::getInstance()->getConnection();
$service = new SalaAudienciasPautaService($pdo);
$processoManager = new ProcessoManager($pdo);
$hoje = date('Y-m-d');
$idEmAndamentoHoje = $service->findIdEmAndamentoNaData($hoje);

$result = $service->list([
    'status' => '',
    'periodo' => 'hoje',
    'tipo' => '',
    'search' => '',
    'incluir_todos_status' => true,
], 500, 0);
$rows = $result['rows'] ?? [];
$totalHoje = count($rows);
$timelineItemsHoje = [];
foreach ($rows as $rowTimeline) {
    $timelineItemsHoje[] = [
        'id' => (string) ($rowTimeline['id_pauta'] ?? ''),
        'data_audiencia' => substr((string) ($rowTimeline['data_audiencia'] ?? ''), 0, 10),
        'hora_audiencia' => substr((string) ($rowTimeline['hora_audiencia'] ?? '00:00'), 0, 5),
        'duracao_estimada_minutos' => max(5, (int) ($rowTimeline['duracao_estimada_minutos'] ?? 0)),
        'data_hora_inicio_efetivo' => (string) ($rowTimeline['data_hora_inicio_efetivo'] ?? ''),
        'data_hora_fim_efetivo' => (string) ($rowTimeline['data_hora_fim_efetivo'] ?? ''),
    ];
}

$dtHoje = new \DateTimeImmutable('today');
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$dataTitulo = $diasSemana[(int) $dtHoje->format('w')] . ', ' . $dtHoje->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Painel do dia — Audiências</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
        <style>
            .timeline-day-ruler { padding: 8px 0 4px; }
            .timeline-day-ruler-track { position: relative; height: 22px; border-radius: 999px; background: #eef2f6; overflow: hidden; }
            .timeline-day-ruler-bar-prev { position: absolute; top: 5px; height: 12px; border-radius: 999px; background: #8db8ff; opacity: .7; }
            .timeline-day-ruler-bar-real { position: absolute; top: 7px; height: 8px; border-radius: 999px; background: #2b7a4b; opacity: .95; }
            .timeline-day-ruler-labels { margin-top: 6px; display: flex; justify-content: space-between; font-size: 11px; color: #667085; font-family: monospace; }
            .timeline-day-ruler-legend { margin-top: 6px; display: flex; gap: 12px; align-items: center; font-size: 11px; color: #667085; }
            .timeline-day-ruler-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        </style>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Painel do dia</span>
        </div>
        <h1 class="title-page">Painel de Audiências</h1>
        <p class="description-page">Audiências de hoje (<?php echo htmlspecialchars($dataTitulo, ENT_QUOTES, 'UTF-8'); ?>). Lista única, sem filtros — todas as situações.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start gap-y-md align-center flex-wrap">
                <h2 style="margin:0;">Audiências de hoje</h2>
                <span class="minor-info"><?php echo (int) $totalHoje; ?> audiência(s)</span>
            </div>
        </div>
        <div class="padding-x-md padding-y-sm border-bottom">
            <div id="timeline-hoje-painel-dia" class="timeline-day-ruler"></div>
        </div>

        <?php if ($totalHoje === 0): ?>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
            <span class="major-info">Não há audiências cadastradas para hoje.</span>
        </div>
        <?php endif; ?>

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

                                    $leftColor = ($expVal === '' || $expVal === 'Não expedido')
                                        ? 'var(--color-secondary-bg-light)'
                                        : 'var(--color-success)';

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
                            if ($stRow === 'cancelada') {
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
                                    if ($stRow === 'designada') {
                                        $disIniciar = false;
                                    }
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
                    <div class="col">
                        <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($classeStatus, ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($textoStatus, ENT_QUOTES); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                $prioridadesRow = [];
                if (!empty($row['numero_processo'])) {
                    try {
                        $prioridadesRow = $processoManager->listarPrioridades((string) $row['numero_processo']);
                    } catch (Throwable $e) {
                        error_log('sala-audiencias-painel-dia: listarPrioridades falhou: ' . $e->getMessage());
                        $prioridadesRow = [];
                    }
                }
            ?>
            <?php
                $processoSigiloso = !empty($row['processo_sigiloso']);
            ?>
            <?php if ($prioridadesRow !== [] || $processoSigiloso): ?>
            <div class="display-flex col col-full grp-subtask-panel" id="subtask-grp-<?php echo htmlspecialchars((string) ($row['id_pauta'] ?? ''), ENT_QUOTES); ?>">
                <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top align-start flex-wrap grp-sub-line grp-sub-line-primary" style="font-family: monospace; font-size: 11px;">
                    <div class="display-flex row row-fit justify-start gap-y-md flex-wrap grp-sub-left">
                        <div class="col">
                            <div class="display-flex flex-wrap align-items-center">
                                <?php if ($processoSigiloso): ?>
                                    <span class="badge badge-black" title="Processo sigiloso">S</span>
                                <?php endif; ?>
                                <?php foreach ($prioridadesRow as $prioridade): ?>
                                    <?php
                                    $rotulo = trim((string) $prioridade);
                                    $codigo = PrioridadesProcessuaisCatalog::codigoForLabel($rotulo)
                                        ?? PrioridadesProcessuaisCatalog::normalizeToCodigo($rotulo);
                                    $badgeClass = 'badge-warning';
                                    if ($codigo !== null && in_array($codigo, ['reu_preso', 'violencia_domestica', 'crianca_adolescente'], true)) {
                                        $badgeClass = 'badge-danger';
                                    } elseif ($codigo === 'sigiloso') {
                                        $badgeClass = 'badge-black';
                                    }
                                    ?>
                                    <span class="badge <?php echo htmlspecialchars($badgeClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($rotulo, ENT_QUOTES); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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
            const root = document.getElementById('timeline-hoje-painel-dia');
            if (!root || items.length === 0) return;
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
            const clamp = (m) => Math.max(dayStart, Math.min(dayEnd, m));
            const pct = (m) => ((clamp(m) - dayStart) / range) * 100;
            const prevHtml = [];
            const realHtml = [];
            items.forEach((it) => {
                const sTs = toTs((it.data_audiencia || '') + ' ' + (it.hora_audiencia || ''));
                if (Number.isNaN(sTs)) return;
                const sMin = toMin(sTs);
                const prevDur = Math.max(5, parseInt(it.duracao_estimada_minutos || 0, 10) || 5);
                prevHtml.push(`<span class="timeline-day-ruler-bar-prev" style="left:${pct(sMin)}%;width:${Math.max(0.4, pct(sMin + prevDur) - pct(sMin))}%"></span>`);
                const rsTs = toTs(it.data_hora_inicio_efetivo || '');
                const reTs = toTs(it.data_hora_fim_efetivo || '');
                if (!Number.isNaN(rsTs)) {
                    const rs = toMin(rsTs);
                    const re = !Number.isNaN(reTs) ? toMin(reTs) : (rs + prevDur);
                    realHtml.push(`<span class="timeline-day-ruler-bar-real" style="left:${pct(rs)}%;width:${Math.max(0.4, pct(re) - pct(rs))}%"></span>`);
                }
            });
            root.innerHTML =
                `<div class="timeline-day-ruler-track">${prevHtml.join('')}${realHtml.join('')}</div>
                 <div class="timeline-day-ruler-labels"><span>08:00</span><span>18:00</span></div>
                 <div class="timeline-day-ruler-legend">
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#8db8ff"></i> Previsto</span>
                   <span><i class="timeline-day-ruler-legend-dot" style="background:#2b7a4b"></i> Real</span>
                 </div>`;
        })();
    </script>
    <script src="/assets/js/modules/audiencias/pauta-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list.js') ?: time(); ?>"></script>
</body>
</html>
