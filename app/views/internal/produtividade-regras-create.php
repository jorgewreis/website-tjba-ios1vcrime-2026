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

$form = [
    'tipo_ato' => '',
    'tipo_documento' => '',
    'sistema_modulo' => '',
    'pontos_por_ato' => '0,00',
    'pontos_multiplicador' => '1,00',
    'ativo' => '1',
];
$error = '';
$success = '';
$catalog = ['tipos_ato' => [], 'tipos_documento' => [], 'sistemas_modulo' => []];
if ($isGestao) {
    try {
        $catalog = $service->listRuleCatalogValues($userId);
    } catch (Throwable $e) {
        // mantém catálogo vazio
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = [
        'tipo_ato' => trim((string) ($_POST['tipo_ato'] ?? '')),
        'tipo_documento' => trim((string) ($_POST['tipo_documento'] ?? '')),
        'sistema_modulo' => trim((string) ($_POST['sistema_modulo'] ?? '')),
        'pontos_por_ato' => trim((string) ($_POST['pontos_por_ato'] ?? '0')),
        'pontos_multiplicador' => trim((string) ($_POST['pontos_multiplicador'] ?? '1')),
        'ativo' => '1',
        'observacoes' => '',
    ];

    try {
        if (!$isGestao) {
            throw new InvalidArgumentException('Acesso permitido apenas para perfis de gestão.');
        }
        $id = $service->createRule($userId, $form);
        $success = 'Regra criada com sucesso.';
        $form = [
            'tipo_ato' => '',
            'tipo_documento' => '',
            'sistema_modulo' => '',
            'pontos_por_ato' => '0,00',
            'pontos_multiplicador' => '1,00',
            'ativo' => '1',
        ];
        $_SESSION['produtividade_regras_flash_success'] = $success;
        header('Location: /frame.php?page=produtividaderegras');
        exit;
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
        <title>Produtividade - Nova regra</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Produtividade - Regras -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Nova regra de produtividade</h1>
        <p class="description-page">Defina o tipo de movimentação e a pontuação aplicada por ato.</p>
    </div>

    <div class="container-full card-page">
        <form method="post">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <h2>Informações da regra</h2>
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Voltar</button>
            </div>

            <?php if ($error !== ''): ?>
            <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
                <span class="status status-cancelled"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>

            <?php
            $tipoAtoInCatalog = in_array($form['tipo_ato'], $catalog['tipos_ato'], true);
            $tipoDocInCatalog = in_array($form['tipo_documento'], $catalog['tipos_documento'], true);
            $sistemaInCatalog = in_array($form['sistema_modulo'], $catalog['sistemas_modulo'], true);
            ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="tipo_ato_select">Tipo de ato <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="tipo_ato_select" class="width-500 height-md white">
                        <option value="">Selecione</option>
                        <?php foreach ($catalog['tipos_ato'] as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo $tipoAtoInCatalog && $form['tipo_ato'] === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                        <option value="__OUTRO__"<?php echo !$tipoAtoInCatalog && $form['tipo_ato'] !== '' ? ' selected' : ''; ?>>Outro (digitar abaixo)</option>
                    </select>
                    <input type="text" id="tipo_ato" name="tipo_ato" class="width-500 height-md white" value="<?php echo htmlspecialchars($form['tipo_ato'], ENT_QUOTES); ?>" required style="<?php echo (!$tipoAtoInCatalog && $form['tipo_ato'] !== '') ? '' : 'display:none; margin-top:8px;'; ?>" placeholder="Ex.: 0001- Expedição">
                </div>
                <div class="col">
                    <label for="tipo_documento_select">Tipo de documento</label>
                    <select id="tipo_documento_select" class="width-400 height-md white">
                        <option value="">Selecione</option>
                        <?php foreach ($catalog['tipos_documento'] as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo $tipoDocInCatalog && $form['tipo_documento'] === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                        <option value="__OUTRO__"<?php echo !$tipoDocInCatalog && $form['tipo_documento'] !== '' ? ' selected' : ''; ?>>Outro (digitar abaixo)</option>
                    </select>
                    <input type="text" id="tipo_documento" name="tipo_documento" class="width-400 height-md white" value="<?php echo htmlspecialchars($form['tipo_documento'], ENT_QUOTES); ?>" style="<?php echo (!$tipoDocInCatalog && $form['tipo_documento'] !== '') ? '' : 'display:none; margin-top:8px;'; ?>" placeholder="Ex.: Mandado de Prisão">
                </div>
                <div class="col">
                    <label for="sistema_modulo_select">Sistema/módulo</label>
                    <select id="sistema_modulo_select" class="width-300 height-md white">
                        <option value="">Selecione</option>
                        <?php foreach ($catalog['sistemas_modulo'] as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo $sistemaInCatalog && $form['sistema_modulo'] === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                        <option value="__OUTRO__"<?php echo !$sistemaInCatalog && $form['sistema_modulo'] !== '' ? ' selected' : ''; ?>>Outro (digitar abaixo)</option>
                    </select>
                    <input type="text" id="sistema_modulo" name="sistema_modulo" class="width-300 height-md white" value="<?php echo htmlspecialchars($form['sistema_modulo'], ENT_QUOTES); ?>" style="<?php echo (!$sistemaInCatalog && $form['sistema_modulo'] !== '') ? '' : 'display:none; margin-top:8px;'; ?>" placeholder="Ex.: BNMP">
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="pontos_por_ato">Pontos por ato <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="pontos_por_ato" name="pontos_por_ato" class="width-200 height-md white" value="<?php echo htmlspecialchars($form['pontos_por_ato'], ENT_QUOTES); ?>" required>
                </div>
                <div class="col">
                    <label for="pontos_multiplicador">Multiplicador <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="pontos_multiplicador" name="pontos_multiplicador" class="width-200 height-md white" value="<?php echo htmlspecialchars($form['pontos_multiplicador'], ENT_QUOTES); ?>" required>
                </div>
            </div>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary height-md" data-nav-href="/frame.php?page=produtividaderegras">Voltar</button>
                <button type="submit" class="btn btn-primary height-md" <?php echo $isGestao ? '' : 'disabled'; ?>>Salvar regra</button>
            </div>
        </form>
    </div>
<script>
(function () {
    function bindContextSelect(selectId, inputId) {
        var select = document.getElementById(selectId);
        var input = document.getElementById(inputId);
        if (!select || !input) return;

        function sync() {
            if (select.value === '__OUTRO__') {
                select.style.display = 'none';
                input.style.display = '';
                input.required = true;
                input.focus();
                return;
            }
            if (select.value === '') {
                input.value = '';
                select.style.display = '';
                input.style.display = 'none';
                input.required = false;
                return;
            }
            input.value = select.value;
            select.style.display = '';
            input.style.display = 'none';
            input.required = inputId === 'tipo_ato';
        }

        select.addEventListener('change', sync);
        input.addEventListener('blur', function () {
            if (select.style.display === 'none' && input.value.trim() === '') {
                select.value = '';
                sync();
            }
        });
        sync();
    }

    bindContextSelect('tipo_ato_select', 'tipo_ato');
    bindContextSelect('tipo_documento_select', 'tipo_documento');
    bindContextSelect('sistema_modulo_select', 'sistema_modulo');
})();
</script>
</body>
</html>

