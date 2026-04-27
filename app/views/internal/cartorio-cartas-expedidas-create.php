<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

function onlyDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function modulo97(string $value): int
{
    $resto = 0;
    $length = strlen($value);
    for ($i = 0; $i < $length; $i++) {
        $digit = ord($value[$i]) - 48;
        if ($digit < 0 || $digit > 9) {
            continue;
        }
        $resto = ($resto * 10 + $digit) % 97;
    }
    return $resto;
}

function calcularDigitoVerificadorCNJ(string $valor): ?string
{
    $digits = onlyDigits($valor);
    if (strlen($digits) !== 20) {
        return null;
    }
    $base = substr($digits, 0, 7) . substr($digits, 9);
    $resto = modulo97($base . '00');
    $dvCalc = 98 - $resto;
    return str_pad((string) $dvCalc, 2, '0', STR_PAD_LEFT);
}

function validarCNJ(string $valor): bool
{
    $digits = onlyDigits($valor);
    if (strlen($digits) !== 20) {
        return false;
    }
    $dv = substr($digits, 7, 2);
    $dvCalc = calcularDigitoVerificadorCNJ($digits);
    if ($dvCalc === null) {
        return false;
    }
    return $dv === $dvCalc;
}

$cnjError = '';
$submittedProcesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedProcesso = trim((string) ($_POST['processo'] ?? $_POST['filter-processo'] ?? ''));
    if ($submittedProcesso !== '' && !validarCNJ($submittedProcesso)) {
        $cnjError = 'Número do processo inválido. Verifique se está completo e correto.';
    }
}

$currentUserName = '';
$currentUserId = $_SESSION['id_usuario'] ?? null;
if ($currentUserId !== null && $currentUserId !== '') {
    $stmtU = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
    $stmtU->execute([$currentUserId]);
    $u = $stmtU->fetch(PDO::FETCH_ASSOC);
    if ($u !== false) {
        $n = trim((string) ($u['nome'] ?? ''));
        $s = trim((string) ($u['sobrenome'] ?? ''));
        $currentUserName = $s !== '' ? $n . ' ' . $s : $n;
    }
}

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleName = 'ALLOCATE-CARTASEXPEDIDAS';
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
} catch (\Throwable $exception) {
    // Mantém o valor padrão se houver erro na leitura do banco.
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <!-- Mapa de navegação do site -->
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Cadastro</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Cartas Expedidas</h1>
        <p class="description-page">Cadastro de cartas expedidas para envio e acompanhamento.</p>
    </div>

    <div class="container-full card-page">       
        <div class="card-title">
            <div class="display-flex row row-fit justify-start">
                <h2>Cadastro</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <div class="card-allocate-rule">
                    <?php $cartorioAlocacaoPrefix = 'cartas-exp'; include __DIR__ . '/_cartorio_alocacao_direcionada_create.php'; ?>
                    <?php if ($hasRuleAllocation): ?>
                        <span>*Conforme regra, o envio de Cartas Expedidas será alocado automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
                    <?php else: ?>
                        <span>*Não existe nenhum usuário alocado para esta tarefa por regra.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="filter-processo" class="display-flex col gap-y-sm" style="align-items: flex-start;">
                    <span>Processo <span class="label-hint-obrigatorio">*obrigatório</span></span>
                    <span id="processo-ap-info" class="minor-info" aria-live="polite"></span>
                </label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col">
                <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md" id="filter-tipo" required>
                    <option value="" selected disabled>Selecione aqui</option>
                    <option value="Intimação para Audiência">Intimação para Audiência</option>
                    <option value="Intimação de Decisão Interlocutória">Intimação de Decisão</option>
                    <option value="Intimação da Sentença">Intimação da Sentença</option>
                    <option value="Citação para apresentar Defesa">Citação para apresentar Defesa</option>                    
                    <option value="Citação para apresentar Alegações Finais">Citação para apresentar Alegações Finais</option>
                    <option value="Suspensão Condicional">Suspensão Condicional</option>
                    <option value="Outra">Outra não listada</option>
                </select>
            </div>
        </div>
        <input type="hidden" id="filter-classe" data-datajud-related="true" value="" autocomplete="off">

        <?php if ($cnjError): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($cnjError, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php endif; ?>

        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md">
            <div class="col">
                <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" required>
            </div>
            <div class="col">
                <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-400 height-md" id="filter-estado" required>
                    <option value="AC">Acre</option>
                    <option value="AL">Alagoas</option>
                    <option value="AP">Amapá</option>
                    <option value="AM">Amazonas</option>
                    <option value="BA" selected>Bahia</option>
                    <option value="CE">Ceará</option>
                    <option value="DF">Distrito Federal</option>
                    <option value="ES">Espírito Santo</option>
                    <option value="GO">Goiás</option>
                    <option value="MA">Maranhão</option>
                    <option value="MT">Mato Grosso</option>
                    <option value="MS">Mato Grosso do Sul</option>
                    <option value="MG">Minas Gerais</option>
                    <option value="PA">Pará</option>
                    <option value="PB">Paraíba</option>
                    <option value="PR">Paraná</option>
                    <option value="PE">Pernambuco</option>
                    <option value="PI">Piauí</option>
                    <option value="RJ">Rio de Janeiro</option>
                    <option value="RN">Rio Grande do Norte</option>
                    <option value="RS">Rio Grande do Sul</option>
                    <option value="RO">Rondônia</option>
                    <option value="RR">Roraima</option>
                    <option value="SC">Santa Catarina</option>
                    <option value="SP">São Paulo</option>
                    <option value="SE">Sergipe</option>
                    <option value="TO">Tocantins</option>
                </select>
            </div>            
            <div class="col">
                <label for="filter-prazo">Prazo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-300 height-md" id="filter-prazo" required>
                    <option value="5 dias">5 dias</option>
                    <option value="10 dias">10 dias</option>
                    <option value="15 dias">15 dias</option>
                    <option value="30 dias">30 dias</option>
                    <option value="60 dias" selected>60 dias</option>
                    <option value="90 dias">90 dias</option>
                </select>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <!-- Ações -->
        <div class="card-buttons">
            <div class="col row-fit justify-start">
                <button class="btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
                <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de envio</button> <!-- visível apenas para usuários com permissão de administrador -->
            </div>
            <div class="col row-fit justify-end">
                <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
            </div>
        </div>        
    </div>
        
    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/user.js"></script>
    <script>
        window.__currentUserName = <?php echo json_encode($currentUserName, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script>
        (function () {
            if (window.App && App.CartorioCreateBootstrap) {
                App.CartorioCreateBootstrap.bindBackButton({ href: '/frame.php?page=cartasexpedidas' });
                App.CartorioCreateBootstrap.bindClasseResetOnShortProcess();
            }
            if (window.App && App.CartorioCityLookup) {
                App.CartorioCityLookup.initialize({
                    endpoint: '/api/cartorio-cartas-expedidas/buscar-cidade.php?cidade=',
                    blockCcm: true,
                    maxLength: 50,
                    formatMaxLength: 50
                });
            }
            if (!window.App || !App.CartorioCartasExpedidasCreate || typeof App.CartorioCartasExpedidasCreate.initialize !== 'function') {
                return;
            }

            App.CartorioCartasExpedidasCreate.initialize({ alocacaoPrefix: 'cartas-exp' });
        })();
    </script>

</body>
</html>
