<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
$routePage = trim((string) ($_GET['page'] ?? 'produtividade'));
$today = date('Y-m-d');

$filters = [
    'status' => trim((string) ($_GET['status'] ?? 'ativo')),
    'tipo_lancamento' => trim((string) ($_GET['tipo_lancamento'] ?? 'manual')),
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

if ($userId !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userId]);
    $userPerfis = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
    $canSeeRegrasButton = in_array('ADMIN', $userPerfis, true) || in_array('CHEFIA', $userPerfis, true);
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

$totalPages = $totalDocuments > 0 ? (int) ceil($totalDocuments / $itemsPerPage) : 0;
if ($totalPages > 0 && $currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
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
        <p class="description-page">Lançamentos de produtividade com pontuação por ato e cadastro manual.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Registrar produtividade</h2>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <?php if ($canSeeRegrasButton): ?>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Regras</button>
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

        <form method="get" id="filtro-produtividade-form" class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
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
                <button type="submit" class="btn btn-primary height-md">Filtrar</button>
            </div>
        </form>

        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom gap-y-md">
            <span class="status status-current padding-x-md">Total de lançamentos: <?php echo $totalDocuments; ?></span>
            <span class="status status-success padding-x-md">Pontos no filtro: <?php echo number_format($sumPontos, 2, ',', '.'); ?></span>
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
                $descricaoTarefa = implode(' - ', [
                    $tipoDoc !== '' ? $tipoDoc : 'Sem documento',
                    $tipoAto !== '' ? $tipoAto : 'Sem ato',
                    $sistemaModulo !== '' ? $sistemaModulo : 'Sem sistema',
                ]);
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
                            <div class="col width-500">
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

        <?php if ($errors === [] && $totalDocuments > 0): ?>
        <div class="display-flex row row-full justify-end align-center gap-y-sm padding-x-md padding-y-md border-top">
            <span class="minor-info">Página <?php echo max(1, $currentPage); ?> de <?php echo max(1, $totalPages); ?></span>
            <?php if ($totalPages > 1): ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php $query = http_build_query(array_merge($baseQuery, ['page_number' => $p])); ?>
                    <button type="button" class="pagination-number<?php echo $p === $currentPage ? ' active' : ''; ?>" data-nav-href="/frame.php?<?php echo htmlspecialchars($query, ENT_QUOTES); ?>">
                        <?php echo $p; ?>
                    </button>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/modules/produtividade/produtividade-list.js"></script>
</body>
</html>

