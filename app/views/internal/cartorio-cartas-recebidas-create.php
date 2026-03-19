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
$ruleName = 'ALLOCATE-CARTASRECEBIDAS';
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
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Recebidas - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Carta Recebida</h1>
        <p class="description-page">Preencha os dados da carta precatória recebida de outro tribunal.</p>
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
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
            <div class="col filter">
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
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-cidade">Cidade de origem <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" required>
            </div>
            <div class="col filter">
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
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <?php if ($cnjError): ?>
            <div class="display-flex row row-full padding-x-sm padding-y-sm">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($cnjError, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php endif; ?>

        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <?php if ($hasRuleAllocation): ?>
                <span>*Conforme regra de alocação, as Cartas Recebidas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
            <?php else: ?>
                <span>*Não existe nenhum usuário alocado automaticamente para esta tarefa no momento.</span>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de alocação</button>
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
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

    <script>
        (() => {
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    window.location.href = '/frame.php?page=cartasrecebidas';
                }, { capture: true });
            }
        })();
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script>
        (function () {
            var processoInput = document.getElementById('filter-processo');
            if (processoInput) {
                processoInput.addEventListener('input', function () {
                    var digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        var classeInput = document.getElementById('filter-classe');
                        if (classeInput) {
                            classeInput.value = '';
                            classeInput.readOnly = true;
                        }
                        var hCod = document.getElementById('hidden-classe-codigo');
                        var hDesc = document.getElementById('hidden-classe-descricao');
                        if (hCod) hCod.value = '';
                        if (hDesc) hDesc.value = '';
                    }
                });
            }
        })();
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
    <script>
        (function () {
            var PERFIS_ALTERAR_REGRA = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
            function hasPermissionToChangeRule(perfis) {
                if (!Array.isArray(perfis) || perfis.length === 0) return false;
                var allowed = PERFIS_ALTERAR_REGRA.map(function (p) { return p.toUpperCase(); });
                return perfis.some(function (perfil) {
                    var p = (perfil && String(perfil)).toUpperCase().trim();
                    return allowed.indexOf(p) !== -1;
                });
            }
            window.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('alterar-regra-btn');
                if (!btn) return;
                fetch('/admin/api/getPerfis.php', { credentials: 'same-origin' })
                    .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                    .then(function (data) {
                        var perfis = (data && data.perfis) ? data.perfis : [];
                        if (hasPermissionToChangeRule(perfis)) {
                            btn.style.display = '';
                            btn.addEventListener('click', function () {
                                window.location.href = '/frame.php?page=cartasrecebidasrules';
                            });
                        }
                    })
                    .catch(function () { btn.style.display = 'none'; });
            });
        })();
    </script>
    <script>
        (() => {
            const btn = document.getElementById('cadastrar-btn');
            const classeCodigoField = document.getElementById('hidden-classe-codigo');
            const classeDescricaoField = document.getElementById('hidden-classe-descricao');
            const showFeedback = (message, type = 'success') => {
                if (typeof showToast === 'function') {
                    showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message });
                }
            };
            const getValue = (id) => document.getElementById(id)?.value?.trim() ?? '';

            const formatCidade = (value) => {
                if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                    return TextFormatter.formatProperName(value, 50);
                }
                return value;
            };

            const cidadeInput = document.getElementById('filter-cidade');
            const estadoSelect = document.getElementById('filter-estado');
            if (cidadeInput) {
                cidadeInput.addEventListener('blur', () => {
                    cidadeInput.value = formatCidade(cidadeInput.value);
                    const cidade = cidadeInput.value.trim();
                    if (cidade.length < 2) return;
                    fetch('/api/cartorio-cartas-recebidas/buscar-cidade.php?cidade=' + encodeURIComponent(cidade))
                        .then(r => r.json())
                        .then(data => {
                            if (!data.found) return;
                            if (data.uf && estadoSelect) {
                                estadoSelect.value = data.uf;
                            }
                            const status = (data.status_cidade || '').trim();
                            if (status.toUpperCase() === 'CCM') {
                                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Comarca com Central de Mandados' });
                                cidadeInput.value = '';
                            } else if (status.toLowerCase() === 'agregada' && data.nome) {
                                cidadeInput.value = formatCidade(data.nome);
                            }
                        })
                        .catch(() => {});
                });
            }

            if (!btn) return;

            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (btn.disabled) return;
                // Dispara blur para aplicar correção automática (CNJ no processo; maiúsculas/minúsculas na cidade)
                document.getElementById('filter-processo')?.blur();
                document.getElementById('filter-cidade')?.blur();

                const processoInputEl = document.getElementById('filter-processo');
                const processoRaw = getValue('filter-processo');
                if (processoRaw === '') {
                    return showFeedback('Informe o número do processo antes de cadastrar.', 'error');
                }
                if (processoInputEl && typeof window.normalizeAndValidateCNJValue === 'function') {
                    const normalized = window.normalizeAndValidateCNJValue(processoRaw, { toastOnInvalid: true });
                    if (!normalized.ok) {
                        processoInputEl.value = '';
                        processoInputEl.focus();
                        return;
                    }
                    processoInputEl.value = normalized.formatted;
                }

                const cidadeOrigem = formatCidade(getValue('filter-cidade'));
                const ufOrigem = getValue('filter-estado');
                const finalidade = getValue('filter-tipo');
                if (cidadeOrigem === '') {
                    return showFeedback('Informe a cidade de origem.', 'error');
                }
                if (ufOrigem === '') {
                    return showFeedback('Informe o estado.', 'error');
                }
                if (finalidade === '') {
                    return showFeedback('Informe a finalidade.', 'error');
                }

                const classeVal = getValue('filter-classe');
                if (classeVal === 'Aguarde...') {
                    return showFeedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                }

                const prazo = parseInt(document.getElementById('filter-prazo')?.value ?? '30', 10) || 30;

                const payload = {
                    numero_processo_origem: getValue('filter-processo'),
                    cidade_origem: cidadeOrigem,
                    uf_origem: ufOrigem,
                    finalidade,
                    prazo_dias: prazo,
                };
                if (classeCodigoField?.value.trim()) {
                    payload.classe_codigo = classeCodigoField.value.trim();
                }
                if (classeDescricaoField?.value.trim()) {
                    payload.classe_descricao = classeDescricaoField.value.trim();
                } else if (classeVal && classeVal !== 'Classe não informada' && classeVal !== 'Aguarde...') {
                    payload.classe_descricao = classeVal;
                    if (!payload.classe_codigo) {
                        payload.classe_codigo = classeVal.replace(/\s+/g, '').substring(0, 20);
                    }
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Enviando dados...' });
                else showFeedback('Enviando dados...', 'success');

                try {
                    const response = await fetch('/api/cartorio-cartas-recebidas/create.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'Falha ao cadastrar a carta recebida.');
                    }
                    window.location.href = '/frame.php?page=cartasrecebidas';
                } catch (err) {
                    showFeedback(err.message || 'Não foi possível cadastrar.', 'error');
                    btn.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
