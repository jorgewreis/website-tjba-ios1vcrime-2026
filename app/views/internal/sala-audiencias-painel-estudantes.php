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
require_once __DIR__ . '/../../access/ProfileNormalizer.php';

$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$usuarioModel = new Usuario();
$perfilsUsuario = $userId !== '' ? $usuarioModel->listarPerfisUsuario($userId) : [];
if (!is_array($perfilsUsuario)) {
    $perfilsUsuario = [];
}
$normPerfis = ProfileNormalizer::normalize($perfilsUsuario);
$podeInscricaoComoEstudante = UserAccessHelper::isUsuarioExterno($perfilsUsuario) && ProfileNormalizer::hasEstudante($normPerfis);

$pdo = Conection::getInstance()->getConnection();
$service = new SalaAudienciasAtaService($pdo);
$hojeRows = $userId !== '' ? $service->listHojeParaInscricaoEstudante($userId) : [];
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
$toTimeTs = static function (array $row): int {
    $inicioEfetivo = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
    if ($inicioEfetivo !== '') {
        $ts = strtotime($inicioEfetivo);
        if ($ts !== false) {
            return $ts;
        }
    }
    $fallback = trim((string) ($row['data_audiencia'] ?? '')) . ' ' . trim((string) ($row['hora_audiencia'] ?? ''));
    $ts = strtotime(trim($fallback));
    return $ts !== false ? $ts : PHP_INT_MAX;
};
usort($hojeRows, static function (array $a, array $b) use ($toTimeTs): int {
    return $toTimeTs($a) <=> $toTimeTs($b);
});
$maxVagas = SalaAudienciasAtaService::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
$itemsPerPage = 15;
if (!in_array($itemsPerPage, [15], true)) {
    $itemsPerPage = 15;
}
$hojePage = max(1, (int) ($_GET['hoje_page'] ?? 1));
$minhasPage = max(1, (int) ($_GET['minhas_page'] ?? 1));

$salaPainelPagWindow = static function (int $currentPage, int $totalPages): array {
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

    return [$paginationButtons, $paginationEllipsis, $trailingPages];
};

$hojeTotal = count($hojeRows);
if ($hojeTotal === 0) {
    $hojeTotalPages = 0;
    $hojePage = 1;
    $hojeOffset = 0;
    $hojePageRows = [];
} else {
    $hojeTotalPages = (int) ceil($hojeTotal / $itemsPerPage);
    if ($hojePage > $hojeTotalPages) {
        $hojePage = $hojeTotalPages;
    }
    $hojeOffset = ($hojePage - 1) * $itemsPerPage;
    $hojePageRows = array_slice($hojeRows, $hojeOffset, $itemsPerPage);
}
$hojeStartItem = count($hojePageRows) > 0 ? $hojeOffset + 1 : 0;
$hojeEndItem = count($hojePageRows) > 0 ? $hojeOffset + count($hojePageRows) : 0;

$minhasTotal = count($minhasRows);
if ($minhasTotal === 0) {
    $minhasTotalPages = 0;
    $minhasPage = 1;
    $minhasOffset = 0;
    $minhasPageRows = [];
} else {
    $minhasTotalPages = (int) ceil($minhasTotal / $itemsPerPage);
    if ($minhasPage > $minhasTotalPages) {
        $minhasPage = $minhasTotalPages;
    }
    $minhasOffset = ($minhasPage - 1) * $itemsPerPage;
    $minhasPageRows = array_slice($minhasRows, $minhasOffset, $itemsPerPage);
}
$minhasStartItem = count($minhasPageRows) > 0 ? $minhasOffset + 1 : 0;
$minhasEndItem = count($minhasPageRows) > 0 ? $minhasOffset + count($minhasPageRows) : 0;

[$hojePaginationButtons, $hojePaginationEllipsis, $hojeTrailingPages] = $salaPainelPagWindow($hojePage, $hojeTotalPages);
[$minhasPaginationButtons, $minhasPaginationEllipsis, $minhasTrailingPages] = $salaPainelPagWindow($minhasPage, $minhasTotalPages);

$hojeDocumentsLabel = $hojeTotal === 1 ? 'audiência' : 'audiências';
$hojePagesLabel = $hojeTotalPages === 1 ? 'página' : 'páginas';
$hojeItemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';
$minhasDocumentsLabel = $minhasTotal === 1 ? 'audiência' : 'audiências';
$minhasPagesLabel = $minhasTotalPages === 1 ? 'página' : 'páginas';
$minhasItemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$baseQueryPainel = ['page' => 'estudapainel', 'items_per_page' => $itemsPerPage];
function painelEstudanteStatusNormalizado(string $statusRaw): string
{
    $status = trim(strtolower($statusRaw));
    if ($status === 'agendada') {
        return 'designada';
    }
    if ($status === 'adiada') {
        return 'redesignada';
    }
    if ($status === 'encerrada') {
        return 'finalizada';
    }

    return $status;
}

function painelEstudanteStatusExibicao(array $row, ?int $agoraTs = null): string
{
    $status = painelEstudanteStatusNormalizado((string) ($row['status'] ?? ''));
    if ($status !== 'designada') {
        return $status;
    }

    $horarioDesignadoTs = painelEstudanteHorarioDesignadoTs($row);
    $agoraTs = $agoraTs ?? time();
    if ($horarioDesignadoTs !== false && $horarioDesignadoTs < $agoraTs) {
        return 'atrasada';
    }

    return $status;
}

function painelEstudanteStatusFinal(string $statusRaw): bool
{
    return in_array(painelEstudanteStatusNormalizado($statusRaw), ['cancelada', 'redesignada', 'finalizada', 'realizada'], true);
}

function painelEstudanteHoraEncerramento(string $fimRaw, bool $incluirData = false): string
{
    $fimTs = trim($fimRaw) !== '' ? strtotime($fimRaw) : false;
    if ($fimTs === false) {
        return '—';
    }

    return $incluirData ? date('d/m/Y H:i', $fimTs) : date('H:i', $fimTs);
}

