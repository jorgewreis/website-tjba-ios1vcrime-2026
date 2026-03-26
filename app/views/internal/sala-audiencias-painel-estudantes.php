<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

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

$service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
$hojeRows = $userId !== '' ? $service->listHojeParaInscricaoEstudante($userId) : [];
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
$maxVagas = SalaAudienciasAtaService::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$hojePage = max(1, (int) ($_GET['hoje_page'] ?? 1));
$minhasPage = max(1, (int) ($_GET['minhas_page'] ?? 1));

$hojeTotal = count($hojeRows);
$hojeTotalPages = max(1, (int) ceil(max(1, $hojeTotal) / $itemsPerPage));
if ($hojePage > $hojeTotalPages) {
    $hojePage = $hojeTotalPages;
}
$hojeOffset = ($hojePage - 1) * $itemsPerPage;
$hojePageRows = array_slice($hojeRows, $hojeOffset, $itemsPerPage);
$hojeStartItem = $hojeTotal > 0 ? $hojeOffset + 1 : 0;
$hojeEndItem = min($hojeOffset + count($hojePageRows), $hojeTotal);

$minhasTotal = count($minhasRows);
$minhasTotalPages = max(1, (int) ceil(max(1, $minhasTotal) / $itemsPerPage));
if ($minhasPage > $minhasTotalPages) {
    $minhasPage = $minhasTotalPages;
}
$minhasOffset = ($minhasPage - 1) * $itemsPerPage;
$minhasPageRows = array_slice($minhasRows, $minhasOffset, $itemsPerPage);
$minhasStartItem = $minhasTotal > 0 ? $minhasOffset + 1 : 0;
$minhasEndItem = min($minhasOffset + count($minhasPageRows), $minhasTotal);
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
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
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

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Audiências de hoje</h2></div>
        </div>
        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page-hoje">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page-hoje">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        <?php if (count($hojePageRows) === 0): ?>
        <div class="padding-x-lg padding-y-lg">
            <p class="description-page">Não há audiências designadas para inscrição hoje ou você já está inscrito em todas as opções disponíveis.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($hojePageRows as $row):
            $idPautaRaw = trim((string) ($row['id_pauta'] ?? ''));
            $idPauta = htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8');
            $pode = !empty($row['pode_inscrever']);
            $ja = !empty($row['ja_inscrito']);
            $lotado = !empty($row['vagas_esgotadas']);
            $sigiloso = !empty($row['processo_sigiloso']);
            $menor = !empty($row['envolve_menor']);
            $totalIns = (int) ($row['total_inscritos_pauta'] ?? 0);
            $statusRow = trim((string) ($row['status'] ?? ''));
            if ($statusRow === 'agendada') {
                $statusRow = 'designada';
            } elseif ($statusRow === 'adiada') {
                $statusRow = 'redesignada';
            }
            $inicioEfetivoRaw = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
            $fimEfetivoRaw = trim((string) ($row['data_hora_fim_efetivo'] ?? ''));
            $inicioTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            if ($inicioTs === false) {
                $inicioFallback = trim((string) ($row['data_audiencia'] ?? '')) . ' ' . trim((string) ($row['hora_audiencia'] ?? ''));
                $inicioTs = strtotime($inicioFallback);
            }
            $duracaoEstimada = max(5, (int) ($row['duracao_estimada_minutos'] ?? 0));
            $previsaoTs = $inicioTs !== false ? ($inicioTs + ($duracaoEstimada * 60)) : false;
            $fimTs = $fimEfetivoRaw !== '' ? strtotime($fimEfetivoRaw) : false;
            $duracaoRealMin = ($inicioTs !== false && $fimTs !== false && $fimTs >= $inicioTs)
                ? (int) floor(($fimTs - $inicioTs) / 60)
                : $duracaoEstimada;
            ?>
        <div class="display-flex col col-full register border-bottom" data-pauta="<?php echo $idPauta; ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div
                        class="col width-500 js-time-block"
                        data-pauta="<?php echo htmlspecialchars($idPautaRaw, ENT_QUOTES, 'UTF-8'); ?>"
                        data-status="<?php echo htmlspecialchars($statusRow, ENT_QUOTES, 'UTF-8'); ?>"
                        data-data-audiencia="<?php echo htmlspecialchars((string) ($row['data_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-hora-audiencia="<?php echo htmlspecialchars((string) ($row['hora_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-inicio="<?php echo htmlspecialchars((string) ($row['data_hora_inicio_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-fim="<?php echo htmlspecialchars((string) ($row['data_hora_fim_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-duracao-estimada="<?php echo (int) ($row['duracao_estimada_minutos'] ?? 0); ?>"
                    >
                        <span class="minor-info">Data e hora</span>
                        <div class="js-time-lines">
                        <?php if ($statusRow === 'em_andamento' && $inicioTs !== false): ?>
                        <span class="major-info">Inicio: <?php echo htmlspecialchars(date('d/m/Y H:i', $inicioTs), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($previsaoTs !== false): ?>
                        <span class="minor-info">Previsao de termino: <?php echo htmlspecialchars(date('d/m/Y H:i', $previsaoTs), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php elseif ($statusRow === 'finalizada' && $inicioTs !== false && $fimTs !== false): ?>
                        <span class="major-info">Inicio: <?php echo htmlspecialchars(date('d/m/Y H:i', $inicioTs), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="minor-info">Fim: <?php echo htmlspecialchars(date('d/m/Y H:i', $fimTs), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $duracaoRealMin; ?> min)</span>
                        <?php else: ?>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . substr((string) $row['hora_audiencia'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        </div>
                    </div>
                    <div class="col width-400 col-process process-info" title="clique para copiar">
                        <?php
                            $classeAudiencia = !empty($row['classe_nome']) ? $row['classe_nome'] : 'Classe não informada';
                            $classeStyleAudiencia = $classeAudiencia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info text-ellipsis" <?php echo $classeStyleAudiencia; ?>><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-400">
                        <span class="minor-info">Tipo</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-300">
                        <span class="minor-info">Vagas</span>
                        <span class="major-info"><?php echo $totalIns; ?> / <?php echo (int) $maxVagas; ?><?php echo $lotado ? ' (lotado)' : ''; ?></span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <?php if ($ja): ?>
                    <span class="minor-info text-success">Voce esta inscrito.</span>
                    <?php elseif ($lotado): ?>
                    <span class="minor-info text-error">Vagas esgotadas.</span>
                    <?php elseif ($sigiloso): ?>
                    <span class="minor-info text-error">Processo sigiloso.</span>
                    <?php elseif ($menor): ?>
                    <span class="minor-info text-error">Audiencia com menor envolvido.</span>
                    <?php elseif ($podeInscricaoComoEstudante && $pode): ?>
                    <button type="button" class="btn btn-primary btn-inscrever-ata" data-pauta="<?php echo $idPauta; ?>">Inscrever-me</button>
                    <?php elseif (!$podeInscricaoComoEstudante): ?>
                    <span class="minor-info">Inscricao exclusiva para estudantes.</span>
                    <?php else: ?>
                    <span class="minor-info">Inscricao indisponivel.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-lg">
            <span class="pagination-info">Mostrando <?php echo $hojeStartItem; ?>-<?php echo $hojeEndItem; ?> de <?php echo $hojeTotal; ?> audiências (<?php echo $hojeTotalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)</span>
            <div class="pagination-controls">
                <?php $prevHoje = max(1, $hojePage - 1); $nextHoje = min($hojeTotalPages, $hojePage + 1); ?>
                <?php
                $qPrevHoje = $hojePage > 1 ? '?page=estudapainel&hoje_page=' . $prevHoje . '&minhas_page=' . $minhasPage . '&items_per_page=' . $itemsPerPage : '';
                $qNextHoje = $hojePage < $hojeTotalPages ? '?page=estudapainel&hoje_page=' . $nextHoje . '&minhas_page=' . $minhasPage . '&items_per_page=' . $itemsPerPage : '';
                ?>
                <button type="button" class="pagination-symbol<?php echo $hojePage <= 1 ? ' disabled' : ''; ?>" <?php echo $qPrevHoje !== '' ? 'data-nav-href="' . htmlspecialchars($qPrevHoje, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                <button type="button" class="pagination-number active" disabled><?php echo $hojePage; ?></button>
                <button type="button" class="pagination-symbol<?php echo $hojePage >= $hojeTotalPages ? ' disabled' : ''; ?>" <?php echo $qNextHoje !== '' ? 'data-nav-href="' . htmlspecialchars($qNextHoje, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
            </div>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Minhas inscrições</h2></div>
        </div>
        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="col">
                    <label for="items-per-page-minhas">Itens por página</label>
                    <select class="width-300 height-md" id="items-per-page-minhas">
                        <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                        <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                        <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                        <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>
        </div>
        <?php if (count($minhasPageRows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma inscrição encontrada.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($minhasPageRows as $m):
            $idPautaMinhasRaw = trim((string) ($m['pauta'] ?? ''));
            $stRow = (string) ($m['status'] ?? '');
            if ($stRow === 'agendada') {
                $stRow = 'designada';
            } elseif ($stRow === 'adiada') {
                $stRow = 'redesignada';
            }
            $inicioEfetivoRaw = trim((string) ($m['data_hora_inicio_efetivo'] ?? ''));
            $fimEfetivoRaw = trim((string) ($m['data_hora_fim_efetivo'] ?? ''));
            $inicioTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            if ($inicioTs === false) {
                $inicioFallback = trim((string) ($m['data_audiencia'] ?? '')) . ' ' . trim((string) ($m['hora_audiencia'] ?? ''));
                $inicioTs = strtotime($inicioFallback);
            }
            $duracaoEstimada = max(5, (int) ($m['duracao_estimada_minutos'] ?? 0));
            $previsaoTs = $inicioTs !== false ? ($inicioTs + ($duracaoEstimada * 60)) : false;
            $fimTs = $fimEfetivoRaw !== '' ? strtotime($fimEfetivoRaw) : false;
            $duracaoRealMin = ($inicioTs !== false && $fimTs !== false && $fimTs >= $inicioTs)
                ? (int) floor(($fimTs - $inicioTs) / 60)
                : $duracaoEstimada;
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
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-bottom align-center flex-wrap gap-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                <div
                    class="col width-300 js-time-block"
                    data-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>"
                    data-status="<?php echo htmlspecialchars($stRow, ENT_QUOTES, 'UTF-8'); ?>"
                    data-data-audiencia="<?php echo htmlspecialchars((string) ($m['data_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-hora-audiencia="<?php echo htmlspecialchars((string) ($m['hora_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-inicio="<?php echo htmlspecialchars((string) ($m['data_hora_inicio_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-fim="<?php echo htmlspecialchars((string) ($m['data_hora_fim_efetivo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                    data-duracao-estimada="<?php echo (int) ($m['duracao_estimada_minutos'] ?? 0); ?>"
                >
                    <span class="minor-info">Data e hora</span>
                    <div class="js-time-lines">
                    <?php if ($stRow === 'em_andamento' && $inicioTs !== false): ?>
                    <span class="major-info">Inicio: <?php echo htmlspecialchars(date('d/m/Y H:i', $inicioTs), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($previsaoTs !== false): ?>
                    <span class="minor-info">Previsao de termino: <?php echo htmlspecialchars(date('d/m/Y H:i', $previsaoTs), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php elseif ($stRow === 'finalizada' && $inicioTs !== false && $fimTs !== false): ?>
                    <span class="major-info">Inicio: <?php echo htmlspecialchars(date('d/m/Y H:i', $inicioTs), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="minor-info">Fim: <?php echo htmlspecialchars(date('d/m/Y H:i', $fimTs), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $duracaoRealMin; ?> min)</span>
                    <?php else: ?>
                    <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . substr((string) $m['hora_audiencia'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="col width-500">
                    <span class="minor-info">Processo</span>
                    <span class="major-info"><?php echo htmlspecialchars((string) ($m['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end padding-x-md">
                <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($classeStatusEst, ENT_QUOTES); ?>" data-status-pauta="<?php echo htmlspecialchars($idPautaMinhasRaw, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($textoStatusEst, ENT_QUOTES); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-lg">
            <span class="pagination-info">Mostrando <?php echo $minhasStartItem; ?>-<?php echo $minhasEndItem; ?> de <?php echo $minhasTotal; ?> audiências (<?php echo $minhasTotalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)</span>
            <div class="pagination-controls">
                <?php $prevMinhas = max(1, $minhasPage - 1); $nextMinhas = min($minhasTotalPages, $minhasPage + 1); ?>
                <?php
                $qPrevMinhas = $minhasPage > 1 ? '?page=estudapainel&hoje_page=' . $hojePage . '&minhas_page=' . $prevMinhas . '&items_per_page=' . $itemsPerPage : '';
                $qNextMinhas = $minhasPage < $minhasTotalPages ? '?page=estudapainel&hoje_page=' . $hojePage . '&minhas_page=' . $nextMinhas . '&items_per_page=' . $itemsPerPage : '';
                ?>
                <button type="button" class="pagination-symbol<?php echo $minhasPage <= 1 ? ' disabled' : ''; ?>" <?php echo $qPrevMinhas !== '' ? 'data-nav-href="' . htmlspecialchars($qPrevMinhas, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                <button type="button" class="pagination-number active" disabled><?php echo $minhasPage; ?></button>
                <button type="button" class="pagination-symbol<?php echo $minhasPage >= $minhasTotalPages ? ' disabled' : ''; ?>" <?php echo $qNextMinhas !== '' ? 'data-nav-href="' . htmlspecialchars($qNextMinhas, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
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

    <script src="/assets/js/modules/audiencias/atas-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/atas-estudantes.js') ?: time(); ?>"></script>
    <script>
    (function () {
        function pad2(n) { return n < 10 ? '0' + n : String(n); }
        function parseDateTime(v) {
            var s = String(v || '').trim();
            if (!s) return NaN;
            var iso = s.replace(' ', 'T');
            var t = Date.parse(iso);
            return isNaN(t) ? NaN : t;
        }
        function fmtDateTime(ts) {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
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
        function renderTimeBlock(block, item) {
            var lines = block.querySelector('.js-time-lines');
            if (!lines) return;
            var status = String(item.status || '').trim();
            var dataAud = String(item.data_audiencia || '').trim();
            var horaAud = String(item.hora_audiencia || '').trim();
            var inicioRaw = String(item.data_hora_inicio_efetivo || '').trim();
            var fimRaw = String(item.data_hora_fim_efetivo || '').trim();
            var inicioTs = parseDateTime(inicioRaw);
            if (isNaN(inicioTs)) {
                inicioTs = parseDateTime((dataAud + ' ' + horaAud).trim());
            }
            var duracao = parseInt(item.duracao_estimada_minutos || 0, 10);
            if (!Number.isFinite(duracao) || duracao < 5) duracao = 5;
            var previsaoTs = isNaN(inicioTs) ? NaN : (inicioTs + duracao * 60000);
            var fimTs = parseDateTime(fimRaw);
            var html = '';
            if (status === 'em_andamento' && !isNaN(inicioTs)) {
                html += '<span class="major-info">Inicio: ' + fmtDateTime(inicioTs) + '</span>';
                if (!isNaN(previsaoTs)) {
                    html += '<span class="minor-info">Previsao de termino: ' + fmtDateTime(previsaoTs) + '</span>';
                }
            } else if (status === 'finalizada' && !isNaN(inicioTs) && !isNaN(fimTs) && fimTs >= inicioTs) {
                var durReal = Math.floor((fimTs - inicioTs) / 60000);
                html += '<span class="major-info">Inicio: ' + fmtDateTime(inicioTs) + '</span>';
                html += '<span class="minor-info">Fim: ' + fmtDateTime(fimTs) + ' (' + durReal + ' min)</span>';
            } else {
                var agendadaTs = parseDateTime((dataAud + ' ' + horaAud).trim());
                if (!isNaN(agendadaTs)) {
                    html += '<span class="major-info">' + fmtDateTime(agendadaTs) + '</span>';
                }
            }
            lines.innerHTML = html;
        }
        function statusClass(status) {
            if (status === 'cancelada') return 'status-cancelled';
            if (status === 'em_andamento') return 'status-current';
            if (status === 'realizada' || status === 'finalizada') return 'status-finished';
            return 'status-pending';
        }
        function statusLabel(status) {
            var map = {
                designada: 'Designada',
                em_andamento: 'Em andamento',
                finalizada: 'Finalizada',
                realizada: 'Realizada',
                redesignada: 'Redesignada',
                cancelada: 'Cancelada'
            };
            return map[status] || status;
        }
        function collectPautas() {
            var ids = {};
            document.querySelectorAll('.js-time-block[data-pauta]').forEach(function (el) {
                var id = String(el.getAttribute('data-pauta') || '').trim();
                if (id) ids[id] = true;
            });
            return Object.keys(ids);
        }
        function applyUpdates(items) {
            document.querySelectorAll('.js-time-block[data-pauta]').forEach(function (block) {
                var id = String(block.getAttribute('data-pauta') || '').trim();
                if (!id || !items[id]) return;
                renderTimeBlock(block, items[id]);
            });
            document.querySelectorAll('[data-status-pauta]').forEach(function (statusEl) {
                var id = String(statusEl.getAttribute('data-status-pauta') || '').trim();
                if (!id || !items[id]) return;
                var status = String(items[id].status || '').trim();
                statusEl.className = 'width-300 padding-x-lg status ' + statusClass(status);
                statusEl.textContent = statusLabel(status);
            });
        }
        function refreshStatusAndTime() {
            var pautas = collectPautas();
            if (!pautas.length) return;
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
                    applyUpdates(res.body.items);
                })
                .catch(function () { /* erro silencioso de polling */ });
        }
        bindItemsPerPage('items-per-page-hoje');
        bindItemsPerPage('items-per-page-minhas');
        window.setInterval(refreshStatusAndTime, 60000);
    })();
    </script>
</body>
</html>
