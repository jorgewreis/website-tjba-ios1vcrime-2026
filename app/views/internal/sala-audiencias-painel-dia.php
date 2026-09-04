<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/SalaAudienciasEstudanteInscricaoRepository.php';
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';

$pdo = Conection::getInstance()->getConnection();
$service = new SalaAudienciasPautaService($pdo);
$inscricaoRepository = new SalaAudienciasEstudanteInscricaoRepository($pdo);
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
$pautaIdsHoje = array_values(array_filter(array_map(static function (array $row): string {
    return trim((string) ($row['id_pauta'] ?? ''));
}, $rows), static function (string $id): bool {
    return $id !== '';
}));
$inscritosByPauta = $inscricaoRepository->listDetalhesByPautaIds($pautaIdsHoje);
$totalInscritosByPauta = $inscricaoRepository->countInscritosPorPautaIds($pautaIdsHoje);
$timelineItemsHoje = [];
foreach ($rows as $rowTimeline) {
    $statusTimeline = strtolower(trim((string) ($rowTimeline['status'] ?? '')));
    if ($statusTimeline === 'agendada') {
        $statusTimeline = 'designada';
    } elseif ($statusTimeline === 'adiada') {
        $statusTimeline = 'redesignada';
    } elseif ($statusTimeline === 'encerrada') {
        $statusTimeline = 'finalizada';
    }
    $timelineItemsHoje[] = [
        'id' => (string) ($rowTimeline['id_pauta'] ?? ''),
        'status' => $statusTimeline,
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
function painelHoraPorStatus(array $row): string
{
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if ($status === 'agendada') {
        $status = 'designada';
    } elseif ($status === 'adiada') {
        $status = 'redesignada';
    } elseif ($status === 'encerrada') {
        $status = 'finalizada';
    }

    $dataAud = trim((string) ($row['data_audiencia'] ?? ''));
    $horaAud = trim((string) ($row['hora_audiencia'] ?? ''));
    $inicioRaw = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
    $fimRaw = trim((string) ($row['data_hora_fim_efetivo'] ?? ''));

    $designadaTs = strtotime(trim($dataAud . ' ' . $horaAud));
    $inicioTs = $inicioRaw !== '' ? strtotime($inicioRaw) : false;
    $fimTs = $fimRaw !== '' ? strtotime($fimRaw) : false;

    if ($status === 'finalizada') {
        if ($inicioTs !== false && $fimTs !== false && $fimTs >= $inicioTs) {
            $duracaoMin = (int) floor(($fimTs - $inicioTs) / 60);

            return date('H:i', $inicioTs) . ' - ' . date('H:i', $fimTs) . ' (' . $duracaoMin . ' min)';
        }
        if ($inicioTs !== false) {
            return date('H:i', $inicioTs) . ' - --:-- (sem encerramento real)';
        }
        if ($fimTs !== false) {
            return '--:-- - ' . date('H:i', $fimTs) . ' (sem início real)';
        }

        return 'Sem horário real registrado';
    }

    if ($status === 'em_andamento' && $inicioTs !== false) {
        return date('H:i', $inicioTs);
    }

    if ($designadaTs !== false) {
        return date('H:i', $designadaTs);
    }

    return '—';
}

function painelResumoInscrito(array $inscrito): string
{
    $ordem = max(1, (int) ($inscrito['ordem_inscricao'] ?? 0));
    $inscritoEmRaw = trim((string) ($inscrito['inscrito_em'] ?? ''));
    $inscritoEmTs = $inscritoEmRaw !== '' ? strtotime($inscritoEmRaw) : false;
    $horaLabel = $inscritoEmTs !== false ? date('H:i:s', $inscritoEmTs) : '--:--:--';

    $nome = trim((string) ($inscrito['nome_completo'] ?? ''));
    if ($nome === '') {
        $nome = 'Estudante não identificado';
    }

    $cpfDigits = preg_replace('/\D+/', '', (string) ($inscrito['cpf'] ?? ''));
    if (strlen((string) $cpfDigits) === 11) {
        $cpfLabel = substr($cpfDigits, 0, 3) . '.' . substr($cpfDigits, 3, 3) . '.' . substr($cpfDigits, 6, 3) . '-' . substr($cpfDigits, 9, 2);
    } else {
        $cpfLabel = 'CPF não informado';
    }

    $telefone = trim((string) ($inscrito['telefone'] ?? ''));
    if ($telefone === '') {
        $telefone = 'Telefone não informado';
    }

    return sprintf(
        '%02d. %s - %s - %s - %s',
        $ordem,
        $horaLabel,
        $nome,
        $cpfLabel,
        $telefone
    );
}
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
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <link rel="stylesheet" href="/assets/css/shared/timeline-day-ruler.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/timeline-day-ruler.css') ?: time(); ?>">
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Painel do dia</span>
        </div>
        <h1 class="title-page">Painel de Audiências</h1>
        <p class="description-page">Audiências de hoje (<?php echo htmlspecialchars($dataTitulo, ENT_QUOTES, 'UTF-8'); ?>).</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start gap-y-md align-center flex-wrap">
                <h2 style="margin:0;">Audiências de hoje</h2>
                <span class="minor-info"><?php echo (int) $totalHoje; ?> audiência(s)</span>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <div id="timeline-hoje-painel-dia" class="timeline-day-ruler row row-full"></div>
        </div>

        <?php if ($totalHoje === 0): ?>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
            <span class="major-info">Não há audiências cadastradas para hoje.</span>
        </div>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
        <?php
            $statusCompactoRaw = strtolower(preg_replace('/[\s-]+/', '_', trim((string) ($row['status'] ?? ''))));
            if ($statusCompactoRaw === 'agendada') {
                $statusCompactoRaw = 'designada';
            } elseif ($statusCompactoRaw === 'adiada') {
                $statusCompactoRaw = 'redesignada';
            } elseif ($statusCompactoRaw === 'encerrada') {
                $statusCompactoRaw = 'finalizada';
            }
            $ocultarInfosCompactas = in_array($statusCompactoRaw, ['finalizada', 'realizada', 'redesignada', 'cancelada'], true);
            $linhaInativa = $ocultarInfosCompactas;
            $linhaEmAndamento = $statusCompactoRaw === 'em_andamento';
            $rotuloHorarioPainelDia = $statusCompactoRaw === 'finalizada'
                ? 'Finalizada em:'
                : ($statusCompactoRaw === 'em_andamento' ? 'Iniciada às:' : 'Previsão:');
            $idPautaRow = (string) ($row['id_pauta'] ?? '');
            $dataAudRow = substr((string) ($row['data_audiencia'] ?? ''), 0, 10);
            $stRow = $statusCompactoRaw;
            $mostrarPainelAcao = ($dataAudRow === $hoje) && ($stRow !== 'finalizada');
            $bloqueioOutroEmAndamento = ($idEmAndamentoHoje !== null && $idEmAndamentoHoje !== $idPautaRow);
            $labelsStatus = [
                'designada' => 'Designada',
                'em_andamento' => 'Em andamento',
                'finalizada' => 'Finalizada',
                'realizada' => 'Realizada',
                'redesignada' => 'Redesignada',
                'cancelada' => 'Cancelada',
            ];
            $textoStatus = $labelsStatus[$stRow] ?? ucfirst(str_replace('_', ' ', $stRow));
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
        <div class="display-flex col col-full register register-painel-dia border-bottom" data-audiencia-id="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
            <div class="register-line<?php echo $linhaInativa ? ' is-inactive' : ''; ?><?php echo $linhaEmAndamento ? ' is-current' : ''; ?>">
                <div class="left">
                    <?php $processo_bloco_model = 'full'; include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                    <div class="container-processo container-sm painel-dia-audiencia-info">
                        <div class="container-line">
                            <div class="left">
                                <div class="line-content box-number">
                                    <span class="content-number major-info">AUDIENCIA DE <?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-assuntos">Status: <strong><?php echo htmlspecialchars($textoStatus, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                <span class="content-assuntos painel-dia-time"><span><?php echo htmlspecialchars($rotuloHorarioPainelDia, ENT_QUOTES, 'UTF-8'); ?></span> <strong><?php echo htmlspecialchars(painelHoraPorStatus($row), ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                <?php if (!$ocultarInfosCompactas): ?>
                                <span class="content-polo painel-dia-intimacoes-label">Intimações:</span>
                                <span class="content-partes painel-dia-intimacoes-list">
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
                                                if (!is_array($x)) return '';
                                                return (string) (($x['tipo_parte'] ?? '') === '' ? ($x['tipo'] ?? '') : ($x['tipo_parte'] ?? ''));
                                            };
                                            return ($ordemTipos[$tipoKey($a)] ?? 4) <=> ($ordemTipos[$tipoKey($b)] ?? 4);
                                        });
                                        foreach ($partesStatus as $parteStatus):
                                            $cp = is_array($parteStatus) ? $parteStatus : ['nome_parte' => '', 'expedicao' => 'Não expedido', 'cumprimento' => (string) ($parteStatus ?? '')];
                                            $nomeParte = trim((string) ($cp['nome_parte'] ?? ''));
                                            $expVal = trim((string) ($cp['expedicao'] ?? 'Não expedido'));
                                            $cumVal = trim((string) ($cp['cumprimento'] ?? ''));
                                            $leftColor = ($expVal === '' || $expVal === 'Não expedido') ? 'var(--color-secondary-bg-light)' : 'var(--color-success)';
                                            $rightColor = $cumVal === 'cumprido' ? 'var(--color-success)' : ($cumVal === 'nao_cumprido' ? 'var(--color-danger)' : 'var(--color-secondary-bg-light)');
                                            $titleIntimacao = ($nomeParte !== '' ? $nomeParte : 'Parte') . ' — Expedição: ' . ($expVal !== '' ? $expVal : 'Não expedido') . ' | Cumprimento: ' . ucfirst(str_replace('_', ' ', $cumVal !== '' ? $cumVal : 'pendente'));
                                    ?>
                                    <span class="mini-status mini-status-dual" style="--left-color: <?php echo htmlspecialchars($leftColor, ENT_QUOTES); ?>; --right-color: <?php echo htmlspecialchars($rightColor, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleIntimacao, ENT_QUOTES); ?>"></span>
                                    <?php endforeach; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <div class="container-historical container-sm painel-dia-acoes">
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-classe">Ações</span>
                                <?php if ($mostrarPainelAcao): ?>
                                <?php
                                    $titleReiniciar = $bloqueioOutroEmAndamento ? $titBloqueio : ($stRow === 'em_andamento' ? 'Reiniciar (volta para Designada)' : $titSoEmAndamento);
                                    $titleIniciar = $bloqueioOutroEmAndamento ? $titBloqueio : ($stRow === 'designada' ? 'Iniciar (Em andamento)' : 'Só disponível quando a audiência estiver designada.');
                                    $titleEncerrar = $bloqueioOutroEmAndamento ? $titBloqueio : ($stRow === 'em_andamento' ? 'Encerrar (Finalizada)' : $titSoEmAndamento);
                                ?>
                                <div class="painel-dia-actions">
                                    <button type="button" class="btn-editar reload" data-audiencia-reiniciar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleReiniciar, ENT_QUOTES); ?>"<?php echo $disReiniciar ? ' disabled' : ''; ?>><i class="fi fi-rr-rotate-square"></i></button>
                                    <button type="button" class="btn-editar start" data-audiencia-iniciar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleIniciar, ENT_QUOTES); ?>"<?php echo $disIniciar ? ' disabled' : ''; ?>><i class="fi fi-rr-arrow-square-right"></i></button>
                                    <button type="button" class="btn-editar stop" data-audiencia-encerrar="<?php echo htmlspecialchars($idPautaRow, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($titleEncerrar, ENT_QUOTES); ?>"<?php echo $disEncerrar ? ' disabled' : ''; ?>><i class="fi fi-rr-stop-square"></i></button>
                                </div>
                                <?php endif; ?>
                                <span class="content-assuntos">Status: <strong><?php echo htmlspecialchars($textoStatus, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                $idPautaAtual = trim((string) ($row['id_pauta'] ?? ''));
                $inscritosRow = $idPautaAtual !== '' ? ($inscritosByPauta[$idPautaAtual] ?? []) : [];
                $totalInscritosReal = $idPautaAtual !== '' ? (int) ($totalInscritosByPauta[$idPautaAtual] ?? count($inscritosRow)) : count($inscritosRow);
            ?>
            <?php if (!$ocultarInfosCompactas): ?>
            <div class="display-flex col col-full grp-subtask-panel" id="subtask-grp-estudantes-<?php echo htmlspecialchars((string) ($row['id_pauta'] ?? ''), ENT_QUOTES); ?>">
                <?php if ($totalInscritosReal <= 0 && $inscritosRow === []): ?>
                <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top" style="font-family: monospace; font-size: 11px;">
                    <span class="minor-info">Nenhum estudante inscrito.</span>
                </div>
                <?php else: ?>
                <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top grp-sub-line grp-sub-line-primary" style="font-family: monospace; font-size: 11px;">
                    <span class="minor-info">Estudantes inscritos (<?php echo (int) max($totalInscritosReal, count($inscritosRow)); ?>):</span>
                </div>
                    <?php foreach ($inscritosRow as $inscrito): ?>
                    <div class="display-flex row row-full subtask padding-x-sm padding-y-sm border-top" style="font-family: monospace; font-size: 11px;">
                        <span class="major-info" style="white-space: normal; word-break: break-word;"><?php echo htmlspecialchars(painelResumoInscrito($inscrito), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

    <script src="/assets/js/shared/timeline-day-ruler.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/timeline-day-ruler.js') ?: time(); ?>"></script>
    <script>
        (function () {
            const itemsBase = <?php echo json_encode($timelineItemsHoje, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || [];
            const itemsById = {};
            itemsBase.forEach((it) => {
                const id = String(it.id || '').trim();
                if (id !== '') itemsById[id] = Object.assign({}, it);
            });
            const root = document.getElementById('timeline-hoje-painel-dia');
            if (!root || itemsBase.length === 0 || !window.TimelineDayRuler) return;
            const isTimelineTerminal = (status) => ['cancelada', 'redesignada', 'finalizada', 'realizada'].includes(window.TimelineDayRuler.normalizeStatus(status));
            const updateRegisterLineState = (id, status) => {
                const pautaId = String(id || '').trim();
                const normalizedStatus = window.TimelineDayRuler.normalizeStatus(status);
                document.querySelectorAll('.register.register-painel-dia[data-audiencia-id]').forEach((register) => {
                    if (String(register.getAttribute('data-audiencia-id') || '').trim() !== pautaId) return;
                    const line = register.querySelector('.register-line');
                    if (!line) return;
                    line.classList.toggle('is-current', normalizedStatus === 'em_andamento');
                    line.classList.toggle('is-inactive', isTimelineTerminal(normalizedStatus));
                });
            };
            const allFinished = () => {
                const ids = Object.keys(itemsById);
                if (!ids.length) return false;
                return ids.every((id) => isTimelineTerminal(itemsById[id]?.status));
            };
            const asTimelineItems = () => Object.keys(itemsById).map((id) => Object.assign({ id: id }, itemsById[id]));
            function renderTimeline() {
                window.TimelineDayRuler.render(root, asTimelineItems(), { studentPanel: true });
            }
            function refreshTimelineData() {
                const pautas = Object.keys(itemsById);
                if (!pautas.length) return;
                if (allFinished()) return;
                fetch('/api/sala-audiencias-pauta/status-bulk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ pautas: pautas })
                })
                    .then((r) => r.json().then((j) => ({ ok: r.ok, body: j || {} })))
                    .then((res) => {
                        if (!res.ok || !res.body.success || !res.body.items) return;
                        Object.keys(res.body.items).forEach((id) => {
                            if (itemsById[id]) {
                                itemsById[id] = Object.assign({}, itemsById[id], res.body.items[id]);
                            }
                            updateRegisterLineState(id, itemsById[id]?.status);
                        });
                        renderTimeline();
                    })
                    .catch(() => { /* erro silencioso no polling */ });
            }
            renderTimeline();
            let pollTimer = null;
            const tick = () => {
                renderTimeline();
                if (allFinished()) {
                    if (pollTimer !== null) {
                        window.clearInterval(pollTimer);
                        pollTimer = null;
                    }
                    return;
                }
                refreshTimelineData();
            };
            pollTimer = window.setInterval(tick, 60000);
        })();
    </script>
    <script src="/assets/js/modules/audiencias/pauta-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-list.js') ?: time(); ?>"></script>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</body>
</html>