function painelEstudanteDuracaoReal(string $inicioRaw, string $fimRaw): string
{
    $inicioTs = trim($inicioRaw) !== '' ? strtotime($inicioRaw) : false;
    $fimTs = trim($fimRaw) !== '' ? strtotime($fimRaw) : false;
    if ($inicioTs === false || $fimTs === false || $fimTs < $inicioTs) {
        return '—';
    }

    return (string) ((int) floor(($fimTs - $inicioTs) / 60));
}

function painelEstudanteHorarioDesignadoTs(array $row): int|false
{
    $data = trim((string) ($row['data_audiencia'] ?? ''));
    $hora = trim((string) ($row['hora_audiencia'] ?? ''));
    if ($data === '' || $hora === '') {
        return false;
    }

    return strtotime($data . ' ' . $hora);
}

function painelEstudantePrevisaoAtrasada(array $row, array $rows, ?int $agoraTs = null): bool
{
    if (painelEstudanteStatusNormalizado((string) ($row['status'] ?? '')) !== 'designada') {
        return false;
    }

    $agoraTs = $agoraTs ?? time();
    $horarioAlvoTs = painelEstudanteHorarioDesignadoTs($row);
    if ($horarioAlvoTs === false || $horarioAlvoTs >= $agoraTs) {
        return false;
    }

    // A previsão de toda audiência que passou do horário deve ser movida
    // visualmente para cinco minutos após o momento atual.
    return true;
}

$agoraPainelTs = time();
$previsoesAtrasadasPainel = [];
foreach ($hojeRows as $rowPrevisao) {
    $idPrevisao = trim((string) ($rowPrevisao['id_pauta'] ?? ''));
    if ($idPrevisao !== '' && painelEstudantePrevisaoAtrasada($rowPrevisao, $hojeRows, $agoraPainelTs)) {
        $previsoesAtrasadasPainel[$idPrevisao] = $agoraPainelTs + (5 * 60);
    }
}

function painelEstudanteHoraPorStatus(string $statusRaw, string $dataAud, string $horaAud, string $inicioRaw, string $fimRaw, ?int $previsaoTs = null): string
{
    $status = painelEstudanteStatusNormalizado($statusRaw);

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

    if ($status === 'atrasada') {
        return date('H:i', $previsaoTs ?? (time() + (5 * 60)));
    }

    if (in_array($status, ['designada', 'atrasada'], true) && $previsaoTs !== null) {
        return date('H:i', $previsaoTs);
    }

    if ($designadaTs !== false) {
        return date('H:i', $designadaTs);
    }

    return '—';
}

