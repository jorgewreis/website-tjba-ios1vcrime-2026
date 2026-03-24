<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasPautaService.php';
require_once __DIR__ . '/../../repositories/GabineteReuPresoProcessoRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
$service = new SalaAudienciasPautaService(Conection::getInstance()->getConnection());
$row = $id !== '' ? $service->find($id) : null;
$partes = $row !== null ? $service->listPartes((string) $row['id_pauta']) : [];
$reusPresosMap = [];
if ($row !== null) {
    $processoId = trim((string) ($row['processo'] ?? ''));
    if ($processoId !== '') {
        $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository(Conection::getInstance()->getConnection());
        $nomesReusPresos = $repoReuPresoProcesso->listReusPresosNomesByProcessoId($processoId);
        foreach ($nomesReusPresos as $nomeReuPreso) {
            $reusPresosMap[mb_strtolower(trim((string) $nomeReuPreso))] = true;
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
$tipos = $service->listTiposAtivos();
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
        <input type="hidden" id="audiencia-edit-id" value="<?php echo htmlspecialchars((string) $row['id_pauta'], ENT_QUOTES); ?>">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados da audiência</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="audiencia-edit-processo">Número do processo</label>
                    <input type="text" class="width-400 height-md readonly" id="audiencia-edit-processo" value="<?php echo htmlspecialchars((string) ($row['numero_processo'] ?? ''), ENT_QUOTES); ?>" autocomplete="off" readonly>
                </div>
                <div class="col">
                    <label for="audiencia-edit-classe">Classe</label>
                    <?php $classeValor = trim((string) ($row['classe_nome'] ?? '')); ?>
                    <input type="text" class="width-600 height-md<?php echo $classeValor !== '' ? ' readonly' : ''; ?>" id="audiencia-edit-classe" value="<?php echo htmlspecialchars($classeValor, ENT_QUOTES); ?>"<?php echo $classeValor !== '' ? ' readonly' : ''; ?>>
                </div>
            </div>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="audiencia-edit-tipo">Tipo de audiência <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-500 height-md" id="audiencia-edit-tipo">
                        <?php $tipoAtual = (string) ($row['tipo_audiencia'] ?? ''); ?>
                        <?php foreach ($tipos as $tp): $tpNome = (string) ($tp['nome'] ?? ''); ?>
                            <option value="<?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?>"<?php echo $tipoAtual === $tpNome ? ' selected' : ''; ?>><?php echo htmlspecialchars($tpNome, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="audiencia-edit-data">Data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="audiencia-edit-data" value="<?php echo htmlspecialchars((string) ($row['data_audiencia'] ?? ''), ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="audiencia-edit-hora">Hora <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="time" class="width-300 height-md" id="audiencia-edit-hora" value="<?php echo htmlspecialchars(substr((string) ($row['hora_audiencia'] ?? '00:00:00'), 0, 5), ENT_QUOTES); ?>">
                </div>
                <div class="col">
                    <label for="audiencia-edit-sala">Localização <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <?php $locAtual = (string) ($row['localizacao'] ?? ''); ?>
                    <select class="width-300 height-md" id="audiencia-edit-sala">
                        <option value="" selected disabled>Selecione a localização</option>
                        <option value="presencial"<?php echo $locAtual === 'presencial' ? ' selected' : ''; ?>>Presencial - Fórum</option>
                        <option value="lifesize"<?php echo $locAtual === 'lifesize' ? ' selected' : ''; ?>>Virtual - Lifesize</option>
                        <option value="audin"<?php echo $locAtual === 'audin' ? ' selected' : ''; ?>>Virtual - Audin</option>
                    </select>
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
        <?php foreach ($partes as $parte): ?>
        <?php
            $defensorTexto = 'Nenhum defensor vinculado';
            $tipoParteAtual = (string) ($parte['tipo_parte'] ?? '');
            $minorInfoParte = (string) ucfirst(str_replace('_', ' ', $tipoParteAtual));
            if ($tipoParteAtual === 'reu') {
                $nomeParteAtualKey = mb_strtolower(trim((string) ($parte['nome_parte'] ?? '')));
                $isReuPreso = $nomeParteAtualKey !== '' && isset($reusPresosMap[$nomeParteAtualKey]);
                $minorInfoParte = $isReuPreso ? 'Réu <span class="id-preso">PRESO</span>' : 'Réu';

                $tipoDefensor = trim((string) ($parte['tipo_defensor'] ?? ''));
                $defensorPublico = trim((string) ($parte['defensor_publico_id'] ?? ''));
                $defensorPrivado = trim((string) ($parte['defensor_privado_id'] ?? ''));
                $defensorPrivadoOutro = trim((string) ($parte['defensor_privado_outro'] ?? ''));

                if ($tipoDefensor === 'privado') {
                    if ($defensorPrivado === 'outro' && $defensorPrivadoOutro !== '') {
                        $defensorTexto = 'Advogado: ' . $defensorPrivadoOutro;
                    } elseif ($defensorPrivado !== '') {
                        $defensorTexto = 'Advogado: ' . $defensorPrivado;
                    }
                } elseif ($tipoDefensor === 'publico' && $defensorPublico !== '') {
                    $defensorTexto = 'Defensor público: ' . $defensorPublico;
                } elseif ($advogadosCadastrados !== []) {
                    $defensorTexto = 'Advogado: ' . implode(' / ', $advogadosCadastrados);
                }
            }
        ?>
        <div class="display-flex col col-full register border-bottom" data-parte-id="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center">
                    <div class="col width-700">
                        <div class="minor-info"><?php echo $minorInfoParte; ?></div>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $parte['nome_parte'], ENT_QUOTES); ?><?php echo (string) $parte['maioridade'] === 'menor' ? ' (Menor)' : ''; ?></span>
                        <span class="minor-info-defesa"><?php echo htmlspecialchars($defensorTexto, ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-600">
                        <div class="minor-info">Documento expedido - ID: <span class="id-info">#<?php echo htmlspecialchars((string) ($parte['id_documento_expedido'] ?? '—'), ENT_QUOTES); ?></span></div>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) ($parte['expedicao'] ?? 'Não expedido'), ENT_QUOTES); ?></span>
                    </div>
                    <div class="col width-400">
                        <div class="minor-info">Status<?php if (!empty($parte['id_documento_cumprido'])): ?> - ID: <span class="id-info">#<?php echo htmlspecialchars((string) $parte['id_documento_cumprido'], ENT_QUOTES); ?></span><?php endif; ?></div>
                        <div class="major-info"><span class="mini-status <?php echo (string) $parte['cumprimento'] === 'cumprido' ? 'status-finished' : ((string) $parte['cumprimento'] === 'nao_cumprido' ? 'status-cancelled' : 'status-pending'); ?>"></span><?php echo htmlspecialchars((string) ucfirst(str_replace('_', ' ', (string) $parte['cumprimento'])), ENT_QUOTES); ?></div>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <div class="col button-table button-edit" data-parte-edit="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Editar parte"><i class="fi fi-rr-file-edit"></i></div>
                    <div class="col button-table" data-parte-remove="<?php echo htmlspecialchars((string) $parte['id_parte'], ENT_QUOTES); ?>" title="Remover parte"><i class="fi fi-rr-trash"></i></div>
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
