<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';

$connection = Conection::getInstance()->getConnection();

function cartas_expedidas_only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function cartas_expedidas_modulo97(string $value): int
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

function cartas_expedidas_calcular_dv_cnj(string $valor): ?string
{
    $digits = cartas_expedidas_only_digits($valor);
    if (strlen($digits) !== 20) {
        return null;
    }
    $base = substr($digits, 0, 7) . substr($digits, 9);
    $resto = cartas_expedidas_modulo97($base . '00');
    $dvCalc = 98 - $resto;
    return str_pad((string) $dvCalc, 2, '0', STR_PAD_LEFT);
}

function cartas_expedidas_validar_cnj(string $valor): bool
{
    $digits = cartas_expedidas_only_digits($valor);
    if (strlen($digits) !== 20) {
        return false;
    }
    $dv = substr($digits, 7, 2);
    $dvCalc = cartas_expedidas_calcular_dv_cnj($digits);
    if ($dvCalc === null) {
        return false;
    }
    return $dv === $dvCalc;
}

$cnjError = '';
$submittedProcesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedProcesso = trim((string) ($_POST['processo'] ?? $_POST['filter-processo'] ?? ''));
    if ($submittedProcesso !== '' && !cartas_expedidas_validar_cnj($submittedProcesso)) {
        $cnjError = 'Número do processo inválido. Verifique se está completo e correto.';
    }
}

$currentUserName = '';
$currentUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
if ($currentUserId !== '') {
    $stmtU = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
    $stmtU->execute([$currentUserId]);
    $u = $stmtU->fetch(PDO::FETCH_ASSOC);
    if ($u !== false) {
        $n = trim((string) ($u['nome'] ?? ''));
        $s = trim((string) ($u['sobrenome'] ?? ''));
        $currentUserName = $s !== '' ? $n . ' ' . $s : $n;
    }
}

$currentUserPerfis = $currentUserId !== ''
    ? UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserId)
    : [];

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

$estados = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins',
];

$finalidades = [
    'Intimação para Audiência'                   => 'Intimação para Audiência',
    'Intimação de Decisão Interlocutória'        => 'Intimação de Decisão',
    'Intimação da Sentença'                      => 'Intimação da Sentença',
    'Citação para apresentar Defesa'             => 'Citação para apresentar Defesa',
    'Citação para apresentar Alegações Finais'   => 'Citação para apresentar Alegações Finais',
    'Suspensão Condicional'                      => 'Suspensão Condicional',
    'Outra'                                      => 'Outra não listada',
];

$prazos = ['5 dias', '10 dias', '15 dias', '30 dias', '60 dias', '90 dias'];
$prazoDefault = '60 dias';

$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$userContextV         = @filemtime($assetsRoot . '/js/shared/user-context.js') ?: time();
$pageHelpersV         = @filemtime($assetsRoot . '/js/shared/page-helpers.js') ?: time();
$toastCatalogV        = @filemtime($assetsRoot . '/js/shared/toast-catalog.js') ?: time();
$navBtnV              = @filemtime($assetsRoot . '/js/shared/navigate-buttons.js') ?: time();
$properNameV          = @filemtime($assetsRoot . '/js/shared/proper-name-helper.js') ?: time();
$textFormatterV       = @filemtime($assetsRoot . '/js/shared/text-formatter.js') ?: time();
$createHelpersV       = @filemtime($assetsRoot . '/js/shared/cartorio-create-helpers.js') ?: time();
$createBootstrapV     = @filemtime($assetsRoot . '/js/shared/cartorio-create-bootstrap.js') ?: time();
$cityLookupV          = @filemtime($assetsRoot . '/js/shared/cartorio-city-lookup.js') ?: time();
$cartasExpCreateV     = @filemtime($assetsRoot . '/js/shared/cartorio-cartas-expedidas-create.js') ?: time();

