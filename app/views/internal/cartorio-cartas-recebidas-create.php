<?php
declare(strict_types=1);

require_once __DIR__ . '/../../services/CartasRecebidasService.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedProcesso = trim((string) ($_POST['processo'] ?? $_POST['filter-processo'] ?? ''));
    if ($submittedProcesso !== '' && !validarCNJ($submittedProcesso)) {
        $cnjError = 'Número do processo inválido. Verifique se está completo e correto.';
    }
}

$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Novo cadastro de Carta Recebida - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
        <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal page-operational-form">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Cadastro</span>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="card-title">
            <h1 class="title-page">Novo cadastro de Carta Recebida</h1>
        </div>
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações da carta</h2></div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="height-md" style="width: 220px;" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off" required>
            </div>
            <div class="col filter">
                <label for="filter-tipo-carta">Tipo de carta <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="height-md" style="width: 300px;" id="filter-tipo-carta" required>
                    <option value="" selected disabled>Selecione aqui</option>
                    <?php foreach (CartasRecebidasService::TIPOS_CARTA as $tipo => $classe): ?>
                        <option value="<?= htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($classe['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="height-md" style="width: 350px;" id="filter-tipo" required>
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
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-cidade">Cidade de origem <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="height-md" style="width: 350px;" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" autocomplete="off" required>
            </div>
            <div class="col filter">
                <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="height-md" style="width: 220px;" id="filter-estado" required>
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
            <div class="col filter">
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

        <?php if ($cnjError): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($cnjError, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php endif; ?>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-primary-outline btn-cartas-voltar" id="voltar-btn" type="button" data-allow-during-loading="true">Voltar</button>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <button class="btn btn-primary btn-with-spinner disabled" id="cadastrar-btn" type="button" disabled aria-disabled="true"><span class="btn-icon-text">Cadastrar</span><span class="btn-icon hidden"><i class="fi fi-rr-spinner" aria-hidden="true"></i></span></button>
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

    <script>
        (function () {
            if (window.App && App.CartorioCreateBootstrap) {
                App.CartorioCreateBootstrap.bindBackButton({ href: '/frame.php?page=cartasrecebidas' });
            }
            if (window.App && App.CartorioCityLookup) {
                App.CartorioCityLookup.initialize({
                    endpoint: '/api/cartorio-cartas-recebidas/buscar-cidade.php?cidade=',
                    blockCcm: false,
                    maxLength: 50,
                    formatMaxLength: 50
                });
            }
            if (!window.App || !App.CartorioCartasRecebidasCreate || typeof App.CartorioCartasRecebidasCreate.initialize !== 'function') {
                return;
            }

            App.CartorioCartasRecebidasCreate.initialize({});
        })();
    </script>
</body>
</html>
