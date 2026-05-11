<?php
declare(strict_types=1);

require_once __DIR__ . '/../../services/CeapaService.php';
require_once __DIR__ . '/../../repositories/CeapaAlocadosRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';

$connection = Conection::getInstance()->getConnection();

$idTarefa = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$tarefa = null;

if ($idTarefa === '') {
    $errorMessage = 'Identificador do registro CEAPA não informado.';
} else {
    try {
        $service = new CeapaService($connection);
        $tarefa = $service->find($idTarefa);
        if ($tarefa === null) {
            $errorMessage = 'Registro CEAPA não encontrado.';
        }
    } catch (Throwable $exception) {
        $errorMessage = 'Erro ao carregar o registro: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';
$reuNome = '';
$tipoSuspensao = '';
$decisaoCumprir = '';
$dataDecisao = '';
$observacoesCumprimento = '';
$statusCumprimento = 'pendente';

if ($tarefa !== null) {
    $processoNumero = trim((string) ($tarefa['numero_processo_origem'] ?? ''));
    $classeCodigo = trim((string) ($tarefa['classe_codigo'] ?? ''));
    $classeNome = trim((string) ($tarefa['classe_nome'] ?? 'Classe não informada'));
    $reuNome = trim((string) ($tarefa['reu_nome'] ?? ''));
    $tipoSuspensao = trim((string) ($tarefa['tipo_suspensao'] ?? ''));
    $decisaoCumprir = trim((string) ($tarefa['decisao_cumprir'] ?? ''));
    $dataDecisao = trim((string) ($tarefa['data_decisao'] ?? ''));
    $observacoesCumprimento = trim((string) ($tarefa['observacoes_cumprimento'] ?? ''));
    $statusCumprimento = trim((string) ($tarefa['status_cumprimento'] ?? 'pendente'));
    if ($statusCumprimento === '') {
        $statusCumprimento = 'pendente';
    }

    if ($classeNome === '' || $classeNome === 'Classe não informada') {
        $processoId = trim((string) ($tarefa['processo_origem_id'] ?? ''));
        if ($processoId !== '') {
            $processosRepo = new ProcessosRepository($connection);
            $processo = $processosRepo->findById($processoId);
            $classeId = trim((string) ($processo['classe_id'] ?? ''));
            if ($classeId !== '') {
                $classesRepo = new ClassesRepository($connection);
                $classe = $classesRepo->findById($classeId);
                if ($classe !== null) {
                    $classeNome = trim((string) ($classe['nome'] ?? 'Classe não informada'));
                    if ($classeNome === '') {
                        $classeNome = 'Classe não informada';
                    }
                    $classeCodigo = trim((string) ($classe['codigo'] ?? $classeCodigo));
                }
            }
        }
    }
}

$lockProcesso = trim($processoNumero) !== '';
$lockClasse = trim($classeNome) !== '' && $classeNome !== 'Classe não informada';
$userPerfisEdit = [];
$userIdEdit = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($userIdEdit !== '') {
    require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
    $userPerfisEdit = UsuarioPerfisAutorizacao::perfisDoUsuario($userIdEdit);
}
$perfisPermitidosExcluir = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$podeExcluirTarefa = count(array_intersect($userPerfisEdit, $perfisPermitidosExcluir)) > 0;
$podeCancelarTarefa = $userIdEdit !== '';
$perfisGestaoAlocCeapa = ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'];
$isGestaoAlocEditCeapa = count(array_intersect($userPerfisEdit, $perfisGestaoAlocCeapa)) > 0;
$tarefaBloqueadaAlocCeapa = ($statusCumprimento === 'finalizada');

$alocadosEditCeapa = [];
if ($tarefa !== null) {
    $tipoOrderAloc = ['rule' => 0, 'all' => 1, 'specific' => 2];
    $repoAlocCeapa = new CeapaAlocadosRepository($connection);
    $alocadosRawCeapa = $repoAlocCeapa->findByCeapaId($idTarefa);
    foreach (array_slice($alocadosRawCeapa, 0, 5) as $a) {
        $tipo = trim((string) ($a['tipo'] ?? ''));
        if (!in_array($tipo, ['rule', 'all', 'specific'], true)) {
            $tipo = 'specific';
        }
        $alocadosEditCeapa[] = [
            'alocado_id' => trim((string) ($a['id'] ?? '')),
            'iniciais' => $a['iniciais'] ?? '—',
            'nome_completo' => $a['nome_completo'] ?? '',
            'usuario_id' => trim((string) ($a['usuario_id'] ?? '')),
            'tipo' => $tipo,
        ];
    }
    usort($alocadosEditCeapa, static function (array $a, array $b) use ($tipoOrderAloc): int {
        return ($tipoOrderAloc[$a['tipo']] ?? 9) <=> ($tipoOrderAloc[$b['tipo']] ?? 9);
    });
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição CEAPA - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Ceapa -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar cadastro Ceapa</h1>
        <p class="description-page">Atualize o andamento de cumprimento da decisão e observações relacionadas ao acompanhamento.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php else: ?>
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Dados do cadastro</h2>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-processo">Número do processo</label>
                    <input type="text" class="width-400 height-md<?php echo $lockProcesso ? ' readonly' : ''; ?>" id="filter-processo" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" placeholder="0000000-00.0000.0.00.0000" <?php echo $lockProcesso ? 'readonly' : ''; ?>>
                </div>
                <div class="col filter">
                    <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual</label>
                    <input type="text" class="width-600 height-md readonly" id="filter-classe" data-datajud-related="true" value="<?php echo htmlspecialchars($lockClasse ? $classeNome : '', ENT_QUOTES); ?>" placeholder="Sem classe vinculada — cadastre em Consultar processo &gt; Editar processo" readonly<?php echo ($lockClasse && $classeNome !== '' && $classeNome !== 'Classe não informada') ? ' data-datajud-initial="1"' : ''; ?>>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo" value="<?php echo htmlspecialchars($classeCodigo, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao" value="<?php echo htmlspecialchars($classeNome !== 'Classe não informada' ? $classeNome : '', ENT_QUOTES); ?>">

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-reu">Nome do réu</label>
                    <select class="width-800 height-md" id="filter-reu-select" style="display: none;" aria-label="Réus vinculados ao processo"></select>
                    <input type="text" class="width-800 height-md" id="filter-reu" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" placeholder="Digite o nome completo" maxlength="120" aria-label="Nome do réu quando não há réus ou opção Outro">
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-tipo-suspensao">Tipo de suspensão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="filter-tipo-suspensao" required>
                        <option value="" <?php echo $tipoSuspensao === '' ? 'selected' : ''; ?> disabled>Selecione aqui</option>
                        <option value="Acordo de não Persecução Penal"<?php echo $tipoSuspensao === 'Acordo de não Persecução Penal' ? ' selected' : ''; ?>>Acordo de não Persecução Penal</option>
                        <option value="Suspensão Condicional do Processo"<?php echo $tipoSuspensao === 'Suspensão Condicional do Processo' ? ' selected' : ''; ?>>Suspensão Condicional do Processo</option>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-data-decisao">Data da decisão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="filter-data-decisao" value="<?php echo htmlspecialchars($dataDecisao, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="filter-status-cumprimento">Status de cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="filter-status-cumprimento" required>
                        <option value="pendente"<?php echo $statusCumprimento === 'pendente' ? ' selected' : ''; ?>>Pendente</option>
                        <option value="em_andamento"<?php echo $statusCumprimento === 'em_andamento' ? ' selected' : ''; ?>>Em andamento</option>
                        <option value="finalizada"<?php echo $statusCumprimento === 'finalizada' ? ' selected' : ''; ?>>Finalizada</option>
                        <option value="cancelado"<?php echo $statusCumprimento === 'cancelado' ? ' selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-decisao-cumprir">Decisão a cumprir <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-800 height-md" id="filter-decisao-cumprir" value="<?php echo htmlspecialchars($decisaoCumprir, ENT_QUOTES); ?>" placeholder="Informe a pena definida na decisão" maxlength="500" required>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-observacoes-cumprimento">Observações acerca do cumprimento da decisão</label>
                    <textarea class="width-800 height-md" id="filter-observacoes-cumprimento" placeholder="Descreva observações sobre o cumprimento"><?php echo htmlspecialchars($observacoesCumprimento, ENT_QUOTES); ?></textarea>
                </div>
            </div>

            <?php if ($tarefa !== null): ?>
                <?php
                $cartorioAlocacaoPrefix = 'ceapa';
                $cartorioAlocacaoEntityId = $idTarefa;
                $cartorioAlocacaoDesalocarEndpoint = '/api/cartorio-ceapa/desalocar.php';
                $cartorioAlocacaoAlocarEndpoint = '/api/cartorio-ceapa/alocar.php';
                $cartorioAlocacaoAlocados = $alocadosEditCeapa;
                $cartorioAlocacaoBloqueada = $tarefaBloqueadaAlocCeapa;
                $cartorioAlocacaoUserId = $userIdEdit;
                $cartorioAlocacaoIsGestao = $isGestaoAlocEditCeapa;
                include __DIR__ . '/_cartorio_alocacao_direcionada_edit.php';
                ?>
            <?php endif; ?>

            <input type="hidden" id="hidden-ceapa-id" value="<?php echo htmlspecialchars($idTarefa, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button type="button" class="btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
                <?php if ($errorMessage === '' && $podeCancelarTarefa && $statusCumprimento !== 'cancelado' && $statusCumprimento !== 'finalizada'): ?>
                    <button type="button" class="btn btn-danger-outline" id="cancelar-btn">Cancelar tarefa</button>
                <?php endif; ?>
                <?php if ($errorMessage === '' && $podeExcluirTarefa): ?>
                    <button type="button" class="btn btn-danger" id="excluir-btn">Excluir tarefa</button>
                <?php endif; ?>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <?php if ($errorMessage === ''): ?>
                    <button type="button" class="btn btn-primary" id="salvar-btn">Salvar alterações</button>
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

    <?php if ($tarefa !== null): ?>
    <?php if ($lockClasse): ?>
    <script>window.DATAJUD_SKIP_CLASSE_SYNC = true;</script>
    <?php endif; ?>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>
        (function () {
            if (!window.App || !App.CeapaEdit || typeof App.CeapaEdit.initialize !== 'function') {
                return;
            }
            App.CeapaEdit.initialize({ lockProcesso: <?php echo $lockProcesso ? 'true' : 'false'; ?> });
        })();
    </script>
    <?php else: ?>
    <script>
        document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/frame.php?page=ceapa';
        }, { capture: true });
    </script>
    <?php endif; ?>
</body>
</html>
