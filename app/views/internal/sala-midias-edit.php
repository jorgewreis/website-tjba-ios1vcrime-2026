<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaMidiasService.php';
require_once __DIR__ . '/../../helpers/ClasseProcessualListagemHelper.php';

$connection = Conection::getInstance()->getConnection();
$service = new SalaMidiasService($connection);
$id = trim((string) ($_GET['id'] ?? ''));
$row = $id !== '' ? $service->find($id) : null;
if ($row === null) {
    http_response_code(404);
    echo 'Registro não encontrado.';
    exit;
}

$statusAtual = trim((string) ($row['status'] ?? 'em_andamento'));
$bloqueada = in_array($statusAtual, ['concluida', 'cancelada'], true);
$subtarefas = is_array($row['Subtarefas'] ?? null) ? $row['Subtarefas'] : [];
$subtarefaLabels = [
    'download_midia' => 'Download de Mídia',
    'sincronizacao_midia' => 'Sincronização de Mídia',
    'upload_midia' => 'Upload de Mídia',
    'certidao_upload_midia' => 'Certidão de Upload de Mídia',
];
$subtarefaButton = [
    'download_midia' => 'Concluir download',
    'sincronizacao_midia' => 'Concluir sincronização',
    'upload_midia' => 'Concluir upload',
    'certidao_upload_midia' => 'Concluir certidão',
];
$progressStatuses = ['pendente', 'pendente', 'pendente', 'pendente'];
$ordemCodigo = ['download_midia' => 1, 'sincronizacao_midia' => 2, 'upload_midia' => 3, 'certidao_upload_midia' => 4];
foreach ($subtarefas as $st) {
    $codigo = trim((string) ($st['codigo'] ?? ''));
    $ord = $ordemCodigo[$codigo] ?? 0;
    if ($ord >= 1 && $ord <= 4) {
        $progressStatuses[$ord - 1] = trim((string) ($st['status'] ?? 'pendente'));
    }
}
$statusLabel = 'Em andamento';
$statusClass = 'status-current';
if ($statusAtual === 'pendente') {
    $statusLabel = 'Pendente';
    $statusClass = 'status-pending';
} elseif ($statusAtual === 'concluida') {
    $statusLabel = 'Finalizada';
    $statusClass = 'status-finished';
} elseif ($statusAtual === 'cancelada') {
    $statusLabel = 'Cancelada';
    $statusClass = 'status-cancelled';
}
$mostrarBarraStatus = in_array($statusAtual, ['pendente', 'em_andamento'], true);
$currentSubtarefaTitle = '';
foreach ($subtarefas as $st) {
    if (trim((string) ($st['status'] ?? '')) === 'em_andamento') {
        $codigo = trim((string) ($st['codigo'] ?? ''));
        $currentSubtarefaTitle = $subtarefaLabels[$codigo] ?? trim((string) ($st['titulo'] ?? 'Subtarefa'));
        break;
    }
}
if ($currentSubtarefaTitle === '' && $statusAtual === 'concluida') {
    $currentSubtarefaTitle = 'Todas as etapas concluídas';
} elseif ($currentSubtarefaTitle === '' && $statusAtual === 'cancelada') {
    $currentSubtarefaTitle = 'Cancelada';
} elseif ($currentSubtarefaTitle === '') {
    $currentSubtarefaTitle = 'Aguardando início das subtarefas';
}
$cartorioAlocacaoPrefix = 'midias';
$cartorioAlocacaoEntityId = $id;
$cartorioAlocacaoDesalocarEndpoint = '/api/sala-midias/desalocar.php';
$cartorioAlocacaoAlocarEndpoint = '/api/sala-midias/alocar.php';
$cartorioAlocacaoAlocados = $row['Alocados'] ?? [];
$cartorioAlocacaoBloqueada = $bloqueada;
$cartorioAlocacaoUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
$cartorioAlocacaoIsGestao = false;
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA'];
$podeExcluirTarefa = false;
$userPerfisEdit = [];
$classeProcessoMidiasEdit = trim((string) ($row['classe_processo'] ?? ''));
$classeProcessoMidiasEditClass = 'width-600 height-md';
if (ClasseProcessualListagemHelper::temDestaqueListagem($classeProcessoMidiasEdit)) {
    $classeProcessoMidiasEditClass .= ' status-finished';
}
if ($cartorioAlocacaoUserId !== '') {
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario($cartorioAlocacaoUserId);
    $cartorioAlocacaoIsGestao = count(array_intersect($userPerfisEdit, ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'])) > 0;
    $podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Sala de Audiências - Mídias - Edição</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>

<div class="header-page">
    <div class="location">
        <span class="all">Interno - Sala de Audiências - Mídias -</span>
        <span class="specific">Edição</span>
    </div>
    <h1 class="title-page">Editar tarefa de mídia</h1>
    <p class="description-page">Altere os dados da tarefa e acompanhe as subtarefas.</p>
</div>

<div class="container-full card-page">
    <form id="midias-edit-form" class="display-flex col col-full">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados da tarefa</h2></div>
        <input type="hidden" id="midias-edit-id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="midias-edit-processo">Processo</label>
                <input type="text" id="midias-edit-processo" class="width-400 height-md readonly" readonly value="<?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label for="midias-edit-classe" id="processo-ap-info" aria-live="polite">Classe processual</label>
                <input type="text" id="midias-edit-classe" class="<?php echo htmlspecialchars($classeProcessoMidiasEditClass, ENT_QUOTES, 'UTF-8'); ?>" data-datajud-related="true"<?php echo ClasseProcessualListagemHelper::temDestaqueListagem($classeProcessoMidiasEdit) ? ' data-datajud-initial="1"' : ''; ?> readonly value="<?php echo htmlspecialchars($classeProcessoMidiasEdit, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="midias-edit-data">Data da audiência</label>
                <input type="text" id="midias-edit-data" class="width-300 height-md readonly" readonly value="<?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($row['data_audiencia'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label for="midias-edit-tipo-audiencia">Tipo da audiência</label>
                <input type="text" id="midias-edit-tipo-audiencia" class="width-500 height-md readonly" readonly value="<?php echo htmlspecialchars((string) ($row['tipo_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label for="midias-edit-qtd">Qtd de mídias</label>
                <input type="number" min="1" step="1" class="width-200 height-md<?php echo $bloqueada ? ' readonly' : ''; ?>" id="midias-edit-qtd" value="<?php echo (int) ($row['qtd_midias'] ?? 1); ?>"<?php echo $bloqueada ? ' readonly' : ''; ?>>
            </div>
            <div class="col filter">
                <label for="midias-edit-status">Status</label>
                <select class="width-300 height-md" id="midias-edit-status"<?php echo $bloqueada ? ' disabled' : ''; ?>>
                    <?php foreach (['pendente', 'em_andamento', 'concluida', 'cancelada'] as $st): ?>
                        <option value="<?php echo $st; ?>"<?php echo $statusAtual === $st ? ' selected' : ''; ?>><?php echo htmlspecialchars(str_replace('_', ' ', $st), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php'; ?>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h3 style="margin:0;font-size:1rem;">Status da tarefa</h3></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md align-center">
            <div class="col status-task" title="<?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($mostrarBarraStatus): ?>
                    <?php
                    $concluidosBarra = count(array_filter($progressStatuses, static function ($x): bool {
                        return $x === 'concluida';
                    }));
                    ?>
                    <div class="width-300 status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?> status-with-progress">
                        <span class="status-task-label width-300"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo $concluidosBarra; ?>" aria-valuemin="0" aria-valuemax="4">
                            <?php
                            $titles = [$subtarefaLabels['download_midia'], $subtarefaLabels['sincronizacao_midia'], $subtarefaLabels['upload_midia'], $subtarefaLabels['certidao_upload_midia']];
                            for ($i = 0; $i < 4; $i++):
                                $segStatus = $progressStatuses[$i] ?? 'pendente';
                                $segClass = $segStatus === 'concluida' ? 'concluida' : ($segStatus === 'em_andamento' ? 'em_andamento' : 'pendente');
                            ?>
                                <div class="status-task-progress-segment <?php echo htmlspecialchars($segClass, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($titles[$i], ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="width-300 padding-x-lg status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
            </div>
            <div class="col">
                <span class="minor-info"><?php echo htmlspecialchars($currentSubtarefaTitle, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h3 style="margin:0;font-size:1rem;">Subtarefas</h3></div>
        <div class="display-flex col col-full">
            <?php foreach ($subtarefas as $sub): ?>
                <?php
                $subId = trim((string) ($sub['id'] ?? ''));
                $subCode = trim((string) ($sub['codigo'] ?? ''));
                $subLabel = $subtarefaLabels[$subCode] ?? trim((string) ($sub['titulo'] ?? 'Subtarefa'));
                $subStatus = trim((string) ($sub['status'] ?? 'pendente'));
                $concluivel = in_array($subStatus, ['pendente', 'em_andamento'], true) && !$bloqueada;
                ?>
                <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-bottom" data-subtarefa-id="<?php echo htmlspecialchars($subId, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col task-status">
                            <div class="padding-x-lg height-sm status status-pending"><?php echo htmlspecialchars($subLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="col">
                            <span class="minor-info">Sistema: <?php echo htmlspecialchars((string) ($sub['sistema'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="col">
                            <span class="minor-info">Pontos: <?php echo htmlspecialchars((string) ($sub['pontos_calculados'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="col">
                            <span class="minor-info">Status: <?php echo htmlspecialchars(str_replace('_', ' ', $subStatus), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end align-center">
                        <?php if ($concluivel): ?>
                            <button type="button"
                                    class="btn btn-primary height-sm js-midias-edit-concluir-subtarefa"
                                    data-subtarefa-id="<?php echo htmlspecialchars($subId, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars($subtarefaButton[$subCode] ?? 'Concluir subtarefa', ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="value-button"><?php echo htmlspecialchars($subtarefaButton[$subCode] ?? 'Concluir subtarefa', ENT_QUOTES, 'UTF-8'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="midias-edit-voltar">Voltar</button>
            <?php if (!$bloqueada): ?>
                <button type="button" class="btn btn-primary" id="btn-midias-salvar">Salvar alterações</button>
            <?php endif; ?>
            <?php if ($podeExcluirTarefa): ?>
                <button type="button" class="btn btn-danger" id="excluir-tarefa-btn" title="Excluir esta tarefa e todas as subtarefas vinculadas">Excluir tarefa</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script src="/assets/js/shared/cartorio-alocacao-direcionada-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/cartorio-alocacao-direcionada-edit.js') ?: time(); ?>"></script>
<script src="/assets/js/modules/audiencias/midias-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/midias-edit.js') ?: time(); ?>"></script>
</body>
</html>
