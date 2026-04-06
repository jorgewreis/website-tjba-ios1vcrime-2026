<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
$routePage = trim((string) ($_GET['page'] ?? 'produtividade'));
$today = date('Y-m-d');

$filters = [
    'status' => trim((string) ($_GET['status'] ?? 'ativo')),
    'tipo_lancamento' => trim((string) ($_GET['tipo_lancamento'] ?? '')),
    'data_inicio' => trim((string) ($_GET['data_inicio'] ?? $today)),
    'data_fim' => trim((string) ($_GET['data_fim'] ?? $today)),
    'usuario_id' => trim((string) ($_GET['usuario_id'] ?? '')),
];
if ($filters['data_inicio'] === '') {
    $filters['data_inicio'] = $today;
}
if ($filters['data_fim'] === '') {
    $filters['data_fim'] = $today;
}
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 25;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 25;
}
$currentPage = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

$rows = [];
$totalDocuments = 0;
$sumPontos = 0.0;
$isGestao = false;
$canSeeRegrasButton = false;
$canSeeMetasButton = false;
$canSeeBadgesButton = false;
$errors = [];
$usuariosFiltro = [];
$userPerfis = [];
$canLoadLancamentos = true;

if ($filters['data_inicio'] !== '' && $filters['data_fim'] !== '') {
    $startDate = DateTime::createFromFormat('Y-m-d', $filters['data_inicio']);
    $endDate = DateTime::createFromFormat('Y-m-d', $filters['data_fim']);

    if (!$startDate || !$endDate) {
        $errors[] = 'Período inválido. Use datas válidas para filtrar os lançamentos.';
        $canLoadLancamentos = false;
    } else {
        $startDate->setTime(0, 0, 0);
        $endDate->setTime(0, 0, 0);

        if ($startDate > $endDate) {
            $errors[] = 'A data inicial não pode ser maior que a data final.';
            $canLoadLancamentos = false;
        } else {
            $intervalDays = (int) $startDate->diff($endDate)->days;
            if ($intervalDays > 60) {
                $errors[] = 'O intervalo entre data inicial e data final não pode ultrapassar 60 dias.';
                $canLoadLancamentos = false;
            }
        }
    }
}

if ($canLoadLancamentos) {
    try {
        $result = $service->listLancamentos($userId, $filters, $itemsPerPage, $offset);
        $rows = $result['rows'] ?? [];
        $totalDocuments = (int) ($result['total'] ?? 0);
        $sumPontos = (float) ($result['sum_pontos'] ?? 0);
        $isGestao = (bool) ($result['is_gestao'] ?? false);
    } catch (Throwable $e) {
        $errors[] = 'Erro ao carregar lançamentos: ' . $e->getMessage();
    }
}

$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($totalDocuments === 0) {
    $currentPage = 1;
    $offset = 0;
} elseif ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
    if ($canLoadLancamentos && $errors === []) {
        try {
            $result = $service->listLancamentos($userId, $filters, $itemsPerPage, $offset);
            $rows = $result['rows'] ?? [];
            $sumPontos = (float) ($result['sum_pontos'] ?? 0);
            $isGestao = (bool) ($result['is_gestao'] ?? false);
        } catch (Throwable $e) {
            $errors[] = 'Erro ao carregar lançamentos: ' . $e->getMessage();
        }
    }
}

if ($userId !== '') {
    $userPerfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    $canSeeRegrasButton = in_array('ADMIN', $userPerfis, true) || in_array('CHEFIA', $userPerfis, true) || in_array('DIRETORA', $userPerfis, true) || in_array('MAGISTRADA', $userPerfis, true);
    $canSeeMetasButton = $canSeeRegrasButton;
    $canSeeBadgesButton = $canSeeRegrasButton;
}

