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
$id = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));

$error = '';
if ($id === '') {
    $error = 'ID do badge não informado.';
}

$badge = null;
if ($error === '') {
    try {
        $badge = $service->findBadge($userId, $id);
        if ($badge === null) {
            $error = 'Badge não encontrado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$form = [
    'id' => $id,
    'nome' => (string) ($badge['nome'] ?? ''),
    'descricao' => (string) ($badge['descricao'] ?? ''),
    'icone' => (string) ($badge['icone'] ?? ''),
    'tipo' => (string) ($badge['tipo'] ?? 'pontuacao_cumulativa'),
    'regra_id' => (string) ($badge['regra_id'] ?? ''),
    'valor_threshold' => (string) max(0, (int) round((float) ($badge['valor_threshold'] ?? 0))),
    'periodo' => (string) ($badge['periodo'] ?? 'total'),
    'ativo' => ((int) ($badge['ativo'] ?? 1)) === 1 ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $form = [
        'id' => $id,
        'nome' => trim((string) ($_POST['nome'] ?? '')),
        'descricao' => trim((string) ($_POST['descricao'] ?? '')),
        'icone' => trim((string) ($_POST['icone'] ?? '')),
        'tipo' => trim((string) ($_POST['tipo'] ?? 'pontuacao_cumulativa')),
        'regra_id' => trim((string) ($_POST['regra_id'] ?? '')),
        'valor_threshold' => trim((string) ($_POST['valor_threshold'] ?? '0')),
        'periodo' => trim((string) ($_POST['periodo'] ?? 'total')),
        'ativo' => isset($_POST['ativo']) ? '1' : '0',
    ];

    try {
        $service->updateBadge($userId, $id, $form);
        $_SESSION['produtividade_badges_flash_success'] = 'Badge atualizado com sucesso.';
        header('Location: /frame.php?page=produtividadebadges');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$rules = [];
try {
    $rulesResult = $service->listRules($userId, ['ativo' => 1], 500, 0);
    $rules = $rulesResult['rows'] ?? [];
} catch (Throwable $e) {
    if ($error === '') {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Produtividade - Editar badge</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php
        $__badgesFormJs = dirname(__DIR__, 3) . '/public/assets/js/modules/produtividade/badges-form.js';
        $__badgesFormV = @filemtime($__badgesFormJs) ?: time();
        ?>
        <script src="/assets/js/modules/produtividade/badges-form.js?v=<?php echo (int) $__badgesFormV; ?>" defer></script>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade - Badges -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar badge</h1>
        <p class="description-page">Ajuste critérios, status e metadados do badge.</p>
    </div>

    <div class="container-full card-page">
        <form method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="nome" name="nome" class="width-500 height-md white" value="<?php echo htmlspecialchars($form['nome'], ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="tipo">Tipo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="tipo" name="tipo" class="width-300 height-md white" required>
                        <option value="pontuacao_cumulativa"<?php echo $form['tipo'] === 'pontuacao_cumulativa' ? ' selected' : ''; ?>>Pontuação cumulativa</option>
                        <option value="ato_especifico"<?php echo $form['tipo'] === 'ato_especifico' ? ' selected' : ''; ?>>Ato específico</option>
                    </select>
                </div>
                <div class="col">
                    <label for="periodo">Período <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="periodo" name="periodo" class="width-300 height-md white" required>
                        <option value="total"<?php echo $form['periodo'] === 'total' ? ' selected' : ''; ?>>Total</option>
                        <option value="mensal"<?php echo $form['periodo'] === 'mensal' ? ' selected' : ''; ?>>Mensal</option>
                        <option value="semanal"<?php echo $form['periodo'] === 'semanal' ? ' selected' : ''; ?>>Semanal</option>
                        <option value="diario"<?php echo $form['periodo'] === 'diario' ? ' selected' : ''; ?>>Diário</option>
                    </select>
                </div>
                <div class="col">
                    <label for="valor_threshold">Meta (pontos ou quantidade) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="valor_threshold" name="valor_threshold" class="width-200 height-md white" value="<?php echo htmlspecialchars($form['valor_threshold'], ENT_QUOTES); ?>" inputmode="numeric" pattern="[0-9]+" maxlength="12" placeholder="Apenas números" autocomplete="off" required>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="regra_id">Regra (para ato específico)</label>
                    <select id="regra_id" name="regra_id" class="width-700 height-md white">
                        <option value="">Não vincular</option>
                        <?php foreach ($rules as $rule): ?>
                            <?php
                            $ruleId = (string) ($rule['id'] ?? '');
                            $label = trim((string) ($rule['tipo_documento'] ?? '')) . ' - ' . trim((string) ($rule['tipo_ato'] ?? '')) . ' - ' . trim((string) ($rule['sistema_modulo'] ?? ''));
                            ?>
                        <option value="<?php echo htmlspecialchars($ruleId, ENT_QUOTES); ?>"<?php echo $form['regra_id'] === $ruleId ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="icone">Ícone (opcional)</label>
                    <input type="text" id="icone" name="icone" class="width-300 height-md white" value="<?php echo htmlspecialchars($form['icone'], ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="ativo">Status</label>
                    <label class="display-flex row row-fit align-center gap-y-sm">
                        <input type="checkbox" id="ativo" name="ativo" value="1"<?php echo $form['ativo'] === '1' ? ' checked' : ''; ?>>
                        <span>Ativo</span>
                    </label>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col col-full">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" class="width-700" rows="3"><?php echo htmlspecialchars($form['descricao'], ENT_QUOTES); ?></textarea>
                </div>
            </div>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividadebadges">Voltar</button>
                <button type="submit" class="btn btn-primary height-md">Salvar alterações</button>
            </div>
        </form>
    </div>
<?php if ($error !== ''): ?>
<script>
if (typeof showToast === 'function') {
    showToast({ type: 'error', title: 'Erro', message: <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?> });
}
</script>
<?php endif; ?>
</body>
</html>
