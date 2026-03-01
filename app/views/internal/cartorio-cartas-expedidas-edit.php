<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../services/CartasExpedidasService.php';

$connection = Conection::getInstance()->getConnection();

$idCarta = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$carta = null;

if ($idCarta === '') {
    $errorMessage = 'Identificador da carta expedida não informado.';
} else {
    try {
        $service = new CartasExpedidasService($connection);
        $carta = $service->find($idCarta);
        if ($carta === null) {
            $errorMessage = 'Carta expedida não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a carta expedida: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';

$finalidadeValue = '';
$cidadeValue = '';
$ufValue = 'BA';
$prazoAtual = 30;
$statusAtual = 'aguardando assinatura';
$observacoesValue = '';
$comarcaDestinoId = '';

if ($carta !== null) {
    $processosRepo = new ProcessosRepository($connection);
    $processoRow = $processosRepo->findById((string) ($carta['processo_origem_id'] ?? ''));
    if ($processoRow !== null) {
        $processoNumero = trim((string) ($processoRow['numero_processo'] ?? ''));
        $classeId = trim((string) ($processoRow['classe_id'] ?? ''));
        if ($classeId !== '') {
            $classesRepo = new ClassesRepository($connection);
            $classeRow = $classesRepo->findById($classeId);
            if ($classeRow !== null) {
                $classeNome = trim((string) ($classeRow['nome'] ?? 'Classe não informada'));
                if ($classeNome === '') {
                    $classeNome = 'Classe não informada';
                }
                $classeCodigo = trim((string) ($classeRow['codigo'] ?? ''));
            }
        }
    }

    $finalidadeValue = trim((string) ($carta['finalidade'] ?? ''));
    $cidadeValue = trim((string) ($carta['cidade_destino'] ?? ''));
    $ufValue = strtoupper(trim((string) ($carta['uf_destino'] ?? 'BA')));
    if ($ufValue === '') {
        $ufValue = 'BA';
    }
    $prazoAtual = (int) ($carta['prazo_dias'] ?? 0);
    if ($prazoAtual <= 0) {
        $prazoAtual = 30;
    }
    $statusAtual = trim((string) ($carta['status'] ?? ''));
    if ($statusAtual === '') {
        $statusAtual = 'aguardando assinatura';
    }
    $observacoesValue = trim((string) ($carta['observacoes'] ?? ''));
    $comarcaDestinoId = trim((string) ($carta['comarca_destino_id'] ?? ''));
}

$ruleServidor = '?';
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
            if ($ruleServidor === '') {
                $ruleServidor = '?';
            }
        }
    }
} catch (\Throwable $exception) {
    // Mantém o valor padrão se houver erro na leitura do banco.
}

$finalidadeOptions = [
    'Intimação para Audiência',
    'Intimação de Decisão Interlocutória',
    'Intimação da Sentença',
    'Citação para apresentar Defesa',
    'Citação para apresentar Alegações Finais',
    'Suspensão Condicional',
    'Outra',
];

$deadlineOptions = ['5 dias', '10 dias', '15 dias', '30 dias', '60 dias', '90 dias'];

$statusOptions = [
    'aguardando assinatura' => 'Aguardando assinatura',
    'aguardando envio' => 'Aguardando envio',
    'aguardando juntada do termo' => 'Aguardando juntada de termo',
    'aguardando cumprimento' => 'Aguardando cumprimento',
    'concluída' => 'Concluída',
    'cancelada' => 'Cancelada',
];

