<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleName = 'ALLOCATE-MEDIDASPROTETIVAS';
try {
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $allocatedUserId = $ruleRow['usuario_id'] ?? null;
    if ($allocatedUserId !== null && $allocatedUserId !== '') {
        $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmt->execute([$allocatedUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user !== false) {
            $nome = trim((string) ($user['nome'] ?? ''));
            $sobrenome = trim((string) ($user['sobrenome'] ?? ''));
            $ruleServidor = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            $hasRuleAllocation = $ruleServidor !== '';
        }
    }
} catch (\Throwable $exception) {
    // Mantém o padrão visual.
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Medidas Protetivas - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
        <script src="/assets/js/shared/cpf.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Medidas Protetivas</h1>
        <p class="description-page">Cadastro de medidas protetivas para acompanhamento e expedição.</p>
    </div>

    <div class="container-full card-page">
        <div class="card-title">
            <div class="display-flex row row-fit justify-start">
                <h2>Cadastro</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="card-allocate-rule">
                    <?php if ($hasRuleAllocation): ?>
                        <span>*Conforme regra, as medidas protetivas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
                    <?php else: ?>
                        <span>*Não existe nenhum usuário alocado para esta tarefa por regra.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo" class="display-flex col gap-y-sm" style="align-items: flex-start;">
                    <span>
                        <span id="label-processo-titulo">Processo</span>
                        <span id="label-processo-obrigatorio" class="label-hint-obrigatorio">*obrigatório</span>
                    </span>
                    <span id="processo-ap-info" class="minor-info" aria-live="polite"></span>
                </label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
        </div>

        <!-- Alocação de servidor -->
        <?php $cartorioAlocacaoPrefix = 'medidas'; include __DIR__ . '/_cartorio_alocacao_direcionada_create.php'; ?>

        <input type="hidden" id="filter-classe" data-datajud-related="true" value="" autocomplete="off">
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md" id="reu-row-principal">
            <div class="col filter">
                <label for="reu-select">Requerido(a) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-800 height-md" id="reu-select" style="display: none;" aria-label="Réus vinculados ao processo ou sugestões por nome">
                    <!-- preenchido via JS: réus do processo ou sugestões (nome + CPF) + Outro / Cadastrar novo -->
                </select>
                <input type="text" class="width-800 height-md" id="reu-nome" placeholder="Digite o nome completo" maxlength="255" aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
            <div class="col filter">
                <label for="reu-cpf">CPF</label>
                <input type="text" class="width-300 height-md" id="reu-cpf" placeholder="000.000.000-00">
            </div>
            <div class="col filter">
                <label for="reu-sexo">Sexo</label>
                <select class="width-300 height-md" id="reu-sexo">
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino" selected>Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="reu-data-nascimento">Data de nascimento</label>
                <input type="date" class="width-400 height-md" id="reu-data-nascimento">
            </div>
        </div>

        <div class="card-buttons">
            <div class="display-flex row row-fit justify-start gap-y-md">
                <button class="btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
                <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de alocação</button>                
            </div>
            <div class="display-flex row row-fit justify-end">
                <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
            </div>
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
        (function () {
            if (window.App && App.CartorioMedidasProtetivasCreateUI) {
                App.CartorioMedidasProtetivasCreateUI.initialize();
            }
            if (!window.App || !App.CartorioMedidasProtetivasCreate || typeof App.CartorioMedidasProtetivasCreate.initialize !== 'function') {
                return;
            }

            App.CartorioMedidasProtetivasCreate.initialize({ alocacaoPrefix: 'medidas' });
        })();
    </script>
</body>
</html>
