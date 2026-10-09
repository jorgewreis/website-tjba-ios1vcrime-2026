<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleName = 'ALLOCATE-EDITAIS';
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
        <title>Editais - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Edital</h1>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-tipo-edital">Tipo de edital <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-500 height-md" id="filter-tipo-edital" required>
                    <option value="">Selecione</option>
                    <option value="Citação">Citação</option>
                    <option value="Intimação">Intimação</option>
                    <option value="Notificação">Notificação</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-prazo-dias">Prazo do edital (dias) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="number" class="width-300 height-md" id="filter-prazo-dias" min="1" placeholder="Ex.: 15" required title="Prazo em dias a partir da data de publicação">
            </div>
        </div>

        <?php $cartorioAlocacaoPrefix = 'editais'; include __DIR__ . '/_cartorio_alocacao_direcionada_create.php'; ?>

        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <?php if ($hasRuleAllocation): ?>
                <span>*Conforme regra de alocação, os Editais serão alocados automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
            <?php else: ?>
                <span>*Não existe nenhum usuário alocado automaticamente para esta tarefa no momento.</span>
            <?php endif; ?>
        </div>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-secondary" id="voltar-btn" type="button" data-allow-during-loading="true">Voltar</button>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <button class="btn btn-primary-outline" id="alterar-regra-btn" type="button" style="display: none;">Alterar regra de alocação</button>
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
            if (window.App && App.CartorioCreateBootstrap) {
                App.CartorioCreateBootstrap.bindBackButton({ href: '/frame.php?page=editais' });
                App.CartorioCreateBootstrap.setupRuleButton({ targetPage: 'editaisrules' });
            }
            if (!window.App || !App.CartorioEditaisCreate || typeof App.CartorioEditaisCreate.initialize !== 'function') {
                return;
            }

            App.CartorioEditaisCreate.initialize({ alocacaoPrefix: 'editais' });
        })();
    </script>
</body>
</html>
