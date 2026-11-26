<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

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
$v2ColorsCssV         = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV       = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();

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
    <title>Novo cadastro de Carta Expedida - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <?php include __DIR__ . '/_internal_head_css.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>

<body class="page-internal page-operational-form">

    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Expedidas -</span>
            <span class="specific">Cadastro</span>
        </div>
    </div>

    <div class="container-full card-page">
        <div class="card-title">
            <h1 class="title-page">Novo cadastro de Carta Expedida</h1>
        </div>
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Informações da carta</h2>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="height-md" style="width: 220px;" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off" required value="<?php echo htmlspecialchars($submittedProcesso, ENT_QUOTES); ?>">
            </div>
            <div class="col filter">
                <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="height-md" style="width: 350px;" id="filter-tipo" required>
                    <option value="" selected disabled>Selecione aqui</option>
                    <?php foreach ($finalidades as $valor => $rotulo): ?>
                        <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES); ?>"><?php echo htmlspecialchars($rotulo, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-prazo">Prazo de cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-300 height-md" id="filter-prazo" required>
                    <?php foreach ($prazos as $prazoOpcao): ?>
                        <option value="<?php echo htmlspecialchars($prazoOpcao, ENT_QUOTES); ?>"<?php echo $prazoOpcao === $prazoDefault ? ' selected' : ''; ?>><?php echo htmlspecialchars($prazoOpcao, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="height-md" style="width: 220px;" id="filter-estado" required>
                    <?php foreach ($estados as $uf => $nomeEstado): ?>
                        <option value="<?php echo htmlspecialchars($uf, ENT_QUOTES); ?>"<?php echo $uf === 'BA' ? ' selected' : ''; ?>><?php echo htmlspecialchars($nomeEstado, ENT_QUOTES); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="height-md" style="width: 350px;" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" autocomplete="off" required>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label id="filter-assuntos" class="processo-bd-info empty" data-empty-prefix="Assuntos:" hidden></label>
                <label id="filter-prioridades" class="processo-bd-info empty" data-empty-prefix="Prioridades:" hidden></label>
            </div>
        </div>

        <input type="hidden" id="filter-classe" data-datajud-related="true" data-datajud-silent-loading="true">
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <input type="hidden" id="filter-sistema-envio" name="sistema_envio" value="outro">

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-primary-outline btn-cartas-voltar" id="voltar-btn" type="button" data-allow-during-loading="true">Voltar</button>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <button class="btn btn-primary btn-with-spinner disabled" id="cadastrar-btn" type="button" disabled aria-disabled="true">
                    <span class="btn-icon-text">Cadastrar</span>
                    <span class="btn-icon hidden"><i class="fi fi-rr-spinner" aria-hidden="true"></i></span>
                </button>
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
                    clearCcmCity: false,
                    onCcmCity: function (city, lookupData) {
                        if (App.CartorioCartasExpedidasCreate && App.CartorioCartasExpedidasCreate.confirmCcm) {
                            var estadoSelect = document.getElementById('filter-estado');
                            var uf = lookupData && lookupData.uf ? lookupData.uf : (estadoSelect ? estadoSelect.value : '');
                            App.CartorioCartasExpedidasCreate.confirmCcm(city, uf);
                        }
                    },
                    maxLength: 50,
                    formatMaxLength: 50
                });
            }

            // Bloco classe/assuntos/prioridades: BD primeiro + refresh ap\u00f3s DataJud.
            (function setupProcessoBdInfo() {
                var assuntosEl = document.getElementById('filter-assuntos');
                var prioridadesEl = document.getElementById('filter-prioridades');
                var processoInput = document.getElementById('filter-processo');
                if (!assuntosEl || !prioridadesEl || !processoInput) return;

                var lastDigitsLoaded = '';

                function onlyDigits(value) {
                    return String(value || '').replace(/\D+/g, '');
                }

                function renderInfoLabel(el, prefix, items) {
                    if (!el) return;
                    var list = Array.isArray(items)
                        ? items.filter(function (it) { return it !== null && it !== undefined && String(it).trim() !== ''; })
                        : [];
                    if (list.length === 0) {
                        el.hidden = true;
                        el.classList.add('empty');
                        el.textContent = '';
                        return;
                    }
                    el.hidden = false;
                    el.classList.remove('empty');
                    el.innerHTML = '';
                    var prefixSpan = document.createElement('span');
                    prefixSpan.className = 'processo-bd-info-label';
                    prefixSpan.textContent = prefix;
                    el.appendChild(prefixSpan);
                    el.appendChild(document.createTextNode(' ' + list.join(' | ')));
                }

                function formatAssunto(item) {
                    if (!item || typeof item !== 'object') return '';
                    var codigo = String(item.codigo || '').trim();
                    var descricao = String(item.descricao || '').trim();
                    if (descricao && codigo && descricao !== codigo) {
                        return codigo + ' - ' + descricao;
                    }
                    return descricao || codigo;
                }

                function formatPrioridade(item) {
                    if (!item || typeof item !== 'object') return '';
                    var label = String(item.label || '').trim();
                    var codigo = String(item.codigo || '').trim();
                    return label || codigo;
                }

                function clearInfo() {
                    renderInfoLabel(assuntosEl, 'Assuntos:', []);
                    renderInfoLabel(prioridadesEl, 'Prioridades:', []);
                    lastDigitsLoaded = '';
                }

                function fetchJson(url) {
                    return fetch(url, { credentials: 'same-origin' })
                        .then(function (response) {
                            return response.json().then(function (body) {
                                return { ok: response.ok, body: body };
                            });
                        });
                }

                function loadInfo(digits) {
                    if (!/^\d{20}$/.test(digits)) {
                        clearInfo();
                        return;
                    }
                    lastDigitsLoaded = digits;

                    var encoded = encodeURIComponent(digits);

                    fetchJson('/api/processos/assunto-read.php?numero=' + encoded)
                        .then(function (res) {
                            if (lastDigitsLoaded !== digits) return;
                            if (!res.ok || !res.body || res.body.error) {
                                renderInfoLabel(assuntosEl, 'Assuntos:', []);
                                return;
                            }
                            var assuntos = Array.isArray(res.body.assuntos) ? res.body.assuntos : [];
                            var formatados = assuntos.map(formatAssunto).filter(function (s) { return s !== ''; });
                            renderInfoLabel(assuntosEl, 'Assuntos:', formatados);
                        })
                        .catch(function () {
                            if (lastDigitsLoaded !== digits) return;
                            renderInfoLabel(assuntosEl, 'Assuntos:', []);
                        });

                    fetchJson('/api/processos/prioridades-read.php?numero=' + encoded)
                        .then(function (res) {
                            if (lastDigitsLoaded !== digits) return;
                            if (!res.ok || !res.body || res.body.error) {
                                renderInfoLabel(prioridadesEl, 'Prioridades:', []);
                                return;
                            }
                            var prioridades = Array.isArray(res.body.prioridades) ? res.body.prioridades : [];
                            var formatadas = prioridades.map(formatPrioridade).filter(function (s) { return s !== ''; });
                            renderInfoLabel(prioridadesEl, 'Prioridades:', formatadas);
                        })
                        .catch(function () {
                            if (lastDigitsLoaded !== digits) return;
                            renderInfoLabel(prioridadesEl, 'Prioridades:', []);
                        });
                }

                window.addEventListener('cnjValidated', function (event) {
                    var detail = (event && event.detail) || {};
                    if (detail.inputId && detail.inputId !== 'filter-processo') return;
                    var digits = onlyDigits(detail.digits || detail.numero || '');
                    if (digits.length === 20) {
                        loadInfo(digits);
                    } else {
                        clearInfo();
                    }
                });

                window.addEventListener('cnjInvalid', function (event) {
                    var detail = (event && event.detail) || {};
                    if (detail.inputId && detail.inputId !== 'filter-processo') return;
                    clearInfo();
                });

                // Ap\u00f3s DataJud finalizar, recarrega BD (postSyncAssuntos pode ter atualizado).
                window.addEventListener('datajudClasseResolved', function (event) {
                    var detail = (event && event.detail) || {};
                    if (detail.processoInputId && detail.processoInputId !== 'filter-processo') return;
                    var digits = onlyDigits(processoInput.value);
                    if (digits.length === 20) {
                        loadInfo(digits);
                    }
                });

                processoInput.addEventListener('input', function () {
                    var digits = onlyDigits(processoInput.value);
                    if (digits.length !== 20) {
                        clearInfo();
                    }
                });

                // Pr\u00e9-carga: se a p\u00e1gina abriu com processo j\u00e1 v\u00e1lido (POST com erro CNJ, por exemplo).
                var initialDigits = onlyDigits(processoInput.value);
                if (initialDigits.length === 20) {
                    loadInfo(initialDigits);
                }
            })();
            if (!window.App || !App.CartorioCartasExpedidasCreate || typeof App.CartorioCartasExpedidasCreate.initialize !== 'function') {
                return;
            }
            App.CartorioCartasExpedidasCreate.initialize({
                submitDelayMs: 1000,
                cityLookupEndpoint: '/api/cartorio-cartas-expedidas/buscar-cidade.php'
            });

            // Botão Cadastrar inicia desabilitado; só habilita quando TODOS os campos
            // marcados com "*" estiverem preenchidos. Trabalha em conjunto com o gate
            // (Aguarde.../Erro/Cadastrar) sem sobrepor seus estados de loading/erro.
            (function setupRequiredFieldsGate() {
                var cadastrarBtn = document.getElementById('cadastrar-btn');
                if (!cadastrarBtn) return;

                var hintNodes = document.querySelectorAll('label .label-hint-obrigatorio');
                var requiredFields = [];
                hintNodes.forEach(function (hint) {
                    var label = hint.closest('label');
                    if (!label) return;
                    var forId = label.getAttribute('for');
                    if (!forId) return;
                    var field = document.getElementById(forId);
                    if (field && requiredFields.indexOf(field) === -1) {
                        requiredFields.push(field);
                    }
                });
                if (requiredFields.length === 0) return;

                function isFilled(field) {
                    return String(field.value || '').trim() !== '';
                }
                function allFilled() {
                    return requiredFields.every(isFilled);
                }
                function gateWantsEnabled() {
                    var labelEl = cadastrarBtn.querySelector('.btn-icon-text');
                    var currentLabel = labelEl
                        ? String(labelEl.textContent || '').trim()
                        : String(cadastrarBtn.textContent || '').trim();
                    return currentLabel === 'Cadastrar';
                }
                function applyState() {
                    if (allFilled() && gateWantsEnabled()) {
                        cadastrarBtn.disabled = false;
                        cadastrarBtn.classList.remove('disabled');
                        cadastrarBtn.removeAttribute('aria-disabled');
                    } else {
                        cadastrarBtn.disabled = true;
                        cadastrarBtn.classList.add('disabled');
                        cadastrarBtn.setAttribute('aria-disabled', 'true');
                    }
                }

                requiredFields.forEach(function (field) {
                    field.addEventListener('input', applyState);
                    field.addEventListener('change', applyState);
                    field.addEventListener('blur', applyState);
                });
                window.addEventListener('datajudClasseResolved', applyState);
                window.addEventListener('cnjInvalid', applyState);

                applyState();
            })();
        })();
    </script>
</body>

</html>