$stateOptions = [
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
?>
<!DOCTYPE html>
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição de Carta Expedida - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar Carta Expedida</h1>
        <p class="description-page">Altere os dados cadastrados e salve para atualizar a carta expedida.</p>
        <a href="/documents/pdf/PJBA - 1VCR - POP - Expedição e Envio de Cartas Precatórias.pdf" target="_blank" rel="noopener noreferrer">
            <div class="badge file">
                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
                <span>Passo a passo - Expedição de Cartas Precatórias</span>
            </div>
        </a>
    </div>

    <div class="card-page">
        <?php if ($errorMessage !== ''): ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES); ?></label>
                </div>
            </div>
        <?php else: ?>
            <div class="row table header">Informações obrigatórias</div>
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="width-md height-md readonly" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <input type="text" id="filter-classe" class="width-lg height-md readonly" value="<?php echo htmlspecialchars($classeCodigo !== '' ? ($classeCodigo . ' - ' . $classeNome) : $classeNome, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-tipo">Finalidade</label>
                    <select class="width-lg height-md" id="filter-tipo" required>
                        <option value="" disabled>Selecione aqui</option>
                        <?php foreach ($finalidadeOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $finalidadeValue === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-cidade">Cidade</label>
                    <input type="text" class="width-lg height-md" id="filter-cidade" placeholder="Digite o nome da cidade" maxlength="120" value="<?php echo htmlspecialchars($cidadeValue, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="filter-estado">Estado</label>
                    <select class="width-md height-md" id="filter-estado" required>
                        <?php foreach ($stateOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>"<?php echo $ufValue === $code ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-prazo">Prazo cumprimento</label>
                    <select class="width-sm height-md" id="filter-prazo" required>
                        <?php foreach ($deadlineOptions as $option): ?>
                            <?php $digits = preg_replace('/\D+/', '', $option); ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo (int) $digits === $prazoAtual ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <!-- <div class="row filter">
                <div class="col filter">
                    <label for="filter-observacoes">Observações</label>
                    <textarea id="filter-observacoes" class="width-xl" rows="4"><?php echo htmlspecialchars($observacoesValue, ENT_QUOTES); ?></textarea>
                </div>
            </div> -->

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-comarca-id" value="<?php echo htmlspecialchars($comarcaDestinoId, ENT_QUOTES); ?>">

        <?php endif; ?>
        <div class="row buttons">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <button class="btn btn-primary" id="salvar-btn" type="button" disabled>Salvar</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($carta !== null): ?>
        <script>
            window.__initialCartaData = {
                finalidade: <?php echo json_encode($finalidadeValue, JSON_UNESCAPED_UNICODE); ?>,
                prazoDias: <?php echo json_encode($prazoAtual, JSON_UNESCAPED_UNICODE); ?>,
                cidade: <?php echo json_encode($cidadeValue, JSON_UNESCAPED_UNICODE); ?>,
                estado: <?php echo json_encode($ufValue, JSON_UNESCAPED_UNICODE); ?>,
                status: <?php echo json_encode($statusAtual, JSON_UNESCAPED_UNICODE); ?>,
                observacoes: <?php echo json_encode($observacoesValue, JSON_UNESCAPED_UNICODE); ?>
            };
            window.__currentCartaId = <?php echo json_encode($idCarta, JSON_UNESCAPED_UNICODE); ?>;
        </script>
        <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
        <script>
            (() => {
                const endpoint = '/api/cartorio-cartas-expedidas/update.php';
                const salvarBtn = document.getElementById('salvar-btn');
                const feedback = document.getElementById('cadastro-feedback');
                const cidadeInput = document.getElementById('filter-cidade');
                const estadoSelect = document.getElementById('filter-estado');
                const finalidadeSelect = document.getElementById('filter-tipo');
                const prazoSelect = document.getElementById('filter-prazo');
                const statusSelect = document.getElementById('filter-status');
                const observacoesInput = document.getElementById('filter-observacoes');
                const hiddenComarcaInput = document.getElementById('hidden-comarca-id');
                const initialData = window.__initialCartaData || {};

                if (!salvarBtn || !finalidadeSelect || !prazoSelect || !statusSelect || !cidadeInput || !estadoSelect || !observacoesInput) {
                    return;
                }

                const showFeedback = (message, type = 'success') => {
                    if (!feedback) return;
                    const variant = type === 'error' ? 'status-cancelled' : 'status-finished';
                    feedback.innerHTML = `<label class="status ${variant}">${message}</label>`;
                };

                const sanitizePrazo = (value) => {
                    const digits = (value || '').replace(/\D+/g, '');
                    if (digits === '') {
                        return '0';
                    }
                    return digits.slice(0, 3);
                };

                const normalizeCidadeForCompare = (value) => {
                    return (value || '').trim().toLowerCase()
                        .normalize('NFD').replace(/\p{M}/gu, '')
                        .replace(/ç/g, 'c');
                };

                const formatCidade = (value) => {
                    if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                        return TextFormatter.formatProperName(value, 120);
                    }
                    return value;
                };

                const getCurrentState = () => ({
                    finalidade: (finalidadeSelect.value || '').trim(),
                    prazo: sanitizePrazo(prazoSelect.value),
                    cidade: cidadeInput.value.trim(),
                    estado: (estadoSelect.value || '').trim(),
                    status: (statusSelect.value || '').trim(),
                    observacoes: observacoesInput.value.trim(),
                });

                const hasChanges = () => {
                    const current = getCurrentState();
                    if (initialData.finalidade !== current.finalidade) return true;
                    if ((initialData.prazoDias || '0').toString() !== current.prazo) return true;
                    if (normalizeCidadeForCompare(initialData.cidade) !== normalizeCidadeForCompare(current.cidade)) return true;
                    if ((initialData.estado || '') !== current.estado) return true;
                    if ((initialData.status || '') !== current.status) return true;
                    if ((initialData.observacoes || '') !== current.observacoes) return true;
                    return false;
                };

                const updateButtonState = () => {
                    salvarBtn.disabled = !hasChanges();
                };

                ['input', 'change'].forEach((eventName) => {
                    [finalidadeSelect, prazoSelect, statusSelect, cidadeInput, estadoSelect, observacoesInput].forEach((element) => {
                        if (element) {
                            element.addEventListener(eventName, updateButtonState);
                        }
                    });
                });

                const applyCidadeFormatting = () => {
                    cidadeInput.value = formatCidade(cidadeInput.value);
                };

                const fetchCidadeInfo = () => {
                    const cidade = cidadeInput.value.trim();
                    if (cidade.length < 2) return;
                    fetch('/api/cartorio-cartas-expedidas/buscar-cidade.php?cidade=' + encodeURIComponent(cidade))
                        .then((response) => response.json())
                        .then((data) => {
                            if (!data || !data.found) return;
                            if (data.uf && estadoSelect) {
                                estadoSelect.value = data.uf;
                            }
                            const status = (data.status_cidade || '').trim();
                            if (status.toUpperCase() === 'CCM') {
                                alert('Comarca com Central de Mandados');
                                cidadeInput.value = '';
                                if (hiddenComarcaInput) hiddenComarcaInput.value = '';
                            } else {
                                if (status.toLowerCase() === 'agregada' && data.nome) {
                                    cidadeInput.value = formatCidade(data.nome);
                                }
                                if (data.id && hiddenComarcaInput) hiddenComarcaInput.value = data.id;
                            }
                        })
                        .catch(() => {});
                };

                cidadeInput.addEventListener('blur', () => {
                    applyCidadeFormatting();
                    fetchCidadeInfo();
                });

                salvarBtn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    if (salvarBtn.disabled) return;
                    const payloadState = getCurrentState();
                    if (payloadState.finalidade === '' || payloadState.cidade === '' || payloadState.estado === '') {
                        return showFeedback('Finalidade, cidade e estado são obrigatórios.', 'error');
                    }
                    if (normalizeCidadeForCompare(payloadState.cidade) === 'ilheus') {
                        return showFeedback('O cadastro não é permitido para a cidade de Ilhéus (comarca de origem).', 'error');
                    }
                    if (!hiddenComarcaInput.value.trim()) {
                        return showFeedback('Selecione uma cidade válida para que a comarca seja identificada.', 'error');
                    }

                    const payload = {
                        id: window.__currentCartaId,
                        finalidade: payloadState.finalidade,
                        prazo_dias: parseInt(payloadState.prazo, 10) || 0,
                        cidade_destino: formatCidade(payloadState.cidade),
                        uf_destino: payloadState.estado,
                        status: payloadState.status,
                        observacoes: payloadState.observacoes,
                        comarca_destino_id: hiddenComarcaInput.value.trim(),
                    };

                    salvarBtn.disabled = true;
                    showFeedback('Atualizando dados...', 'success');

                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload),
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || 'Falha ao atualizar a carta expedida.');
                        }
                        window.location.href = '/frame.php?page=cartasexpedidas';
                    } catch (error) {
                        showFeedback(error.message || 'Erro ao salvar.', 'error');
                        salvarBtn.disabled = false;
                    }
                });

                updateButtonState();
            })();
        </script>
    <?php endif; ?>

    <script>
        (() => {
            const btn = document.getElementById('voltar-btn');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                window.location.href = '/frame.php?page=cartasexpedidas';
            }, { capture: true });
        })();
    </script>

</body>
</html>