if ($isGestao) {
    $stmt = $connection->query(
        'SELECT DISTINCT u.`id_usuario`, u.`nome`, u.`sobrenome`
         FROM `usuarios` u
         INNER JOIN `produtividade_lancamentos` l ON l.`usuario_id` = u.`id_usuario`
         ORDER BY u.`nome` ASC, u.`sobrenome` ASC'
    );
    $usuariosFiltro = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;
$documentsLabel = $totalDocuments === 1 ? 'lançamento' : 'lançamentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

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

$baseQuery = [
    'page' => $routePage,
    'status' => $filters['status'],
    'tipo_lancamento' => $filters['tipo_lancamento'],
    'data_inicio' => $filters['data_inicio'],
    'data_fim' => $filters['data_fim'],
    'usuario_id' => $filters['usuario_id'],
    'items_per_page' => $itemsPerPage,
];
$exportQuery = [
    'status' => $filters['status'],
    'tipo_lancamento' => $filters['tipo_lancamento'],
    'data_inicio' => $filters['data_inicio'],
    'data_fim' => $filters['data_fim'],
    'usuario_id' => $filters['usuario_id'],
];
$exportCsvHref = '/api/produtividade/export-csv.php?' . http_build_query($exportQuery);
$exportPdfHref = '/api/produtividade/export-pdf.php?' . http_build_query($exportQuery);
$rankingApiHref = '/api/produtividade/ranking.php?' . http_build_query($exportQuery);

$resumoCards = [
    'week' => ['current' => 0.0, 'previous' => 0.0, 'variation_percent' => 0.0],
    'month' => ['current' => 0.0, 'previous' => 0.0, 'variation_percent' => 0.0],
    'quarter' => ['current' => 0.0, 'previous' => 0.0, 'variation_percent' => 0.0],
    'projection' => ['projected_points' => 0.0, 'current_points' => 0.0, 'business_days_total' => 0, 'business_days_with_launch' => 0],
];
$metaResumo = ['has_meta' => false];
if ($canLoadLancamentos && $errors === []) {
    try {
        $resumoCards = $service->getResumoCards($userId, $filters);
        $metaResumo = $service->getMetaResumoUsuario($userId, $filters);
    } catch (Throwable $e) {
        $errors[] = 'Erro ao carregar resumo de período: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Produtividade</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            #regra_sugestoes {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-top: 8px;
            }
        </style>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno -</span>
            <span class="specific">Produtividade</span>
        </div>
        <h1 class="title-page">Produtividade</h1>
        <p class="description-page">Lançamentos de produtividade por ato: cadastro manual e registros automáticos do cartório. Por padrão o filtro Tipo lista todos os lançamentos.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Registrar produtividade</h2>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <?php if ($canSeeRegrasButton): ?>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Regras</button>
                <?php endif; ?>
                <?php if ($canSeeMetasButton): ?>
                <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=produtividademetas">Metas</button>
                <?php endif; ?>
                <?php if ($canSeeBadgesButton): ?>
                <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=produtividadebadges">Badges</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md align-end">
            <div class="col">
                <label for="filter-processo">Processo</label>
                <input type="text" id="filter-processo" class="width-400 height-md white" placeholder="0000000-00.0000.0.00.0000">
            </div>
            <div class="col">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" id="filter-classe" class="width-500 height-md white" data-datajud-related="true" placeholder="Preenchida automaticamente pelo DataJud" readonly>
            </div>
            <div class="col">
                <label for="regra_busca">Tarefa<span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="hidden" id="regra_id" value="">
                <input type="text" id="regra_busca" class="width-700 height-md white" placeholder="Ex.: 0001- Expedição de Mandado de Prisão no BNMP">
            </div>
            <div class="col">
                <label for="quantidade">Qtd <span class="label-hint-obrigatorio">*</span></label>
                <input type="number" id="quantidade" class="width-200 height-md white" min="1" value="1">
            </div>
            <div class="col">
                <label for="observacoes">Observação</label>
                <input type="text" id="observacoes" class="width-400 height-md white" placeholder="Detalhes relevantes">
            </div>
            <div class="col">
                <button type="button" class="btn btn-primary height-md" id="salvar-lancamento-manual" disabled>Cadastrar</button>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" value="">
        <input type="hidden" id="hidden-classe-descricao" value="">
        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <div class="col col-full">
                <div id="regra_sugestoes"></div>
                <select id="regra_sugestoes_select" class="width-600 height-md white" style="display:none; margin-top:8px;">
                    <option value="">Selecione uma opção da lista</option>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Lançamentos</h2>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>

        <form method="get" id="filtro-produtividade-form" class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md" data-auto-submit-filters>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>">
            <div class="display-flex row row-fit justify-start gap-y-md">
                <div class="col">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="width-200 height-md">
                        <option value=""<?php echo $filters['status'] === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="ativo"<?php echo $filters['status'] === 'ativo' ? ' selected' : ''; ?>>Ativo</option>
                        <option value="anulado"<?php echo $filters['status'] === 'anulado' ? ' selected' : ''; ?>>Anulado</option>
                    </select>
                </div>
                <div class="col">
                    <label for="tipo_lancamento">Tipo</label>
                    <select id="tipo_lancamento" name="tipo_lancamento" class="width-300 height-md">
                        <option value=""<?php echo $filters['tipo_lancamento'] === '' ? ' selected' : ''; ?>>Todos</option>
                        <option value="manual"<?php echo $filters['tipo_lancamento'] === 'manual' ? ' selected' : ''; ?>>Manual</option>
                        <option value="automatico"<?php echo $filters['tipo_lancamento'] === 'automatico' ? ' selected' : ''; ?>>Automático</option>
                    </select>
                </div>
                <div class="col">
                    <label for="data_inicio">Data inicial</label>
                    <input type="date" id="data_inicio" name="data_inicio" class="width-300 height-md" value="<?php echo htmlspecialchars($filters['data_inicio'], ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="data_fim">Data final</label>
                    <input type="date" id="data_fim" name="data_fim" class="width-300 height-md" value="<?php echo htmlspecialchars($filters['data_fim'], ENT_QUOTES); ?>">
                </div>
                <?php if ($isGestao): ?>
                <div class="col">
                    <label for="usuario_id">Usuário</label>
                    <select id="usuario_id" name="usuario_id" class="width-400 height-md">
                        <option value="">Todos</option>
                        <?php foreach ($usuariosFiltro as $usuario): ?>
                            <?php
                            $idUsuario = (string) ($usuario['id_usuario'] ?? '');
                            $nomeUsuario = trim((string) ($usuario['nome'] ?? '') . ' ' . (string) ($usuario['sobrenome'] ?? ''));
                            ?>
                        <option value="<?php echo htmlspecialchars($idUsuario, ENT_QUOTES); ?>"<?php echo $filters['usuario_id'] === $idUsuario ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($nomeUsuario !== '' ? $nomeUsuario : $idUsuario, ENT_QUOTES); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col">
                    <label for="items_per_page">Itens por página</label>
                    <select id="items_per_page" name="items_per_page" class="width-300 height-md">
                        <?php foreach ($allowedPerPage as $size): ?>
                        <option value="<?php echo $size; ?>"<?php echo $itemsPerPage === $size ? ' selected' : ''; ?>><?php echo $size; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-end">
                <?php
                $listExportCsvUrl = $exportCsvHref;
                $listExportPdfUrl = $exportPdfHref;
                $listExportControlId = 'produtividade-lancamentos';
                include __DIR__ . '/_list_export_buttons.php';
                ?>
            </div>
        </form>

        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom gap-y-md">
            <span class="status status-pending padding-x-lg">Total de lançamentos: <?php echo $totalDocuments; ?></span>
            <span class="status status-success padding-x-lg">Pontos no filtro: <?php echo number_format($sumPontos, 2, ',', '.'); ?></span>
            <span class="status status-pending padding-x-lg">
                Semana: <?php echo number_format((float) ($resumoCards['week']['current'] ?? 0), 2, ',', '.'); ?>
                (<?php echo number_format((float) ($resumoCards['week']['variation_percent'] ?? 0), 1, ',', '.'); ?>%)
            </span>
            <span class="status status-pending padding-x-lg">
                Mês: <?php echo number_format((float) ($resumoCards['month']['current'] ?? 0), 2, ',', '.'); ?>
                (<?php echo number_format((float) ($resumoCards['month']['variation_percent'] ?? 0), 1, ',', '.'); ?>%)
            </span>
            <span class="status status-pending padding-x-lg">
                Trimestre: <?php echo number_format((float) ($resumoCards['quarter']['current'] ?? 0), 2, ',', '.'); ?>
                (<?php echo number_format((float) ($resumoCards['quarter']['variation_percent'] ?? 0), 1, ',', '.'); ?>%)
            </span>
            <span class="status status-pending padding-x-lg">
                Projeção: <?php echo number_format((float) ($resumoCards['projection']['projected_points'] ?? 0), 2, ',', '.'); ?> pts
            </span>
            <?php if ((bool) ($metaResumo['has_meta'] ?? false)): ?>
            <span class="status status-success padding-x-lg">
                Meta <?php echo htmlspecialchars((string) ($metaResumo['periodo_tipo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>:
                <?php echo number_format((float) ($metaResumo['pontos_atuais'] ?? 0), 2, ',', '.'); ?> /
                <?php echo number_format((float) ($metaResumo['meta_pontos'] ?? 0), 2, ',', '.'); ?>
                (<?php echo number_format((float) ($metaResumo['percentual'] ?? 0), 1, ',', '.'); ?>%)
            </span>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Ranking</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom" id="ranking-section" data-ranking-url="<?php echo htmlspecialchars($rankingApiHref, ENT_QUOTES); ?>">
            <span class="minor-info" id="ranking-loading">Ranking indisponível no momento.</span>
            <div id="ranking-content" class="display-flex col col-full gap-y-md" style="display:none;"></div>
        </div>

        <?php foreach ($errors as $error): ?>
        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <span class="status status-cancelled"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></span>
        </div>
        <?php endforeach; ?>

        <?php if ($errors === []): ?>
            <?php if ($rows === []): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                <span class="minor-info">Nenhum lançamento encontrado para os filtros informados.</span>
            </div>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                <?php
                $tipoDoc = trim((string) ($row['tipo_documento'] ?? ''));
                $tipoAto = trim((string) ($row['tipo_ato'] ?? ''));
                $sistemaModulo = trim((string) ($row['sistema_modulo'] ?? ''));
                $obsRow = trim((string) ($row['observacoes'] ?? ''));
                $tipoLan = trim((string) ($row['tipo_lancamento'] ?? ''));
                if ($obsRow !== '' && $tipoLan === 'automatico') {
                    $descricaoTarefa = $obsRow;
                } else {
                    $descricaoTarefa = implode(' - ', [
                        $tipoDoc !== '' ? $tipoDoc : 'Sem documento',
                        $tipoAto !== '' ? $tipoAto : 'Sem ato',
                        $sistemaModulo !== '' ? $sistemaModulo : 'Sem sistema',
                    ]);
                }
                ?>
                <div class="display-flex col col-full register border-bottom">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap">
                            <div class="col width-300">
                                <span class="minor-info">Data/hora</span>
                                <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($row['data_hora_ato'] ?? 'now'))), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-500">
                                <span class="minor-info">Processo</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-700">
                                <span class="minor-info">Descrição da tarefa</span>
                                <span class="major-info"><?php echo htmlspecialchars($descricaoTarefa, ENT_QUOTES); ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Qtd</span>
                                <span class="major-info"><?php echo (int) ($row['quantidade'] ?? 0); ?></span>
                            </div>
                            <div class="col width-200">
                                <span class="minor-info">Pontos</span>
                                <span class="major-info"><?php echo number_format((float) ($row['pontos_calculados'] ?? 0), 2, ',', '.'); ?></span>
                            </div>
                            <div class="col width-500">
                                <span class="minor-info">Usuário</span>
                                <span class="major-info"><?php echo htmlspecialchars((string) ($row['usuario_nome'] ?? $row['usuario_id'] ?? ''), ENT_QUOTES); ?></span>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <?php if ((string) ($row['status'] ?? '') === 'ativo' && (string) ($row['tipo_lancamento'] ?? '') === 'manual'): ?>
                            <button type="button" class="btn btn-danger-outline height-md" data-anular-id="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES); ?>">Anular</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($errors === [] && $canLoadLancamentos): ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-top">
            <span class="pagination-info">
                Mostrando <?php echo (int) $startItem; ?>-<?php echo (int) $endItem; ?> de <?php echo (int) $totalDocuments; ?> <?php echo htmlspecialchars($documentsLabel, ENT_QUOTES, 'UTF-8'); ?>
                (<?php echo (int) $totalPages; ?> <?php echo htmlspecialchars($pagesLabel, ENT_QUOTES, 'UTF-8'); ?>, <?php echo (int) $itemsPerPage; ?> <?php echo htmlspecialchars($itemsLabel, ENT_QUOTES, 'UTF-8'); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                    $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                    $prevParams = array_merge($baseQuery, ['page_number' => $prevPage]);
                    $prevHref = '/frame.php?' . http_build_query($prevParams);
                    $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                    $nextParams = array_merge($baseQuery, ['page_number' => $nextPage]);
                    $nextHref = '/frame.php?' . http_build_query($nextParams);
                    ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = array_merge($baseQuery, ['page_number' => $page]); ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="/frame.php?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && $trailingPages !== []): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = array_merge($baseQuery, ['page_number' => $page]); ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="/frame.php?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled<?php endif; ?>><?php echo (int) $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8'); ?>"<?php else: ?>disabled aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/modules/produtividade/produtividade-list.js"></script>
    <script src="/assets/js/modules/produtividade/badge-popup.js"></script>
</body>
</html>

