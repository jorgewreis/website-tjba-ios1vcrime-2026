<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/OficiosCartorioService.php';
require_once __DIR__ . '/../../repositories/OficiosCartorioSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/CartorioAlocacaoChipHelper.php';
require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
require_once __DIR__ . '/../../helpers/OficioRegistroMetadataHelper.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleNameAllocOficios = 'ALLOCATE-CARTORIOOFICIOS';
try {
    $regrasRepoAlloc = new AlocacoesRegrasRepository($connection);
    $ruleRowAlloc = $regrasRepoAlloc->findByRegra($ruleNameAllocOficios);
    $allocatedUserIdAlloc = $ruleRowAlloc['usuario_id'] ?? null;
    if ($allocatedUserIdAlloc !== null && $allocatedUserIdAlloc !== '') {
        $stmtAlloc = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmtAlloc->execute([$allocatedUserIdAlloc]);
        $uAlloc = $stmtAlloc->fetch(PDO::FETCH_ASSOC);
        if ($uAlloc !== false) {
            $nomeAlloc = trim((string) ($uAlloc['nome'] ?? ''));
            $sobrenomeAlloc = trim((string) ($uAlloc['sobrenome'] ?? ''));
            $ruleServidor = $sobrenomeAlloc !== '' ? $nomeAlloc . ' ' . $sobrenomeAlloc : $nomeAlloc;
            $hasRuleAllocation = $ruleServidor !== '';
        }
    }
} catch (\Throwable $e) {
    // mantém padrão
}

$currentUserId   = trim((string) ($_SESSION['id_usuario'] ?? ''));
$perfisGestaoLst = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestao        = false;
if ($currentUserId !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $perfisUsuario = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId);
    $isGestao = count(array_intersect($perfisUsuario, $perfisGestaoLst)) > 0;
}

$routePage = trim((string) ($_GET['page'] ?? 'oficioscartorio'));
$statusFilter = trim($_GET['status'] ?? '');
$oficioFilter = trim($_GET['oficio'] ?? '');
$allowedPerPage = [15];
$itemsPerPage = 15;
if (!in_array($itemsPerPage, $allowedPerPage, true)) {
    $itemsPerPage = 15;
}

$pageNumberParam = $_GET['page_number'] ?? null;
if ($pageNumberParam !== null && $pageNumberParam !== '') {
    $currentPage = max(1, (int) $pageNumberParam);
} else {
    $currentPage = 1;
}

$offset = ($currentPage - 1) * $itemsPerPage;

