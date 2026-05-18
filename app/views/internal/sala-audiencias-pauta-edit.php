<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/PrioridadesProcessuaisCatalog.php';
require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../helpers/ClasseProcessualListagemHelper.php';
require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../../repositories/GabineteReuPresoProcessoRepository.php';
require_once __DIR__ . '/../../repositories/GabineteReuPresoRepository.php';
require_once __DIR__ . '/../../helpers/ParteDocumentoHelper.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';
require_once __DIR__ . '/../../repositories/PartesDocumentosRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';
require_once __DIR__ . '/../../repositories/PartesInstituicoesRepository.php';
require_once __DIR__ . '/../../repositories/PartesTelefonesRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
$row = $id !== '' ? $service->find($id) : null;
$partes = $row !== null ? $service->listPartes((string) $row['id_pauta']) : [];

$currentUserIdEdit = trim((string) ($_SESSION['id_usuario'] ?? ''));
$isGestaoEdit = false;
if ($currentUserIdEdit !== '') {
    $perfisEd = UsuarioPerfisAutorizacao::perfisDoUsuario($currentUserIdEdit);
    $isGestaoEdit = count(array_intersect($perfisEd, ['ADMIN', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'])) > 0;
}
$usuariosAlocacaoLista = [];
if ($row !== null) {
    $pdoU = Conection::getInstance()->getConnection();
    $stmtU = $pdoU->query('SELECT `id_usuario`, `nome`, `sobrenome` FROM `usuarios` WHERE `cadastro_ativo` = 1 ORDER BY `nome` ASC, `sobrenome` ASC');
    $usuariosAlocacaoLista = $stmtU ? $stmtU->fetchAll(PDO::FETCH_ASSOC) : [];
}
$prioridadesEdicaoInicial = null;
if ($row !== null) {
    try {
        $prioridadesEdicaoInicial = $service->listPrioridadesEdicaoAudiencia((string) $row['id_pauta']);
    } catch (Throwable $e) {
        $prioridadesEdicaoInicial = null;
    }
}
$reusPresosMap = [];
$reusPresosSigMap = [];
/** @var list<string> Nomes de réus/indiciados/sentenciados já vinculados ao processo (cadastro local). */
$reusNomesProcesso = [];

// Normalização usada para comparar o nome do "reu" na audiência
// com os nomes cadastrados em "réus presos" (reduz variações por espaços, acentos e pontuação).
$normalizeNomeParte = static function (string $s): string {
    $s = trim($s);
    $s = str_replace("\xC2\xA0", ' ', $s); // NBSP
    $s = preg_replace('/\p{Z}+/u', ' ', $s) ?: $s;
    $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;
    $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

    try {
        if (class_exists('Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
            $s = preg_replace('/\p{Mn}/u', '', $s) ?: $s;
        }
    } catch (Throwable $e) {
        // fallback ignora
    }

    if (function_exists('iconv')) {
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($trans !== false && $trans !== null) {
            $s = $trans;
        }
    }

    $s = preg_replace('/[^\p{L}\p{N} ]/u', '', $s) ?: $s;
    $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;
    return trim($s);
};

// Assinatura (somente letras/números, sem espaços) para comparação mais tolerante.
$signatureNomeParte = static function (string $normalized): string {
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
};

// Fallback para cenários de encoding inconsistente: evita descartar nomes válidos quando a
// normalização Unicode retorna vazio.
$normalizeNomeParteFallback = static function (string $s): string {
    $s = trim(str_replace("\xC2\xA0", ' ', $s));
    $s = preg_replace('/\s+/', ' ', $s) ?: $s;
    $s = strtolower($s);
    // Mantém letras/números/espaços em ASCII como fallback seguro.
    $s = preg_replace('/[^a-z0-9 ]+/', '', $s) ?: $s;
    $s = preg_replace('/\s+/', ' ', $s) ?: $s;
    return trim($s);
};

// Helper centralizado para alimentar os mapas com fallback robusto.
$addNomeToReuMap = static function (string $nome, array &$map, array &$sigMap) use ($normalizeNomeParte, $signatureNomeParte, $normalizeNomeParteFallback): void {
    $key = $normalizeNomeParte($nome);
    if ($key === '') {
        $key = $normalizeNomeParteFallback($nome);
    }
    if ($key === '') {
        return;
    }
    $map[$key] = true;
    $sig = $signatureNomeParte($key);
    if ($sig === '') {
        $sig = preg_replace('/[^a-z0-9]+/', '', strtolower($key)) ?: '';
    }
    if ($sig !== '') {
        $sigMap[$sig] = true;
    }
};
if ($row !== null) {
    $processoId = trim((string) ($row['processo'] ?? ''));
    $numeroProcesso = trim((string) ($row['numero_processo'] ?? ''));

    if ($processoId !== '') {
        try {
            $repoProcessoPartes = new ProcessosPartesRepository(Conection::getInstance()->getConnection());
            $reusNomesProcesso = $repoProcessoPartes->listNomesReusPorProcessoId($processoId);
        } catch (Throwable $e) {
            $reusNomesProcesso = [];
        }
    }

    // Monta mapa global de "réus presos" apenas pelo cadastro ativo,
    // para evitar falhas de resolução por processo_id/CNJ.
    try {
        $repoReuPreso = new GabineteReuPresoRepository(Conection::getInstance()->getConnection());
        $nomesGlobais = $repoReuPreso->listAtivosNomes();
        foreach ($nomesGlobais as $nomeGlobal) {
            $addNomeToReuMap((string) $nomeGlobal, $reusPresosMap, $reusPresosSigMap);
        }
    } catch (Throwable $e) {
        // Não bloqueia a edição da pauta.
    }
    if ($processoId !== '') {
        try {
            $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository(Conection::getInstance()->getConnection());
            $nomesReusPresos = $repoReuPresoProcesso->listReusPresosNomesByProcessoId($processoId);

            // Normaliza nomes para comparação (acentos/espacos não-padrão/pontuação/maiúsculas).
            $normalizeNomeParte = static function (string $s): string {
                $s = trim($s);
                // NBSP comum vindo de HTML/DB.
                $s = str_replace("\xC2\xA0", ' ', $s);

                // Colapsa qualquer tipo de espaço para " ".
                $s = preg_replace('/\p{Z}+/u', ' ', $s) ?: $s;
                $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;

                $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

                // Remove acentos de forma robusta (se intl estiver disponível).
                try {
                    if (class_exists('Normalizer')) {
                        $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
                        // Remove marcas diacríticas (acentos).
                        $s = preg_replace('/\p{Mn}/u', '', $s) ?: $s;
                    }
                } catch (Throwable $e) {
                    // fallback: ignora
                }

                // Fallback adicional: se `Normalizer` não estiver disponível (ou não funcionar),
                // translitera para ASCII removendo acentos.
                if (function_exists('iconv')) {
                    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
                    if ($trans !== false && $trans !== null) {
                        $s = $trans;
                    }
                }

                // Remove pontuação/símbolos (mantendo letras/numeros/espaços).
                $s = preg_replace('/[^\p{L}\p{N} ]/u', '', $s) ?: $s;

                $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;
                return trim($s);
            };

            // Assinatura: somente letras/números (sem espaços) para tolerar caracteres invisíveis.
            $signatureNomeParte = static function (string $normalized): string {
                return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
            };

            foreach ($nomesReusPresos as $nomeReuPreso) {
                $addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
            }

            if ($numeroProcesso !== '') {
                $nomesFallback = $repoReuPresoProcesso->listReusPresosNomesByNumeroProcesso($numeroProcesso);
                foreach ($nomesFallback as $nomeReuPreso) {
                    $addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
                }
            }
        } catch (Throwable $e) {
            // Ambiente sem tabelas do módulo gabinete ou falha pontual: não bloqueia a edição da pauta.
        }
    }

    // Se por algum motivo o processo_id estiver vazio, ainda assim tenta por CNJ (numero_processo).
    if ($processoId === '' && $numeroProcesso !== '') {
        try {
            $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository(Conection::getInstance()->getConnection());
            $nomesFallback = $repoReuPresoProcesso->listReusPresosNomesByNumeroProcesso($numeroProcesso);
            foreach ($nomesFallback as $nomeReuPreso) {
                $addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
            }
        } catch (Throwable $e) {
            // Não bloqueia a edição da pauta.
        }
    }
}
$partesRepoLista = new PartesRepository(Conection::getInstance()->getConnection());
$partesDocumentosRepoLista = new PartesDocumentosRepository(Conection::getInstance()->getConnection());
$advogadosCadastrados = [];
foreach ($partes as $parteItem) {
    if ((string) ($parteItem['tipo_parte'] ?? '') === 'advogado') {
        $nomeAdv = trim((string) ($parteItem['nome_parte'] ?? ''));
        if ($nomeAdv === '') {
            continue;
        }
        $parteAdvCadastro = null;
        $parteAdvId = trim((string) ($parteItem['parte_id_global'] ?? ''));
        if ($parteAdvId !== '' && preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $parteAdvId)) {
            $parteAdvCadastro = $partesRepoLista->findById($parteAdvId);
        }
        $docsAdv = $parteAdvCadastro !== null
            ? $partesDocumentosRepoLista->findByParteId((string) ($parteAdvCadastro['id'] ?? ''))
            : [];
        $advogadosCadastrados[] = ParteDocumentoHelper::montarRotuloDefensorPrivado($nomeAdv, $parteAdvCadastro, $docsAdv);
    }
}

$ordemTiposPartes = [
    'reu' => 0,
    'vitima' => 1,
    'testemunha_denuncia' => 2,
    'testemunha_defesa' => 3,
];
$partesOrdenadas = $partes;
usort($partesOrdenadas, static function (array $a, array $b) use ($ordemTiposPartes): int {
    $ta = (string) ($a['tipo_parte'] ?? '');
    $tb = (string) ($b['tipo_parte'] ?? '');
    $ia = $ordemTiposPartes[$ta] ?? 4;
    $ib = $ordemTiposPartes[$tb] ?? 4;
    if ($ia === $ib) {
        return strcmp((string) ($a['id_parte'] ?? ''), (string) ($b['id_parte'] ?? ''));
    }
    return $ia <=> $ib;
});

$tipos = $service->listTiposAtivos();
$tipoNppExtra = 'Proposta de Acordo de NPP';
$temTipoNppExtra = false;
foreach ($tipos as $tpItem) {
    $nomeTp = trim((string) ($tpItem['nome'] ?? ''));
    if ($nomeTp === $tipoNppExtra) {
        $temTipoNppExtra = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Pauta de Audiências - Edição</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Audiências -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar audiência</h1>
        <p class="description-page">Atualize os dados da audiência e gerencie as partes vinculadas à pauta.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($row === null): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                <span class="major-info">Audiência não encontrada.</span>
            </div>
        <?php else: ?>
        <?php
            $dataMinimaCadastro = date('Y-m-d');
            $horariosGrade = SalaAudienciasPautaService::horariosGradeCadastroAudiencia();
            $horaAtualEdit = substr((string) ($row['hora_audiencia'] ?? '00:00:00'), 0, 5);
            $statusAtualEdit = strtolower(trim((string) ($row['status'] ?? 'designada')));
            if ($statusAtualEdit === 'agendada') {
                $statusAtualEdit = 'designada';
            }
            if ($statusAtualEdit === 'adiada') {
                $statusAtualEdit = 'redesignada';
            }
            $statusOpcoesEdit = [
                'designada' => 'Designada',
                'em_andamento' => 'Em andamento',
                'finalizada' => 'Finalizada',
                'realizada' => 'Realizada',
                'redesignada' => 'Redesignada',
                'cancelada' => 'Cancelada',
            ];
            $horaNaGradeEdit = in_array($horaAtualEdit, $horariosGrade, true);
            $dtdesFmt = '—';
            if (!empty($row['data_hora_designacao'])) {
                $dtdesFmt = date('d/m/Y H:i', strtotime((string) $row['data_hora_designacao']));
            }
            $hiFmt = '—';
            if (!empty($row['hora_inicio'])) {
                $hiFmt = substr((string) $row['hora_inicio'], 0, 5);
            }
            $hfFmt = '—';
            if (!empty($row['hora_fim'])) {
                $hfFmt = substr((string) $row['hora_fim'], 0, 5);
            }
            $durMin = (int) ($row['duracao_estimada_minutos'] ?? 0);
            $dataAudRow = trim((string) ($row['data_audiencia'] ?? ''));
            $dataMinAttr = '';
            if ($statusAtualEdit === 'designada') {
                if ($dataAudRow !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAudRow) && $dataAudRow < $dataMinimaCadastro) {
                    $dataMinAttr = ' min="' . htmlspecialchars($dataAudRow, ENT_QUOTES) . '"';
                } else {
                    $dataMinAttr = ' min="' . htmlspecialchars($dataMinimaCadastro, ENT_QUOTES) . '"';
                }
            }
        ?>
        <input type="hidden" id="audiencia-edit-id" value="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados da audiência</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full gap-y-md">
                <div class="col filter">
                    <label for="audiencia-edit-processo">Processo</label>
                    <input type="text" class="width-400 height-md readonly" id="audiencia-edit-processo" value="<?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES); ?>" autocomplete="off" readonly>
                </div>
                <div class="col filter">
                    <?php
                    $classeValor = trim((string) ($row['classe_nome'] ?? ''));
                    $classeValorTemInfo = ClasseProcessualListagemHelper::temDestaqueListagem($classeValor);
                    $classeInputClassPautaEdit = 'width-600 height-md';
                    if ($classeValorTemInfo) {
                        $classeInputClassPautaEdit .= ' status-finished';
                    }
                    ?>
                    <label for="audiencia-edit-classe" id="processo-ap-info" aria-live="polite">Classe processual<?php echo $classeValorTemInfo ? ' <span class="label-hint-automatizado">*automatizado</span>' : ''; ?></label>
                    <input type="text" class="<?php echo htmlspecialchars($classeInputClassPautaEdit, ENT_QUOTES, 'UTF-8'); ?>" id="audiencia-edit-classe" data-datajud-related="true"<?php echo $classeValorTemInfo ? ' data-datajud-initial="1"' : ''; ?> value="<?php echo htmlspecialchars($classeValor, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Sem classe vinculada — cadastre em Consultar processo &gt; Editar processo" readonly autocomplete="off">
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col filter">
                    <label for="audiencia-edit-tipo">Tipo de audiência <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="audiencia-edit-tipo">
                        <?php $tipoAtual = (string) ($row['tipo_audiencia'] ?? ''); ?>
                        <?php foreach ($tipos as $tp): $tpNome = (string) ($tp['nome'] ?? ''); ?>
                            <option value="<?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?>"<?php echo $tipoAtual === $tpNome ? ' selected' : ''; ?>><?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                        <?php if (!$temTipoNppExtra || $tipoAtual === $tipoNppExtra): ?>
                            <option value="<?php echo htmlspecialchars($tipoNppExtra, ENT_QUOTES); ?>"<?php echo $tipoAtual === $tipoNppExtra ? ' selected' : ''; ?>><?php echo htmlspecialchars($tipoNppExtra, ENT_QUOTES); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-edit-data">Data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="audiencia-edit-data" data-min-designada="<?php echo htmlspecialchars($dataMinimaCadastro, ENT_QUOTES); ?>"<?php echo $dataMinAttr; ?> value="<?php echo htmlspecialchars((string) ($row['data_audiencia'] ?? ''), ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="audiencia-edit-hora">Hora <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="audiencia-edit-hora" required>
                        <option value="" disabled<?php echo $horaAtualEdit === '' ? ' selected' : ''; ?>>Selecione a hora</option>
                        <?php if (!$horaNaGradeEdit && $horaAtualEdit !== ''): ?>
                            <option value="<?php echo htmlspecialchars($horaAtualEdit, ENT_QUOTES); ?>" selected><?php echo htmlspecialchars($horaAtualEdit); ?> (registro atual)</option>
                        <?php endif; ?>
                        <?php foreach ($horariosGrade as $hm): ?>
                            <option value="<?php echo htmlspecialchars($hm, ENT_QUOTES); ?>"<?php echo $horaNaGradeEdit && $horaAtualEdit === $hm ? ' selected' : ''; ?>><?php echo htmlspecialchars($hm, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-edit-sala">Localização <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <?php $locAtual = (string) ($row['localizacao'] ?? ''); ?>
                    <select class="width-400 height-md" id="audiencia-edit-sala">
                        <option value="" disabled<?php echo $locAtual === '' ? ' selected' : ''; ?>>Selecione a localização</option>
                        <option value="presencial"<?php echo $locAtual === 'presencial' ? ' selected' : ''; ?>>Presencial - Fórum</option>
                        <option value="lifesize"<?php echo $locAtual === 'lifesize' ? ' selected' : ''; ?>>Virtual - Lifesize</option>
                        <option value="audin"<?php echo $locAtual === 'audin' ? ' selected' : ''; ?>>Virtual - Audin</option>
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-edit-status">Situação</label>
                    <select class="width-400 height-md" id="audiencia-edit-status">
                        <?php foreach ($statusOpcoesEdit as $valSt => $labelSt): ?>
                            <option value="<?php echo htmlspecialchars($valSt, ENT_QUOTES); ?>"<?php echo $statusAtualEdit === $valSt ? ' selected' : ''; ?>><?php echo htmlspecialchars($labelSt, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap gap-y-md" style="margin-top:8px;padding-top:12px;border-top:1px solid rgba(0,0,0,.08);">
                <div class="col filter">
                    <label>Duração estimada</label>
                    <input type="text" class="width-400 height-md readonly" readonly value="<?php echo htmlspecialchars($durMin . ' min', ENT_QUOTES); ?>" title="Atualizado ao salvar a audiência ou ao alterar partes">
                </div>
            </div>
        </div>

        <?php if ($row !== null): ?>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Servidores alocados</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <p class="description-page" style="margin:0;">Até cinco servidores por audiência. Quem cadastra é incluído automaticamente como responsável.</p>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="pauta-alocar-usuario">Incluir servidor</label>
                    <select class="width-500 height-md" id="pauta-alocar-usuario">
                        <option value="" selected disabled>Selecione…</option>
                        <?php foreach ($usuariosAlocacaoLista as $uAl): ?>
                            <?php
                            $uidAl = htmlspecialchars((string) ($uAl['id_usuario'] ?? ''), ENT_QUOTES);
                            $nomAl = trim(trim((string) ($uAl['nome'] ?? '')) . ' ' . trim((string) ($uAl['sobrenome'] ?? '')));
                            ?>
                            <option value="<?php echo $uidAl; ?>"><?php echo htmlspecialchars($nomAl, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-pauta-alocar">Alocar</button>
            </div>
            <div class="display-flex row row-fit justify-start gap-y-sm flex-wrap allocate-chips-row allocate-users" id="pauta-alocados-chips">
                <?php
                $alocadosEd = $row['Alocados'] ?? [];
                foreach ($alocadosEd as $alocadoEd) {
                    $tipoEd = $alocadoEd['tipo'] ?? 'specific';
                    $iniciaisEd = htmlspecialchars($alocadoEd['iniciais'] ?? '—', ENT_QUOTES);
                    $nomeCompletoEd = $alocadoEd['nome_completo'] ?? '';
                    $nomeLabelEd = $nomeCompletoEd !== '' ? $nomeCompletoEd : ($alocadoEd['iniciais'] ?? '—');
                    if ($tipoEd === 'rule') {
                        $chipTitleEd = 'Servidor responsável (regra): ' . $nomeLabelEd;
                    } elseif ($tipoEd === 'all') {
                        $chipTitleEd = 'Responsável pelo cadastro: ' . $nomeLabelEd;
                    } else {
                        $chipTitleEd = 'Alocado: ' . $nomeLabelEd;
                    }
                    $classeTipoEd = in_array($tipoEd, ['rule', 'specific', 'all'], true) ? ' ' . $tipoEd : '';
                    $alocadoIdEd = htmlspecialchars($alocadoEd['alocado_id'] ?? '', ENT_QUOTES);
                    $usuarioIdEd = $alocadoEd['usuario_id'] ?? '';
                    $podeRemoverEd = $isGestaoEdit || ($tipoEd === 'specific' && $usuarioIdEd === $currentUserIdEdit);
                    ?>
                    <span class="user-allocate-wrap">
                        <span class="user-allocate<?php echo $classeTipoEd; ?>" title="<?php echo htmlspecialchars($chipTitleEd, ENT_QUOTES); ?>"><?php echo $iniciaisEd; ?></span>
                        <?php if ($podeRemoverEd): ?>
                        <button type="button"
                            class="user-allocate-remove js-desalocar"
                            data-alocado-id="<?php echo $alocadoIdEd; ?>"
                            data-tipo="<?php echo htmlspecialchars($tipoEd, ENT_QUOTES); ?>"
                            data-endpoint="/api/sala-audiencias-pauta/desalocar.php"
                            title="Remover alocação"
                            aria-label="Remover alocação">×</button>
                        <?php endif; ?>
                    </span>
                    <?php
                }
                ?>
            </div>
        </div>
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Prioridades processuais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md" id="prioridades-processuais-wrap">
            <p class="description-page" style="margin:0;">Prioridades do processo (valem para todas as audiências). Inclusões e exclusões são salvas ao confirmar cada ação.</p>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="prioridade-catalog-select">Incluir prioridade</label>
                    <select class="width-500 height-md" id="prioridade-catalog-select">
                        <option value="" selected disabled>Selecione...</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-prioridade-adicionar">Adicionar</button>
            </div>
            <div class="display-flex col col-full grp-subtask-panel">
                <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top align-start flex-wrap grp-sub-line grp-sub-line-primary" style="font-family: monospace; font-size: 11px;">
                    <div id="prioridades-processuais-list" class="display-flex col col-full gap-y-sm" aria-live="polite"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Adicionar parte</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-nome-selecao">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span> <span id="parte-nome-processo-status" class="label-hint-automatizado" aria-live="polite"></span></label>
                    <select
                        class="width-700 height-md white"
                        id="parte-nome-selecao"
                    >
                        <option value="__OUTRO__" selected>Outro (digite abaixo)</option>
                    </select>
                    <input
                        type="text"
                        class="width-700 height-md"
                        id="parte-nome"
                        placeholder="Digite o nome completo"
                        autocomplete="off"
                        style="display:none;"
                    >
                    <input type="hidden" id="parte-id-global" value="">
                </div>
                <div class="col">
                    <label for="parte-tipo">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="parte-tipo">
                        <option value="" selected disabled>Selecione o tipo</option>
                        <option value="reu">Réu</option>
                        <option value="vitima">Vítima</option>
                        <option value="testemunha_defesa">Testemunha da Defesa</option>
                        <option value="testemunha_denuncia">Testemunha da Denúncia</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="col">
                    <label for="parte-maioridade">Maioridade <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-300 height-md" id="parte-maioridade">
                        <option value="">Selecione...</option>
                        <option value="adulto" selected>Adulto</option>
                        <option value="menor">Menor</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="btn-parte-adicionar">Adicionar parte</button>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Partes cadastradas</h2>
        </div>
        <?php
            $pdoPautaEditPartes = Conection::getInstance()->getConnection();
            $repoPiPautaEdit = new PartesInstituicoesRepository($pdoPautaEditPartes);
            $repoTelPautaEdit = new PartesTelefonesRepository($pdoPautaEditPartes);
            $repoProcPartesPautaEdit = new ProcessosPartesRepository($pdoPautaEditPartes);
            $processoIdAudiencia = trim((string) ($row['processo'] ?? ''));
            /** Cadastro global de partes com dados além do nome (evita destaque em registros criados só pela audiência via ensureParteByNomeOuCpf). */
            $parteGlobalTemCadastroGeralParaDestaqueNome = static function (?array $g): bool {
                if ($g === null) {
                    return false;
                }
                if (trim((string) ($g['cpf'] ?? '')) !== '') {
                    return true;
                }
                if (trim((string) ($g['profissao'] ?? '')) !== '') {
                    return true;
                }
                if (trim((string) ($g['sexo'] ?? '')) !== '') {
                    return true;
                }
                $dn = trim((string) ($g['data_nascimento'] ?? ''));
                if ($dn !== '' && $dn !== '0000-00-00' && strncmp($dn, '0000', 4) !== 0) {
                    return true;
                }

                return false;
            };
        ?>
        <?php foreach ($partesOrdenadas as $parte): ?>
        <?php
            $defensorTexto = 'Nenhum defensor vinculado';
            $tipoParteAtual = (string) ($parte['tipo_parte'] ?? '');
            $mostrarDefensor = in_array($tipoParteAtual, ['reu', 'vitima'], true);
            $minorInfoParte = (string) ucfirst(str_replace('_', ' ', $tipoParteAtual));
            $parteIdGlobalRaw = trim((string) ($parte['parte_id_global'] ?? ''));
            $parteGlobalCadastro = null;
            $parteGlobalLinkId = '';
            if ($parteIdGlobalRaw !== '') {
                $parteGlobalCadastro = $partesRepoLista->findById($parteIdGlobalRaw);
                if ($parteGlobalCadastro !== null) {
                    $parteGlobalLinkId = (string) ($parteGlobalCadastro['id'] ?? '');
                }
            }
            $mostrarBtnLinkGlobal = in_array($tipoParteAtual, ['testemunha_denuncia', 'testemunha_defesa'], true) && $parteGlobalLinkId !== '';

            $parteLigadaAoProcessoDaAudiencia = false;
            if ($processoIdAudiencia !== '' && $parteIdGlobalRaw !== '') {
                foreach ($repoProcPartesPautaEdit->findByParteId($parteIdGlobalRaw) as $ppLinha) {
                    if (trim((string) ($ppLinha['processo_id'] ?? '')) === $processoIdAudiencia) {
                        $parteLigadaAoProcessoDaAudiencia = true;
                        break;
                    }
                }
            }

            $cargoPrefixoTestemunhaDenuncia = '';
            $linhaCompanhiaTelefoneTestemunha = '';
            $vinculosInstituicaoTestemunhaDenuncia = [];
            if ($tipoParteAtual === 'testemunha_denuncia' && $parteGlobalLinkId !== '') {
                $vinculosInstituicaoTestemunhaDenuncia = $repoPiPautaEdit->findByParteId($parteIdGlobalRaw, true);
                $vin = $vinculosInstituicaoTestemunhaDenuncia[0] ?? null;
                if ($vin !== null) {
                    $cargoPrefixoTestemunhaDenuncia = trim((string) ($vin['cargo_funcao'] ?? ''));
                    $rotuloInst = trim((string) ($vin['instituicao_nome'] ?? ''));
                    if ($rotuloInst === '') {
                        $rotuloInst = trim((string) ($vin['instituicao_sigla'] ?? ''));
                    }
                    $telEscolhido = '';
                    foreach ($repoTelPautaEdit->findByParteId($parteIdGlobalRaw) as $telRow) {
                        if ((int) ($telRow['ativo'] ?? 1) !== 1) {
                            continue;
                        }
                        $telEscolhido = trim((string) ($telRow['numero'] ?? ''));
                        if ($telEscolhido !== '') {
                            break;
                        }
                    }
                    if ($rotuloInst !== '' || $telEscolhido !== '') {
                        $linhaCompanhiaTelefoneTestemunha = $rotuloInst;
                        if ($telEscolhido !== '') {
                            $linhaCompanhiaTelefoneTestemunha .= ($linhaCompanhiaTelefoneTestemunha !== '' ? ' ' : '') . '(' . $telEscolhido . ')';
                        }
                    }
                }
            }
            $temVinculoInstituicaoOuContatoTestemunhaDenuncia = $tipoParteAtual === 'testemunha_denuncia'
                && $parteGlobalLinkId !== ''
                && (
                    $vinculosInstituicaoTestemunhaDenuncia !== []
                    || $cargoPrefixoTestemunhaDenuncia !== ''
                    || $linhaCompanhiaTelefoneTestemunha !== ''
                );

            $cadastroGlobalRico = $parteGlobalTemCadastroGeralParaDestaqueNome($parteGlobalCadastro);
            if ($tipoParteAtual === 'reu') {
                $destacarNomeVinculoDb = ($parteGlobalLinkId !== '' || $parteLigadaAoProcessoDaAudiencia);
            } elseif ($tipoParteAtual === 'testemunha_denuncia') {
                $destacarNomeVinculoDb = $temVinculoInstituicaoOuContatoTestemunhaDenuncia
                    || ($parteGlobalLinkId !== '' && $cadastroGlobalRico);
            } elseif ($tipoParteAtual === 'vitima' || $tipoParteAtual === 'testemunha_defesa') {
                $destacarNomeVinculoDb = $parteGlobalLinkId !== '' && $cadastroGlobalRico;
            } else {
                $destacarNomeVinculoDb = ($parteGlobalLinkId !== '' || $parteLigadaAoProcessoDaAudiencia);
            }

            // Só "Réu PRESO" depende do nome da parte.
            if ($tipoParteAtual === 'reu') {
                $nomeParteRaw = trim((string) ($parte['nome_parte'] ?? ''));
                $nomeParteAtualKey = isset($normalizeNomeParte)
                    ? $normalizeNomeParte($nomeParteRaw)
                    : $nomeParteRaw;
                $nomeParteAtualSig = '';
                if ($nomeParteAtualKey !== '' && isset($signatureNomeParte)) {
                    $nomeParteAtualSig = $signatureNomeParte($nomeParteAtualKey);
                }
                $isReuPreso = $nomeParteAtualKey !== ''
                    && (isset($reusPresosMap[$nomeParteAtualKey])
                        || ($nomeParteAtualSig !== '' && isset($reusPresosSigMap[$nomeParteAtualSig])));

                // Fallback: comparação por palavras (tolerante a variações pequenas).
                if (!$isReuPreso && $nomeParteAtualKey !== '') {
                    $wordsReu = array_values(array_filter(explode(' ', $nomeParteAtualKey)));
                    if ($wordsReu !== []) {
                        foreach ($reusPresosMap as $k => $_) {
                            $wordsPreso = array_values(array_filter(explode(' ', (string) $k)));
                            if ($wordsPreso === []) {
                                continue;
                            }
                            $allWordsExist = true;
                            foreach ($wordsReu as $w) {
                                if ($w === '') continue;
                                if (!in_array($w, $wordsPreso, true) && strpos((string) $k, (string) $w) === false) {
                                    $allWordsExist = false;
                                    break;
                                }
                            }
                            if ($allWordsExist) {
                                $isReuPreso = true;
                                break;
                            }
                        }
                    }

                    // Fallback final: match fuzzy (similaridade) para diferenças pequenas.
                    if (!$isReuPreso) {
                        $bestPercent = 0;
                        foreach ($reusPresosMap as $k => $_) {
                            $presoKey = (string) $k;
                            if ($presoKey === '') continue;
                            if (function_exists('similar_text')) {
                                $percent = 0.0;
                                similar_text($nomeParteAtualKey, $presoKey, $percent);
                                if ($percent > $bestPercent) $bestPercent = $percent;
                                // Threshold mais alto para reduzir falsos positivos.
                                if ($percent >= 90) {
                                    $isReuPreso = true;
                                    break;
                                }
                            } else {
                                // Se similar_text não existir, não faz fuzzy.
                                break;
                            }
                        }
                    }
                }
                // "Réu preso" deve ficar colorido (color-danger).
                $minorInfoParte = $isReuPreso
                    ? '<span style="color: var(--color-danger); font-weight: 600;">Réu preso</span>'
                    : 'Réu solto';
            }

            // Defensor/advogado só deve existir e ser exibido para reu ou vitima.
            if ($mostrarDefensor) {
                $tipoDefensor = trim((string) ($parte['tipo_defensor'] ?? ''));
                $defensorPublico = trim((string) ($parte['defensor_publico_id'] ?? ''));
                $defensorPublicoOutroLista = trim((string) ($parte['defensor_publico_outro'] ?? ''));
                $defensorPrivado = trim((string) ($parte['defensor_privado_id'] ?? ''));
                $defensorPrivadoOutro = trim((string) ($parte['defensor_privado_outro'] ?? ''));

                if ($tipoDefensor === 'privado') {
                    if ($defensorPrivado === 'outro' && $defensorPrivadoOutro !== '') {
                        $defensorTexto = ParteDocumentoHelper::montarRotuloDefensorPrivado($defensorPrivadoOutro);
                    } elseif ($defensorPrivado !== '') {
                        $pAdv = null;
                        $nomeAdv = $defensorPrivado;
                        if (preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $defensorPrivado)) {
                            $pAdv = $partesRepoLista->findById($defensorPrivado);
                            if ($pAdv !== null) {
                                $nomeAdv = trim((string) ($pAdv['nome_completo'] ?? $defensorPrivado));
                            }
                        }
                        $docsAdv = $pAdv !== null
                            ? $partesDocumentosRepoLista->findByParteId((string) ($pAdv['id'] ?? ''))
                            : [];
                        $defensorTexto = ParteDocumentoHelper::montarRotuloDefensorPrivado($nomeAdv, $pAdv, $docsAdv);
                    }
                } elseif ($tipoDefensor === 'publico' && $defensorPublico !== '') {
                    if ($defensorPublico === 'outro' && $defensorPublicoOutroLista !== '') {
                        $defensorTexto = 'Defensor público: ' . $defensorPublicoOutroLista;
                    } else {
                        $rotuloDp = $defensorPublico;
                        if (preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $defensorPublico)) {
                            $pDp = $partesRepoLista->findById($defensorPublico);
                            if ($pDp !== null) {
                                $rotuloDp = trim((string) ($pDp['nome_completo'] ?? $defensorPublico));
                            }
                        }
                        $defensorTexto = 'Defensor público: ' . $rotuloDp;
                    }
                } elseif ($advogadosCadastrados !== []) {
                    $defensorTexto = implode(' / ', $advogadosCadastrados);
                }
            }

            $styleNomeParteAttrs = [];
            if ($tipoParteAtual === 'reu') {
                $styleNomeParteAttrs[] = 'font-weight: 600';
            }
            if ($destacarNomeVinculoDb) {
                $styleNomeParteAttrs[] = 'color: var(--color-primary-active)';
                $styleNomeParteAttrs[] = 'font-weight: 600';
            }
            if ($tipoParteAtual === 'testemunha_denuncia') {
                $styleNomeParteAttrs[] = 'display: block';
                $styleNomeParteAttrs[] = 'width: 100%';
                $styleNomeParteAttrs[] = 'max-width: 100%';
                $styleNomeParteAttrs[] = 'min-width: 0';
                $styleNomeParteAttrs[] = 'white-space: nowrap';
                $styleNomeParteAttrs[] = 'overflow: hidden';
                $styleNomeParteAttrs[] = 'text-overflow: ellipsis';
            }
            $styleAttrNomeParte = $styleNomeParteAttrs !== []
                ? ' style="' . htmlspecialchars(implode('; ', $styleNomeParteAttrs), ENT_QUOTES, 'UTF-8') . '"'
                : '';

            // Mini-status dual: metade esquerda = expedição, metade direita = cumprimento.
            $expedicaoVal = trim((string) ($parte['expedicao'] ?? 'Não expedido'));
            $cumprVal = trim((string) ($parte['cumprimento'] ?? ''));
            $leftColor = ($expedicaoVal === '' || $expedicaoVal === 'Não expedido')
                ? 'var(--color-secondary-bg-light)'
                : 'var(--color-success)';
            $rightColor = $cumprVal === 'cumprido'
                ? 'var(--color-success)'
                : ($cumprVal === 'nao_cumprido' ? 'var(--color-danger)' : 'var(--color-secondary-bg-light)');
            $labelCumprimento = ucfirst(str_replace('_', ' ', $cumprVal !== '' ? $cumprVal : 'pendente'));
            $titleMiniStatus = 'Expedição: ' . ($expedicaoVal !== '' ? $expedicaoVal : 'Não expedido') . ' | Cumprimento: ' . $labelCumprimento;
        ?>
        <div class="display-flex col col-full register border-bottom" data-parte-id="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-700"<?php echo $tipoParteAtual === 'testemunha_denuncia' ? ' style="min-width: 0"' : ''; ?>>
                        <div class="minor-info"><?php echo $minorInfoParte; ?></div>
                        <span class="major-info text-ellipsis"<?php echo $styleAttrNomeParte; ?>>
                            <?php
                                $nomeParteDisplay = (string) ($parte['nome_parte'] ?? '');
                                // Remove espaços invisíveis que podem vir do BD (ex.: &nbsp; e espaços extras).
                                $nomeParteDisplay = str_replace("\xC2\xA0", ' ', $nomeParteDisplay);
                                $nomeParteDisplay = trim(preg_replace('/\s+/u', ' ', $nomeParteDisplay) ?: $nomeParteDisplay);
                            ?>
                            <?php echo $cargoPrefixoTestemunhaDenuncia !== '' ? htmlspecialchars($cargoPrefixoTestemunhaDenuncia, ENT_QUOTES, 'UTF-8') . ' ' : ''; ?><?php echo htmlspecialchars($nomeParteDisplay, ENT_QUOTES, 'UTF-8'); ?><?php echo (string) $parte['maioridade'] === 'menor' ? ' (Menor)' : ''; ?>
                        </span>
                        <?php if ($linhaCompanhiaTelefoneTestemunha !== ''): ?>
                            <span class="minor-info-defesa" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;"><?php echo htmlspecialchars($linhaCompanhiaTelefoneTestemunha, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($mostrarDefensor): ?>
                            <span class="minor-info-defesa"><?php echo htmlspecialchars($defensorTexto, ENT_QUOTES); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col width-600">
                        <div class="minor-info">Documento expedido - ID: <span class="id-info">#<?php echo htmlspecialchars((string) ($parte['id_documento_expedido'] ?? '—'), ENT_QUOTES); ?></span></div>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($parte['expedicao'] ?? 'Não expedido'), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400">
                        <div class="minor-info">Status<?php if (!empty($parte['id_documento_cumprido'])): ?> - ID: <span class="id-info">#<?php echo htmlspecialchars((string) $parte['id_documento_cumprido'], ENT_QUOTES); ?></span><?php endif; ?></div>
                        <div class="major-info">
                            <span
                                class="mini-status mini-status-dual"
                                style="--left-color: <?php echo htmlspecialchars($leftColor, ENT_QUOTES); ?>; --right-color: <?php echo htmlspecialchars($rightColor, ENT_QUOTES); ?>"
                                title="<?php echo htmlspecialchars($titleMiniStatus, ENT_QUOTES); ?>"
                            ></span>
                            <?php echo htmlspecialchars((string) $labelCumprimento, ENT_QUOTES); ?>
                        </div>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <button type="button" class="btn btn-editar" data-parte-edit="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Editar parte"><i class="fi fi-rr-pen-square"></i></button>
                    <?php if ($mostrarBtnLinkGlobal): ?>
                        <button type="button" class="btn btn-editar" data-parte-global-id="<?php echo htmlspecialchars($parteGlobalLinkId, ENT_QUOTES); ?>" title="Ir para o cadastro da parte"><i class="fi fi-rr-user-link"></i></button>
                    <?php endif; ?>
                    <button type="button" class="btn-editar stop" data-parte-remove="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Remover parte"><i class="fi fi-rr-cross-circle"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="btn-audiencia-edit-voltar" data-allow-during-loading="true">Voltar</button>
            <button type="button" class="btn btn-danger-outline" id="btn-audiencia-edit-cancelar" data-allow-during-loading="true">Cancelar audiência</button>
            <button type="button" class="btn btn-danger" id="btn-audiencia-edit-excluir">Excluir audiência</button>
            <button type="button" class="btn btn-primary" id="btn-audiencia-edit-salvar">Salvar alterações</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php
        // Para filtrar o select de nomes do processo: não mostrar nomes que já existem na lista de partes desta audiência.
        // Assinatura = apenas letras/números (remove espaços e pontuação), para reduzir diferenças de formatação.
        $existingPartSignatures = [];
        foreach ($partes as $pItem) {
            $nome = (string) ($pItem['nome_parte'] ?? '');
            $nome = trim(str_replace("\xC2\xA0", ' ', $nome));
            $norm = mb_strtolower($nome, 'UTF-8');
            // Remove acentos e pontuação/espaços, mantendo apenas letras/números.
            try {
                if (class_exists('Normalizer')) {
                    $norm = \Normalizer::normalize($norm, \Normalizer::FORM_D);
                    $norm = preg_replace('/\p{Mn}/u', '', $norm) ?: $norm;
                }
            } catch (Throwable $e) {
                // ignora
            }
            $sig = preg_replace('/[^\p{L}\p{N}]+/u', '', $norm) ?: '';
            if ($sig !== '') {
                $existingPartSignatures[$sig] = true;
            }
        }
        $existingPartSignatures = array_keys($existingPartSignatures);

        $pautaHorariosGrade = [];
        $pautaHoraForaGrade = '';
        if ($row !== null) {
            $pautaHorariosGrade = SalaAudienciasPautaService::horariosGradeCadastroAudiencia();
            $hEditRow = substr((string) ($row['hora_audiencia'] ?? '00:00:00'), 0, 5);
            $pautaHoraForaGrade = in_array($hEditRow, $pautaHorariosGrade, true) ? '' : $hEditRow;
        }
    ?>
    <script>
        window.PAUTA_HORARIOS_GRADE = <?php echo json_encode($pautaHorariosGrade, JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_HORA_FORA_GRADE = <?php echo json_encode($pautaHoraForaGrade, JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_REUS_NOMES_INITIAL = <?php echo json_encode($reusNomesProcesso, JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_EXISTING_PART_NAMES_SIGS = <?php echo json_encode($existingPartSignatures, JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_PRIORIDADES_INITIAL = <?php echo json_encode($prioridadesEdicaoInicial, JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_EDIT_ID = <?php echo json_encode($row !== null ? (string) ($row['id_pauta'] ?? '') : '', JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_PROCESSO_ID = <?php echo json_encode($row !== null ? (string) ($row['processo'] ?? '') : '', JSON_UNESCAPED_UNICODE); ?>;
        window.PAUTA_PROCESSO_SIGILOSO = <?php echo json_encode($row !== null && !empty($row['processo_sigiloso'])); ?>;
        window.PRIORIDADES_BADGE_DANGER_CODES = <?php echo json_encode([
            PrioridadesProcessuaisCatalog::codigoAutomaticaReuPreso(),
            PrioridadesProcessuaisCatalog::codigoViolenciaDomestica(),
            PrioridadesProcessuaisCatalog::codigoAutomaticaParteMenor(),
        ], JSON_UNESCAPED_UNICODE); ?>;
        window.PRIORIDADES_BADGE_BLACK_CODES = <?php echo json_encode([
            PrioridadesProcessuaisCatalog::codigoSigiloso(),
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script src="/assets/js/modules/audiencias/pauta-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/audiencias/pauta-edit-prioridades.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit-prioridades.js') ?: time(); ?>"></script>
    <script>
        (function () {
            if (!window.App || !App.CartorioTaskList || typeof App.CartorioTaskList.initialize !== 'function') {
                return;
            }
            App.CartorioTaskList.initialize({
                storageKey: 'salapauta.edit.postReloadToast',
                enableDesalocar: true
            });
        })();
    </script>
</body>
</html>
