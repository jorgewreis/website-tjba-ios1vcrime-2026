<?php
declare(strict_types=1);

require_once __DIR__ . '/../class/Connect-New.php';
require_once __DIR__ . '/../class/Cartorio/CartasExpedidas/Rules/RulesAllocator.php';

use App\Cartorio\CartasExpedidas\Rules\RulesAllocator;

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

$ruleServidor = '?';
$ruleName = 'ALLOCATE-CARTASEXPEDIDAS';
try {
    $allocator = new RulesAllocator($connection);
    $allocatedName = $allocator->getAssignedUserName($ruleName);
    if ($allocatedName !== null) {
        $ruleServidor = $allocatedName;
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
        <title>Template Model - Components - Buttons</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/reset.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/variables.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/page.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/form.css">
        <link rel="stylesheet" href="./../v2026/public/assets/css/shared/table.css">
        
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
        <h1 class="title-page">Cadastro de Carta Expedida</h1>
        <p class="description-page">Caso tenha alguma dificuldade no preenchimento do cadastro, verifique os arquivos abaixo para obter ajuda.</p>
        
        <!-- Badges de arquivos para download -->
        <a href="./../docs/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" target="_blank" rel="noopener noreferrer">
            <div class="badge file">
                <img src="./../v2026/public/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
                <span>Passo a passo - Expedição de Cartas Precatórias</span>
            </div>
        </a>
    </div>

    <div class="card-page">       
        <div class="row table header">Informações obrigatórias</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-processo">Processo</label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="Digite o número do processo" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe</label>
                <input type="text" class="width-lg height-md" id="filter-classe" placeholder="Classe processual" required>                
            </div>
            <div class="col filter">
                <label for="filter-tipo">Finalidade</label>
                <select class="width-md height-md" id="filter-tipo" required>
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
            <div class="col filter">
                <label for="filter-prazo">Prazo cumprimento</label>
                <select class="width-sm height-md" id="filter-prazo" required>
                    <option value="5 dias">5 dias</option>
                    <option value="10 dias">10 dias</option>
                    <option value="15 dias">15 dias</option>
                    <option value="30 dias" selected>30 dias</option>
                    <option value="60 dias">60 dias</option>
                    <option value="90 dias">90 dias</option>
                </select>
            </div>
        </div>
        <?php if ($cnjError): ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($cnjError, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php endif; ?>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-estado">Estado</label>
                <select class="width-md height-md" id="filter-estado" required>
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
                <label for="filter-cidade">Cidade</label>
                <input type="text" class="width-lg height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="50" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col">
                <label class="status status-primary-light">*Conforme regra de divisão de tarefas, todas as Cartas Expedidas serão alocadas automaticamente e deverão ser remetidas por <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></label>
            </div>
            <div class="col">
                <label id="cadastro-feedback" class="status-placeholder" aria-live="polite"></label>
            </div>
        </div>

        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <!-- Ações -->
        <div class="row buttons">
            <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de envio</button> <!-- visível apenas para usuários com permissão de administrador -->
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
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

    <script>
        (() => {
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.location.href = 'cartorio-cartas-expedidas-list.php';
                }, { capture: true });
            }
        })();
    </script>
    <script src="../v2026/public/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="../v2026/public/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script src="../v2026/public/assets/js/modules/cartorio/cartas-expedidas/user.js"></script>
    <script src="../v2026/public/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
    <script>
        (() => {
            const btn = document.getElementById('cadastrar-btn');
            const feedback = document.getElementById('cadastro-feedback');
            const classeCodigoField = document.getElementById('hidden-classe-codigo');
            const classeDescricaoField = document.getElementById('hidden-classe-descricao');
            const showFeedback = (message, type = 'success') => {
                if (!feedback) return;
                const variant = type === 'error' ? 'status-cancelled' : 'status-finished';
                feedback.innerHTML = `<label class="status ${variant}">${message}</label>`;
            };

            const getValue = (id) => document.getElementById(id)?.value.trim() || '';
            const sanitizePrazo = (value) => {
                const digits = (value || '').replace(/\D+/g, '');
                if (digits === '') {
                    return '0';
                }
                return digits.slice(0, 3);
            };
            const formatCidade = (value) => {
                if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                    return TextFormatter.formatProperName(value, 50);
                }
                return value;
            };
            const cidadeInput = document.getElementById('filter-cidade');
            if (cidadeInput) {
                cidadeInput.setAttribute('maxlength', '50');
                cidadeInput.addEventListener('blur', () => {
                    cidadeInput.value = formatCidade(cidadeInput.value);
                });
            }
            const endpoint = '../v2026/api/cartorio-cartas-expedidas/create.php';

            if (!btn) {
                return;
            }

            const requiredFields = [
                { id: 'filter-processo', label: 'Número do processo' },
                { id: 'filter-classe', label: 'Classe processual' },
                { id: 'filter-tipo', label: 'Finalidade' },
                { id: 'filter-prazo', label: 'Prazo de cumprimento' },
                { id: 'filter-estado', label: 'Estado' },
                { id: 'filter-cidade', label: 'Cidade' },
            ];

            const validateRequiredFields = () => {
                for (const field of requiredFields) {
                    const element = document.getElementById(field.id);
                    if (!element) {
                        continue;
                    }
                    const value = element.value.trim();
                    if (value === '') {
                        showFeedback(`O campo ${field.label} é obrigatório.`, 'error');
                        return false;
                    }
                }
                return true;
            };

            btn.addEventListener('click', async (event) => {
                event.preventDefault();
                if (btn.disabled) {
                    return;
                }
                const processoRaw = getValue('filter-processo');
                if (processoRaw === '') {
                    return showFeedback('Informe o número do processo antes de cadastrar.', 'error');
                }
                if (typeof window.validarCNJ === 'function' && !window.validarCNJ(processoRaw)) {
                    return showFeedback('O número do processo é inválido. Verifique os dígitos e o dígito verificador.', 'error');
                }
                if (!validateRequiredFields()) {
                    return;
                }

                const usuarioAtual = typeof getCurrentUserName === 'function' ? getCurrentUserName() : null;
                if (!usuarioAtual) {
                    return showFeedback('Não foi possível identificar o usuário logado.', 'error');
                }

                const payload = {
                    numero_processo: processoRaw,
                    classe: getValue('filter-classe'),
                    finalidade: getValue('filter-tipo'),
                    prazo: sanitizePrazo(getValue('filter-prazo')),
                    estado: getValue('filter-estado'),
                    cidade: formatCidade(getValue('filter-cidade')),
                    usuario: usuarioAtual,
                };

                if (classeCodigoField?.value.trim()) {
                    payload.classe_codigo = classeCodigoField.value.trim();
                }
                if (classeDescricaoField?.value.trim()) {
                    payload.classe_descricao = classeDescricaoField.value.trim();
                }

                btn.disabled = true;
                showFeedback('Enviando dados...', 'success');

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.error || 'Falha ao salvar a carta expedida.');
                    }

                    window.location.href = 'cartorio-cartas-expedidas-list.php';
                } catch (error) {
                    showFeedback(error.message || 'Não foi possível cadastrar a carta expedida.', 'error');
                    btn.disabled = false;
                }
            });
        })();
    </script>

</body>
</html>