function painelEstudanteDataHoraPorStatus(string $statusRaw, string $dataAud, string $horaAud, string $inicioRaw, string $fimRaw): string
{
    $status = painelEstudanteStatusNormalizado($statusRaw);

    $designadaTs = strtotime(trim($dataAud . ' ' . $horaAud));
    $inicioTs = $inicioRaw !== '' ? strtotime($inicioRaw) : false;
    $fimTs = $fimRaw !== '' ? strtotime($fimRaw) : false;

    if (in_array($status, ['finalizada', 'realizada'], true)) {
        if ($inicioTs !== false && $fimTs !== false && $fimTs >= $inicioTs) {
            $duracaoMin = (int) floor(($fimTs - $inicioTs) / 60);

            return date('d/m/Y H:i', $inicioTs) . ' - ' . date('H:i', $fimTs) . ' (' . $duracaoMin . ' min)';
        }
        if ($inicioTs !== false) {
            return date('d/m/Y H:i', $inicioTs) . ' - --:-- (sem encerramento real)';
        }
        if ($fimTs !== false) {
            return date('d/m/Y', $fimTs) . ' --:-- - ' . date('H:i', $fimTs) . ' (sem início real)';
        }
        if ($designadaTs !== false) {
            return date('d/m/Y H:i', $designadaTs) . ' (sem horário real registrado)';
        }

        return 'Sem horário real registrado';
    }

    if ($status === 'em_andamento' && $inicioTs !== false) {
        return date('d/m/Y H:i', $inicioTs);
    }

    if ($status === 'atrasada') {
        return date('d/m/Y H:i', time() + (5 * 60));
    }

    if ($designadaTs !== false) {
        return date('d/m/Y H:i', $designadaTs);
    }

    return '—';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Painel em tempo real - Estudantes</title>
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
            <span class="all">Estudantes - Audiências -</span>
            <span class="specific">Painel em tempo real</span>
        </div>
        <h1 class="title-page">Painel em tempo real</h1>
        <p class="description-page">Área para inscrições e acompanhamento das suas participações em audiências.</p>
    </div>

    <div class="container-full card-page" id="card-audiencias-hoje-estudante">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Audiências de hoje</h2></div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <div id="timeline-hoje-painel-estudante" class="timeline-day-ruler row row-full"></div>
        </div>
        <?php if (count($hojePageRows) === 0): ?>
        <div class="padding-x-lg padding-y-lg">
            <p class="description-page">Não há audiências cadastradas para hoje.</p>
        </div>
        <?php endif; ?>
        <?php $processo_bloco_variant = 'estudantes'; ?>
        <?php $processo_bloco_model = 'full'; ?>
        <?php foreach ($hojePageRows as $row):
            $idPautaRaw = trim((string) ($row['id_pauta'] ?? ''));
            $idPauta = htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8');
            $pode = !empty($row['pode_inscrever']);
            $ja = !empty($row['ja_inscrito']);
            $lotado = !empty($row['vagas_esgotadas']);
            $sigiloso = !empty($row['processo_sigiloso']);
            $menor = !empty($row['envolve_menor']);
            $prioridadeBloqueio = !empty($row['tem_prioridade_bloqueio']);
            $totalIns = (int) ($row['total_inscritos_pauta'] ?? 0);
            $statusRow = trim((string) ($row['status'] ?? ''));
            $statusRow = painelEstudanteStatusExibicao($row);
            $labelsStatusAud = [
                'designada' => 'Aguardando início',
                'atrasada' => 'Atrasada',
                'em_andamento' => 'Em andamento',
                'finalizada' => 'Finalizada',
                'realizada' => 'Realizada',
                'redesignada' => 'Redesignada',
                'cancelada' => 'Cancelada',
            ];
            $textoStatusAud = $labelsStatusAud[$statusRow] ?? ucfirst(str_replace('_', ' ', $statusRow));
            $exibirStatusAoInvesDeInscricao = !in_array($statusRow, ['designada', 'atrasada'], true);
            $ocultarInfoInscritos = $exibirStatusAoInvesDeInscricao || $sigiloso || $menor || $prioridadeBloqueio;
            $linkAudienciaHoje = trim((string) ($row['link_audiencia'] ?? ''));
            if (filter_var($linkAudienciaHoje, FILTER_VALIDATE_URL) === false) {
                $linkAudienciaHoje = '';
            }
            $statusFinalHoje = painelEstudanteStatusFinal($statusRow);
            $linhaEmAndamentoHoje = $statusRow === 'em_andamento';
            $rotuloHoraHoje = $statusFinalHoje
                ? 'Finalizada em:'
                : ($statusRow === 'em_andamento' ? 'Iniciada às:' : 'Previsão:');
            $inicioEfetivoRaw = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
            $fimEfetivoRaw = trim((string) ($row['data_hora_fim_efetivo'] ?? ''));
            $horaExibicao = painelEstudanteHoraPorStatus(
                $statusRow,
                (string) ($row['data_audiencia'] ?? ''),
                (string) ($row['hora_audiencia'] ?? ''),
                $inicioEfetivoRaw,
                $fimEfetivoRaw,
                $previsoesAtrasadasPainel[$idPautaRaw] ?? null
            );
            if ($statusFinalHoje) {
                $horaExibicao = painelEstudanteHoraEncerramento($fimEfetivoRaw);
            }
            $duracaoExibicaoHoje = painelEstudanteDuracaoReal($inicioEfetivoRaw, $fimEfetivoRaw);
            $linkVisivelHoje = $linkAudienciaHoje !== '' && !$statusFinalHoje && !$sigiloso;
            ?>
        <div class="display-flex col col-full register register-painel-estudante border-bottom" data-pauta="<?php echo $idPauta; ?>">
            <div class="register-line<?php echo $statusFinalHoje ? ' is-inactive' : ''; ?><?php echo $linhaEmAndamentoHoje ? ' is-current' : ''; ?>">
                <div class="left">
                    <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                    <div class="container-processo container-sm painel-estudante-document-info">
                        <div class="container-line">
                            <div class="left">
                                <div class="line-content box-number">
                                    <span class="content-number major-info">AUDIENCIA DE <?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-assuntos audiencia-status">Status: <strong data-status-label-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($textoStatusAud, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                <span
                                    class="content-assuntos js-time-block audiencia-previsao"
                                    data-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($statusRow, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-data-audiencia="<?php echo htmlspecialchars((string) ($row['data_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-hora-audiencia="<?php echo htmlspecialchars((string) ($row['hora_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-inicio="<?php echo htmlspecialchars((string) ($row['data_hora_inicio_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-fim="<?php echo htmlspecialchars((string) ($row['data_hora_fim_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-duracao-estimada="<?php echo (int) ($row['duracao_estimada_minutos'] ?? 0); ?>"
                                >
                                    <span class="js-time-label"><?php echo htmlspecialchars($rotuloHoraHoje, ENT_QUOTES, 'UTF-8'); ?></span> <span class="js-time-lines"><strong><?php echo htmlspecialchars($horaExibicao, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                </span>
                                <span class="content-assuntos js-duration-block" data-duration-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $statusFinalHoje ? '' : ' style="display:none;"'; ?>>Duração: <strong><span class="js-duration-value"><?php echo htmlspecialchars($duracaoExibicaoHoje, ENT_QUOTES, 'UTF-8'); ?></span> min</strong></span>
                                <?php if ($linkVisivelHoje): ?>
                                <span class="content-polo js-audience-link-info" data-audience-link-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>">Link para acesso:</span>
                                <span class="content-partes js-audience-link-info" data-audience-link-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"><a href="<?php echo htmlspecialchars($linkAudienciaHoje, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">CLIQUE AQUI PARA ENTRAR NA SALA</a></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <div class="container-historical container-sm painel-estudante-inscricoes">
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-classe">Inscrições</span>
                                <div class="painel-estudante-inscricao-ui" data-inscricao-ui="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $exibirStatusAoInvesDeInscricao ? ' style="display:none;"' : ''; ?>>
                    <?php if ($sigiloso): ?>
                    <span class="content-assuntos danger">Não é permitida inscrição</span>
                    <span class="content-assuntos danger">para processos sigilosos</span>
                    <?php elseif ($lotado): ?>
                    <span class="content-assuntos danger">Não é permitida mais nenhuma inscrição</span>
                    <?php elseif ($ja): ?>
                    <span class="minor-info text-success">Voce esta inscrito.</span>
                    <?php elseif ($menor): ?>
                    <span class="minor-info text-error">Inscricao indisponivel.</span>
                    <?php elseif ($prioridadeBloqueio): ?>
                    <span class="minor-info text-error">Inscricao indisponivel.</span>
                    <?php elseif ($podeInscricaoComoEstudante && $pode): ?>
                    <button type="button" class="btn btn-primary btn-inscrever-ata" data-pauta="<?php echo $idPauta; ?>">Inscrever-me</button>
                    <?php elseif (!$podeInscricaoComoEstudante): ?>
                    <span class="minor-info">Inscricao exclusiva para estudantes.</span>
                    <?php else: ?>
                    <span class="minor-info">Inscricao indisponivel.</span>
                    <?php endif; ?>
                                </div>
                                <span class="content-assuntos painel-estudante-vagas" data-vagas-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>" data-max-vagas="<?php echo (int) $maxVagas; ?>"<?php echo $ocultarInfoInscritos ? ' style="display:none;"' : ''; ?>><span class="js-vagas-text"><?php echo $totalIns . ($totalIns === 1 ? ' pessoa inscrita' : ' pessoas inscritas') . ($lotado ? ' (lotado)' : ''); ?></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $hojeStartItem; ?>-<?php echo $hojeEndItem; ?> de <?php echo $hojeTotal; ?> <?php echo $hojeDocumentsLabel; ?>
                (<?php echo $hojeTotalPages; ?> <?php echo $hojePagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $hojeItemsLabel; ?> por página)
            </span>
            <?php if ($hojeTotalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevHoje = $hojePage > 1 ? $hojePage - 1 : 1;
                    $prevParamsHoje = array_merge($baseQueryPainel, ['hoje_page' => $prevHoje, 'minhas_page' => $minhasPage]);
                    $prevHrefHoje = '?' . http_build_query($prevParamsHoje);
                    $nextHoje = $hojePage < $hojeTotalPages ? $hojePage + 1 : $hojePage;
                    $nextParamsHoje = array_merge($baseQueryPainel, ['hoje_page' => $nextHoje, 'minhas_page' => $minhasPage]);
                    $nextHrefHoje = '?' . http_build_query($nextParamsHoje);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $hojePage <= 1 ? ' disabled' : ''; ?>" <?php if ($hojePage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHrefHoje, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                    <?php foreach ($hojePaginationButtons as $pBtn): ?>
                        <?php $pageParamsHoje = array_merge($baseQueryPainel, ['hoje_page' => $pBtn, 'minhas_page' => $minhasPage]); ?>
                        <button type="button" class="pagination-number<?php echo $pBtn === $hojePage ? ' active' : ''; ?>" <?php if ($pBtn !== $hojePage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParamsHoje), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                    <?php endforeach; ?>
                    <?php if ($hojePaginationEllipsis && !empty($hojeTrailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($hojeTrailingPages as $pBtn): ?>
                            <?php $pageParamsHoje = array_merge($baseQueryPainel, ['hoje_page' => $pBtn, 'minhas_page' => $minhasPage]); ?>
                            <button type="button" class="pagination-number<?php echo $pBtn === $hojePage ? ' active' : ''; ?>" <?php if ($pBtn !== $hojePage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParamsHoje), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $hojePage >= $hojeTotalPages ? ' disabled' : ''; ?>" <?php if ($hojePage < $hojeTotalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHrefHoje, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Minhas inscrições</h2></div>
        </div>

        <?php if (count($minhasPageRows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma inscrição encontrada.</p>
        </div>
        <?php endif; ?>
        <?php $processo_bloco_variant = 'estudantes'; ?>
        <?php $processo_bloco_model = 'full'; ?>
        <?php foreach ($minhasPageRows as $m):
            $idPautaMinhasRaw = trim((string) ($m['pauta'] ?? ''));
            $stRow = (string) ($m['status'] ?? '');
            $stRow = painelEstudanteStatusExibicao($m);
            $inicioEfetivoRaw = trim((string) ($m['data_hora_inicio_efetivo'] ?? ''));
            $fimEfetivoRaw = trim((string) ($m['data_hora_fim_efetivo'] ?? ''));
            $dataHoraMinhas = painelEstudanteDataHoraPorStatus(
                $stRow,
                (string) ($m['data_audiencia'] ?? ''),
                (string) ($m['hora_audiencia'] ?? ''),
                $inicioEfetivoRaw,
                $fimEfetivoRaw
            );
            $statusFinalMinhas = painelEstudanteStatusFinal($stRow);
            $linhaEmAndamentoMinhas = $stRow === 'em_andamento';
            $rotuloHoraMinhas = $statusFinalMinhas
                ? 'Finalizada em:'
                : ($stRow === 'em_andamento' ? 'Iniciada às:' : 'Data e hora:');
            if ($statusFinalMinhas) {
                $dataHoraMinhas = painelEstudanteHoraEncerramento($fimEfetivoRaw, true);
            }
            $duracaoExibicaoMinhas = painelEstudanteDuracaoReal($inicioEfetivoRaw, $fimEfetivoRaw);
            $labelsStatusEst = [
                'designada' => 'Aguardando início',
                'atrasada' => 'Atrasada',
                'em_andamento' => 'Em andamento',
                'finalizada' => 'Finalizada',
                'realizada' => 'Realizada',
                'redesignada' => 'Redesignada',
                'cancelada' => 'Cancelada',
            ];
            $textoStatusEst = $labelsStatusEst[$stRow] ?? ucfirst(str_replace('_', ' ', $stRow));
            $classeStatusEst = 'status-pending';
            if ($stRow === 'cancelada' || $stRow === 'redesignada') {
                $classeStatusEst = 'status-cancelled';
            } elseif ($stRow === 'em_andamento') {
                $classeStatusEst = 'status-current';
            } elseif (in_array($stRow, ['realizada', 'finalizada'], true)) {
                $classeStatusEst = 'status-finished';
            }

            $row = $m;
            $linkAudienciaMinhas = trim((string) ($m['link_audiencia'] ?? ''));
            if (filter_var($linkAudienciaMinhas, FILTER_VALIDATE_URL) === false) {
                $linkAudienciaMinhas = '';
            }
            $linkVisivelMinhas = $linkAudienciaMinhas !== '' && !$statusFinalMinhas;
            ?>
        <div class="display-flex col col-full register register-painel-estudante border-bottom" data-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="register-line<?php echo $statusFinalMinhas ? ' is-inactive' : ''; ?><?php echo $linhaEmAndamentoMinhas ? ' is-current' : ''; ?>">
                <div class="left">
                    <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                    <div class="container-processo container-sm painel-estudante-document-info">
                        <div class="container-line">
                            <div class="left">
                                <div class="line-content box-number">
                                    <span class="content-number major-info">AUDIENCIA DE <?php echo htmlspecialchars((string) $m['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-assuntos audiencia-status">Status: <strong data-status-label-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($textoStatusEst, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                <span
                                    class="content-assuntos js-time-block audiencia-previsao"
                                    data-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($stRow, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-include-date="1"
                                    data-data-audiencia="<?php echo htmlspecialchars((string) ($m['data_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-hora-audiencia="<?php echo htmlspecialchars((string) ($m['hora_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-inicio="<?php echo htmlspecialchars((string) ($m['data_hora_inicio_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-fim="<?php echo htmlspecialchars((string) ($m['data_hora_fim_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-duracao-estimada="<?php echo (int) ($m['duracao_estimada_minutos'] ?? 0); ?>"
                                >
                                    <span class="js-time-label"><?php echo htmlspecialchars($rotuloHoraMinhas, ENT_QUOTES, 'UTF-8'); ?></span> <span class="js-time-lines"><strong><?php echo htmlspecialchars($dataHoraMinhas, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                </span>
                                <span class="content-assuntos js-duration-block" data-duration-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $statusFinalMinhas ? '' : ' style="display:none;"'; ?>>Duração: <strong><span class="js-duration-value"><?php echo htmlspecialchars($duracaoExibicaoMinhas, ENT_QUOTES, 'UTF-8'); ?></span> min</strong></span>
                                <?php if ($linkVisivelMinhas): ?>
                                <span class="content-polo js-audience-link-info" data-audience-link-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>">Link para acesso:</span>
                                <span class="content-partes js-audience-link-info" data-audience-link-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>"><a href="<?php echo htmlspecialchars($linkAudienciaMinhas, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">CLIQUE AQUI PARA ENTRAR NA SALA</a></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right">
                    <div class="container-historical container-sm painel-estudante-inscricoes">
                        <div class="container-line">
                            <div class="line-content double-line">
                                <span class="content-classe">Inscrições</span>
                                <div class="painel-estudante-inscricao-ui">
                                    <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($classeStatusEst, ENT_QUOTES); ?>" data-status-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($textoStatusEst, ENT_QUOTES); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">
                Mostrando <?php echo $minhasStartItem; ?>-<?php echo $minhasEndItem; ?> de <?php echo $minhasTotal; ?> <?php echo $minhasDocumentsLabel; ?>
                (<?php echo $minhasTotalPages; ?> <?php echo $minhasPagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $minhasItemsLabel; ?> por página)
            </span>
            <?php if ($minhasTotalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevMinhas = $minhasPage > 1 ? $minhasPage - 1 : 1;
                    $prevParamsMinhas = array_merge($baseQueryPainel, ['hoje_page' => $hojePage, 'minhas_page' => $prevMinhas]);
                    $prevHrefMinhas = '?' . http_build_query($prevParamsMinhas);
                    $nextMinhas = $minhasPage < $minhasTotalPages ? $minhasPage + 1 : $minhasPage;
                    $nextParamsMinhas = array_merge($baseQueryPainel, ['hoje_page' => $hojePage, 'minhas_page' => $nextMinhas]);
                    $nextHrefMinhas = '?' . http_build_query($nextParamsMinhas);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $minhasPage <= 1 ? ' disabled' : ''; ?>" <?php if ($minhasPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHrefMinhas, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                    <?php foreach ($minhasPaginationButtons as $pBtn): ?>
                        <?php $pageParamsMinhas = array_merge($baseQueryPainel, ['hoje_page' => $hojePage, 'minhas_page' => $pBtn]); ?>
                        <button type="button" class="pagination-number<?php echo $pBtn === $minhasPage ? ' active' : ''; ?>" <?php if ($pBtn !== $minhasPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParamsMinhas), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                    <?php endforeach; ?>
                    <?php if ($minhasPaginationEllipsis && !empty($minhasTrailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($minhasTrailingPages as $pBtn): ?>
                            <?php $pageParamsMinhas = array_merge($baseQueryPainel, ['hoje_page' => $hojePage, 'minhas_page' => $pBtn]); ?>
                            <button type="button" class="pagination-number<?php echo $pBtn === $minhasPage ? ' active' : ''; ?>" <?php if ($pBtn !== $minhasPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParamsMinhas), ENT_QUOTES); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $pBtn; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $minhasPage >= $minhasTotalPages ? ' disabled' : ''; ?>" <?php if ($minhasPage < $minhasTotalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHrefMinhas, ENT_QUOTES); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
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

    <script src="/assets/js/modules/audiencias/atas-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/atas-estudantes.js') ?: time(); ?>"></script>
    <script src="/assets/js/shared/timeline-day-ruler.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/timeline-day-ruler.js') ?: time(); ?>"></script>
    <script>
    (function () {
        function pad2(n) { return n < 10 ? '0' + n : String(n); }
        function parseDateTime(v) {
            var s = String(v || '').trim();
            if (!s) return NaN;
            var m = s.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
            if (!m) return NaN;
            var y = Number(m[1]);
            var mo = Number(m[2]) - 1;
            var d = Number(m[3]);
            var hh = Number(m[4] || '0');
            var mm = Number(m[5] || '0');
            var ss = Number(m[6] || '0');
            var dt = new Date(y, mo, d, hh, mm, ss, 0);
            var t = dt.getTime();
            return isNaN(t) ? NaN : t;
        }
        function fmtTime(ts) {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        }
        function fmtDate(ts) {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear();
        }
        function fmtDateTime(ts) {
            var dt = fmtDate(ts);
            var tm = fmtTime(ts);
            if (!dt || !tm) return dt || tm;
            return dt + ' ' + tm;
        }
        function toMinuteValue(ts) {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return NaN;
            return d.getHours() * 60 + d.getMinutes() + (d.getSeconds() / 60);
        }
        function getFramePhpHref() {
            var path = window.location.pathname || '/frame.php';
            var slash = path.lastIndexOf('/');
            var dir = slash >= 0 ? path.slice(0, slash + 1) : '/';
            return dir + 'frame.php';
        }
        function bindItemsPerPage(id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                var n = String(el.value || '25').trim();
                var params = new URLSearchParams();
                params.set('page', 'estudapainel');
                params.set('hoje_page', '1');
                params.set('minhas_page', '1');
                params.set('items_per_page', n);
                window.location.href = getFramePhpHref() + '?' + params.toString();
            });
        }
        function scheduledTimestamp(item) {
            var dataAud = String(item && item.data_audiencia || '').trim();
            var horaAud = String(item && item.hora_audiencia || '').trim();
            return parseDateTime((dataAud + ' ' + horaAud).trim());
        }
        function statusForItem(item) {
            var status = normalizeStatus(item && item.status);
            if (status === 'designada') {
                var scheduledTs = scheduledTimestamp(item);
                if (!isNaN(scheduledTs) && scheduledTs < Date.now()) return 'atrasada';
            }
            return status;
        }
        function ongoingTimestamp(item) {
            var scheduledTs = scheduledTimestamp(item);
            if (!isNaN(scheduledTs)) return scheduledTs;
            return parseDateTime(String(item && item.data_hora_inicio_efetivo || '').trim());
        }
        function isDelayedNextForecast(item, allItems, nowTs) {
            var status = statusForItem(item);
            var targetTs = scheduledTimestamp(item);
            if ((status !== 'designada' && status !== 'atrasada') || isNaN(targetTs) || targetTs >= nowTs) return false;

            var ids = Object.keys(allItems || {});
            return ids.some(function (id) {
                var ongoing = allItems[id] || {};
                if (statusForItem(ongoing) !== 'em_andamento') return false;
                var ongoingTs = ongoingTimestamp(ongoing);
                if (isNaN(ongoingTs) || ongoingTs >= targetTs) return false;

                var nextTs = NaN;
                ids.forEach(function (candidateId) {
                    var candidate = allItems[candidateId] || {};
                    var candidateStatus = statusForItem(candidate);
                    if (candidateStatus !== 'designada' && candidateStatus !== 'atrasada') return;
                    var candidateTs = scheduledTimestamp(candidate);
                    if (isNaN(candidateTs) || candidateTs <= ongoingTs || candidateTs >= nowTs) return;
                    if (isNaN(nextTs) || candidateTs < nextTs) nextTs = candidateTs;
                });
                return !isNaN(nextTs) && nextTs === targetTs;
            });
        }
        function forecastTimestamp(item, allItems, includeDate) {
            var scheduledTs = scheduledTimestamp(item);
            if (isNaN(scheduledTs)) return scheduledTs;
            var nowTs = Date.now();
            if (statusForItem(item) === 'atrasada') {
                return nowTs + (5 * 60 * 1000);
            }
            if (!includeDate && isDelayedNextForecast(item, allItems, nowTs)) {
                return nowTs + (5 * 60 * 1000);
            }
            return scheduledTs;
        }
        function isTerminalStatus(statusRaw) {
            var status = normalizeStatus(statusRaw);
            return status === 'cancelada'
                || status === 'redesignada'
                || status === 'finalizada'
                || status === 'realizada';
        }
        function renderAudienceLinkState(pauta, terminal) {
            document.querySelectorAll('[data-audience-link-pauta]').forEach(function (linkEl) {
                if (String(linkEl.getAttribute('data-audience-link-pauta') || '').trim() !== pauta) return;
                linkEl.style.display = terminal ? 'none' : '';
            });
        }
        function renderTimeBlock(block, item, allItems) {
            var lines = block.querySelector('.js-time-lines');
            if (!lines) return;
            var includeDate = String(block.getAttribute('data-include-date') || '') === '1';
            var status = statusForItem(item);
            var inicioRaw = String(item.data_hora_inicio_efetivo || '').trim();
            var fimRaw = String(item.data_hora_fim_efetivo || '').trim();
            var inicioTs = parseDateTime(inicioRaw);
            var fimTs = parseDateTime(fimRaw);
            var designadaTs = forecastTimestamp(item, allItems, includeDate);
            var finalizada = isTerminalStatus(status);
            var encerramentoLbl = includeDate ? fmtDateTime(fimTs) : fmtTime(fimTs);
            var horarioLbl = finalizada
                ? (encerramentoLbl || '—')
                : (status === 'em_andamento'
                    ? (includeDate ? fmtDateTime(inicioTs) : fmtTime(inicioTs))
                    : (includeDate ? fmtDateTime(designadaTs) : fmtTime(designadaTs)));
            var labelEl = block.querySelector('.js-time-label');
            if (labelEl) {
                labelEl.textContent = finalizada
                    ? 'Finalizada em:'
                    : (status === 'em_andamento' ? 'Iniciada às:' : (includeDate ? 'Data e hora:' : 'Previsão:'));
            }
            var pauta = String(block.getAttribute('data-pauta') || '').trim();
            renderAudienceLinkState(pauta, finalizada);
            var durationBlock = block.parentElement ? block.parentElement.querySelector('[data-duration-pauta="' + pauta.replace(/"/g, '\\"') + '"]') : null;
            if (durationBlock) {
                durationBlock.style.display = finalizada ? '' : 'none';
                var durationValue = durationBlock.querySelector('.js-duration-value');
                if (durationValue) {
                    var durationMin = (!isNaN(inicioTs) && !isNaN(fimTs) && fimTs >= inicioTs)
                        ? Math.floor((fimTs - inicioTs) / 60000)
                        : '—';
                    durationValue.textContent = String(durationMin);
                }
            }
            var inicioLbl = includeDate ? fmtDateTime(inicioTs) : fmtTime(inicioTs);
            var designadaLbl = includeDate ? fmtDateTime(designadaTs) : fmtTime(designadaTs);
            var html = '';
            if (finalizada) {
                html += '<span class="major-info">' + escapeHtml(horarioLbl) + '</span>';
            } else if (status === 'em_andamento' && !isNaN(inicioTs)) {
                html += '<span class="major-info">' + inicioLbl + '</span>';
            } else if (!isNaN(designadaTs)) {
                html += '<span class="major-info">' + designadaLbl + '</span>';
            } else {
                html += '<span class="major-info">—</span>';
            }
            lines.innerHTML = html;
        }
        function statusClass(status) {
            if (status === 'cancelada' || status === 'redesignada') return 'status-cancelled';
            if (status === 'em_andamento') return 'status-current';
            if (status === 'realizada' || status === 'finalizada') return 'status-finished';
            return 'status-pending';
        }
        function statusLabel(status) {
            var map = {
                designada: 'Aguardando início',
                atrasada: 'Atrasada',
                em_andamento: 'Em andamento',
                finalizada: 'Finalizada',
                realizada: 'Realizada',
                redesignada: 'Redesignada',
                cancelada: 'Cancelada'
            };
            return map[status] || status;
        }
        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        function renderInscricaoUI(wrapper, item) {
            var lotado = !!item.vagas_esgotadas;
            var ja = !!item.ja_inscrito;
            var sigiloso = !!item.processo_sigiloso;
            var menor = !!item.envolve_menor;
            var prioridadeBloqueio = !!item.tem_prioridade_bloqueio;
            var pode = !!item.pode_inscrever;
            var pauta = String(item.id_pauta || wrapper.getAttribute('data-inscricao-ui') || '').trim();
            var status = statusForItem(item);
            if (status && status !== 'designada' && status !== 'atrasada') {
                wrapper.style.display = 'none';
                return;
            }
            wrapper.style.display = '';
            if (sigiloso) {
                wrapper.innerHTML = '<span class="content-assuntos danger">Não é permitida inscrição</span><span class="content-assuntos danger">para processos sigilosos</span>';
            } else if (lotado) {
                wrapper.innerHTML = '<span class="content-assuntos danger">Não é permitida mais nenhuma inscrição</span>';
            } else if (ja) {
                wrapper.innerHTML = '<span class="minor-info text-success">Voce esta inscrito.</span>';
            } else if (menor) {
                wrapper.innerHTML = '<span class="minor-info text-error">Proibida inscrição de estudantes.</span>';
            } else if (prioridadeBloqueio) {
                wrapper.innerHTML = '<span class="minor-info text-error">Inscricao indisponivel.</span>';
            } else if (pode && pauta) {
                wrapper.innerHTML = '<button type="button" class="btn btn-primary btn-inscrever-ata" data-pauta="' + escapeHtml(pauta) + '">Inscrever-me</button>';
            } else {
                wrapper.innerHTML = '<span class="minor-info">Inscricao indisponivel.</span>';
            }
        }
        function renderVagas(item) {
            var pauta = String(item.id_pauta || '').trim();
            if (!pauta) return;
            var wrap = null;
            document.querySelectorAll('[data-vagas-pauta]').forEach(function (el) {
                if (wrap) return;
                if (String(el.getAttribute('data-vagas-pauta') || '').trim() === pauta) {
                    wrap = el;
                }
            });
            if (!wrap) return;
            var status = statusForItem(item);
            var indisponivel = (status && status !== 'designada' && status !== 'atrasada')
                || !!item.processo_sigiloso
                || !!item.envolve_menor
                || !!item.tem_prioridade_bloqueio;
            if (indisponivel) {
                wrap.style.display = 'none';
                return;
            }
            wrap.style.display = '';
            var text = wrap.querySelector('.js-vagas-text');
            if (!text) return;
            var total = parseInt(item.total_inscritos_pauta || 0, 10);
            if (!Number.isFinite(total) || total < 0) total = 0;
            var suffix = item.vagas_esgotadas ? ' (lotado)' : '';
            text.textContent = total + (total === 1 ? ' pessoa inscrita' : ' pessoas inscritas') + suffix;
        }
        function collectPautas() {
            var ids = {};
            document.querySelectorAll('.js-time-block[data-pauta]').forEach(function (el) {
                var id = String(el.getAttribute('data-pauta') || '').trim();
                if (id) ids[id] = true;
            });
            return Object.keys(ids);
        }
        function updateRegisterLineState(pauta, statusRaw) {
            var terminal = isTerminalStatus(statusRaw);
            document.querySelectorAll('.register.register-painel-estudante[data-pauta]').forEach(function (register) {
                if (String(register.getAttribute('data-pauta') || '').trim() !== pauta) return;
                var line = register.querySelector('.register-line');
                if (line) {
                    line.classList.toggle('is-inactive', terminal);
                    line.classList.toggle('is-current', normalizeStatus(statusRaw) === 'em_andamento');
                }
            });
        }
        function applyUpdates(items) {
            renderVisibleTimeBlocks(latestItemsById);
            document.querySelectorAll('[data-status-label-pauta]').forEach(function (labelEl) {
                var id = String(labelEl.getAttribute('data-status-label-pauta') || '').trim();
                if (!id || !items[id]) return;
                labelEl.textContent = statusLabel(statusForItem(items[id]));
            });
            document.querySelectorAll('[data-status-pauta]').forEach(function (statusEl) {
                var id = String(statusEl.getAttribute('data-status-pauta') || '').trim();
                if (!id || !items[id]) return;
                var status = statusForItem(items[id]);
                statusEl.className = 'width-300 padding-x-lg status ' + statusClass(status);
                statusEl.textContent = statusLabel(status);
                var ataEl = document.querySelector('[data-ata-info="' + id.replace(/"/g, '\\"') + '"]');
                if (ataEl) {
                    var finalizada = (status === 'finalizada' || status === 'realizada');
                    statusEl.style.display = finalizada ? 'none' : '';
                    ataEl.style.display = finalizada ? '' : 'none';
                }
            });
            document.querySelectorAll('[data-inscricao-ui]').forEach(function (uiWrap) {
                var id = String(uiWrap.getAttribute('data-inscricao-ui') || '').trim();
                if (!id || !items[id]) return;
                renderInscricaoUI(uiWrap, items[id]);
            });
            Object.keys(items).forEach(function (id) {
                if (!items[id]) return;
                updateRegisterLineState(id, items[id].status);
                renderVagas(items[id]);
            });
            renderTimeline(items);
        }
        function normalizeStatus(statusRaw) {
            var s = String(statusRaw || '').trim().toLowerCase();
            if (s === 'agendada') return 'designada';
            if (s === 'adiada') return 'redesignada';
            if (s === 'encerrada') return 'finalizada';
            return s;
        }
        function classByStatus(statusRaw) {
            var st = normalizeStatus(statusRaw);
            if (st === 'em_andamento') return 'is-warning';
            if (st === 'finalizada' || st === 'realizada') return 'is-success';
            if (st === 'cancelada' || st === 'redesignada') return 'is-danger';
            return 'is-warning';
        }
        function isFinishedStatus(statusRaw) {
            var st = normalizeStatus(statusRaw);
            return isTerminalStatus(st);
        }
        function allPautasFinished(itemsMap) {
            var ids = Object.keys(itemsMap || {});
            if (!ids.length) return false;
            return ids.every(function (id) {
                return isFinishedStatus(itemsMap[id] && itemsMap[id].status);
            });
        }
        function buildCurrentItemsFromDom() {
            var out = {};
            document.querySelectorAll('.js-time-block[data-pauta]').forEach(function (block) {
                var id = String(block.getAttribute('data-pauta') || '').trim();
                if (!id) return;
                out[id] = {
                    id_pauta: id,
                    status: String(block.getAttribute('data-status') || '').trim(),
                    data_audiencia: block.getAttribute('data-data-audiencia') || '',
                    hora_audiencia: block.getAttribute('data-hora-audiencia') || '',
                    data_hora_inicio_efetivo: block.getAttribute('data-inicio') || '',
                    data_hora_fim_efetivo: block.getAttribute('data-fim') || '',
                    duracao_estimada_minutos: block.getAttribute('data-duracao-estimada') || '0'
                };
            });
            return out;
        }
        function renderVisibleTimeBlocks(itemsMap) {
            document.querySelectorAll('.js-time-block[data-pauta]').forEach(function (block) {
                var id = String(block.getAttribute('data-pauta') || '').trim();
                if (!id) return;
                var item = (itemsMap && itemsMap[id]) ? itemsMap[id] : {
                    status: block.getAttribute('data-status') || '',
                    data_audiencia: block.getAttribute('data-data-audiencia') || '',
                    hora_audiencia: block.getAttribute('data-hora-audiencia') || '',
                    data_hora_inicio_efetivo: block.getAttribute('data-inicio') || '',
                    data_hora_fim_efetivo: block.getAttribute('data-fim') || ''
                };
                renderTimeBlock(block, item, itemsMap || {});
            });
        }
        function buildTimelineItems(overrides) {
            var root = document.getElementById('timeline-hoje-painel-estudante');
            if (!root) return [];
            var container = document.getElementById('card-audiencias-hoje-estudante');
            if (!container) return [];
            var out = [];
            container.querySelectorAll('.js-time-block[data-pauta]').forEach(function (block) {
                var pautaId = String(block.getAttribute('data-pauta') || '').trim();
                var item = (overrides && pautaId && overrides[pautaId]) ? overrides[pautaId] : {
                    data_audiencia: block.getAttribute('data-data-audiencia') || '',
                    hora_audiencia: block.getAttribute('data-hora-audiencia') || '',
                    status: block.getAttribute('data-status') || '',
                    data_hora_inicio_efetivo: block.getAttribute('data-inicio') || '',
                    data_hora_fim_efetivo: block.getAttribute('data-fim') || '',
                    duracao_estimada_minutos: block.getAttribute('data-duracao-estimada') || '0'
                };
                out.push({
                    id: pautaId,
                    data_audiencia: item.data_audiencia || '',
                    hora_audiencia: item.hora_audiencia || '',
                    status: item.status || '',
                    data_hora_inicio_efetivo: item.data_hora_inicio_efetivo || '',
                    data_hora_fim_efetivo: item.data_hora_fim_efetivo || '',
                    duracao_estimada_minutos: item.duracao_estimada_minutos || 0
                });
            });
            return out;
        }
        function renderTimeline(overrides) {
            var root = document.getElementById('timeline-hoje-painel-estudante');
            if (!root || !window.TimelineDayRuler) return;
            window.TimelineDayRuler.render(root, buildTimelineItems(overrides), { studentPanel: true });
        }
        function refreshStatusAndTime() {
            var pautas = collectPautas();
            if (!pautas.length) return;
            if (allPautasFinished(latestItemsById)) return;
            fetch('/api/sala-audiencias-pauta/status-bulk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ pautas: pautas })
            })
                .then(function (r) {
                    return r.json().then(function (j) {
                        return { ok: r.ok, body: j || {} };
                    });
                })
                .then(function (res) {
                    if (!res.ok || !res.body.success || !res.body.items) return;
                    Object.keys(res.body.items).forEach(function (id) {
                        latestItemsById[id] = Object.assign({}, latestItemsById[id] || {}, res.body.items[id]);
                    });
                    applyUpdates(res.body.items);
                })
                .catch(function () { /* erro silencioso de polling */ });
        }
        var latestItemsById = buildCurrentItemsFromDom();
        var pollTimer = null;
        function tick() {
            // Atualiza marcador "agora" mesmo sem mudança de dados.
            renderVisibleTimeBlocks(latestItemsById);
            renderTimeline(latestItemsById);
            if (allPautasFinished(latestItemsById)) {
                if (pollTimer !== null) {
                    window.clearInterval(pollTimer);
                    pollTimer = null;
                }
                return;
            }
            // Atualiza dados reais sem recarregar a página.
            refreshStatusAndTime();
        }
        bindItemsPerPage('items-per-page-hoje');
        bindItemsPerPage('items-per-page-minhas');
        renderVisibleTimeBlocks(latestItemsById);
        renderTimeline(latestItemsById);
        refreshStatusAndTime();
        pollTimer = window.setInterval(tick, 60000);
    })();
    </script>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</body>
</html>
