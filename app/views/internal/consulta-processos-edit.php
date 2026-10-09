<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/SalaAudienciasPautaParteRepository.php';
require_once __DIR__ . '/../../repositories/PartesDocumentosRepository.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../helpers/ParteDocumentoHelper.php';
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
$partesInstituicoesRepoFicha = new PartesInstituicoesRepository($connection);
$partesDocumentosRepoFicha = new PartesDocumentosRepository($connection);
$instituicoesPorParteFicha = [];
$documentosPorParteFicha = [];
$classeNomeAtual = trim((string) ($processo['classe_nome'] ?? ''));
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
            </div>
        </div>
    </div>
    <div class="container-full card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processo['id'] ?? ''); ?>">
        <input type="hidden" id="proc-id" value="<?php echo htmlspecialchars((string) ($processo['id'] ?? ''), ENT_QUOTES); ?>">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Classe processual</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="proc-classe-atual">Classe atual</label>
                    <input type="text" class="width-700 height-md" id="proc-classe-atual" value="<?php echo htmlspecialchars($classeNomeAtual !== '' ? $classeNomeAtual : 'Classe não cadastrada', ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="proc-classe-codigo"><?php echo $classeNomeAtual !== '' ? 'Alterar classe processual' : 'Incluir classe processual'; ?></label>
                    <input type="text" class="width-300 height-md" id="proc-classe-codigo" placeholder="Ex.: 283" maxlength="20" autocomplete="off" title="Consulta automática após 1 s sem digitar; Enter consulta na hora.">
                </div>
            </div>
            <div class="display-flex col col-full gap-y-md" id="proc-classe-resultado" style="display: none;">
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" id="proc-classe-bloco-existente" style="display: none; gap: 12px;">
                    <div class="col">
                        <label for="proc-classe-nome-existente">Classe encontrada no banco</label>
                        <input type="text" class="width-700 height-md" id="proc-classe-nome-existente" readonly>
                    </div>
                    <button type="button" class="btn btn-primary height-md" id="btn-proc-classe-vincular"><?php echo $classeNomeAtual !== '' ? 'Alterar classe' : 'Incluir classe'; ?></button>
                </div>
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" id="proc-classe-bloco-novo" style="display: none; gap: 12px;">
                    <div class="col">
                        <label for="proc-classe-nome-novo">Nome da classe (não cadastrada)</label>
                        <input type="text" class="width-700 height-md" id="proc-classe-nome-novo" maxlength="200" placeholder="Informe o nome para cadastrar a classe">
                    </div>
                    <button type="button" class="btn btn-primary height-md" id="btn-proc-classe-cadastrar">Cadastrar e vincular</button>
                </div>
            </div>
        </div>
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
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Adicionar parte</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="processo-parte-nome-selecao">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span> <span id="processo-parte-nome-status" class="label-hint-automatizado" aria-live="polite"></span></label>
                    <select class="width-700 height-md white" id="processo-parte-nome-selecao" style="display:none;"></select>
                    <input type="text" class="width-700 height-md" id="processo-parte-nome" placeholder="Digite o nome completo" autocomplete="off">
                    <input type="hidden" id="processo-parte-id" value="">
                </div>
                <div class="col">
                    <label for="processo-parte-tipo">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="processo-parte-tipo">
                        <option value="" selected disabled>Selecione o tipo</option>
                        <option value="reu">Réu</option>
                        <option value="indiciado">Indiciado</option>
                        <option value="sentenciado">Sentenciado</option>
                        <option value="requerido">Requerido</option>
                        <option value="vitima">Vítima</option>
                        <option value="requerente">Requerente</option>
                        <option value="testemunha_denuncia">Testemunha da Denúncia</option>
                        <option value="testemunha_defesa">Testemunha da Defesa</option>
                        <option value="advogado">Advogado</option>
                        <option value="defensor_publico">Defensor público</option>
                    </select>
                </div>
                <div class="col">
                    <label for="processo-parte-polo">Polo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="processo-parte-polo">
                        <option value="" selected disabled>Selecione...</option>
                        <option value="passivo">Passivo</option>
                        <option value="ativo">Ativo</option>
                        <option value="terceiros">Terceiros</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="btn-processo-parte-adicionar" disabled>Adicionar parte</button>
            </div>
        </div>
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Partes cadastradas</h2></div>
        <?php if (!empty($partes)): ?>
            <?php
                $partesGruposOrdem = [
                    'reus' => ['label' => 'Réus', 'ordem' => 10],
                    'defesa_reus' => ['label' => 'Defesa vinculada aos réus', 'ordem' => 20],
                    'vitimas' => ['label' => 'Vítimas', 'ordem' => 30],
                    'testemunhas_denuncia' => ['label' => 'Testemunhas da denúncia', 'ordem' => 40],
                    'testemunhas_defesa' => ['label' => 'Testemunhas da defesa', 'ordem' => 50],
                    'outros' => ['label' => 'Outras partes', 'ordem' => 900],
                ];
                $grupoDaParteProcesso = static function (array $parte): string {
                    $tipo = trim((string) ($parte['tipo_parte'] ?? ''));
                    if (in_array($tipo, ['reu', 'indiciado', 'sentenciado', 'requerido'], true)) {
                        return 'reus';
                    }
                    if (in_array($tipo, ['advogado', 'defensor_publico'], true)) {
                        return 'defesa_reus';
                    }
                    if (in_array($tipo, ['vitima', 'requerente'], true)) {
                        return 'vitimas';
                    }
                    if ($tipo === 'testemunha_denuncia') {
                        return 'testemunhas_denuncia';
                    }
                    if ($tipo === 'testemunha_defesa') {
                        return 'testemunhas_defesa';
                    }

                    return 'outros';
                };
                $partesAgrupadasProcesso = [];
                foreach ($partes as $parteItemProcesso) {
                    if (!is_array($parteItemProcesso)) {
                        continue;
                    }
                    $grupoParteProcesso = $grupoDaParteProcesso($parteItemProcesso);
                    if (!isset($partesAgrupadasProcesso[$grupoParteProcesso])) {
                        $partesAgrupadasProcesso[$grupoParteProcesso] = [];
                    }
                    $partesAgrupadasProcesso[$grupoParteProcesso][] = $parteItemProcesso;
                }
                foreach ($partesAgrupadasProcesso as &$grupoItensProcesso) {
                    usort($grupoItensProcesso, static function (array $a, array $b): int {
                        return strcasecmp((string) ($a['nome_completo'] ?? ''), (string) ($b['nome_completo'] ?? ''));
                    });
                }
                unset($grupoItensProcesso);
                uksort($partesAgrupadasProcesso, static function (string $a, string $b) use ($partesGruposOrdem): int {
                    return ($partesGruposOrdem[$a]['ordem'] ?? 999) <=> ($partesGruposOrdem[$b]['ordem'] ?? 999);
                });
            ?>
            <?php foreach ($partesAgrupadasProcesso as $grupoKeyProcesso => $partesGrupoProcesso): ?>
            <?php if ($partesGrupoProcesso === []): ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="display-flex row row-full justify-between padding-x-md padding-y-sm border-bottom" style="background-color: var(--color-primary-bg-light);">
                <div class="display-flex row row-fit justify-start align-center padding-x-sm">
                    <span class="text text-ellipsis" style="font-weight: 500; color: var(--color-primary-active); font-size: 12px;">
                        <?php echo htmlspecialchars((string) ($partesGruposOrdem[$grupoKeyProcesso]['label'] ?? 'Partes'), ENT_QUOTES); ?>
                    </span>
                </div>
            </div>
            <?php foreach ($partesGrupoProcesso as $p): ?>
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
                $dataNascimentoParte = trim((string) ($p['data_nascimento'] ?? ''));
                $parteIdDetalhes = trim((string) ($p['parte_id'] ?? ''));
                $detalhesParte = [];
                if ($cpfParte !== '') {
                    $detalhesParte[] = 'CPF: ' . $cpfParte;
                }
                if ($dataNascimentoParte !== '' && $dataNascimentoParte !== '0000-00-00') {
                    $timestampNascimento = strtotime($dataNascimentoParte);
                    $detalhesParte[] = 'Nascimento: ' . ($timestampNascimento !== false ? date('d/m/Y', $timestampNascimento) : $dataNascimentoParte);
                }
                if ($tipoParteRaw === 'testemunha_denuncia' && $parteIdDetalhes !== '') {
                    if (!array_key_exists($parteIdDetalhes, $instituicoesPorParteFicha)) {
                        $instituicoesPorParteFicha[$parteIdDetalhes] = $partesInstituicoesRepoFicha->findByParteId($parteIdDetalhes, true);
                    }
                    $vinculoInstituicao = $instituicoesPorParteFicha[$parteIdDetalhes][0] ?? null;
                    if (is_array($vinculoInstituicao)) {
                        $cargoPatente = trim((string) ($vinculoInstituicao['cargo_funcao'] ?? ''));
                        $instituicaoNome = trim((string) ($vinculoInstituicao['instituicao_sigla'] ?? ''));
                        if ($instituicaoNome === '') {
                            $instituicaoNome = trim((string) ($vinculoInstituicao['instituicao_nome'] ?? ''));
                        }
                        if ($instituicaoNome !== '') {
                            $detalhesParte[] = 'Instituição: ' . $instituicaoNome;
                        }
                        if ($cargoPatente !== '') {
                            $detalhesParte[] = 'Cargo/patente: ' . $cargoPatente;
                        }
                    }
                }
                if ($tipoParteRaw === 'advogado' && $parteIdDetalhes !== '') {
                    if (!array_key_exists($parteIdDetalhes, $documentosPorParteFicha)) {
                        $documentosPorParteFicha[$parteIdDetalhes] = $partesDocumentosRepoFicha->findByParteId($parteIdDetalhes);
                    }
                    foreach ($documentosPorParteFicha[$parteIdDetalhes] as $documentoParte) {
                        $docTipo = ParteDocumentoHelper::normalizarTipo((string) ($documentoParte['tipo'] ?? ''));
                        if ($docTipo !== 'OAB') {
                            continue;
                        }
                        $docNumero = ParteDocumentoHelper::formatarExibicao('OAB', (string) ($documentoParte['numero'] ?? ''));
                        if ($docNumero !== '') {
                            $detalhesParte[] = 'OAB: ' . $docNumero;
                        }
                        break;
                    }
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
        </div>
    </div>
    <?php if ($prioridadesFichaEstado !== null): ?>
    <script>
        window.PROCESSO_PRIORIDADES_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_PRIORIDADES_INITIAL = <?php echo json_encode($prioridadesFichaEstado, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="/assets/js/modules/audiencias/pauta-edit-prioridades.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit-prioridades.js') ?: time(); ?>"></script>
    <?php endif; ?>
    <script src="/assets/js/shared/processos-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/processos-edit.js') ?: time(); ?>"></script>
    <script>
        window.PROCESSO_ASSUNTOS_PROCESSO_ID = <?php echo json_encode($processoIdFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTOS_INITIAL = <?php echo json_encode($assuntosFicha, JSON_UNESCAPED_UNICODE); ?>;
        window.PROCESSO_ASSUNTO_FIND_ENDPOINT = '/api/assuntos/find-by-codigo.php';
        window.PROCESSO_ASSUNTO_LINK_ENDPOINT = '/api/processos/assunto-link.php';
        window.PROCESSO_ASSUNTO_UNLINK_ENDPOINT = '/api/processos/assunto-unlink.php';
    </script>
    <script>
        (function () {
            if (window.App && App.ProcessosEdit && typeof App.ProcessosEdit.initialize === 'function') {
                App.ProcessosEdit.initialize();
            }
            if (!window.App || !App.ConsultaProcessosEdit || typeof App.ConsultaProcessosEdit.initialize !== 'function') {
                return;
            }

            App.ConsultaProcessosEdit.initialize();
        })();
    </script>
</body>
</html>
