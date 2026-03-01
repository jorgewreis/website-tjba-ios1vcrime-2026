<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/CartasExpedidasService.php';

$connection = Conection::getInstance()->getConnection();

$routePage = trim((string) ($_GET['page'] ?? 'cartasexpedidas'));
$finalidadeFilter = trim($_GET['finalidade'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$processoFilter = trim($_GET['processo'] ?? '');
$allowedPerPage = [10, 25, 50, 100];
$itemsPerPage = isset($_GET['items_per_page']) ? (int) $_GET['items_per_page'] : 10;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 10;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} elseif (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = max(1, (int) $_GET['page']);
} else {
    $currentPage = 1;
}
$filters = [
    'finalidade' => $finalidadeFilter,
    'status' => $statusFilter,
    'processo' => $processoFilter,
];
$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new CartasExpedidasService($connection);
    $listResult = $service->listCartas($filters, $itemsPerPage, $offset);
} catch (\Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 0;
    $currentPage = 1;
} else {
    $totalPages = (int) ceil($totalDocuments / $itemsPerPage);
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
}

$offset = ($currentPage - 1) * $itemsPerPage;
$displayedCount = count($rows);
$startItem = $displayedCount > 0 ? $offset + 1 : 0;
$endItem = $displayedCount > 0 ? $offset + $displayedCount : 0;

$documentsLabel = $totalDocuments === 1 ? 'documento' : 'documentos';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptions = [
    'pendente',
    'em_andamento',
    'aguardando assinatura',
    'aguardando envio',
    'aguardando juntada do termo',
    'aguardando cumprimento',
    'concluída',
    'cancelada',
];

$statusCounts = array_merge(array_fill_keys($statusOptions, 0), $service->getStatusCounts($filters));
$statusCounts['em_andamento'] = (int) ($statusCounts['em_andamento'] ?? 0) + (int) ($statusCounts['aguardando assinatura'] ?? 0) + (int) ($statusCounts['aguardando envio'] ?? 0) + (int) ($statusCounts['aguardando juntada do termo'] ?? 0) + (int) ($statusCounts['aguardando juntada de termo'] ?? 0) + (int) ($statusCounts['aguardando cumprimento'] ?? 0);

$subtarefaLabels = [
    'aguardando_assinatura' => 'Assinar carta expedida',
    'aguardando_gerar_pdf' => 'Gerar PDF da documentação',
    'aguardando_envio' => 'Aguardando envio',
    'aguardando_juntada_termo' => 'Aguardando juntada de termo de envio',
    'aguardando_informacoes' => 'Aguardando informações',
    'aguardando_devolucao' => 'Aguardando devolução',
];
$ordemLabels = [
    1 => $subtarefaLabels['aguardando_assinatura'],
    2 => $subtarefaLabels['aguardando_gerar_pdf'],
    3 => $subtarefaLabels['aguardando_envio'],
    4 => $subtarefaLabels['aguardando_juntada_termo'],
    5 => $subtarefaLabels['aguardando_informacoes'],
    6 => $subtarefaLabels['aguardando_devolucao'],
];

$defaultFinalidades = [
    'Intimação para Audiência',
    'Intimação de Decisão Interlocutória',
    'Intimação da Sentença',
    'Citação para apresentar Defesa',
    'Citação para apresentar Alegações Finais',
    'Suspensão Condicional',
    'Outra',
];
$rawFinalidadeCounts = $service->getFinalidadeCounts($filters);
$finalidadeOrder = array_unique(array_merge($defaultFinalidades, array_keys($rawFinalidadeCounts)));
$finalidadeCounts = [];
foreach ($finalidadeOrder as $term) {
    $finalidadeCounts[$term] = $rawFinalidadeCounts[$term] ?? 0;
}

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($finalidadeFilter !== '') {
    $baseQueryParams['finalidade'] = $finalidadeFilter;
}
if ($statusFilter !== '') {
    $baseQueryParams['status'] = $statusFilter;
}
if ($processoFilter !== '') {
    $baseQueryParams['processo'] = $processoFilter;
}
$baseQueryParams['items_per_page'] = $itemsPerPage;

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
?>