$normalizedPerfis = [];
foreach ($currentUserPerfis as $perfilRaw) {
    $perfilNormalizado = strtoupper(trim((string) $perfilRaw));
    if ($perfilNormalizado !== '') {
        $normalizedPerfis[] = $perfilNormalizado;
    }
}
$sessionUserName = trim((string) ($_SESSION['nome'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <style>
        /* Ajustes locais: variante "secondary" do button-btn v2 e banner clic\u00e1vel. */
        .button-btn {
            min-height: 40px;
            padding: 0 20px;
            font-family: 'Google Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
        }
        .button-btn.btn-secondary {
            background-color: var(--color-background);
            border: 1px solid var(--color-border);
            color: var(--color-title-text);
        }
        .button-btn.btn-secondary:hover {
            background-color: var(--color-gray-300);
        }
        .block-cell {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-start;
            gap: 4px;
            padding: 6px 10px;
        }
        .block-cell label { margin-left: 0; }
        .block-cell label.empty { display: none; }
        .line-results.alert.rule.clickable {
            cursor: pointer;
            transition: background-color 120ms ease-in-out;
        }
        .line-results.alert.rule.clickable:hover {
            background-color: var(--color-bg-current-light);
        }
        .line-results.alert.rule.clickable:focus-visible {
            outline: 2px solid var(--color-bg-primary);
            outline-offset: 2px;
        }
        .status-classe {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>

<body data-theme="light">

    <div class="page-card-list">

        <div class="page-card-list-title">
            <div class="page-card-list-title-line">
                <div class="line-left title">
                    <span class="page-title-text">Novo Cadastro de Carta Expedida</span>
                </div>
            </div>
            <div class="page-card-list-title-line">
                <div class="line-left description">
                    <span class="page-description-text">Documentos expedidos pelo Cartório para cumprimento por outras Comarcas</span>
                </div>
                <div class="line-right"></div>
            </div>
        </div>

        <div class="card-line">
            <div class="block-cell">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*</span></label>
                <input type="text" class="width-300 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off" required value="<?php echo htmlspecialchars($submittedProcesso, ENT_QUOTES); ?>">
            </div>
            <div class="block-cell">
                <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*</span></label>
                <select class="width-300 height-md" id="filter-tipo" required>
                    <option value="" selected disabled>Selecione aqui</option>
                    <?php foreach ($finalidades as $valor => $rotulo): ?>
                        <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES); ?>"><?php echo htmlspecialchars($rotulo, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="block-cell">
                <label for="filter-prazo">Prazo de cumprimento <span class="label-hint-obrigatorio">*</span></label>
                <select class="width-200 height-md" id="filter-prazo" required>
                    <?php foreach ($prazos as $prazoOpcao): ?>
                        <option value="<?php echo htmlspecialchars($prazoOpcao, ENT_QUOTES); ?>"<?php echo $prazoOpcao === $prazoDefault ? ' selected' : ''; ?>><?php echo htmlspecialchars($prazoOpcao, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card-line">
            <div class="block-cell">
                <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*</span></label>
                <select class="width-200 height-md" id="filter-estado" required>
                    <?php foreach ($estados as $uf => $nomeEstado): ?>
                        <option value="<?php echo htmlspecialchars($uf, ENT_QUOTES); ?>"<?php echo $uf === 'BA' ? ' selected' : ''; ?>><?php echo htmlspecialchars($nomeEstado, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="block-cell">
                <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*</span></label>
                <input type="text" class="width-400 height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" autocomplete="off" required>
            </div>
        </div>

        <div class="card-line full">
            <div class="block-cell full">
                <label class="status-classe" for="filter-classe">
                    <span>Classe processual</span>
                    <span class="label-hint-consulta-datajud" id="processo-ap-info" aria-live="polite"></span>
                </label>
                <input type="text" class="width-400 height-md" id="filter-classe" data-datajud-related="true" value="" disabled autocomplete="off">
                <label id="filter-assuntos" class="empty"></label>
                <label id="filter-prioridades" class="empty"></label>
            </div>
        </div>

        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <input type="hidden" id="filter-sistema-envio" name="sistema_envio" value="outro">

        <div class="card-line full">
            <div class="card-line left results">
                <div class="line-results alert" id="banner-alterar-regra" data-rule-target="cartasexpedidasrules" role="note">
                    <div class="text rule">
                        <?php if ($hasRuleAllocation): ?>
                            Envio de Cartas Expedidas será realizado por <span class="text-primary"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></span> conforme regra definida.
                        <?php else: ?>
                            Nenhum usuário alocado por regra para o envio de Cartas Expedidas.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-line right">
                <div class="buttons">
                    <button type="button" class="button-btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
                    <button type="button" class="button-btn btn-primary" id="cadastrar-btn">Cadastrar</button>
                </div>
            </div>
        </div>

    </div>

    <?php if ($cnjError): ?>
        <script>
            if (typeof showToast === 'function') {
                showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($cnjError, JSON_UNESCAPED_UNICODE); ?> });
            }
        </script>
    <?php endif; ?>

    <script>
        window.__APP_CONTEXT = Object.assign({}, window.__APP_CONTEXT || {}, {
            initialPerfis: <?php echo json_encode(array_values($normalizedPerfis), JSON_UNESCAPED_UNICODE); ?>,
            fallbackPerfis: [],
            allowedRulePerfis: ['ADMIN', 'DIRETORA', 'MAGISTRADA'],
            currentUser: {
                id: <?php echo json_encode($currentUserId, JSON_UNESCAPED_UNICODE); ?>,
                nome: <?php echo json_encode($sessionUserName, JSON_UNESCAPED_UNICODE); ?>,
                usuario: <?php echo json_encode($sessionUserName, JSON_UNESCAPED_UNICODE); ?>
            }
        });
        window.__currentUserName = <?php echo json_encode($currentUserName, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/shared/user-context.js?v=<?php echo (int) $userContextV; ?>"></script>
    <script src="/assets/js/shared/page-helpers.js?v=<?php echo (int) $pageHelpersV; ?>"></script>
    <script src="/assets/js/shared/toast-catalog.js?v=<?php echo (int) $toastCatalogV; ?>"></script>
    <script src="/assets/js/shared/navigate-buttons.js?v=<?php echo (int) $navBtnV; ?>"></script>
    <script src="/assets/js/shared/text-formatter.js?v=<?php echo (int) $textFormatterV; ?>"></script>
    <script src="/assets/js/shared/proper-name-helper.js?v=<?php echo (int) $properNameV; ?>"></script>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script src="/assets/js/shared/cartorio-create-helpers.js?v=<?php echo (int) $createHelpersV; ?>"></script>
    <script src="/assets/js/shared/cartorio-create-bootstrap.js?v=<?php echo (int) $createBootstrapV; ?>"></script>
    <script src="/assets/js/shared/cartorio-city-lookup.js?v=<?php echo (int) $cityLookupV; ?>"></script>
    <script src="/assets/js/shared/cartorio-cartas-expedidas-create.js?v=<?php echo (int) $cartasExpCreateV; ?>"></script>
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

            // Banner clic\u00e1vel: admin/chefia abre a p\u00e1gina de altera\u00e7\u00e3o de regra de aloca\u00e7\u00e3o.
            (function setupRuleBannerClick() {
                var banner = document.getElementById('banner-alterar-regra');
                if (!banner) return;
                var targetPage = banner.getAttribute('data-rule-target') || '';
                if (!targetPage) return;
                if (!window.App || !App.User || typeof App.User.fetchPerfis !== 'function' || typeof App.User.canChangeRule !== 'function') return;
                App.User.fetchPerfis().then(function (perfis) {
                    if (!App.User.canChangeRule(perfis)) return;
                    banner.classList.add('clickable');
                    banner.setAttribute('role', 'button');
                    banner.setAttribute('tabindex', '0');
                    banner.setAttribute('title', 'Alterar regra de alocação');
                    banner.setAttribute('aria-label', 'Alterar regra de alocação');
                    var navigate = function () {
                        window.location.href = '/frame.php?page=' + encodeURIComponent(targetPage);
                    };
                    banner.addEventListener('click', navigate);
                    banner.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            navigate();
                        }
                    });
                }).catch(function () { /* silent */ });
            })();

            if (!window.App || !App.CartorioCartasExpedidasCreate || typeof App.CartorioCartasExpedidasCreate.initialize !== 'function') {
                return;
            }
            App.CartorioCartasExpedidasCreate.initialize({ alocacaoPrefix: 'cartas-exp' });
        })();
    </script>
</body>

</html>
