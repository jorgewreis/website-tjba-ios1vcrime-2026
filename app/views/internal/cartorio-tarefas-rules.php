<?php
declare(strict_types=1);

require_once __DIR__ . '/../../access/ProfileNormalizer.php';
require_once __DIR__ . '/../../repositories/CartorioTarefasRepository.php';
require_once __DIR__ . '/../../repositories/CartorioTarefasRegrasRepository.php';
require_once __DIR__ . '/../../services/CartorioTarefasAutomacaoService.php';

if (!ProfileNormalizer::isElevated(ProfileNormalizer::normalize($userPerfils ?? []))) {
    http_response_code(403);
    exit('Acesso restrito à chefia e à administração.');
}

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$eventos = ['criacao' => 'Cadastro da tarefa', 'finalizacao' => 'Finalização da tarefa', 'status_vinculada' => 'Status de tarefa vinculada', 'alocacao' => 'Alocação ou transferência', 'prazo' => 'Prazo vencido'];
$listaOpcoes = static fn(array $values): array => array_combine($values, $values) ?: [];
$repo = new CartorioTarefasRegrasRepository($connection);
$tarefas = new CartorioTarefasRepository($connection);
$erro = '';
$regras = $lotes = $falhas = $usuarios = [];
$monitor = null;
$editando = null;
$pronta = true;

/** Valida também os valores de domínio, no servidor, antes da gravação. */
$validarConteudo = static function (array &$regra, array $usuarios): void {
    CartorioTarefasRegras::validar($regra);
    if (empty($regra['ativa'])) return;
    foreach ($regra['condicoes'] as &$grupo) {
        if (isset($grupo['especificacao_documento'])) $grupo['especificacao_documento'] = CartorioTarefasRepository::normalizarEspecificacao($grupo['especificacao_documento']);
    }
    unset($grupo);
    foreach ($regra['acoes'] as &$acao) {
        if (isset($acao['especificacao_documento'])) $acao['especificacao_documento'] = CartorioTarefasRepository::normalizarEspecificacao($acao['especificacao_documento']);
    }
    unset($acao);
    $dominios = [
        'tipo' => array_keys(CartorioTarefasRepository::TIPOS),
        'tipo_documento' => CartorioTarefasRepository::DOCUMENTOS,
        'especificacao_documento' => CartorioTarefasRepository::ESPECIFICACOES,
        'local_tarefa' => CartorioTarefasRepository::LOCAIS,
    ];
    foreach ($regra['condicoes'] as $grupo) foreach ($grupo as $campo => $valor) {
        if (!in_array($valor, $dominios[$campo], true)) throw new InvalidArgumentException('Uma condição contém valor que não está disponível no cadastro de tarefas.');
    }
    foreach ($regra['acoes'] as $acao) {
        if ($acao['tipo'] === 'alocar' && !empty($regra['ativa']) && !in_array($acao['usuario_id'], array_column($usuarios, 'id'), true)) throw new InvalidArgumentException('Selecione um usuário interno ativo para alocação.');
        if ($acao['tipo'] !== 'criar_tarefa') continue;
        if (!in_array($acao['tipo_tarefa'], $dominios['tipo'], true) || !in_array($acao['tipo_documento'], $dominios['tipo_documento'], true) || !in_array($acao['local_tarefa'], $dominios['local_tarefa'], true) || (($acao['especificacao_documento'] ?? '') !== '' && !in_array($acao['especificacao_documento'], $dominios['especificacao_documento'], true))) throw new InvalidArgumentException('Selecione valores válidos para a tarefa criada.');
        foreach ($regra['acoes'] as $posterior) {
            if (($posterior['tipo'] ?? '') !== 'alterar_status' || ($posterior['alvo'] ?? '') !== $acao['id'] || ($posterior['status'] ?? '') !== 'finalizada') continue;
            $filha = ['tipo' => $acao['tipo_tarefa'], 'tipo_documento' => $acao['tipo_documento'], 'especificacao_documento' => $acao['especificacao_documento'] ?? '', 'local_tarefa' => $acao['local_tarefa']];
            if (CartorioTarefasCondicoes::corresponde($regra['condicoes'], $filha)) throw new InvalidArgumentException('A regra criaria e finalizaria uma tarefa que acionaria a própria regra. Ajuste as condições ou o status.');
        }
    }
};

