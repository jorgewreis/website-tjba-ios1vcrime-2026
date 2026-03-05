<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

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

$ruleServidor = '?';
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
            if ($ruleServidor === '') {
                $ruleServidor = '?';
            }
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
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Carta Recebida</h1>
        <p class="description-page">Preencha os dados da carta precatória recebida de outro tribunal.</p>
    </div>

    <div class="card-page">
        <div class="row table header">Informações obrigatórias</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-processo">Número do processo de origem</label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="Número do processo (20 dígitos ou livre)" required>
            </div>
            <div class="col filter">
                <label for="filter-data-recebimento">Data de recebimento</label>
                <input type="date" class="width-md height-md" id="filter-data-recebimento" placeholder="Data de recebimento">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-cidade">Cidade de origem</label>
                <input type="text" class="width-xl height-md" id="filter-cidade" placeholder="Cidade de origem" maxlength="120" required>
            </div>
            <div class="col filter">
                <label for="filter-estado">UF de origem</label>
                <select class="width-md height-md" id="filter-estado" required>
                    <option value="AC">AC</option>
                    <option value="AL">AL</option>
                    <option value="AP">AP</option>
                    <option value="AM">AM</option>
                    <option value="BA">BA</option>
                    <option value="CE">CE</option>
                    <option value="DF">DF</option>
                    <option value="ES">ES</option>
                    <option value="GO">GO</option>
                    <option value="MA">MA</option>
                    <option value="MT">MT</option>
                    <option value="MS">MS</option>
                    <option value="MG">MG</option>
                    <option value="PA">PA</option>
                    <option value="PB">PB</option>
                    <option value="PR">PR</option>
                    <option value="PE">PE</option>
                    <option value="PI">PI</option>
                    <option value="RJ">RJ</option>
                    <option value="RN">RN</option>
                    <option value="RS">RS</option>
                    <option value="RO">RO</option>
                    <option value="RR">RR</option>
                    <option value="SC">SC</option>
                    <option value="SP">SP</option>
                    <option value="SE">SE</option>
                    <option value="TO">TO</option>
                </select>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-tipo">Finalidade</label>
                <select class="width-lg height-md" id="filter-tipo" required>
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
                <label for="filter-prazo">Prazo (dias)</label>
                <input type="number" class="width-sm height-md" id="filter-prazo" value="30" min="1" max="999" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-observacoes">Observações</label>
                <textarea class="width-xl height-md" id="filter-observacoes" rows="2" placeholder="Opcional"></textarea>
            </div>
        </div>
        <div class="row info">
            <span>*Conforme regra de alocação, as Cartas Recebidas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
        </div>

        <div class="row buttons">
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
        </div>

        <div class="row pagination">
            <div class="col">
                <label id="cadastro-feedback" class="status-placeholder" aria-live="polite"></label>
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
    <script>
        (() => {
            const btn = document.getElementById('cadastrar-btn');
            const feedback = document.getElementById('cadastro-feedback');
            const showFeedback = (message, type = 'success') => {
                if (!feedback) return;
                const variant = type === 'error' ? 'status-cancelled' : 'status-finished';
                feedback.innerHTML = `<label class="status ${variant}">${message}</label>`;
            };
            const getValue = (id) => document.getElementById(id)?.value?.trim() ?? '';

            if (!btn) return;

            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (btn.disabled) return;

                const numeroProcesso = getValue('filter-processo');
                const cidadeOrigem = getValue('filter-cidade');
                const ufOrigem = getValue('filter-estado');
                const finalidade = getValue('filter-tipo');
                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo de origem.', 'error');
                }
                if (cidadeOrigem === '') {
                    return showFeedback('Informe a cidade de origem.', 'error');
                }
                if (ufOrigem === '') {
                    return showFeedback('Informe a UF de origem.', 'error');
                }
                if (finalidade === '') {
                    return showFeedback('Informe a finalidade.', 'error');
                }

                const prazo = parseInt(document.getElementById('filter-prazo')?.value ?? '30', 10) || 30;
                const dataRecebimento = getValue('filter-data-recebimento');
                const observacoes = getValue('filter-observacoes');

                const payload = {
                    numero_processo_origem: numeroProcesso,
                    cidade_origem: cidadeOrigem,
                    uf_origem: ufOrigem,
                    finalidade,
                    prazo_dias: prazo,
                    observacoes,
                };
                if (dataRecebimento !== '') {
                    payload.data_recebimento = dataRecebimento;
                }

                btn.disabled = true;
                showFeedback('Enviando dados...', 'success');

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
