<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';
require_once __DIR__ . '/../../helpers/ProdutividadeRankingDisplayHelper.php';

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
$isGestao = false;
$canSeeRegrasButton = false;
$canSeeMetasButton = false;
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
}

$showRankingBlock = false;
if ($userId !== '') {
    try {
        $showRankingBlock = $service->usuarioPodeVerRankingProdutividade($userId);
    } catch (Throwable $e) {
        $showRankingBlock = false;
    }
}

$rankingBlockRows = [];
$rankingBlockOculto = false;
if ($showRankingBlock && $userId !== '') {
    try {
        $rankingPayload = $service->listRanking($userId, ['status' => 'ativo'], 200);
        $rankingBlockOculto = !empty($rankingPayload['ranking_oculto']);
        $allRanking = $rankingPayload['rows'] ?? [];
        if (!$rankingBlockOculto && $allRanking !== []) {
            $rankingBlockRows = array_slice($allRanking, 0, 5);
        }
    } catch (Throwable $e) {
        $rankingBlockRows = [];
        $rankingBlockOculto = false;
    }
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
        <p class="description-page">Lançamentos de produtividade por ato: cadastro manual e registros automáticos.</p>
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
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md align-end">
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
        
        <!-- RANKING -->
        <?php if ($showRankingBlock): ?>
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex col col-fit gap-y-sm">
                <h2>Ranking</h2>
            </div>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom" id="ranking-section">
            <?php if ($rankingBlockOculto): ?>
                <span class="minor-info">Ranking indisponível para o seu perfil neste período.</span>
            <?php elseif ($rankingBlockRows === []): ?>
                <span class="minor-info">Sem dados de ranking no mês atual (Cartório/Gabinete).</span>
            <?php else: ?>
                <div class="display-flex col col-full">
                    <div class="display-flex col col-full padding-y-sm">
                        <?php foreach ($rankingBlockRows as $row): ?>
                            <?php
                            $nomeRank = ProdutividadeRankingDisplayHelper::doisPrimeirosNomes((string) ($row['usuario_nome'] ?? ''));
                            if ($nomeRank === '') {
                                $nomeRank = 'Usuário';
                            }
                            $posRank = (int) ($row['posicao'] ?? 0);
                            $ptsRank = (float) ($row['pontos_total'] ?? 0);
                            ?>
                        <div class="display-flex row row-full justify-between align-center flex-wrap gap-y-sm padding-y-sm border-bottom">
                            <div class="display-flex row row-fit align-center flex-wrap gap-y-sm" style="min-width: 0; flex: 1 1 auto;">
                                <span class="major-info" style="font-family: monospace; font-size: 12px; font-weight: 600;"><?php echo $posRank; ?>. <?php echo htmlspecialchars($nomeRank, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <span class="major-info" style="font-family: monospace; font-size: 12px; flex-shrink:0;"><?php echo number_format($ptsRank, 2, ',', '.'); ?> pts</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- FIM RANKING -->

        <!-- LANÇAMENTOS -->
        <div class="display-flex row row-full align-center justify-between border-bottom padding-x-lg padding-y-md filters">
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

        <!-- ERROS -->
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
                $refSistemaLinha = trim((string) ($row['referencia_sistema'] ?? ''));
                $obsRow = trim((string) ($row['observacoes'] ?? ''));
                $tipoLan = trim((string) ($row['tipo_lancamento'] ?? ''));
                $legacyObsConsultaProcLista = $tipoLan === 'automatico'
                    && preg_match('/consulta\s+de\s+processos?\s*\(\s*listagem\s*\)/iu', $obsRow);
                $legacyObsConsultaProcDetalhe = $tipoLan === 'automatico'
                    && preg_match('/consulta\s+de\s+processo\s*\(\s*detalhe\s*\)/iu', $obsRow);
                if ($obsRow !== '' && $tipoLan === 'automatico') {
                    $descricaoTarefa = $obsRow;
                } else {
                    $descricaoTarefa = implode(' - ', [
                        $tipoAto !== '' ? $tipoAto : 'Sem ato',
                        $tipoDoc !== '' ? $tipoDoc : 'Sem documento',
                        $sistemaModulo !== '' ? $sistemaModulo : 'Sem sistema',
                    ]);
                }
                // Classe judicial vinda de processos + classes (CNJ), não de produtividade_lancamentos.classe_processo (contexto do módulo).
                $classeJudicialRotulo = trim((string) ($row['classe_judicial_nome'] ?? ''));
                $labelColunaProcesso = $classeJudicialRotulo !== '' ? $classeJudicialRotulo : 'Processo';

                $npLinha = trim((string) ($row['numero_processo'] ?? ''));
                $temProcessoLinha = $npLinha !== '';
                $qtdLinha = (int) ($row['quantidade'] ?? 0);
                $qtdSufixoLinha = $qtdLinha > 1 ? (' x' . $qtdLinha) : '';
                $pontosLinha = (float) ($row['pontos_calculados'] ?? 0);
                $pontosFmtLinha = number_format($pontosLinha, 2, ',', '.');
                $pontosUnidadeLinha = abs($pontosLinha - 1.0) < 0.000001 ? 'pt' : 'pts';
                $dataFmtLinha = date('d/m/Y H:i', strtotime((string) ($row['data_hora_ato'] ?? 'now')));
                $omitPontosUm = abs($pontosLinha - 1.0) < 0.000001 && max(0, $qtdLinha) <= 1;
                $pontosSufixoLinha = $omitPontosUm ? '' : (' - ' . $pontosFmtLinha . ' ' . $pontosUnidadeLinha);
                $baseDescTarefa = (string) ($descricaoTarefa ?? '') . $qtdSufixoLinha;
                $tarefaPontosLinha = $baseDescTarefa . $pontosSufixoLinha;
                $usuarioLinha = (string) ($row['usuario_nome'] ?? $row['usuario_id'] ?? '');
                $isConsultaProcessoCompact = $tipoLan === 'automatico'
                    && (
                        $sistemaModulo === 'consultas-processos-lista'
                        || $sistemaModulo === 'consultas-processos-detalhe'
                        || $refSistemaLinha === 'consultas-processos'
                        || $legacyObsConsultaProcLista
                        || $legacyObsConsultaProcDetalhe
                    );
                if ($isConsultaProcessoCompact) {
                    $labConsulta = 'Consulta processo';
                    $npSegConsulta = $npLinha !== '' ? $npLinha : '—';
                    $meioLinha = $labConsulta . $pontosSufixoLinha;
                    $linhaLancamento = $dataFmtLinha . ' | ' . $npSegConsulta . ' | ' . $meioLinha . ' | ' . $usuarioLinha;
                } else {
                    $linhaLancamento = $temProcessoLinha
                        ? ($dataFmtLinha . ' | ' . $npLinha . ' | ' . $tarefaPontosLinha . ' | ' . $usuarioLinha)
                        : ($dataFmtLinha . ' | ' . $tarefaPontosLinha . ' | ' . $usuarioLinha);
                }
                ?>
                <div class="display-flex col col-full register border-bottom">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                        <div class="display-flex row row-fit justify-start gap-y-md flex-wrap" style="width: calc(100% - 50px);">
                            <div class="row width-full gap-x-sm padding-x-md padding-y-sm" style="font-family: monospace; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($linhaLancamento, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                            <?php if ($service->canActorAnularLancamentoManual($userId, $row)): ?>
                            <button type="button" class="btn btn-danger-outline" data-anular-id="<?php echo htmlspecialchars((string) ($row['id'] ?? ''), ENT_QUOTES); ?>" style="display: flex; align-items: center; justify-content: center; width: 20px; height: 20px !important; padding: 5px; margin-right: 10px; border-radius: 4px; border: 1px solid var(--color-border); font-family: monospace; font-size: 12px;">x</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($errors === [] && $canLoadLancamentos): ?>
        <div class="display-flex row row-full justify-between padding-x-md padding-y-lg">
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
        <!-- FIM LANÇAMENTOS -->
    </div>

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/modules/produtividade/produtividade-list.js"></script>
</body>
</html>