try {
    $usuarios = $tarefas->listarUsuariosInternos();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
        if (!Security::verifyCSRFToken($csrf)) throw new InvalidArgumentException('Sua sessão expirou. Atualize a página e tente novamente.');
        $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
        if ($acao === 'salvar') {
            $json = is_string($_POST['regra_json'] ?? null) ? $_POST['regra_json'] : '';
            if (strlen($json) > 100000) throw new InvalidArgumentException('A configuração da regra é muito grande.');
            $editando = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($editando)) throw new InvalidArgumentException('Configuração de regra inválida.');
            $id = filter_var($_POST['regra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
            if ($id !== null) $editando['id'] = (int) $id;
            $validarConteudo($editando, $usuarios);
            $repo->salvar($editando, $id === null ? null : (int) $id);
            $_SESSION['tarefas_regra_mensagem'] = !empty($editando['ativa']) ? 'Regra salva. A aplicação às tarefas existentes foi enviada para processamento.' : 'Regra salva como inativa.';
        } elseif ($acao === 'alternar') {
            $id = filter_var($_POST['regra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $regra = $id ? $repo->obter((int) $id) : null;
            if (!$regra) throw new InvalidArgumentException('Regra não encontrada.');
            $regra['ativa'] = !$regra['ativa'];
            $validarConteudo($regra, $usuarios);
            $repo->salvar($regra, (int) $id);
            $_SESSION['tarefas_regra_mensagem'] = $regra['ativa'] ? 'Regra ativada e enviada para processamento.' : 'Regra desativada.';
        } elseif ($acao === 'excluir') {
            $id = filter_var($_POST['regra_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) throw new InvalidArgumentException('Regra não encontrada.');
            $repo->excluirInativa((int) $id);
            $_SESSION['tarefas_regra_mensagem'] = 'Regra inativa excluída.';
        } elseif ($acao === 'tentar') {
            $id = filter_var($_POST['falha_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) throw new InvalidArgumentException('Falha não encontrada.');
            (new CartorioTarefasAutomacaoService($connection))->tentarFalha((int) $id);
            $_SESSION['tarefas_regra_mensagem'] = 'Nova tentativa enviada para processamento.';
        } else {
            throw new InvalidArgumentException('Ação inválida.');
        }
        header('Location: /frame.php?page=tarefasrules', true, 303);
        exit;
    }
    $id = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id) {
        $editando = $repo->obter((int) $id);
        if (!$editando) throw new InvalidArgumentException('Regra não encontrada.');
    }
} catch (InvalidArgumentException | JsonException $e) {
    $erro = $e->getMessage();
} catch (Throwable $e) {
    error_log('Falha na administração de regras de tarefas: ' . $e->getMessage());
    $erro = 'Não foi possível acessar as regras. Tente novamente.';
    $pronta = false;
}
try {
    $regras = $repo->listar();
    $lotes = $repo->lotesAtivos();
    $falhas = $repo->falhasRecentes();
    $monitor = $connection->query('SELECT ultimo_sucesso_em, ultima_falha_em, ultima_falha FROM cartorio_tarefas_automacao_monitor WHERE id = 1')->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    error_log('Falha ao carregar regras de tarefas: ' . $e->getMessage());
    $pronta = false;
    $erro = $erro ?: 'Não foi possível carregar as regras.';
}
$mensagem = (string) ($_SESSION['tarefas_regra_mensagem'] ?? '');
unset($_SESSION['tarefas_regra_mensagem']);
$monitorAtrasado = true;
if ($monitor && $monitor['ultimo_sucesso_em']) {
    $ultimaExecucao = new DateTimeImmutable((string) $monitor['ultimo_sucesso_em'], new DateTimeZone('America/Bahia'));
    $monitorAtrasado = (new DateTimeImmutable('now', new DateTimeZone('America/Bahia')))->getTimestamp() - $ultimaExecucao->getTimestamp() > 420;
}
$csrfToken = Security::generateCSRFToken();
$usuarioOpcoes = [];
foreach ($usuarios as $usuario) $usuarioOpcoes[$usuario['id']] = $usuario['name'];
$dados = [
    'regra' => $editando,
    'tipos' => (object) CartorioTarefasRepository::TIPOS,
    'documentos' => (object) $listaOpcoes(CartorioTarefasRepository::DOCUMENTOS),
    'especificacoes' => (object) $listaOpcoes(CartorioTarefasRepository::ESPECIFICACOES),
    'locais' => (object) $listaOpcoes(CartorioTarefasRepository::LOCAIS),
    'usuarios' => (object) $usuarioOpcoes,
    'status' => (object) CartorioTarefasRepository::STATUS,
];
$assetRoot = dirname(__DIR__, 3) . '/public/assets/';
$lotesEmAndamento = array_values(array_filter($lotes, static fn(array $lote): bool => in_array($lote['estado'], ['pendente', 'processando'], true)));
$mostrarFormulario = $editando !== null || isset($_GET['form']) || (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['acao'] ?? '') === 'salvar' && $erro !== '');
$tituloFormulario = !empty($editando['id']) ? 'Editar regra' : 'Nova regra';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Regras de automação - Primeira Vara Crime de Ilhéus</title>
    <?php foreach (['reset', 'color', 'layout', 'page', 'form', 'table', 'toast'] as $css): ?>
    <link rel="stylesheet" href="/assets/css/shared/<?= $css ?>.css?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . 'css/shared/' . $css . '.css') ?: '1')) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="/assets/css/cartorio-tarefas-regras.css?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . 'css/cartorio-tarefas-regras.css') ?: '1')) ?>">
</head>
<body class="page-internal">
<div class="spacing-top-page"></div>
<header class="header-page"><div class="location"><span class="all">FokusLaw - Tarefas -</span><span class="specific">Regras de automação</span></div><div class="card-title"><h1 class="title-page">Regras de automação</h1></div></header>
<main class="container-full card-page automacao-pagina">
    <?php if ($erro !== ''): ?><p class="automacao-mensagem erro" role="alert"><?= $escape($erro) ?></p><?php endif; ?>
    <?php if ($mensagem !== ''): ?><p class="automacao-mensagem" role="status"><?= $escape($mensagem) ?></p><?php endif; ?>
    <div class="card-subtitle"><h2><?= $mostrarFormulario ? $tituloFormulario : 'Configuração de regras' ?></h2></div>
    <div class="automacao-controles padding-x-lg padding-y-md border-bottom"><?php if ($mostrarFormulario): ?><a class="btn btn-secondary" href="/frame.php?page=tarefasrules">Voltar às regras</a><?php else: ?><a class="btn btn-secondary" href="/frame.php?page=tarefas">Voltar às tarefas</a><a class="btn btn-primary" href="/frame.php?page=tarefasrules&amp;form=1">Nova regra</a><?php endif; ?></div>
    <?php if ($pronta): ?>
    <?php if (!$mostrarFormulario): ?>
    <section class="automacao-secao" aria-labelledby="regras-existentes"><div class="automacao-secao-cabecalho"><div><h2 id="regras-existentes">Regras cadastradas</h2><p class="automacao-ajuda">Consulte e administre as regras de automação das tarefas.</p></div><span class="automacao-contagem"><?= count($regras) ?> <?= count($regras) === 1 ? 'regra' : 'regras' ?></span></div>
        <?php if (!$regras): ?><p>Nenhuma regra cadastrada.</p><?php endif; ?>
        <div class="automacao-tabela-scroll"><table class="automacao-tabela"><thead><tr><th scope="col">Regra</th><th scope="col">Evento</th><th scope="col">Prioridade</th><th scope="col">Status</th><th scope="col">Ações</th></tr></thead><tbody>
        <?php foreach ($regras as $regra): ?>
        <tr><td><strong><?= $escape($regra['nome']) ?></strong></td><td><?= $escape($eventos[$regra['evento']] ?? $regra['evento']) ?></td><td><?= (int) $regra['prioridade'] ?></td><td><span class="automacao-estado <?= $regra['ativa'] ? 'ativa' : 'inativa' ?>"><?= $regra['ativa'] ? 'Ativa' : 'Inativa' ?></span></td><td>
            <div class="automacao-controles automacao-regra-acoes"><a class="btn btn-secondary" href="/frame.php?page=tarefasrules&amp;editar=<?= (int) $regra['id'] ?>#editar-regra">Editar</a>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="acao" value="alternar"><input type="hidden" name="regra_id" value="<?= (int) $regra['id'] ?>"><button class="btn btn-secondary" type="submit"><?= $regra['ativa'] ? 'Desativar' : 'Ativar' ?></button></form>
            <?php if (!$regra['ativa']): ?><form method="post" onsubmit="return confirm('Excluir esta regra inativa? As tarefas e o histórico serão preservados.')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="regra_id" value="<?= (int) $regra['id'] ?>"><button class="btn btn-secondary automacao-excluir" type="submit">Excluir</button></form><?php endif; ?></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php else: ?>
    <section class="automacao-secao" id="editar-regra"><h2><?= $tituloFormulario ?></h2>
        <noscript><p role="alert">Ative o JavaScript para configurar as condições e ações.</p></noscript>
        <form id="regra-form" method="post" action="/frame.php?page=tarefasrules">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="regra_id" value="<?= (int) ($editando['id'] ?? 0) ?>"><input type="hidden" id="regra-json" name="regra_json">
            <div class="automacao-campos">
                <label class="automacao-campo"><span>Nome da regra</span><input class="height-md" id="regra-nome" type="text" maxlength="200" value="<?= $escape((string) ($editando['nome'] ?? '')) ?>" required></label>
                <label class="automacao-campo"><span>Evento</span><select class="height-md" id="regra-evento"><?php foreach ($eventos as $value => $label): ?><option value="<?= $escape($value) ?>" <?= ($editando['evento'] ?? 'criacao') === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
                <label class="automacao-campo"><span>Status da tarefa vinculada</span><select class="height-md" id="regra-evento-status"><option value="">Selecione</option><?php foreach (CartorioTarefasRepository::STATUS as $value => $label): ?><option value="<?= $escape($value) ?>" <?= ($editando['evento_status'] ?? '') === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label>
                <label class="automacao-campo"><span>Prioridade</span><input class="height-md" id="regra-prioridade" type="number" min="-100000" max="100000" step="1" value="<?= (int) ($editando['prioridade'] ?? 0) ?>" required></label>
                <label class="automacao-checkbox"><input id="regra-ativa" type="checkbox" <?= ($editando['ativa'] ?? true) ? 'checked' : '' ?>> Regra ativa</label>
            </div>
            <h3>Condições</h3><p class="automacao-ajuda">Os grupos são alternativas (OU). Um grupo sem campos escolhidos corresponde a qualquer tarefa.</p><div id="regra-grupos"></div><button class="btn btn-secondary" id="adicionar-grupo" type="button">Adicionar grupo OU</button>
            <h3>Ações em ordem</h3><p class="automacao-ajuda">Crie a tarefa antes de apontar outras ações para ela.</p><div id="regra-acoes"></div><button class="btn btn-secondary" id="adicionar-acao" type="button">Adicionar ação</button>
            <div class="automacao-controles automacao-salvar"><a class="btn btn-secondary" href="/frame.php?page=tarefasrules">Cancelar</a><button class="btn btn-primary" type="submit">Salvar regra</button></div>
        </form>
    </section>
    <?php endif; ?>
    <?php if (!$mostrarFormulario): ?>
    <section class="automacao-secao"><h2>Aplicação às tarefas existentes</h2>
        <?php if ($monitor && $monitor['ultimo_sucesso_em']): ?>
        <p class="automacao-ajuda" <?= $monitorAtrasado || $monitor['ultima_falha'] ? 'role="alert"' : '' ?>>Última execução do processamento: <?= $escape((string) $monitor['ultimo_sucesso_em']) ?><?= $monitorAtrasado ? ' · Sem confirmação há mais de sete minutos.' : '' ?><?= $monitor['ultima_falha'] ? ' · Última tentativa falhou: ' . $escape((string) $monitor['ultima_falha']) : '' ?></p>
        <?php else: ?><p class="automacao-ajuda">O processamento automático ainda não confirmou uma execução.</p><?php endif; ?>
        <?php if (!$lotesEmAndamento): ?><p>Nenhum processamento em andamento.</p><?php endif; ?>
        <?php foreach ($lotesEmAndamento as $lote): ?><div class="automacao-linha"><span><?= $escape((string) $lote['nome']) ?> · <?= $lote['estado'] === 'pendente' ? 'Pendente' : 'Em andamento' ?></span><span><?= (int) $lote['processadas'] ?>/<?= (int) $lote['total'] ?> · <?= (int) $lote['falhas'] ?> falha(s)</span></div><?php endforeach; ?>
    </section>
    <section class="automacao-secao"><h2>Falhas de execução</h2>
        <?php if (!$falhas): ?><p>Nenhuma falha registrada.</p><?php endif; ?>
        <?php foreach ($falhas as $falha): ?><div class="automacao-linha"><div><strong><?= $escape((string) ($falha['nome'] ?? 'Regra indisponível')) ?></strong> · <?= $escape((string) ($falha['tarefa_id'] ?? 'Sem tarefa')) ?><br><small><?= $escape((string) $falha['criado_em']) ?> · <?= $escape((string) $falha['detalhes']) ?><?= $falha['resolvida_em'] ? ' · Nova tentativa solicitada' : '' ?></small></div>
            <?php if ($falha['pode_tentar'] && !$falha['resolvida_em']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="acao" value="tentar"><input type="hidden" name="falha_id" value="<?= (int) $falha['id'] ?>"><button class="btn btn-secondary" type="submit">Tentar novamente</button></form><?php endif; ?></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($mostrarFormulario): ?>
    <script type="application/json" id="tarefas-regras-dados"><?= json_encode($dados, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="/assets/js/cartorio-tarefas-regras.js?v=<?= $escape((string) (@hash_file('sha256', $assetRoot . 'js/cartorio-tarefas-regras.js') ?: '1')) ?>"></script>
    <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
