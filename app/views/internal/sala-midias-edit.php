<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaMidiasService.php';

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
$cartorioAlocacaoPrefix = 'midias';
$cartorioAlocacaoEntityId = $id;
$cartorioAlocacaoDesalocarEndpoint = '/api/sala-midias/desalocar.php';
$cartorioAlocacaoAlocarEndpoint = '/api/sala-midias/alocar.php';
$cartorioAlocacaoAlocados = $row['Alocados'] ?? [];
$cartorioAlocacaoBloqueada = $bloqueada;
$cartorioAlocacaoUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
$cartorioAlocacaoIsGestao = false;
if ($cartorioAlocacaoUserId !== '') {
    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($cartorioAlocacaoUserId);
    $cartorioAlocacaoIsGestao = count(array_intersect($perfis, ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'])) > 0;
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
</div>

<div class="container-full card-page">
    <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
        <h2>Detalhes da tarefa</h2>
    </div>

    <form id="midias-edit-form" class="display-flex col col-full">
        <input type="hidden" id="midias-edit-id" value="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label>Processo</label>
                <input type="text" class="width-400 height-md readonly" readonly value="<?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label>Classe processual</label>
                <input type="text" class="width-600 height-md readonly" readonly value="<?php echo htmlspecialchars((string) ($row['classe_processo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label>Data da audiência</label>
                <input type="text" class="width-300 height-md readonly" readonly value="<?php echo htmlspecialchars(date('d/m/Y', strtotime((string) ($row['data_audiencia'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col filter">
                <label>Tipo da audiência</label>
                <input type="text" class="width-500 height-md readonly" readonly value="<?php echo htmlspecialchars((string) ($row['tipo_audiencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
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

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=salamidias">Voltar</button>
            <?php if (!$bloqueada): ?>
                <button type="button" class="btn btn-primary" id="btn-midias-salvar">Salvar alterações</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script src="/assets/js/shared/cartorio-alocacao-direcionada-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/cartorio-alocacao-direcionada-edit.js') ?: time(); ?>"></script>
<script src="/assets/js/modules/audiencias/midias-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/midias-edit.js') ?: time(); ?>"></script>
</body>
</html>
