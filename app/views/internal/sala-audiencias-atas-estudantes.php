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
require_once __DIR__ . '/../../helpers/SalaAudienciasEstudantePrivacidade.php';

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
    return SalaAudienciasEstudantePrivacidade::indisponivel($row)
        || !SalaAudienciasAtaService::ocultarDaListagemAtasPorPrazoSemDownload($row);
}));
$itemsPerPage = 15;
if (!in_array($itemsPerPage, [15], true)) {
    $itemsPerPage = 15;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$total = count($minhasRows);
$totalPages = max(1, (int) ceil($total / $itemsPerPage));
if ($totalPages > 0 && $pageNumber > $totalPages) {
    $pageNumber = $totalPages;
}
if ($total === 0) {
    $pageNumber = 1;
}
$offset = ($pageNumber - 1) * $itemsPerPage;
$rowsPage = array_slice($minhasRows, $offset, $itemsPerPage);
$observadas = [];
foreach ($rowsPage as $entry) {
    if (SalaAudienciasEstudantePrivacidade::indisponivel($entry)) {
        $id = trim((string) ($entry['pauta'] ?? ''));
        if ($id !== '') $observadas[] = $id;
    }
}
$observacaoToken = '';
if ($observadas && $podeInscricaoComoEstudante && $userId !== '') {
    $observacoes = is_array($_SESSION['rei161_observacoes'] ?? null) ? $_SESSION['rei161_observacoes'] : [];
    $observacoes = array_filter($observacoes, static fn($entry): bool => is_array($entry) && (int)($entry['created'] ?? 0) >= time() - 3600);
    $observacoes = array_slice($observacoes, -15, null, true);
    $observacaoToken = bin2hex(random_bytes(16));
    $observacoes[$observacaoToken] = ['usuario' => $userId, 'ids' => array_values(array_unique($observadas)), 'created' => time()];
    $_SESSION['rei161_observacoes'] = $observacoes;
}
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
        <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Estudantes - Audiências -</span>
            <span class="specific">Atas de audiências</span>
        </div>
    </div>

    <div class="container-full card-page" data-observacao="<?php echo htmlspecialchars($observacaoToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" tabindex="-1" style="color: var(--color-primary-active);">Atas de Audiências</h1>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">

            </div>
        </div>

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-600); font-size: 13px;">Não há filtros adicionais.</span>

            </div>
        </div>

        <div class="display-flex col col-full register border-bottom status-current border-bottom" style="margin-bottom: 15px;">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="padding-y-md major-info">Total: <?php echo (int) $total; ?> <?php echo htmlspecialchars($documentsLabel); ?></span>
            </div>
        </div>
        <div id="rei161-lista-alterada" role="status" aria-live="polite" hidden>
            <span id="rei161-lista-mensagem">Há uma atualização na lista.</span> <button type="button" id="rei161-atualizar-lista">Atualizar lista</button>
        </div>

        <?php if (count($rowsPage) === 0): ?>
        <div class="display-flex col col-full register border-bottom">
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <span class="major-info">Nenhuma inscrição encontrada.</span>
            </div>
        </div>
        <?php endif; ?>

        <?php $processo_bloco_variant = 'estudantes'; ?>
        <?php foreach ($rowsPage as $m):
            if (SalaAudienciasEstudantePrivacidade::indisponivel($m)): ?>
        <div class="display-flex col col-full register border-bottom" data-inicial-indisponivel="1"><p class="padding-x-lg padding-y-md">Audiência indisponível.</p></div>
        <?php continue; endif;
            $arquivoRelEst = trim((string) ($m['arquivo_relativo'] ?? ''));
            $expira = trim((string) ($m['expires_at'] ?? ''));
            $expiraTs = $expira !== '' ? strtotime($expira) : false;
            $downloadAtaOk = $arquivoRelEst !== '' && !empty($m['id_ata']) && $expiraTs !== false && $expiraTs > time();
            $ataPrazoEncerrado = !empty($m['id_ata']) && $arquivoRelEst === '';
            $row = $m;
            $expiraFmt = $expira !== '' ? date('d/m', strtotime($expira)) : '';
            $inicioEfetivoRaw = trim((string) ($m['data_hora_inicio_efetivo'] ?? ''));
            $inicioEfetivoTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            $horaExibicao = $inicioEfetivoTs !== false ? date('H:i', $inicioEfetivoTs) : substr((string) ($m['hora_audiencia'] ?? ''), 0, 5);
            ?>
        <div class="display-flex col col-full register border-bottom" data-pauta="<?php echo htmlspecialchars((string) ($m['pauta'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div class="col width-300">
                        <span class="minor-info">Data</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . $horaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500 col-process">
                        <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
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

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
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
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
    <script>
    (function () {
        var requestInFlight = false;
        function rows() {
            return Array.prototype.slice.call(document.querySelectorAll('.register[data-pauta]'));
        }
        function pautaIds(registers) {
            return Array.from(new Set(registers.map(function (row) {
                return String(row.getAttribute('data-pauta') || '').trim();
            }).filter(Boolean)));
        }
        function renderUnavailable(row) {
            moveFocusToTitle(row);
            row.innerHTML = '<p class="padding-x-lg padding-y-md">Audiência indisponível.</p>';
            row.setAttribute('data-indisponivel', '1');
        }
        function renderUnverified(row) {
            moveFocusToTitle(row);
            row.innerHTML = '<p class="padding-x-lg padding-y-md">Não foi possível verificar a disponibilidade. Atualize a página.</p>';
            row.setAttribute('data-indisponivel', 'unknown');
        }
        function moveFocusToTitle(row) {
            if (!row.contains(document.activeElement)) return;
            var title = document.querySelector('.card-page .card-title h1');
            if (title) title.focus();
        }
        function showNotice(message) {
            var notice = document.getElementById('rei161-lista-alterada');
            var label = document.getElementById('rei161-lista-mensagem');
            if (label) label.textContent = message;
            if (notice) notice.hidden = false;
        }
        // Remove legacy sensitive payloads from the rendered card; this does
        // not retract any information already delivered over the network.
        function normalizeItem(id, item) {
            function active(name) {
                if (!Object.prototype.hasOwnProperty.call(item, name)) return false;
                var value = item[name];
                return !(value === false || value === 0 || value === '0');
            }
            var enrolled = item.ja_inscrito === true || item.ja_inscrito === 1 || item.ja_inscrito === '1';
            if (active('indisponivel') || active('processo_sigiloso') || active('envolve_menor') ||
                active('qtd_menor') || active('tem_prioridade_bloqueio') || !enrolled) {
                return {id_pauta:id, indisponivel:true};
            }
            return item;
        }
        function validItem(id, item) {
            if (!item || typeof item !== 'object' || Array.isArray(item) || item.id_pauta !== id) return false;
            if (item.indisponivel === true) return Object.keys(item).length === 2;
            var strings = ['status','data_audiencia','hora_audiencia','data_hora_inicio_efetivo','data_hora_fim_efetivo'];
            var bools = ['ja_inscrito','vagas_esgotadas','pode_inscrever'];
            var known = ['id_pauta','duracao_estimada_minutos','total_inscritos_pauta'].concat(strings, bools);
            return Object.keys(item).every(function (key) { return known.indexOf(key) !== -1; }) &&
                strings.every(function (key) { return typeof item[key] === 'string'; }) &&
                bools.every(function (key) { return typeof item[key] === 'boolean'; }) &&
                Number.isInteger(item.duracao_estimada_minutos) && item.duracao_estimada_minutos >= 0 &&
                Number.isInteger(item.total_inscritos_pauta) && item.total_inscritos_pauta >= 0;
        }
        function refreshAvailability() {
            if (requestInFlight) return;
            var registers = rows();
            var ids = pautaIds(registers);
            var container = document.querySelector('.card-page');
            var watch = container ? String(container.getAttribute('data-observacao') || '') : '';
            if (!ids.length && !watch) return;
            requestInFlight = true;
            var controller = typeof AbortController === 'function' ? new AbortController() : null;
            var timer;
            var deadline = new Promise(function (_, reject) {
                timer = setTimeout(function () {
                    if (controller) controller.abort();
                    reject(new Error('availability request timed out'));
                }, 8000);
            });
            var request = Promise.resolve().then(function () {
                return fetch('/api/sala-audiencias-pauta/status-bulk.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    signal: controller ? controller.signal : undefined,
                    body: JSON.stringify({ pautas: ids, observacao: watch })
                });
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body || {} };
                });
            });
            Promise.race([request, deadline]).then(function (result) {
                var body = result.body;
                if (!result.ok || body.success !== true || body.completo !== true ||
                    !Array.isArray(body.consultados) || !body.items || typeof body.items !== 'object' ||
                    Array.isArray(body.items) || (watch && typeof body.observacao_alterada !== 'boolean')) throw new Error('availability snapshot incomplete');
                var consulted = body.consultados;
                if (consulted.length !== ids.length || new Set(consulted).size !== ids.length ||
                    ids.some(function (id) { return consulted.indexOf(id) === -1; }) ||
                    consulted.some(function (id) { return typeof id !== 'string'; }) ||
                    Object.keys(body.items).some(function (id) {
                        return ids.indexOf(id) === -1 || !body.items[id] ||
                            String(body.items[id].id_pauta || '') !== id;
                    })) throw new Error('availability snapshot mismatch');

                var safeItems = {};
                Object.keys(body.items).forEach(function (id) {
                    var raw = body.items[id];
                    if (!raw || typeof raw !== 'object' || Array.isArray(raw) || raw.id_pauta !== id) throw new Error('invalid item');
                    var item = normalizeItem(id, raw);
                    if (!validItem(id, item)) throw new Error('invalid item');
                    safeItems[id] = item;
                });

                if (body.observacao_alterada === true) {
                    showNotice('Há uma atualização na lista.');
                }

                var changed = false;
                registers.forEach(function (row) {
                    var id = String(row.getAttribute('data-pauta') || '').trim();
                    var item = safeItems[id];
                    if (!item) {
                        renderUnavailable(row);
                        return;
                    }
                    if (item.indisponivel === true) {
                        renderUnavailable(row);
                        return;
                    }
                    if (row.hasAttribute('data-indisponivel')) changed = true;
                });
                if (changed) {
                    showNotice('Há uma atualização na lista.');
                }
            }).catch(function () {
                registers.forEach(function (row) {
                    if (row.getAttribute('data-indisponivel') === '1') return;
                    renderUnverified(row);
                });
                if (watch) showNotice('Não foi possível verificar a lista. Atualize a página.');
            }).finally(function () {
                clearTimeout(timer);
                requestInFlight = false;
            });
        }
        var updateButton = document.getElementById('rei161-atualizar-lista');
        if (updateButton) updateButton.addEventListener('click', function () { window.location.reload(); });
        refreshAvailability();
        window.setInterval(refreshAvailability, 30000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshAvailability();
        });
    })();
    </script>

</body>
</html>
