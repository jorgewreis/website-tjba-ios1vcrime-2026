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
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$total = count($minhasRows);
$totalPages = max(1, (int) ceil(max(1, $total) / $itemsPerPage));
if ($pageNumber > $totalPages) {
    $pageNumber = $totalPages;
}
$offset = ($pageNumber - 1) * $itemsPerPage;
$rowsPage = array_slice($minhasRows, $offset, $itemsPerPage);
$startItem = $total > 0 ? $offset + 1 : 0;
$endItem = min($offset + count($rowsPage), $total);
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
        <h1 class="title-page">Atas de Audiências</h1>
        <p class="description-page">Lista das audiências em que você se inscreveu, com situação de disponibilidade da ata para download.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Minhas audiências</h2></div>
        </div>
        <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
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
        <?php if (count($rowsPage) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma inscrição encontrada.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($rowsPage as $m):
            $idPauta = htmlspecialchars((string) ($m['pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $temAta = !empty($m['id_ata']);
            $classeAudiencia = !empty($m['classe_nome']) ? (string) $m['classe_nome'] : 'Classe não informada';
            $expira = trim((string) ($m['expires_at'] ?? ''));
            $expiraFmt = $expira !== '' ? date('d/m', strtotime($expira)) : '';
            $inicioEfetivoRaw = trim((string) ($m['data_hora_inicio_efetivo'] ?? ''));
            $inicioEfetivoTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            $horaExibicao = $inicioEfetivoTs !== false ? date('H:i', $inicioEfetivoTs) : substr((string) ($m['hora_audiencia'] ?? ''), 0, 5);
            ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-bottom align-center flex-wrap gap-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                <div class="col width-300">
                    <span class="minor-info">Data</span>
                    <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . $horaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-500">
                    <span class="minor-info text-ellipsis"><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="major-info"><?php echo htmlspecialchars((string) ($m['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-400">
                    <span class="minor-info">Ata para download</span>
                    <span class="major-info">
                        <?php echo $temAta ? 'Disponível até ' . htmlspecialchars($expiraFmt, ENT_QUOTES, 'UTF-8') : 'Ainda não disponível'; ?>
                    </span>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end padding-x-md">
                <?php if ($temAta && $podeInscricaoComoEstudante): ?>
                <button type="button" class="btn btn-primary" data-nav-href="/api/sala-audiencias-ata/download.php?pauta=<?php echo urlencode((string) $m['pauta']); ?>" data-nav-target="_blank">Baixar Ata (PDF)</button>
                <?php elseif ($temAta): ?>
                <span class="minor-info">Download exclusivo para estudantes.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="display-flex row row-full justify-between padding-x-lg padding-y-lg">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> audiências (<?php echo $totalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)</span>
            <div class="pagination-controls">
                <?php $prev = max(1, $pageNumber - 1); $next = min($totalPages, $pageNumber + 1); ?>
                <?php
                $qPrevE = $pageNumber > 1 ? '?page=estudaata&page_number=' . $prev . '&items_per_page=' . $itemsPerPage : '';
                $qNextE = $pageNumber < $totalPages ? '?page=estudaata&page_number=' . $next . '&items_per_page=' . $itemsPerPage : '';
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

</body>
</html>
