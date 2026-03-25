<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../../repositories/GabineteReuPresoProcessoRepository.php';
require_once __DIR__ . '/../../repositories/PartesRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
$row = $id !== '' ? $service->find($id) : null;
$partes = $row !== null ? $service->listPartes((string) $row['id_pauta']) : [];
$reusPresosMap = [];
if ($row !== null) {
    $processoId = trim((string) ($row['processo'] ?? ''));
    if ($processoId !== '') {
        try {
            $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository(Conection::getInstance()->getConnection());
            $nomesReusPresos = $repoReuPresoProcesso->listReusPresosNomesByProcessoId($processoId);
            foreach ($nomesReusPresos as $nomeReuPreso) {
                $nomeNorm = trim((string) $nomeReuPreso);
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower($nomeNorm, 'UTF-8')
                    : strtolower($nomeNorm);
                $reusPresosMap[$key] = true;
            }
        } catch (Throwable $e) {
            // Ambiente sem tabelas do módulo gabinete ou falha pontual: não bloqueia a edição da pauta.
            $reusPresosMap = [];
        }
    }
}
$advogadosCadastrados = [];
foreach ($partes as $parteItem) {
    if ((string) ($parteItem['tipo_parte'] ?? '') === 'advogado') {
        $nomeAdv = trim((string) ($parteItem['nome_parte'] ?? ''));
        if ($nomeAdv !== '') {
            $advogadosCadastrados[] = $nomeAdv;
        }
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
$partesRepoLista = new PartesRepository(Conection::getInstance()->getConnection());
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
            $statusAtualEdit = (string) ($row['status'] ?? 'designada');
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
                    <?php $classeValor = trim((string) ($row['classe_nome'] ?? '')); ?>
                    <label for="audiencia-edit-classe">Classe processual<?php echo $classeValor !== '' ? ' <span class="label-hint-automatizado">*automatizado</span>' : ''; ?></label>
                    <input type="text" class="width-600 height-md<?php echo $classeValor !== '' ? ' readonly' : ''; ?>" id="audiencia-edit-classe" value="<?php echo htmlspecialchars($classeValor, ENT_QUOTES); ?>" placeholder="Preencha se ainda não houver classe vinculada ao processo"<?php echo $classeValor !== '' ? ' readonly' : ''; ?> autocomplete="off">
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
                    </select>
                </div>
                <div class="col filter">
                    <label for="audiencia-edit-data">Data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="audiencia-edit-data" min="<?php echo htmlspecialchars($dataMinimaCadastro, ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string) ($row['data_audiencia'] ?? ''), ENT_QUOTES); ?>" required>
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
                        <option value="" selected disabled>Selecione a localização</option>
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

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Adicionar parte</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="parte-nome">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-700 height-md" id="parte-nome" placeholder="Digite o nome da parte" autocomplete="off">
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
        <?php foreach ($partesOrdenadas as $parte): ?>
        <?php
            $defensorTexto = 'Nenhum defensor vinculado';
            $tipoParteAtual = (string) ($parte['tipo_parte'] ?? '');
            $mostrarDefensor = in_array($tipoParteAtual, ['reu', 'vitima'], true);
            $minorInfoParte = (string) ucfirst(str_replace('_', ' ', $tipoParteAtual));

            // Só "Réu PRESO" depende do nome da parte.
            if ($tipoParteAtual === 'reu') {
                $nomeParteRaw = trim((string) ($parte['nome_parte'] ?? ''));
                $nomeParteAtualKey = function_exists('mb_strtolower')
                    ? mb_strtolower($nomeParteRaw, 'UTF-8')
                    : strtolower($nomeParteRaw);
                $isReuPreso = $nomeParteAtualKey !== '' && isset($reusPresosMap[$nomeParteAtualKey]);
                $minorInfoParte = $isReuPreso ? 'Réu <span class="id-preso">PRESO</span>' : 'Réu';
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
                        $defensorTexto = 'Advogado: ' . $defensorPrivadoOutro;
                    } elseif ($defensorPrivado !== '') {
                        $rotuloAdv = $defensorPrivado;
                        if (preg_match('/^[A-Z]{3}-[a-f0-9]{26}$/i', $defensorPrivado)) {
                            $pAdv = $partesRepoLista->findById($defensorPrivado);
                            if ($pAdv !== null) {
                                $rotuloAdv = trim((string) ($pAdv['nome_completo'] ?? $defensorPrivado));
                            }
                        }
                        $defensorTexto = 'Advogado: ' . $rotuloAdv;
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
                    $defensorTexto = 'Advogado: ' . implode(' / ', $advogadosCadastrados);
                }
            }

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
                    <div class="col width-700">
                        <div class="minor-info"><?php echo $minorInfoParte; ?></div>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $parte['nome_parte'], ENT_QUOTES); ?><?php echo (string) $parte['maioridade'] === 'menor' ? ' (Menor)' : ''; ?></span>
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
                    <button type="button" class="btn-editar" data-parte-edit="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Editar parte"><i class="fi fi-rr-pen-square"></i></button>
                    <button type="button" class="btn-editar stop" data-parte-remove="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Remover parte"><i class="fi fi-rr-cross-circle"></i></button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="btn-audiencia-edit-voltar">Voltar</button>
            <button type="button" class="btn btn-danger-outline" id="btn-audiencia-edit-cancelar">Cancelar audiência</button>
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

    <script src="/assets/js/shared/text-formatter.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/shared/text-formatter.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/audiencias/pauta-edit.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/pauta-edit.js') ?: time(); ?>"></script>
</body>
</html>
