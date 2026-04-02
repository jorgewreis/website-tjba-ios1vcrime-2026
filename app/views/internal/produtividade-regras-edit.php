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
$ruleId = trim((string) ($_GET['id'] ?? ''));

$isGestao = false;
if ($userId !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userId]);
    $perfis = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
    foreach ($perfis as $perfil) {
        $p = strtoupper(trim((string) $perfil));
        if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
            $isGestao = true;
            break;
        }
    }
}

$error = '';
$success = (string) ($_SESSION['produtividade_regras_flash_success'] ?? '');
unset($_SESSION['produtividade_regras_flash_success']);

try {
    if (!$isGestao) {
        throw new InvalidArgumentException('Acesso permitido apenas para perfis de gestão.');
    }
    if ($ruleId === '') {
        throw new InvalidArgumentException('ID da regra não informado.');
    }
    $rule = $service->findRule($userId, $ruleId);
    if ($rule === null) {
        throw new InvalidArgumentException('Regra não encontrada.');
    }
} catch (Throwable $e) {
    $rule = null;
    $error = $e->getMessage();
}

if ($rule !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'tipo_ato' => trim((string) ($_POST['tipo_ato'] ?? '')),
        'tipo_documento' => trim((string) ($_POST['tipo_documento'] ?? '')),
        'sistema_modulo' => trim((string) ($_POST['sistema_modulo'] ?? '')),
        'pontos_por_ato' => trim((string) ($_POST['pontos_por_ato'] ?? '0')),
        'pontos_multiplicador' => trim((string) ($_POST['pontos_multiplicador'] ?? '1')),
        'ativo' => isset($_POST['ativo']) ? '1' : '0',
        'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
    ];
    try {
        $service->updateRule($userId, $ruleId, $payload);
        $success = 'Regra atualizada com sucesso.';
        $rule = $service->findRule($userId, $ruleId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Produtividade - Editar regra</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade - Regras -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar regra de produtividade</h1>
        <p class="description-page">Atualize os parâmetros de cálculo utilizados no lançamento manual.</p>
    </div>

    <div class="container-full card-page">
        <form method="post">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <h2>Detalhes da regra</h2>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Voltar</button>
            </div>

            <?php if ($error !== ''): ?>
            <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
                <span class="status status-cancelled"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($rule !== null): ?>
            <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
                <span class="minor-info">ID da regra: <?php echo htmlspecialchars((string) ($rule['id'] ?? ''), ENT_QUOTES); ?></span>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="tipo_ato">Tipo de ato <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="tipo_ato" name="tipo_ato" class="width-500 height-md white" value="<?php echo htmlspecialchars((string) ($rule['tipo_ato'] ?? ''), ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="tipo_documento">Tipo de documento</label>
                    <input type="text" id="tipo_documento" name="tipo_documento" class="width-400 height-md white" value="<?php echo htmlspecialchars((string) ($rule['tipo_documento'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="sistema_modulo">Sistema/módulo</label>
                    <input type="text" id="sistema_modulo" name="sistema_modulo" class="width-300 height-md white" value="<?php echo htmlspecialchars((string) ($rule['sistema_modulo'] ?? ''), ENT_QUOTES); ?>">
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="pontos_por_ato">Pontos por ato <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="pontos_por_ato" name="pontos_por_ato" class="width-200 height-md white" value="<?php echo htmlspecialchars(str_replace('.', ',', (string) ($rule['pontos_por_ato'] ?? '0.00')), ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="pontos_multiplicador">Multiplicador <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="pontos_multiplicador" name="pontos_multiplicador" class="width-200 height-md white" value="<?php echo htmlspecialchars(str_replace('.', ',', (string) ($rule['pontos_multiplicador'] ?? '1.00')), ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label class="inline" style="margin-top:30px;">
                        <input type="checkbox" name="ativo" value="1"<?php echo ((int) ($rule['ativo'] ?? 0)) === 1 ? ' checked' : ''; ?>>
                        Regra ativa
                    </label>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col col-full">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" class="width-1200 white" rows="4"><?php echo htmlspecialchars((string) ($rule['observacoes'] ?? ''), ENT_QUOTES); ?></textarea>
                </div>
            </div>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Cancelar</button>
                <button type="submit" class="btn btn-primary height-md" <?php echo $isGestao ? '' : 'disabled'; ?>>Salvar alterações</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

<?php if ($success !== ''): ?>
<script>
if (typeof showToast === 'function') {
    showToast({ type: 'success', message: <?php echo json_encode($success, JSON_UNESCAPED_UNICODE); ?> });
}
</script>
<?php endif; ?>
</body>
</html>

