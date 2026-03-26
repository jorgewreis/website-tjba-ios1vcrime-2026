<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasAtaService.php';

$service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
$itemsPerPage = (int) ($_GET['items_per_page'] ?? 25);
if (!in_array($itemsPerPage, [10, 25, 50, 100], true)) {
    $itemsPerPage = 25;
}
$pageNumber = max(1, (int) ($_GET['page_number'] ?? 1));
$offset = ($pageNumber - 1) * $itemsPerPage;
$result = $service->listRealizadasParaUpload($itemsPerPage, $offset);
$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$totalPages = max(1, (int) ceil(max(1, $total) / $itemsPerPage));
$startItem = $total > 0 ? $offset + 1 : 0;
$endItem = min($offset + count($rows), $total);
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Atas de Audiências - Lista</title>
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
            <span class="all">Interno - Sala de Audiências -</span>
            <span class="specific">Atas de audiências</span>
        </div>
        <h1 class="title-page">Atas de Audiências</h1>
        <p class="description-page">Envio do PDF da ata para audiências realizadas. O arquivo fica disponível aos estudantes inscritos por 30 dias.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Audiências realizadas</h2></div>
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

        <?php if (count($rows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma audiência realizada encontrada.</p>
        </div>
        <?php endif; ?>

        <?php foreach ($rows as $row):
            $idPauta = htmlspecialchars((string) ($row['id_pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $temAta = !empty($row['id_ata']) && !empty($row['arquivo_relativo']);
            $inicioEfetivoRaw = trim((string) ($row['data_hora_inicio_efetivo'] ?? ''));
            $inicioEfetivoTs = $inicioEfetivoRaw !== '' ? strtotime($inicioEfetivoRaw) : false;
            $horaExibicao = $inicioEfetivoTs !== false ? date('H:i', $inicioEfetivoTs) : substr((string) ($row['hora_audiencia'] ?? ''), 0, 5);
            ?>
        <div class="display-flex col col-full register border-bottom" data-pauta="<?php echo $idPauta; ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div class="col width-300">
                        <span class="minor-info">Data e hora</span>
                        <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $row['data_audiencia'])) . ' - ' . $horaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-400 col-process process-info" title="clique para copiar">
                        <?php
                            $classeAudiencia = !empty($row['classe_nome']) ? $row['classe_nome'] : 'Classe não informada';
                            $classeStyleAudiencia = $classeAudiencia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info text-ellipsis" <?php echo $classeStyleAudiencia; ?>><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500">
                        <span class="minor-info">Tipo de audiência</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-500">
                        <span class="minor-info">Ata para download</span>
                        <?php if ($temAta): ?>
                        <span class="major-info text-ellipsis ata-status-text">Disponível para download</span>
                        <?php else: ?>
                        <span class="major-info text-ellipsis ata-status-text">Nenhum arquivo enviado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <input type="file" class="ata-file-input visually-hidden" accept="application/pdf,.pdf" aria-label="Selecionar PDF da ata">
                    <button type="button" class="btn btn-primary btn-upload-ata" data-pauta="<?php echo $idPauta; ?>"><?php echo $temAta ? 'Substituir PDF' : 'Enviar PDF'; ?></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm">
            <span class="pagination-info">Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $total; ?> audiências (<?php echo $totalPages; ?> página(s), <?php echo $itemsPerPage; ?> itens por página)</span>
            <div class="pagination-controls">
                <?php $prev = max(1, $pageNumber - 1); $next = min($totalPages, $pageNumber + 1); ?>
                <?php
                $qPrevA = $pageNumber > 1 ? '?page=salaata&page_number=' . $prev . '&items_per_page=' . $itemsPerPage : '';
                $qNextA = $pageNumber < $totalPages ? '?page=salaata&page_number=' . $next . '&items_per_page=' . $itemsPerPage : '';
                ?>
                <button type="button" class="pagination-symbol<?php echo $pageNumber <= 1 ? ' disabled' : ''; ?>" <?php echo $qPrevA !== '' ? 'data-nav-href="' . htmlspecialchars($qPrevA, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior"></button>
                <button type="button" class="pagination-number active" disabled><?php echo $pageNumber; ?></button>
                <button type="button" class="pagination-symbol<?php echo $pageNumber >= $totalPages ? ' disabled' : ''; ?>" <?php echo $qNextA !== '' ? 'data-nav-href="' . htmlspecialchars($qNextA, ENT_QUOTES) . '"' : 'disabled aria-disabled="true"'; ?>><img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página"></button>
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

    <style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>
    <script src="/assets/js/modules/audiencias/atas-list.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/atas-list.js') ?: time(); ?>"></script>
</body>
</html>
