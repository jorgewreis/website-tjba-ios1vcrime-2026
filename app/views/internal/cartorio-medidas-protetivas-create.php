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
        <h1 class="title-page">Cadastro de Medida Protetiva</h1>
        <p class="description-page">Registre o processo, identifique o réu, relacione as vítimas e prepare automaticamente o vínculo posterior com o fluxo do Cejusc.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
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

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Réu</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="reu-row-principal">
            <div class="col filter">
                <label for="reu-select">Réu / Denunciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-800 height-md" id="reu-select" style="display: none;" aria-label="Réus vinculados ao processo ou sugestões por nome">
                    <!-- preenchido via JS: réus do processo ou sugestões (nome + CPF) + Outro / Cadastrar novo -->
                </select>
                <input type="text" class="width-800 height-md" id="reu-nome" placeholder="Digite o nome completo" maxlength="255" aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="reu-campos-complementares" style="display: none;">
            <div class="col filter">
                <label for="reu-cpf">CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-300 height-md" id="reu-cpf" placeholder="000.000.000-00">
            </div>
            <div class="col filter">
                <label for="reu-sexo">Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-300 height-md" id="reu-sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="reu-data-nascimento">Data de nascimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-300 height-md" id="reu-data-nascimento">
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Vítimas</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <button class="btn btn-primary-outline height-sm" id="add-vitima-btn" type="button">Adicionar vítima</button>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom" id="vitimas-container"></div>

        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <?php if ($hasRuleAllocation): ?>
                <span>*Conforme regra de alocação, as medidas protetivas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
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

    <template id="vitima-template">
        <div class="vitima-card">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2><span class="vitima-card-index">Vítima</span></h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md vitima-row-principal">
                <div class="col filter">
                    <label>Vítima (nome)</label>
                    <select class="width-600 height-md vitima-select" style="display: none;" aria-label="Sugestões por nome"></select>
                    <input type="text" class="width-600 height-md vitima-nome" placeholder="Digite o nome completo" maxlength="255">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md vitima-campos-complementares" style="display: none;">
                <div class="col filter">
                    <label>CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-200 height-md vitima-cpf" placeholder="000.000.000-00">
                </div>
                <div class="col filter">
                    <label>Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-200 height-md vitima-sexo">
                        <option value="">Selecione</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="col filter">
                    <label>Data de nascimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-200 height-md vitima-data-nascimento">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-mulher"> Mulher</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-crianca"> Criança/Adolescente</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-idoso"> Idoso</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-pcd"> Pessoa com deficiência</label>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <button class="btn btn-danger-outline height-sm btn-remover-vitima" type="button">Remover</button>
                </div>
            </div>
        </div>
    </template>

    <script src="/assets/js/modules/datajud/cnj.js"></script>
    <script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/datajud/datajud-cycle-helper.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/datajud/datajud-api.js"></script>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script>
        (function () {
            if (window.App && App.CartorioMedidasProtetivasCreateUI) {
                App.CartorioMedidasProtetivasCreateUI.initialize();
            }
            if (!window.App || !App.CartorioMedidasProtetivasCreate || typeof App.CartorioMedidasProtetivasCreate.initialize !== 'function') {
                return;
            }

            App.CartorioMedidasProtetivasCreate.initialize();
        })();
    </script>
</body>
</html>
