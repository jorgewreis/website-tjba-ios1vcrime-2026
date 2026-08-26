<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../../services/ConsultaProcessosService.php';
require_once __DIR__ . '/../../services/ProcessoPrioridadesService.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new ConsultaProcessosService($connection);
$processo = $service->get($id);
if ($processo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$partes = $processo['partes'] ?? [];
$assuntosFicha = is_array($processo['assuntos'] ?? null) ? $processo['assuntos'] : [];
$processoIdFicha = trim((string) ($processo['id'] ?? ''));
$nMenoresPauta = $processoIdFicha !== ''
    ? (new SalaAudienciasPautaParteRepository($connection))->countPartesMenoresAtivasPorProcesso($processoIdFicha)
    : 0;
$prioridadesFichaEstado = $processoIdFicha !== ''
    ? (new ProcessoPrioridadesService($connection))->getEstadoEdicaoPorProcessoId($processoIdFicha, $nMenoresPauta)
    : null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Processo <?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?></title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Processo</span></div>
        <div class="display-flex row row-full justify-between align-center flex-wrap gap-y-md" style="gap: 12px;">
            <div class="display-flex col" style="width: auto; flex: 1 1 auto;">
                <h1 class="title-page"><?php echo htmlspecialchars($processo['numero_processo'] ?? 'Processo'); ?></h1>
                <p class="description-page" style="margin-top: 0;">Classe: <?php echo htmlspecialchars($processo['classe_nome'] ?? '—'); ?></p>
            </div>
            <div class="display-flex row row-fit align-center justify-end gap-y-md">
                <button type="button" class="btn btn-primary-outline" data-nav-href="/frame.php?page=processosedit&id=<?php echo urlencode($processo['id'] ?? ''); ?>">Editar processo</button>
            </div>
        </div>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">
        <?php if ($prioridadesFichaEstado !== null): ?>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Prioridades</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md" id="prioridades-processuais-wrap">
            <div id="prioridades-processuais-list" class="display-flex col col-full gap-y-sm" aria-live="polite"></div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="prioridade-catalog-select">Incluir prioridade</label>
                    <select class="width-500 height-md" id="prioridade-catalog-select">
                        <option value="" selected disabled>Selecione...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-prioridade-adicionar">Adicionar</button>
            </div>
        </div>
        <?php endif; ?>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Assuntos</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md" id="processo-assuntos-wrap">
            <div id="processo-assuntos-list" class="display-flex col col-full gap-y-sm" aria-live="polite"></div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="processo-assunto-codigo">Código do assunto</label>
                    <input type="text" id="processo-assunto-codigo" class="height-md" placeholder="Ex.: 10247" maxlength="20" autocomplete="off" title="Consulta automática após 1 s sem digitar; Enter consulta na hora.">
                </div>
                <div class="col width-700">
                    <label for="processo-assunto-descricao">Descrição do assunto</label>
                    <input type="text" id="processo-assunto-descricao" class="width-700 height-md" placeholder="Preenchida automaticamente se o código existir no catálogo" readonly>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-processo-assunto-vincular" disabled>Incluir</button>
            </div>
        </div>
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Partes cadastradas</h2></div>
        <?php if (!empty($partes)): ?>
            <?php foreach ($partes as $p): ?>
            <?php
                $tipoParteRaw = trim((string) ($p['tipo_parte'] ?? ''));
                $poloRaw = trim((string) ($p['polo'] ?? ''));
                $tipoParteLabels = [
                    'reu' => 'Réu',
                    'indiciado' => 'Indiciado',
                    'sentenciado' => 'Sentenciado',
                    'requerido' => 'Requerido',
                    'vitima' => 'Vítima',
                    'requerente' => 'Requerente',
                    'testemunha_defesa' => 'Testemunha da Defesa',
                    'testemunha_denuncia' => 'Testemunha da Denúncia',
                    'advogado' => 'Advogado',
                    'defensor_publico' => 'Defensor Público',
                ];
                $poloLabels = [
                    'ativo' => 'Ativo',
                    'passivo' => 'Passivo',
                    'terceiros' => 'Terceiros',
                ];
                $tipoParteLabel = $tipoParteLabels[$tipoParteRaw] ?? ($tipoParteRaw !== '' ? ucfirst(str_replace('_', ' ', $tipoParteRaw)) : 'Parte');
                $poloLabel = $poloLabels[$poloRaw] ?? ($poloRaw !== '' ? ucfirst(str_replace('_', ' ', $poloRaw)) : 'Polo não informado');
                $cpfParte = trim((string) ($p['cpf'] ?? ''));
                $nomeParte = trim((string) ($p['nome_completo'] ?? ''));
                $nomeParte = $nomeParte !== '' ? $nomeParte : 'Nome não informado';
                $sexoParte = trim((string) ($p['sexo'] ?? ''));
                $dataNascimentoParte = trim((string) ($p['data_nascimento'] ?? ''));
                $detalhesParte = [];
                if ($cpfParte !== '') {
                    $detalhesParte[] = 'CPF: ' . $cpfParte;
                }
                if ($sexoParte !== '') {
                    $detalhesParte[] = 'Sexo: ' . ucfirst($sexoParte);
                }
                if ($dataNascimentoParte !== '' && $dataNascimentoParte !== '0000-00-00') {
                    $timestampNascimento = strtotime($dataNascimentoParte);
                    $detalhesParte[] = 'Nascimento: ' . ($timestampNascimento !== false ? date('d/m/Y', $timestampNascimento) : $dataNascimentoParte);
                }
                $detalhesParteTexto = implode(' | ', $detalhesParte);
            ?>
            <div class="display-flex col col-full register border-bottom" data-parte-id="<?php echo htmlspecialchars((string) ($p['id'] ?? ''), ENT_QUOTES); ?>">
                <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                    <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                        <div class="col width-700">
                            <div class="minor-info"><?php echo htmlspecialchars($tipoParteLabel . ' - ' . $poloLabel, ENT_QUOTES); ?></div>
                            <span class="major-info text-ellipsis"><?php echo htmlspecialchars($nomeParte, ENT_QUOTES); ?></span>
                            <?php if ($detalhesParteTexto !== ''): ?>
                                <span class="minor-info-defesa"><?php echo htmlspecialchars($detalhesParteTexto, ENT_QUOTES); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                        <button type="button" class="btn btn-editar" data-nav-href="/frame.php?page=processospartesedit&id=<?php echo urlencode((string) ($p['id'] ?? '')); ?>" title="Editar vínculo"><i class="fi fi-rr-pen-square"></i></button>
                        <button type="button" class="btn btn-editar" data-nav-href="/frame.php?page=consultapartesedit&id=<?php echo urlencode((string) ($p['parte_id'] ?? '')); ?>" title="Ir para o cadastro da parte"><i class="fi fi-rr-user-link"></i></button>
                        <button type="button" class="btn-editar stop btn-desvincular" data-id="<?php echo htmlspecialchars((string) ($p['id'] ?? ''), ENT_QUOTES); ?>" data-processo-id="<?php echo htmlspecialchars((string) ($processo['id'] ?? ''), ENT_QUOTES); ?>" title="Remover parte"><i class="fi fi-rr-cross-circle"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col subtask subtask-collapsed padding-lr-md full-line">
                <div class="display-flex row row-full justify-between padding-y-md border-top">
                    <div class="col no-data"><span class="major-info">Nenhuma parte vinculada.</span></div>
                </div>
            </div>
        <?php endif; ?>
        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" data-nav-href="/frame.php?page=consultaprocessos">Voltar</button>
            <button type="button" class="btn btn-primary" data-nav-href="/frame.php?page=processospartescreate&processo_id=<?php echo urlencode($processo['id'] ?? ''); ?>">Vincular parte</button>
        </div>
    </div>
    <?php if ($prioridadesFichaEstado !== null): ?>
    <script>
        window.PROCESSO_PRIORIDADES_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_PRIORIDADES_INITIAL = <?php echo json_encode($prioridadesFichaEstado, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/modules/audiencias/pauta-edit-prioridades.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit-prioridades.js') ?: time(); ?>"></script>
    <?php endif; ?>
    <script>
        window.PROCESSO_ASSUNTOS_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTOS_INITIAL = <?php echo json_encode($assuntosFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTO_FIND_ENDPOINT = '/api/assuntos/find-by-codigo.php';
        window.PROCESSO_ASSUNTO_LINK_ENDPOINT = '/api/processos/assunto-link.php';
        window.PROCESSO_ASSUNTO_UNLINK_ENDPOINT = '/api/processos/assunto-unlink.php';
    </script>
    <script>
        (function () {
            if (!window.App || !App.ConsultaProcessosEdit || typeof App.ConsultaProcessosEdit.initialize !== 'function') {
                return;
            }

            App.ConsultaProcessosEdit.initialize();
        })();
    </script>
</body>
</html>
