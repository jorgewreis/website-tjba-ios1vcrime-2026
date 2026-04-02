<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleName = 'ALLOCATE-GUIAS';
try {
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $allocatedUserId = $ruleRow['usuario_id'] ?? null;
    if ($allocatedUserId !== null && $allocatedUserId !== '') {
        $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmt->execute([$allocatedUserId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u !== false) {
            $nome = trim((string) ($u['nome'] ?? ''));
            $sobrenome = trim((string) ($u['sobrenome'] ?? ''));
            $ruleServidor = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            $hasRuleAllocation = $ruleServidor !== '';
        }
    }
} catch (\Throwable $e) {
    // mantém padrão
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Guias - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Guia</h1>
        <p class="description-page">Registre uma nova guia de execução ou recolhimento (processo e denunciado).</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter" id="denunciado-col">
                <label for="filter-denunciado">Sentenciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-800 height-md" id="filter-denunciado-select" style="display: none;" aria-label="Réus vinculados ao processo">
                    <!-- preenchido via JS quando houver réus -->
                </select>
                <input type="text" class="width-800 height-md" id="filter-denunciado" placeholder="Digite o nome completo" maxlength="255" aria-label="Nome do denunciado quando não há réus ou opção Outro">
            </div>
            <div class="col filter">
                <label for="filter-tipo">Tipo de guia <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md" id="filter-tipo" required>
                    <option value="Guia - Tipo não informado" selected>Guia - Tipo não informado</option>
                    <option value="Guia de Execução Definitiva">Guia de Execução Definitiva</option>
                    <option value="Guia de Recolhimento Provisória">Guia de Recolhimento Provisória</option>
                    <option value="Guia de Recolhimento Definitiva">Guia de Recolhimento Definitiva</option>
                    <option value="Guia (corrigir)">Guia (corrigir)</option>
                    <option value="Guia (reenviar)">Guia (reenviar)</option>
                </select>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-regime">Regime</label>
                <select class="width-400 height-md" id="filter-regime">
                    <option value="">Selecione o regime</option>
                    <option value="Aberto">Aberto</option>
                    <option value="Semiaberto">Semiaberto</option>
                    <option value="Fechado">Fechado</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-data-fato">Data do fato</label>
                <input type="date" class="width-300 height-md" id="filter-data-fato" max="">
            </div>
            <div class="col filter">
                <label for="filter-dias-multa">Dias-multa</label>
                <input type="number" class="width-300 height-md" id="filter-dias-multa" min="0" placeholder="—">
            </div>
        </div>

        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <?php if ($hasRuleAllocation): ?>
                <span>*Conforme regra de alocação, a expedição e envio das Guias serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
            <?php else: ?>
                <span>*Não existe nenhum usuário alocado automaticamente para esta tarefa no momento.</span>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de alocação</button>
            <button class="btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
            <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
        </div>

        
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script src="/assets/js/modules/datajud/cnj.js"></script>
    <script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/datajud/datajud-cycle-helper.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/datajud/datajud-api.js"></script>
    <script>
        (function () {
            if (window.App && App.CartorioGuiasCreateUI) {
                App.CartorioGuiasCreateUI.initialize();
            }
            if (!window.App || !App.CartorioGuiasCreate || typeof App.CartorioGuiasCreate.initialize !== 'function') {
                return;
            }

            App.CartorioGuiasCreate.initialize();
        })();
    </script>
</body>
</html>
