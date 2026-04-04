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
$metaId = trim((string) ($_GET['id'] ?? ''));

$isGestao = false;
if ($userId !== '') {
    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    foreach ($perfis as $perfil) {
        $p = strtoupper(trim((string) $perfil));
        if ($p === 'ADMIN' || $p === 'CHEFIA' || $p === 'DIRETORA' || $p === 'MAGISTRADA') {
            $isGestao = true;
            break;
        }
    }
}

$error = '';
$success = (string) ($_SESSION['produtividade_metas_flash_success'] ?? '');
unset($_SESSION['produtividade_metas_flash_success']);

try {
    if (!$isGestao) {
        throw new InvalidArgumentException('Acesso permitido apenas para perfis de gestão.');
    }
    if ($metaId === '') {
        throw new InvalidArgumentException('ID da meta não informado.');
    }
    $meta = $service->findMeta($userId, $metaId);
    if ($meta === null) {
        throw new InvalidArgumentException('Meta não encontrada.');
    }
} catch (Throwable $e) {
    $meta = null;
    $error = $e->getMessage();
}

if ($meta !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'tipo_alvo' => trim((string) ($_POST['tipo_alvo'] ?? '')),
        'alvo_valor' => trim((string) ($_POST['alvo_valor'] ?? '')),
        'periodo_tipo' => trim((string) ($_POST['periodo_tipo'] ?? '')),
        'vigencia_inicio' => trim((string) ($_POST['vigencia_inicio'] ?? '')),
        'vigencia_fim' => trim((string) ($_POST['vigencia_fim'] ?? '')),
        'pontos_meta' => trim((string) ($_POST['pontos_meta'] ?? '0')),
        'ativo' => isset($_POST['ativo']) ? '1' : '0',
    ];
    try {
        $service->updateMeta($userId, $metaId, $payload);
        $success = 'Meta atualizada com sucesso.';
        $meta = $service->findMeta($userId, $metaId);
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
        <title>Produtividade - Editar meta</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade - Metas -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar meta de produtividade</h1>
        <p class="description-page">Atualize alvo, período e vigência da meta configurada.</p>
    </div>

    <div class="container-full card-page">
        <form method="post">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <h2>Detalhes da meta</h2>
            </div>

            <?php if ($meta !== null): ?>
            <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
                <span class="minor-info">ID da meta: <?php echo htmlspecialchars((string) ($meta['id'] ?? ''), ENT_QUOTES); ?></span>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="tipo_alvo">Tipo de alvo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="tipo_alvo" name="tipo_alvo" class="width-300 height-md white" required>
                        <option value="setor"<?php echo (string) ($meta['tipo_alvo'] ?? '') === 'setor' ? ' selected' : ''; ?>>Setor</option>
                        <option value="perfil"<?php echo (string) ($meta['tipo_alvo'] ?? '') === 'perfil' ? ' selected' : ''; ?>>Perfil</option>
                        <option value="usuario"<?php echo (string) ($meta['tipo_alvo'] ?? '') === 'usuario' ? ' selected' : ''; ?>>Usuário</option>
                    </select>
                </div>
                <?php
                $metaFormTipoAlvo = (string) ($meta['tipo_alvo'] ?? 'setor');
                $metaFormAlvoValor = (string) ($meta['alvo_valor'] ?? '');
                include __DIR__ . '/_produtividade_meta_alvo_controls.php';
                ?>
                <div class="col">
                    <label for="periodo_tipo">Período <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="periodo_tipo" name="periodo_tipo" class="width-300 height-md white" required>
                        <option value="semana"<?php echo (string) ($meta['periodo_tipo'] ?? '') === 'semana' ? ' selected' : ''; ?>>Semana</option>
                        <option value="mes"<?php echo (string) ($meta['periodo_tipo'] ?? '') === 'mes' ? ' selected' : ''; ?>>Mês</option>
                        <option value="trimestre"<?php echo (string) ($meta['periodo_tipo'] ?? '') === 'trimestre' ? ' selected' : ''; ?>>Trimestre</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="vigencia_inicio">Vigência inicial <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" id="vigencia_inicio" name="vigencia_inicio" class="width-300 height-md white" value="<?php echo htmlspecialchars((string) ($meta['vigencia_inicio'] ?? ''), ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="vigencia_fim">Vigência final</label>
                    <input type="date" id="vigencia_fim" name="vigencia_fim" class="width-300 height-md white" value="<?php echo htmlspecialchars((string) ($meta['vigencia_fim'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="pontos_meta">Pontos da meta <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="pontos_meta" name="pontos_meta" class="width-200 height-md white" value="<?php echo htmlspecialchars(str_replace('.', ',', (string) ($meta['pontos_meta'] ?? '0.00')), ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label class="inline" style="margin-top:30px;">
                        <input type="checkbox" name="ativo" value="1"<?php echo ((int) ($meta['ativo'] ?? 0)) === 1 ? ' checked' : ''; ?>>
                        Meta ativa
                    </label>
                </div>
            </div>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividademetas">Cancelar</button>
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
<?php if ($error !== ''): ?>
<script>
if (typeof showToast === 'function') {
    showToast({ type: 'error', title: 'Erro', message: <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?> });
}
</script>
<?php endif; ?>
</body>
</html>