try {
    $service = new OficiosCartorioService($connection);
    /** @var list<int> $anosComOficios */
    $anosComOficios = $service->listAnosComOficios();
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$anoAtual = (int) date('Y');
$anoFromGet = trim($_GET['ano'] ?? '');
if ($anoFromGet !== '' && ctype_digit($anoFromGet) && in_array((int) $anoFromGet, $anosComOficios, true)) {
    $anoFilter = $anoFromGet;
} elseif ($anosComOficios !== []) {
    $anoFilter = in_array($anoAtual, $anosComOficios, true) ? (string) $anoAtual : (string) $anosComOficios[0];
} else {
    $anoFilter = '';
}

$filters = [
    'status' => $statusFilter,
    'oficio' => $oficioFilter,
    'ano' => $anoFilter,
];

try {
    $listResult = $service->listOficios($filters, $itemsPerPage, $offset);
} catch (Throwable $exception) {
    die('Erro ao carregar os dados: ' . $exception->getMessage());
}

$rows = $listResult['rows'] ?? [];
$rows = ProcessoBlocoListagemHelper::appendBlocoData($connection, $rows);
$oficioListViewerContext = OficioRegistroMetadataHelper::viewerContextFromSession();
$totalDocuments = (int) ($listResult['total'] ?? 0);

if ($totalDocuments === 0) {
    $totalPages = 1;
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

$documentsLabel = $totalDocuments === 1 ? 'ofício' : 'ofícios';
$pagesLabel = $totalPages === 1 ? 'página' : 'páginas';
$itemsLabel = $itemsPerPage === 1 ? 'item' : 'itens';

$statusOptionsUi = ['pendente', 'finalizada', 'cancelada'];
$statusCountsRaw = $service->getStatusCounts($filters);
$statusCounts = array_merge(array_fill_keys($statusOptionsUi, 0), $statusCountsRaw);

$subtarefaLabels = [
    OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Enviar ofício',
    OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Juntar termo de envio',
];
$buttonTitleByTipo = [
    OficiosCartorioSubtarefasRepository::TIPO_ENVIAR => 'Informar envio do ofício',
    OficiosCartorioSubtarefasRepository::TIPO_JUNTAR => 'Informar juntada do termo',
];

$baseQueryParams = [];
if ($routePage !== '') {
    $baseQueryParams['page'] = $routePage;
}
if ($oficioFilter !== '') {
    $baseQueryParams['oficio'] = $oficioFilter;
    if ($anoFilter !== '') {
        $baseQueryParams['ano'] = $anoFilter;
    }
} else {
    if ($statusFilter !== '') {
        $baseQueryParams['status'] = $statusFilter;
    }
    if ($anoFilter !== '') {
        $baseQueryParams['ano'] = $anoFilter;
    }
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

/**
 * @param array<string,mixed> $row
 */
function cartorio_oficios_label_cumprimento(array $row): string
{
    $forma = (string) ($row['FormaCumprimento'] ?? '');
    if ($forma === 'ccm') {
        return 'CCM';
    }
    $meio = (string) ($row['MeioEnvio'] ?? '');
    $map = [
        'email' => 'E-mail',
        'malote_digital' => 'Malote digital',
        'pessoalmente' => 'Pessoalmente',
    ];
    return 'Cartório — ' . ($map[$meio] ?? $meio);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Ofícios - Cartório - Primeira Vara Crime de Ilhéus</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
</head>

<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Ofícios -</span>
            <span class="specific">Lista</span>
        </div>
    </div>

    <div class="container-full card-page">

        <div class="card-title">
            <div class="display-flex col col-fit justify-start">
                <h1 class="title-page" style="color: var(--color-primary-active);">Ofícios</h1>
            </div>
            <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md">

            </div>
        </div>

        <div class="display-flex col col-full padding-x-lg padding-y-sm gap-y-sm border-bottom">
            <div class="card-allocate-rule">
                <?php if ($hasRuleAllocation): ?>
                    <span>*Conforme regra de alocação, o envio de ofícios será alocado automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
                <?php else: ?>
                    <span>*Não existe nenhum usuário alocado automaticamente para esta tarefa no momento.</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="filter-processo" class="display-flex col gap-y-sm" style="align-items: flex-start;">
                        <span>Número do processo <span id="cartorio-processo-hint" class="label-hint-obrigatorio">*obrigatório</span></span>
                        <span id="processo-ap-info" class="minor-info" aria-live="polite"></span>
                    </label>
                    <input type="text" class="width-400 height-md" id="filter-processo" name="processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                </div>
                <div class="col">
                    <label for="oficio-tipo">Tipo de ofício <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="oficio-tipo" name="tipo_oficio">
                        <option value="">Selecione…</option>
                        <optgroup label="Solicitação">
                            <option value="Ofício Solicitando Reativação do CPF">Ofício solicitando Reativação do CPF</option>
                            <option value="Ofício Solicitando Suspensão do CPF">Ofício solicitando Suspensão do CPF</option>
                            <option value="Ofício Solicitando Agendamento de Audiência">Ofício solicitando Agendamento de Audiência</option>
                        </optgroup>
                        <optgroup label="Encaminhamento">
                            <option value="Ofício Encaminhando Decisão de Medida Protetiva">Ofício encaminhando Decisão de Medida Protetiva</option>
                            <option value="Ofício Encaminhando Decisão Interlocutória">Ofício encaminhando Decisão Interlocutória</option>
                            <option value="Ofício Encaminhando Documentos">Ofício encaminhando Documentos</option>
                            <option value="Ofício Encaminhando Sentença">Ofício encaminhando Sentença</option>
                            <option value="Ofício Encaminhando Informação">Ofício encaminhando Informação</option>
                        </optgroup>
                        <optgroup label="Informando">
                            <option value="Ofício Informando Baixa ao Cedep">Ofício informando Baixa ao Cedep</option>
                            <option value="Ofício Informando Condenação ao CEDEP">Ofício informando Condenação ao CEDEP</option>
                        </optgroup>
                        <optgroup label="Requisitando">
                            <option value="Ofício Requisitando Policial para Audiência">Ofício requisitando Policial para Audiência</option>
                            <option value="Ofício Requisitando Preso para Audiência">Ofício requisitando Preso para Audiência</option>
                            <option value="Ofício Requisitando Servidor para Audiência">Ofício requisitando Servidor para Audiência</option>
                            <option value="Ofício Requisitando Informações">Ofício requisitando Informações</option>
                        </optgroup>
                        <optgroup label="Administrativos">
                            <option value="Ofício Solicitando Férias" data-oficio-grupo="administrativo">Ofício solicitando Férias</option>
                            <option value="Ofício Solicitando Licença Prêmio" data-oficio-grupo="administrativo">Ofício solicitando Licença Prêmio</option>
                            <option value="Ofício Solicitando Reembolso" data-oficio-grupo="administrativo">Ofício solicitando Reembolso</option>
                            <option value="Ofício Solicitando Afastamento" data-oficio-grupo="administrativo">Ofício solicitando Afastamento</option>
                            <option value="Ofício Diversos" data-oficio-grupo="administrativo">Ofício diversos</option>
                        </optgroup>
                    </select>
                </div>
                <div class="col">
                    <label for="oficio-forma-cumprimento">Forma de cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="oficio-forma-cumprimento" name="forma_cumprimento">
                        <option value="ccm" selected>por Oficial de Justiça (CCM)</option>
                        <option value="cartorio">pelo Cartório</option>
                    </select>
                </div>

                <input type="hidden" id="filter-classe" data-datajud-related="true" value="" autocomplete="off">
                <div class="col" id="oficio-meio-envio-row">
                    <label for="oficio-meio-envio">Meio de envio</label>
                    <select class="width-400 height-md" id="oficio-meio-envio" name="meio_envio">
                        <option value="">Selecione…</option>
                        <option value="email">E-mail</option>
                        <option value="malote_digital">Malote digital</option>
                        <option value="pessoalmente">Pessoalmente</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-buttons padding-x-lg padding-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-md">

                <div class="display-flex row row-fit justify-end align-center gap-x-md gap-y-md flex-wrap">
                    <input type="text" class="height-md list-search-input" id="search-input" placeholder="Pesquisar" aria-label="Pesquisar por número do ofício (com ou sem ano) ou por número do processo CNJ" value="<?php echo htmlspecialchars($oficioFilter, ENT_QUOTES); ?>">
                    <?php
                $listExportPage = $routePage;
                $listExportQuery = $baseQueryParams;
                include __DIR__ . '/_list_export_buttons.php';
                ?>
                </div>
<button type="button" class="btn btn-secondary-outline" id="btn-oficio-limpar">Limpar</button>
            </div>
            <div class="display-flex row row-fit justify-start gap-y-md">
                <button type="button" class="btn btn-primary-outline" id="alterar-regra-btn">Alterar regra de alocação</button>
                <button type="button" class="btn btn-primary" id="btn-oficio-registrar">Cadastrar</button>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full justify-between padding-x-lg padding-y-md border-bottom border-top">
            <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-gray-900); font-size: 13px;">Filtros</span>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md align-center">
                <select class="width-500 height-md" id="filter-status">
                    <option value="" <?php echo $statusFilter === '' ? ' selected' : ''; ?>>Todos os status</option>
                    <option value="pendente" <?php echo $statusFilter === 'pendente' ? ' selected' : ''; ?>>
                        Pendente (<?php echo htmlspecialchars((string) ($statusCounts['pendente'] ?? 0)); ?>)
                    </option>
                    <option value="finalizada" <?php echo $statusFilter === 'finalizada' ? ' selected' : ''; ?>>
                        Finalizada (<?php echo htmlspecialchars((string) ($statusCounts['finalizada'] ?? 0)); ?>)
                    </option>
                    <option value="cancelada" <?php echo $statusFilter === 'cancelada' ? ' selected' : ''; ?>>
                        Cancelada (<?php echo htmlspecialchars((string) ($statusCounts['cancelada'] ?? 0)); ?>)
                    </option>
                </select>
                <select class="width-300 height-md" id="filter-ano" <?php echo $anosComOficios === [] ? ' disabled' : ''; ?>>
                    <?php if ($anosComOficios === []): ?>
                        <option value="">—</option>
                    <?php else: ?>
                        <?php foreach ($anosComOficios as $y): ?>
                            <option value="<?php echo (int) $y; ?>" <?php echo $anoFilter === (string) $y ? ' selected' : ''; ?>><?php echo (int) $y; ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

            </div>
        </div>

        <div class="display-flex col col-full register">
            <?php
            $activeFilters = [];
            if ($statusFilter !== '') {
                $statusFilterLabels = [
                    'pendente' => 'Pendente',
                    'finalizada' => 'Finalizada',
                    'cancelada' => 'Cancelada',
                ];
                $statusFilterDisplay = $statusFilterLabels[$statusFilter] ?? $statusFilter;
                $activeFilters[] = 'Status [' . htmlspecialchars($statusFilterDisplay, ENT_QUOTES) . ']';
            }
            if ($oficioFilter !== '') {
                $buscaDigits = preg_replace('/\D+/', '', $oficioFilter);
                $buscaRotulo = strlen($buscaDigits) === 20 ? 'Processo' : 'Ofício';
                $activeFilters[] = $buscaRotulo . ' [' . htmlspecialchars($oficioFilter, ENT_QUOTES) . ']';
            }
            if ($anoFilter !== '' && $anosComOficios !== []) {
                $activeFilters[] = 'Ano [' . htmlspecialchars($anoFilter, ENT_QUOTES) . ']';
            }

            if (!empty($activeFilters)) {
                echo '
                    <div class="display-flex row row-full justify-between padding-x-lg padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);">
                        <div class="display-flex row row-fit justify-start align-center padding-x-sm padding-y-sm gap-y-sm">
                            <span class="text text-ellipsis" style="font-weight: 400; color: var(--color-primary-active); font-size: 12px;">
                                Lista com os seguintes filtros:
                            </span>
                            <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                                ' . implode(' + ', $activeFilters) . '
                            </span>
                        </div>
                    </div>';
            }
            ?>

            <div class="display-flex row row-full padding-x-sm padding-y-sm align-center justify-start border-bottom"
                style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex col col-fit align-center justify-start padding-x-lg padding-y-sm">
                    <span class="text" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php
                        $totalRegistros = (int) ($totalDocuments ?? 0);
                        if ($totalRegistros === 0) {
                            echo 'Nenhum registro encontrado';
                        } else {
                            $totalRegistrosText = $totalRegistros === 1 ? 'registro encontrado' : 'registros encontrados';
                            echo htmlspecialchars((string) $totalRegistros, ENT_QUOTES) . ' ' . htmlspecialchars($totalRegistrosText, ENT_QUOTES);
                        }
                        ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $row):
                    $idOficio = (string) ($row['IdOficio'] ?? '');
                    $numSeq = (int) ($row['NumeroSeq'] ?? 0);
                    $ano = (int) ($row['Ano'] ?? 0);
                    $nAno = $numSeq . '/' . $ano;
                    $subtarefas = $row['Subtarefas'] ?? [];
                    $allSt = $row['SubtarefasAll'] ?? [];
                    $statusTarefa = (string) ($row['Status'] ?? '');
                    $formaC = (string) ($row['FormaCumprimento'] ?? '');
                    $tarefaConcluida = in_array($statusTarefa, ['finalizada'], true);
                    $tarefaCancelada = ($statusTarefa === 'cancelada');
                    $mostrarBarraStatus = ($statusTarefa === 'pendente' && $formaC === 'cartorio');
                    $progressStatuses = ['pendente', 'pendente'];
                    foreach ($allSt as $s) {
                        $o = (int) ($s['ordem'] ?? 0);
                        if ($o >= 1 && $o <= 2) {
                            $stSeg = (string) ($s['status'] ?? 'pendente');
                            $progressStatuses[$o - 1] = $stSeg === 'concluida' ? 'concluida' : ($stSeg === 'em_andamento' ? 'em_andamento' : 'pendente');
                        }
                    }
                    $currentSubtarefaTitle = '';
                    foreach ($allSt as $s) {
                        if (trim((string) ($s['status'] ?? '')) === 'em_andamento') {
                            $tipoS = (string) ($s['tipo'] ?? '');
                            $currentSubtarefaTitle = $subtarefaLabels[$tipoS] ?? $tipoS;
                            break;
                        }
                    }
                    if ($currentSubtarefaTitle === '' && $statusTarefa === 'pendente' && $formaC === 'cartorio') {
                        $currentSubtarefaTitle = $subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_ENVIAR] ?? '';
                    }
                    $segmentosBarra = [];
                    if ($mostrarBarraStatus) {
                        for ($i = 0; $i < 2; $i++) {
                            $statusSeg = $progressStatuses[$i] ?? 'pendente';
                            if ($statusSeg === 'concluida') {
                                $segClass = 'concluida';
                            } elseif ($statusSeg === 'em_andamento') {
                                $segClass = 'em_andamento';
                            } else {
                                $segClass = 'pendente';
                            }
                            $ordemSeg = $i + 1;
                            $titleSeg = $ordemSeg === 1 ? ($subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_ENVIAR] ?? '') : ($subtarefaLabels[OficiosCartorioSubtarefasRepository::TIPO_JUNTAR] ?? '');
                            $segmentosBarra[] = ['class' => $segClass, 'title' => $titleSeg];
                        }
                    }
                    $expandir = !$tarefaConcluida && !$tarefaCancelada && $formaC === 'cartorio';
                    $mostrarBadgePendente = !$tarefaCancelada && !$tarefaConcluida;
                    $oficioMetadata = OficioRegistroMetadataHelper::fromListRow($row, 'cartorio', $oficioListViewerContext);
                ?>
                    <div class="display-flex col col-full register register-oficio-list register-with-metadata border-bottom" data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>">

                        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center register-oficio-list-row">
                            <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-1">
                                <div class="col">
                                    <?php if ($expandir): ?>
                                        <label class="button-table" title="Expandir detalhes">
                                            <input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-expanded="false" />
                                            <i class="fi fi-rr-angle-small-down"></i>
                                        </label>
                                    <?php else: ?>
                                        <span class="button-table" style="visibility:hidden;" aria-hidden="true"><i class="fi fi-rr-angle-small-down"></i></span>
                                    <?php endif; ?>
                                </div>
                                <div class="col width-200">
                                    <span class="minor-info">Ofício</span>
                                    <span class="major-info"><?php echo htmlspecialchars($nAno, ENT_QUOTES); ?></span>
                                </div>
                                <div class="col width-500 col-process">
                                    <?php include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                                </div>
                                <div class="col width-700">
                                    <span class="minor-info">Tipo</span>
                                    <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($row['TipoOficio'] ?? '')); ?></span>
                                </div>
                            </div>
                            <?php
                            $alocadosLista = CartorioAlocacaoChipHelper::filterForOficiosList($row['Alocados'] ?? [], $row);
                            $alocadosLista = CartorioAlocacaoChipHelper::ordenarAlocadosPorTipo($alocadosLista);
                            if ($alocadosLista !== []):
                            ?>
                            <div class="display-flex row row-fit justify-end gap-y-sm align-center allocate-chips-row allocate-users">
                                    <?php
                                    foreach ($alocadosLista as $alocado) {
                                        $chip = CartorioAlocacaoChipHelper::buildChipData($alocado, [
                                            'entity_id' => (string) ($idOficio ?? ''),
                                            'origem' => 'oficios.list',
                                        ]);
                                        if ($chip === null) {
                                            continue;
                                        }
                                        $tipo         = $chip['tipo'];
                                        $iniciais     = htmlspecialchars($chip['iniciais'], ENT_QUOTES);
                                        $chipTitle    = $chip['title'];
                                        $chipAria     = $chip['aria_label'];
                                        $classeTipo   = $chip['classe_tipo'];
                                        $alocadoIdStr = htmlspecialchars($chip['alocado_id'], ENT_QUOTES);
                                        $usuarioIdStr = $chip['usuario_id'];
                                        $podeRemover  = $isGestao || ($tipo === 'specific' && $usuarioIdStr === $currentUserId);
                                    ?>
                                        <span class="user-allocate-wrap">
                                            <span class="user-allocate<?php echo $classeTipo; ?>" title="<?php echo htmlspecialchars($chipTitle, ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars($chipAria, ENT_QUOTES); ?>"><?php echo $iniciais; ?></span>
                                            <?php if ($podeRemover): ?>
                                                <button type="button"
                                                    class="user-allocate-remove js-desalocar"
                                                    data-alocado-id="<?php echo $alocadoIdStr; ?>"
                                                    data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"
                                                    data-endpoint="/api/cartorio-oficios/desalocar.php"
                                                    title="Remover alocação"
                                                    aria-label="Remover alocação">×</button>
                                            <?php endif; ?>
                                        </span>
                                    <?php
                                    }
                                    ?>
                            </div>
                            <?php endif; ?>
                                <?php if ($mostrarBadgePendente): ?>
                                    <div class="col oficio-list-status-col<?php echo $mostrarBarraStatus ? ' status-task' : ''; ?>" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES); ?>">
                                        <?php if ($mostrarBarraStatus): ?>
                                            <div class="width-300 status status-pending status-with-progress">
                                                <span class="status-task-label width-300">Pendente</span>
                                                <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo (int) count(array_filter($progressStatuses, static function ($x) {
                                                    return $x === 'concluida';
                                                })); ?>" aria-valuemin="0" aria-valuemax="2">
                                                    <?php foreach ($segmentosBarra as $seg): ?>
                                                        <div class="status-task-progress-segment <?php echo htmlspecialchars($seg['class'], ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($seg['title'], ENT_QUOTES); ?>"></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="width-300 padding-x-lg status status-pending">Pendente</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php include __DIR__ . '/_oficio_registro_metadata.php'; ?>
                        </div>

                        <?php if ($expandir): ?>
                            <div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idOficio, ENT_QUOTES); ?>" aria-hidden="true">
                                <?php foreach ($subtarefas as $st):
                                    $stId = (string) ($st['id'] ?? '');
                                    $stTipo = (string) ($st['tipo'] ?? '');
                                    $stLabel = $subtarefaLabels[$stTipo] ?? $stTipo;
                                ?>
                                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
                                        <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                                            <div class="col task-status">
                                                <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($stLabel); ?></div>
                                            </div>
                                        </div>
                                        <div class="display-flex row row-fit justify-end align-center">
                                            <button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa-oficio" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?>">
                                                <span class="value-button"><?php echo htmlspecialchars($buttonTitleByTipo[$stTipo] ?? $stLabel, ENT_QUOTES); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($subtarefas)): ?>
                                    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top">
                                        <span class="minor-info">Nenhuma sub-tarefa pendente.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md">
            <span class="pagination-info">
                Mostrando <?php echo $startItem; ?>-<?php echo $endItem; ?> de <?php echo $totalDocuments; ?> <?php echo $documentsLabel; ?>
                (<?php echo $totalPages; ?> <?php echo $pagesLabel; ?>, <?php echo $itemsPerPage; ?> <?php echo $itemsLabel; ?> por página)
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
                    <button type="button" class="pagination-symbol<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" <?php if ($currentPage > 1): ?>data-nav-href="<?php echo htmlspecialchars($prevHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Left-2--Streamline-Ultimate.png" alt="Página anterior">
                    </button>
                    <?php foreach ($paginationButtons as $page): ?>
                        <?php $pageParams = $baseQueryParams;
                        $pageParams['page_number'] = $page; ?>
                        <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                    <?php endforeach; ?>
                    <?php if ($paginationEllipsis && !empty($trailingPages)): ?>
                        <span class="pagination-symbol pagination-ellipsis" aria-hidden="true"><img src="/assets/icons/Navigation-Menu-Horizontal--Streamline-Ultimate.png" alt="Mais páginas"></span>
                        <?php foreach ($trailingPages as $page): ?>
                            <?php $pageParams = $baseQueryParams;
                            $pageParams['page_number'] = $page; ?>
                            <button type="button" class="pagination-number<?php echo $page === $currentPage ? ' active' : ''; ?>" <?php if ($page !== $currentPage): ?>data-nav-href="?<?php echo htmlspecialchars(http_build_query($pageParams), ENT_QUOTES); ?>" <?php else: ?>disabled<?php endif; ?>><?php echo $page; ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <button type="button" class="pagination-symbol<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" <?php if ($currentPage < $totalPages): ?>data-nav-href="<?php echo htmlspecialchars($nextHref, ENT_QUOTES); ?>" <?php else: ?>disabled aria-disabled="true" <?php endif; ?>>
                        <img src="/assets/icons/Arrow-Button-Right-2--Streamline-Ultimate.png" alt="Próxima página">
                    </button>
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

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>
        (function() {

            if (window.App && App.ListSearch && typeof App.ListSearch.initializeProcessSearch === 'function') {
                App.ListSearch.initializeProcessSearch({
                    inputId: 'search-input',                    searchParamKey: 'oficio',
                    filters: [{
                            id: 'filter-status',
                            param: 'status'
                        },
                        {
                            id: 'filter-ano',
                            param: 'ano'
                        },
                    ],
                    minSearchDigits: 1,
                    debounceDelay: 1000,
                    initialProcessFilter: <?php echo json_encode($oficioFilter, JSON_UNESCAPED_UNICODE); ?>,
                });
            }
        })();
    </script>
    <script>
        (function() {
            const formaSel = document.getElementById('oficio-forma-cumprimento');
            const meioRow = document.getElementById('oficio-meio-envio-row');
            const tipoSel = document.getElementById('oficio-tipo');
            const meioSel = document.getElementById('oficio-meio-envio');
            const tiposCedepEmail = new Set([
                'Ofício Informando Baixa ao Cedep',
                'Ofício Informando Condenação ao CEDEP'
            ]);

            function toggleMeioEnvio() {
                if (!meioRow || !formaSel) return;
                meioRow.style.display = formaSel.value === 'cartorio' ? '' : 'none';
            }

            function aplicarPadraoCedep() {
                if (!tipoSel || !formaSel || !meioSel || !tiposCedepEmail.has(tipoSel.value)) return;
                formaSel.value = 'cartorio';
                meioSel.value = 'email';
                toggleMeioEnvio();
            }

            formaSel?.addEventListener('change', toggleMeioEnvio);
            tipoSel?.addEventListener('change', aplicarPadraoCedep);

            if (!window.App || !App.OficiosList || typeof App.OficiosList.initialize !== 'function') {
                return;
            }

            const ctx = App.OficiosList.initialize({
                storageKey: 'cartorio.postReloadToast',
                highlightField: 'highlight_oficio_id',
                routePage: '<?php echo htmlspecialchars($routePage, ENT_QUOTES); ?>',
                typeSelectId: 'oficio-tipo',
                processHintId: 'cartorio-processo-hint',
                processLabelSelector: 'label[for="filter-processo"]',
                clearButtonId: 'btn-oficio-limpar',
                createButtonId: 'btn-oficio-registrar',
                createEndpoint: '/api/cartorio-oficios/create.php',
                enableDesalocar: true,
                cancelEndpoint: '/api/cartorio-oficios/cancel.php',
                onClear: function() {
                    const meio = document.getElementById('oficio-meio-envio');
                    const forma = document.getElementById('oficio-forma-cumprimento');
                    if (meio) meio.selectedIndex = 0;
                    if (forma) {
                        const optCcm = forma.querySelector('option[value="ccm"]');
                        if (optCcm) {
                            forma.value = 'ccm';
                        } else {
                            forma.selectedIndex = 0;
                        }
                    }
                    toggleMeioEnvio();
                },
                buildRequest: function(sharedCtx) {
                    const proc = document.getElementById('filter-processo')?.value?.trim() || '';
                    const tipo = document.getElementById('oficio-tipo')?.value || '';
                    const forma = document.getElementById('oficio-forma-cumprimento')?.value || '';
                    const meio = document.getElementById('oficio-meio-envio')?.value || '';
                    let cc = document.getElementById('hidden-classe-codigo')?.value || '';
                    let cd = document.getElementById('hidden-classe-descricao')?.value || '';
                    const admin = sharedCtx.isAdministrative();

                    if (!tipo || !forma) {
                        sharedCtx.notify('error', 'Validação', 'Preencha tipo e forma de cumprimento.');
                        return null;
                    }
                    if (!admin && !proc) {
                        sharedCtx.notify('error', 'Validação', 'Preencha o número do processo.');
                        return null;
                    }
                    if (!admin) {
                        const classeVal = document.getElementById('filter-classe')?.value?.trim() || '';
                        if (classeVal === 'Aguarde...' || classeVal === 'Consultando DataJud…') {
                            sharedCtx.notify('error', 'Validação', 'Aguarde a consulta ao DataJud terminar antes de cadastrar.');
                            return null;
                        }
                    }
                    if (forma === 'cartorio' && !meio) {
                        sharedCtx.notify('error', 'Validação', 'Selecione o meio de envio.');
                        return null;
                    }
                    if (admin && !proc) {
                        cc = '';
                        cd = '';
                    }

                    return {
                        numero_processo: proc,
                        tipo_oficio: tipo,
                        forma_cumprimento: forma,
                        meio_envio: forma === 'cartorio' ? meio : '',
                        classe_codigo: cc,
                        classe_descricao: cd
                    };
                },
                buildSuccessPayload: function(data) {
                    const numeroOficio = (data.numero_oficio || '').toString().trim();
                    return {
                        type: 'success',
                        title: 'Sucesso',
                        message: numeroOficio ? ('Ofício ' + numeroOficio + ' registrado com sucesso.') : 'Ofício registrado com sucesso.',
                        highlight_oficio_id: data.id
                    };
                }
            });

            toggleMeioEnvio();

            document.querySelectorAll('input[name="open-subtask"]').forEach(function(radio) {
                radio.addEventListener('change', function() {
                    const targetId = 'subtask-' + this.value;
                    document.querySelectorAll('.col.subtask').forEach(function(row) {
                        const isTarget = row.id === targetId;
                        row.classList.toggle('subtask-collapsed', !isTarget);
                        row.setAttribute('aria-hidden', isTarget ? 'false' : 'true');
                    });
                });
            });

            document.querySelectorAll('.btn-concluir-subtarefa-oficio').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const subtarefaId = this.getAttribute('data-subtarefa-id');
                    if (!subtarefaId) return;
                    this.disabled = true;
                    fetch('/api/cartorio-oficios/concluir-subtarefa.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                subtarefa_id: subtarefaId
                            }),
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.json();
                        })
                        .then(function(data) {
                            if (data.success) {
                                ctx.queuePostReloadToast({
                                    type: 'success',
                                    title: 'Sucesso',
                                    message: 'Subtarefa concluída com sucesso.'
                                });
                                window.location.reload();
                            } else {
                                ctx.notify('error', 'Erro', data.error || 'Erro ao concluir.');
                                btn.disabled = false;
                            }
                        })
                        .catch(function() {
                            ctx.notify('error', 'Erro', 'Erro de conexão.');
                            btn.disabled = false;
                        });
                });
            });
        })();

        (function() {
            window.addEventListener('DOMContentLoaded', function() {
                if (!window.App || !App.User || typeof App.User.setupRuleButton !== 'function') return;
                App.User.setupRuleButton({
                    buttonId: 'alterar-regra-btn',
                    targetPage: 'oficiosrules'
                });
            });
        })();
    </script>
</body>

</html>