<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
                
        <!-- IMPORT - FLATICON -->
        <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css'>
        
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <!-- Mapa de navegação do site -->
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Expedidas -</span>
            <span class="specific">Lista de documentos</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Cartas Expedidas</h1>
        <p class="description-page">Controle de cartas precatórias, rogatórias e de ordem expedidas pelo cartório.</p>
         
        <!-- Badges de arquivos para download -->
        <a href="/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" target="_blank" rel="noopener noreferrer">
            <div class="badge file">
                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
                <span>Passo a passo - Expedição de Cartas Precatórias</span>
            </div>
        </a>
    </div>

    <div class="card-page">

        <!-- Filtros -->
        <div class="row filter">
            <div class="col filter">
                <label for="filter-tipo">Filtrar por finalidade</label>
                <select class="width-md height-md" id="filter-tipo">
                    <option value=""<?php echo $finalidadeFilter === '' ? ' selected' : ''; ?>>Mostrar todas</option>
                    <?php foreach ($finalidadeCounts as $finalidade => $count): ?>
                        <option value="<?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?>"<?php echo $finalidadeFilter === $finalidade ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?> (<?php echo htmlspecialchars((string) $count); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-status">Filtrar por status</label>
                <select class="width-md height-md" id="filter-status">
                    <option value=""<?php echo $statusFilter === '' ? ' selected' : ''; ?>>Mostrar todos</option>
                    <option value="pendente"<?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                        Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                    </option>
                    <option value="em_andamento"<?php echo $statusFilter === 'em_andamento' ? ' selected' : ''; ?>>
                        Em andamento (<?php echo htmlspecialchars((string) ($statusCounts['em_andamento'] ?? 0)); ?>)
                    </option>
                    <option value="concluída"<?php echo $statusFilter === 'concluída' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['concluída'] ?? 0)); ?>)
                    </option>
                    <option value="cancelada"<?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                    </option>
                </select>
            </div>
            <div class="col filter">
                <label for="items-per-page">Itens por página</label>
                <select class="width-sm height-md" id="items-per-page">
                    <option value="10"<?php echo $itemsPerPage === 10 ? ' selected' : ''; ?>>10</option>
                    <option value="25"<?php echo $itemsPerPage === 25 ? ' selected' : ''; ?>>25</option>
                    <option value="50"<?php echo $itemsPerPage === 50 ? ' selected' : ''; ?>>50</option>
                    <option value="100"<?php echo $itemsPerPage === 100 ? ' selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>
        
        <!-- Ações -->
        <div class="row buttons">

            <!-- Pesquisar na lista -->
            <input type="text" class="width-md height-md" id="search-input" placeholder="Pesquisar na lista..." value="<?php echo htmlspecialchars($processoFilter, ENT_QUOTES); ?>">

            <!-- Exportar -->
            <select class="width-sm height-md" id="export-type" disabled>
                <option value="" selected>Exportar lista</option>
                <option value="pdf">Gerar PDF</option>
                <option value="csv">Gerar CSV</option>
                <option value="print">Imprimir</option>
            </select>

            <!-- Botões de ação -->
            <button class="btn btn-primary" id="novo-cadastro-btn">Novo cadastro</button>
        </div>

        <!-- Tabela -->
        <div class="row table header">Lista de Documentos</div>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $row):
                $processo = $row['Processo'];
                $idCarta = $row['IdCarta'];
                $subtarefas = $row['Subtarefas'] ?? [];
            ?>
            <div class="col full-line register" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
                <div class="row table" id="<?php echo htmlspecialchars($idCarta); ?>">
                    <div class="row">
                        <div class="col">
                            <label class="button-table" title="Expandir detalhes">
                                <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-expanded="false" />
                                <i class="fi fi-rr-angle-small-down"></i>
                            </label>
                        </div>
                        <div class="col filter process-info" title="clique para copiar">
                            <?php
                                $classeNome = $row['ClasseNome'] ?? 'Classe não informada';
                                $classeStyle = $classeNome !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                            ?>
                            <span class="minor-info" <?php echo $classeStyle; ?>><?php echo htmlspecialchars($classeNome); ?></span>
                            <span class="major-info"><?php echo htmlspecialchars($row['Processo']); ?></span>
                        </div>
                    </div>
                    <div class="col filter comarca-info">
                        <span class="minor-info">Comarca de destino</span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Comarca'] . ' - ' . $row['Estado']); ?></span>
                    </div>
                    <div class="col filter detalhes-info">
                        <span class="minor-info"><?php echo htmlspecialchars($row['Finalidade']); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars($row['Prazo'] . ' dias'); ?></span>
                    </div>
                    <div class="row">
                        <div class="col button-table button-edit" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" title="Editar">
                            <i class="fi fi-rr-file-edit"></i>
                        </div>
                        <div class="row allocate-users"></div>
                        <?php
                        $st = $row['Status'] ?? '';
                        $allSt = $row['SubtarefasAll'] ?? [];
                        $progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente', 'pendente', 'pendente'];
                        foreach ($allSt as $s) {
                            $o = (int) ($s['ordem'] ?? 0);
                            if ($o >= 1 && $o <= 6) {
                                $progressStatuses[$o - 1] = (string) ($s['status'] ?? 'pendente');
                            }
                        }
                        $currentSubtarefaTitle = '';
                        foreach ($allSt as $s) {
                            if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                                $currentSubtarefaTitle = $subtarefaLabels[$s['tipo'] ?? ''] ?? $s['tipo'] ?? '';
                                break;
                            }
                        }
                        if ($currentSubtarefaTitle === '' && $st === 'pendente') {
                            $currentSubtarefaTitle = $ordemLabels[1] ?? 'Assinar carta expedida';
                        } elseif ($currentSubtarefaTitle === '' && ($st === 'concluída' || $st === 'finalizada')) {
                            $currentSubtarefaTitle = 'Todas as etapas concluídas';
                        } elseif ($currentSubtarefaTitle === '') {
                            $currentSubtarefaTitle = $row['Status'] ?? 'Em andamento';
                        }
                        $isEmAndamento = in_array($st, ['em_andamento', 'aguardando assinatura', 'aguardando envio', 'aguardando juntada do termo', 'aguardando juntada de termo', 'aguardando cumprimento'], true);
                        $mostrarBarraStatus = ($st !== 'concluída' && $st !== 'cancelada');
                        $segmentosBarra = [];
                        if ($mostrarBarraStatus) {
                            $segmentosBarra[] = ['class' => 'concluida', 'title' => 'Carta expedida e cadastrada'];
                            for ($i = 0; $i < 6; $i++) {
                                $statusSeg = $progressStatuses[$i] ?? 'pendente';
                                $segClass = $statusSeg === 'concluida' ? 'concluida' : ($statusSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                                $segmentosBarra[] = ['class' => $segClass, 'title' => $ordemLabels[$i + 1] ?? ''];
                            }
                        }
                        ?>
                        <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                            <?php
                            if ($st === 'concluída') {
                                echo '<div class="width-sm status status-finished">Finalizada</div>';
                            } elseif ($st === 'cancelada') {
                                echo '<div class="width-sm status status-cancelled">Cancelada</div>';
                            } elseif ($mostrarBarraStatus) {
                                $labelStatus = $isEmAndamento ? 'Em andamento' : 'Pendente';
                                $classStatus = $isEmAndamento ? 'status-current' : 'status-pending';
                                echo '<div class="width-sm status ' . $classStatus . ' status-with-progress">';
                                echo '<span class="status-task-label">' . htmlspecialchars($labelStatus, ENT_QUOTES) . '</span>';
                                $concluidosBarra = 1 + count(array_filter($progressStatuses, fn($x) => $x === 'concluida'));
                                echo '<div class="status-task-progress" role="progressbar" aria-valuenow="' . $concluidosBarra . '" aria-valuemin="0" aria-valuemax="7" aria-label="Progresso das subtarefas">';
                                foreach ($segmentosBarra as $seg) {
                                    echo '<div class="status-task-progress-segment ' . htmlspecialchars($seg['class'], ENT_QUOTES) . '" title="' . htmlspecialchars($seg['title'], ENT_QUOTES) . '"></div>';
                                }
                                echo '</div>';
                                echo '</div>';
                            } else {
                                echo '<div class="width-sm status status-pending">Pendente</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="row subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta); ?>" aria-hidden="true">
                    <?php foreach ($subtarefas as $st): 
                        $stId = $st['id'] ?? '';
                        $stTipo = $st['tipo'] ?? '';
                        $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                        $stStatus = $st['status'] ?? 'pendente';
                        $statusLabel = $stStatus === 'em_andamento' ? 'Em andamento' : ($stStatus === 'concluida' ? 'Concluída' : 'Aguardando');
                    ?>
                    <div class="col full-line" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" style="border-top: 1px solid var(--color-gray-400);">
                        <div class="row subtask-info">
                            <div class="col task-info">
                                <span class="minor-info">Subtarefa</span>
                                <span class="major-info"><?php echo htmlspecialchars($stLabel); ?></span>
                            </div>
                            <div class="row">
                                <?php if ($stTipo === 'aguardando_gerar_pdf'): ?>
                                <div class="col button-table button-subtask" title="Reiniciar subtarefa"><i class="fi fi-rr-refresh"></i></div>
                                <?php endif; ?>
                                <div class="col task-status">
                                    <div class="height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php if ($stTipo === 'aguardando_gerar_pdf'): ?>
                        <div class="row subtask-info">
                            <div class="col filter">
                                <span class="minor-info">Qtd docs gerados</span>
                                <input type="number" class="width-sm height-md subtask-qtd-docs" value="1" min="1" max="10" style="background-color: var(--color-gray-000);" />
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($stTipo === 'aguardando_informacoes'): ?>
                        <div class="row subtask-info" style="justify-content: flex-start;">
                            <div class="col filter">
                                <span class="minor-info">Número processo destino</span>
                                <input type="text" class="width-md height-md subtask-numero-destino" placeholder="Número do processo" style="background-color: var(--color-gray-000);" />
                            </div>
                            <div class="col filter">
                                <span class="minor-info">Vara destino</span>
                                <input type="text" class="width-xl height-md subtask-vara-destino" placeholder="Vara destino" style="background-color: var(--color-gray-000);" />
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($stTipo === 'aguardando_devolucao'): ?>
                        <div class="row subtask-info">
                            <div class="col filter">
                                <span class="minor-info">Resultado</span>
                                <select class="width-lg height-md subtask-resultado-devolucao" style="background-color: var(--color-gray-000);">
                                    <option value="">Selecione</option>
                                    <option value="devolvida_sem_cumprimento">Devolvida sem cumprimento</option>
                                    <option value="cumprida_positivamente">Cumprida positivamente</option>
                                    <option value="cumprida_parcialmente">Cumprida parcialmente</option>
                                    <option value="cumprida_negativamente">Cumprida negativamente</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="row subtask-buttons">
                            <?php if ($stTipo === 'aguardando_assinatura'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Informar assinatura</button>
                            <?php elseif ($stTipo === 'aguardando_gerar_pdf'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Informar PDF's gerados</button>
                            <?php elseif ($stTipo === 'aguardando_envio'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Informar envio realizado</button>
                            <?php elseif ($stTipo === 'aguardando_juntada_termo'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Informar juntada do termo</button>
                            <?php elseif ($stTipo === 'aguardando_informacoes'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Juntar informações</button>
                            <?php elseif ($stTipo === 'aguardando_devolucao'): ?>
                            <button type="button" class="btn btn-primary btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>">Juntar Carta Devolvida</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($subtarefas)): ?>
                    <div class="col full-line">
                        <span class="minor-info">Nenhuma subtarefa pendente.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: // Caso não haja dados para exibir ?>
            <div class="row table">
                <div class="col">
                    <div class="col filter no-data">
                        <span class="major-info">Nenhum documento cadastrado.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Paginação -->
        <div class="row pagination">
            <span class="pagination-info">
                Mostrando <?php echo htmlspecialchars((string) $startItem); ?>-<?php echo htmlspecialchars((string) $endItem); ?> de <?php echo htmlspecialchars((string) $totalDocuments); ?> <?php echo htmlspecialchars($documentsLabel); ?>
                (<?php echo htmlspecialchars((string) $totalPages); ?> <?php echo htmlspecialchars($pagesLabel); ?>, <?php echo htmlspecialchars((string) $itemsPerPage); ?> <?php echo htmlspecialchars($itemsLabel); ?> por página)
            </span>
            <?php if ($totalPages > 0): ?>
                <div class="pagination-controls">
                    <?php
                        $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
                        $prevParams = $baseQueryParams;
                        $prevParams['page_number'] = $prevPage;
                        $prevHref = '?' . http_build_query($prevParams);
                        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $currentPage;
                        $nextParams = $baseQueryParams;
                        $nextParams['page_number'] = $nextPage;
                        $nextHref = '?' . http_build_query($nextParams);
                    ?>
                    <a class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>"
                       <?php if ($currentPage > 1): ?>href="<?php echo htmlspecialchars($prevHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </a>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php
                            $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page;
                        ?>
                        <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <a class="pagination-symbol pagination-ellipsis" aria-hidden="true">
                            <img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas">
                        </a>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php
                                $pageParams = $baseQueryParams;
                                $pageParams['page_number'] = $page;
                            ?>
                            <a class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" href="?<?php echo htmlspecialchars(http_build_query($pageParams)); ?>">
                                <?php echo $page; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
                       <?php if ($currentPage < $totalPages): ?>href="<?php echo htmlspecialchars($nextHref); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </a>
                </div>
            <?php endif; ?>
        </div> 
    </div>

    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/cartorio/cartas-expedidas/search-list.js"></script>
    <script>
        (function () {
            if (window.CartasExpedidasSearch && typeof window.CartasExpedidasSearch.initializeProcessSearch === 'function') {
                window.CartasExpedidasSearch.initializeProcessSearch({
                    inputId: 'search-input',
                    itemsPerPageId: 'items-per-page',
                    filters: [
                        { id: 'filter-tipo', param: 'finalidade' },
                        { id: 'filter-status', param: 'status' },
                    ],
                    minSearchDigits: 3,
                    debounceDelay: 1000,
                    initialProcessFilter: <?php echo json_encode($processoFilter, JSON_UNESCAPED_UNICODE); ?>,
                });
            }

            const novoCadastroBtn = document.getElementById('novo-cadastro-btn');
            if (novoCadastroBtn) {
                novoCadastroBtn.addEventListener('click', function () {
                    window.location.href = '/frame.php?page=cartasexpedidascreate';
                });
            }

            // Copiar número do processo ao clicar
            document.querySelectorAll('.process-info .major-info').forEach(function(el) {
                el.addEventListener('click', function() {
                    const text = el.textContent.trim();
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text);
                    } else {
                        const tempInput = document.createElement('input');
                        tempInput.value = text;
                        document.body.appendChild(tempInput);
                        tempInput.select();
                        document.execCommand('copy');
                        document.body.removeChild(tempInput);
                    }
                });
            });
            document.querySelectorAll('.button-edit').forEach(function (button) {
                button.addEventListener('click', function () {
                    const cartaId = button.dataset.cartaId;
                    if (!cartaId) return;
                    window.location.href = '/frame.php?page=cartasexpedidasedit&id=' + encodeURIComponent(cartaId);
                });
            });

            // Expandir/recolher subtarefa ao selecionar o radio
            document.querySelectorAll('input[name="open-subtask"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    const cartaId = this.value;
                    const targetId = 'subtask-' + cartaId;
                    document.querySelectorAll('.row.subtask').forEach(function (subtaskRow) {
                        const isTarget = subtaskRow.id === targetId;
                        subtaskRow.classList.toggle('subtask-collapsed', !isTarget);
                        subtaskRow.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                    document.querySelectorAll('input[name="open-subtask"]').forEach(function (r) {
                        r.setAttribute('aria-expanded', r.value === cartaId ? 'true' : 'false');
                    });
                });
            });

            // Concluir subtarefa
            document.querySelectorAll('.btn-concluir-subtarefa').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    const block = this.closest('.col.full-line[data-subtarefa-id]');
                    let dadosEspecificos = null;
                    if (block) {
                        const qtdEl = block.querySelector('.subtask-qtd-docs');
                        const numeroDestino = block.querySelector('.subtask-numero-destino');
                        const varaDestino = block.querySelector('.subtask-vara-destino');
                        const resultadoDevolucao = block.querySelector('.subtask-resultado-devolucao');
                        const obj = {};
                        if (qtdEl) obj.qtd_docs_gerados = parseInt(qtdEl.value, 10) || 1;
                        if (numeroDestino && numeroDestino.value.trim()) obj.numero_processo_destino = numeroDestino.value.trim();
                        if (varaDestino && varaDestino.value.trim()) obj.vara_destino = varaDestino.value.trim();
                        if (resultadoDevolucao && resultadoDevolucao.value) obj.resultado_devolucao = resultadoDevolucao.value;
                        if (Object.keys(obj).length > 0) dadosEspecificos = JSON.stringify(obj);
                    }
                    btn.disabled = true;
                    fetch('/api/cartorio-cartas-expedidas/concluir-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            subtarefa_id: subtarefaId,
                            dados_especificos: dadosEspecificos
                        })
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert(data.error || 'Erro ao concluir subtarefa.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        alert('Erro de conexão.');
                        btn.disabled = false;
                    });
                });
            });
        })();
    </script>

</body>
</html>
