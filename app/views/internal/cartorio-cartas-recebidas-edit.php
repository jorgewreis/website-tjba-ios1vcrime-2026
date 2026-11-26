<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';
require_once __DIR__ . '/../../repositories/TribunaisRepository.php';
require_once __DIR__ . '/../../helpers/CartorioTimelineHelper.php';
require_once __DIR__ . '/../../services/CartasRecebidasService.php';

$connection = Conection::getInstance()->getConnection();

$idCarta = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$carta = null;

if ($idCarta === '') {
    $errorMessage = 'Identificador da carta recebida não informado.';
} else {
    try {
        $service = new CartasRecebidasService($connection);
        $carta = $service->find($idCarta);
        if ($carta === null) {
            $errorMessage = 'Carta recebida não encontrada.';
        }
    } catch (\Throwable $exception) {
        $errorMessage = 'Erro ao carregar a carta recebida: ' . $exception->getMessage();
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
    $processoNumero = trim((string) ($carta['numero_processo_origem'] ?? ''));
    $processosRepo = new ProcessosRepository($connection);
    $processoRow = $processosRepo->findById((string) ($carta['processo_origem_id'] ?? ''));
    if ($processoRow !== null) {
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
    $cidadeValue = trim((string) ($carta['cidade_origem'] ?? ''));
    $ufValue = strtoupper(trim((string) ($carta['uf_origem'] ?? 'BA')));
    if ($ufValue === '') {
        $ufValue = 'BA';
    }
    $prazoAtual = (int) ($carta['prazo_dias'] ?? 0);
    if ($prazoAtual <= 0) {
        $prazoAtual = 30;
    }
    $statusAtual = trim((string) ($carta['status'] ?? ''));
    if ($statusAtual === '') {
        $statusAtual = 'pendente';
    }
    $observacoesValue = trim((string) ($carta['observacoes'] ?? ''));
    $comarcaDestinoId = trim((string) ($carta['comarca_origem_id'] ?? ''));

    $subtarefasRepo = new CartasRecebidasSubtarefasRepository($connection);
    $subtarefas = $subtarefasRepo->findByCartaId($idCarta);
    $tribunaisRepo = new TribunaisRepository($connection);
    $temSubtarefas = count($subtarefas) > 0;
    $temEmAndamentoOuPendente = false;
    foreach ($subtarefas as $st) {
        $s = trim((string) ($st['status'] ?? ''));
        if ($s === 'em_andamento' || $s === 'pendente') {
            $temEmAndamentoOuPendente = true;
            break;
        }
    }
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
    'aguardando cumprimento' => 'Aguardando cumprimento',
    'aguardando devolução' => 'Aguardando devolução',
    'concluída' => 'Concluída',
    'cancelada' => 'Cancelada',
];

/** Perfis que podem excluir a tarefa e subtarefas (tabela usuario_perfil). */
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$userPerfisEdit = [];
$userIdEdit = $_SESSION['id_usuario'] ?? null;
if ($userIdEdit !== null && trim((string) $userIdEdit) !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario(trim((string) $userIdEdit));
}
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;

/** Perfis que podem cancelar a tarefa (apenas altera status para Cancelada): servidor, admin, diretora, magistrada. */
$podeCancelarTarefa = $userIdEdit !== '';
$tarefaFinalizadaOuCancelada = in_array($statusAtual ?? '', ['cancelada', 'concluída'], true);
$criadorCarta = '';
if ($carta !== null && trim((string) ($carta['criado_por'] ?? '')) !== '') {
    $stmtCriador = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
    $stmtCriador->execute([trim((string) $carta['criado_por'])]);
    $criadorRow = $stmtCriador->fetch(PDO::FETCH_ASSOC);
    if ($criadorRow !== false) {
        $criadorCarta = trim((string) ($criadorRow['nome'] ?? '') . ' ' . (string) ($criadorRow['sobrenome'] ?? ''));
    }
}
$timelineData = $carta !== null
    ? cartorioTimelineBuildViewData('cartas_recebidas', [
        'task' => $carta,
        'task_status' => $statusAtual,
        'subtarefas' => $subtarefas ?? [],
        'ruleServidor' => $criadorCarta,
        'tribunaisRepo' => $tribunaisRepo ?? null,
        'connection' => $connection,
    ])
    : [];

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
        <title>Edição de Carta Recebida - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/v2-colors.css') ?: time(); ?>">
        <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/shared/v2-page-list.css') ?: time(); ?>">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal page-operational-form">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Cartas Recebidas -</span>
            <span class="specific">Edição</span>
        </div>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php else: ?>
            <div class="card-title"><h1 class="title-page">Editar Carta Recebida</h1></div>
            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações da carta</h2></div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-processo">Processo</label>
                    <input type="text" id="filter-processo" class="height-md readonly" style="width: 220px;" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-classe">Classe</label>
                    <?php
                    $classeReadonly = ($classeNome !== 'Classe não informada');
                    $classeValue = $classeReadonly ? $classeNome : '';
                    $classeClass = 'height-md readonly';
                    ?>
                    <input type="text" id="filter-classe" class="<?php echo htmlspecialchars($classeClass, ENT_QUOTES); ?>" style="width: 350px;" value="<?php echo htmlspecialchars($classeValue, ENT_QUOTES); ?>" placeholder="Sem classe vinculada — cadastre na página do processo" readonly>
                </div>
                <div class="col filter">
                    <label for="filter-tipo">Finalidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="height-md" style="width: 350px;" id="filter-tipo" required>
                        <option value="" disabled>Selecione aqui</option>
                        <?php foreach ($finalidadeOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo $finalidadeValue === $option ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>            
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-cidade">Cidade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="height-md" style="width: 350px;" id="filter-cidade" aria-describedby="comarca-lookup-status" placeholder="Digite o nome da cidade" maxlength="120" value="<?php echo htmlspecialchars($cidadeValue, ENT_QUOTES); ?>" required>
                    <p id="comarca-lookup-status" role="status" aria-live="polite"></p>
                    <button type="button" class="operational-retry" id="comarca-lookup-retry" hidden>Consultar cidade novamente</button>
                </div>
                <div class="col filter">
                    <label for="filter-estado">Estado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="height-md" style="width: 220px;" id="filter-estado" required>
                        <?php foreach ($stateOptions as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code, ENT_QUOTES); ?>"<?php echo $ufValue === $code ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-prazo">Prazo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="filter-prazo" required>
                        <?php foreach ($deadlineOptions as $option): ?>
                            <?php $digits = preg_replace('/\D+/', '', $option); ?>
                            <option value="<?php echo htmlspecialchars($option, ENT_QUOTES); ?>"<?php echo (int) $digits === $prazoAtual ? ' selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Timeline de subtarefas -->
            <?php include __DIR__ . '/_cartorio_timeline.php'; ?>

            <input type="hidden" id="hidden-carta-id" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-comarca-id" value="<?php echo htmlspecialchars($comarcaDestinoId, ENT_QUOTES); ?>">

        <?php endif; ?>
        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-primary-outline btn-cartas-voltar" id="voltar-btn" type="button">Voltar</button>
                <?php if ($errorMessage === '' && $podeCancelarTarefa && !$tarefaFinalizadaOuCancelada): ?>
                    <button class="btn btn-danger-outline" id="cancelar-tarefa-btn" type="button" title="Alterar status da tarefa para Cancelada (não exclui dados)">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($errorMessage === '' && $podeExcluirTarefa): ?>
                    <button class="btn btn-danger" id="excluir-tarefa-btn" type="button" title="Excluir esta tarefa e todas as subtarefas">Excluir tarefa</button>
                <?php endif; ?>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <?php if ($errorMessage === ''): ?>
                    <button class="btn btn-primary" id="salvar-btn" type="button" disabled>Salvar alterações</button>
                <?php endif; ?>
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
        <script>
            window.__CARTAS_RECEBIDAS_CTX = {
                initial: window.__initialCartaData || {},
                cartaId: window.__currentCartaId,
                endpoints: {
                    update: '/api/cartorio-cartas-recebidas/update.php',
                    lookupCidade: '/api/cartorio-cartas-recebidas/buscar-cidade.php?cidade=',
                    cancel: '/api/cartorio-cartas-recebidas/cancel.php',
                    delete: '/api/cartorio-cartas-recebidas/delete.php'
                }
            };
            if (window.App && App.CartorioCartasRecebidasEdit && typeof App.CartorioCartasRecebidasEdit.initialize === 'function') {
                App.CartorioCartasRecebidasEdit.initialize(window.__CARTAS_RECEBIDAS_CTX);
            }
        </script>
    <?php endif; ?>

</body>
</html>
