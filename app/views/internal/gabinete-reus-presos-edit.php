<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();
$origem = (($_GET['origem'] ?? '') === 'reuspresossubstituto') ? 'reuspresossubstituto' : 'reuspresos';
$isSubstituto = $origem === 'reuspresossubstituto';
$service = new GabineteReusPresosService($connection, $isSubstituto);

$id = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$detail = null;

if ($id === '') {
    $errorMessage = 'Identificador não informado.';
} else {
    try {
        $detail = $service->findById($id);
        if ($detail === null) {
            $errorMessage = 'Registro não encontrado.';
        }
    } catch (Throwable $e) {
        $errorMessage = 'Erro ao carregar: ' . $e->getMessage();
    }
}

$reu = $detail['reu'] ?? null;
$processos = $detail['processos'] ?? [];
$movimentacoes = $detail['movimentacoes'] ?? [];
$arquivado = $reu !== null && (($reu['status'] ?? '') === 'arquivado');
$tiposMov = GabineteReusPresosService::tiposMovimentacao();

function grpFmtYmd(?string $d): string
{
    if ($d === null || $d === '') {
        return '';
    }
    $t = strtotime((string) $d);

    return $t !== false ? date('Y-m-d', $t) : '';
}

function grpFmtBr(?string $d): string
{
    if ($d === null || $d === '') {
        return '—';
    }
    $t = strtotime((string) $d);

    return $t !== false ? date('d/m/Y', $t) : '—';
}

function grpFmtDateTimeBr(?string $d): string
{
    if ($d === null || $d === '') {
        return '—';
    }
    $t = strtotime((string) $d);

    return $t !== false ? date('d/m/Y H:i', $t) : '—';
}

$proxLabel = grpFmtBr($reu['proxima_revisao'] ?? null);
$ultimaMov = $movimentacoes[0] ?? null;
$ultimaMovLabel = '—';
if ($ultimaMov !== null) {
    $ultimaMovLabel = htmlspecialchars(
        ($ultimaMov['tipo_label'] ?? '') . ' — ' . grpFmtBr($ultimaMov['data_movimentacao'] ?? null),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Réus Presos (Gabinete) — Edição</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/processo-bloco-listagem.css?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/css/shared/processo-bloco-listagem.css') ?: time(); ?>">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <?php include __DIR__ . '/_copy_process_script.php'; ?>
    <style>
        .grp-proc-link .grp-proc-bloco-wrap { flex: 0 0 auto; min-width: 0; }
        .grp-proc-link .grp-proc-fields { flex: 1 1 auto; min-width: 0; }
    </style>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Réus Presos -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar réu preso</h1>
        <p class="description-page">Vincule processos, informe datas e movimentações.</p>
    </div>

    <?php if ($errorMessage !== '' || $reu === null): ?>
    <div class="container-full card-page">
        <div class="padding-x-md padding-y-md">
            <p class="major-info"><?php echo htmlspecialchars($errorMessage !== '' ? $errorMessage : 'Registro não encontrado.', ENT_QUOTES); ?></p>
            <button type="button" class="btn btn-secondary margin-top-md" id="btn-erro-voltar" data-allow-during-loading="true">Voltar à lista</button>
        </div>
    </div>
    <?php else: ?>
    <input type="hidden" id="hidden-grp-id" value="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>">

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Resumo</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md flex-wrap" style="gap:24px;">
            <div class="col">
                <span class="minor-info">Próxima reavaliação</span>
                <span class="major-info" style="color:var(--color-primary-active);font-weight:600;"><?php echo htmlspecialchars($proxLabel, ENT_QUOTES); ?></span>
            </div>
            <div class="col">
                <span class="minor-info">Última movimentação</span>
                <span class="major-info"><?php echo $ultimaMovLabel; ?></span>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Dados gerais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md gap-y-md border-bottom">
            <div class="col">
                <label for="grp-edit-nome">Nome do réu</label>
                <input type="text" class="width-800 height-md readonly" id="grp-edit-nome" value="<?php echo htmlspecialchars((string) ($reu['nome'] ?? ''), ENT_QUOTES); ?>" maxlength="255" <?php echo $arquivado ? 'disabled' : ''; ?> readonly>
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Processos vinculados</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md gap-y-md border-bottom">
            <?php if (!empty($processos)): ?>
            <?php $grpProcCount = count($processos); ?>
            <div class="display-flex col col-full gap-y-sm">
                <?php foreach ($processos as $p):
                    $lidRaw = (string) ($p['id'] ?? '');
                    $lid = htmlspecialchars($lidRaw, ENT_QUOTES);
                    $vid = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $lidRaw), ENT_QUOTES);
                    $num = htmlspecialchars((string) ($p['numero_processo'] ?? ''), ENT_QUOTES);
                    $isPrin = !empty($p['principal']);
                    ?>
                <div class="display-flex col col-full register border-bottom grp-proc-link" data-link-id="<?php echo $lid; ?>" data-is-principal="<?php echo $isPrin ? '1' : '0'; ?>" data-numero-processo="<?php echo $num; ?>">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-start flex-wrap gap-y-md">
                        <div class="display-flex row row-fit flex-wrap gap-y-md align-end grp-proc-fields" style="flex:1 1 auto;min-width:0;">
                            <div class="col width-500 col-process grp-proc-bloco-wrap">
                                <?php $row = $p; $processo_bloco_model = 'full'; $processo_bloco_show_polo_passivo = false; include __DIR__ . '/_processo_bloco_listagem.php'; ?>
                            </div>
                            <div class="col" style="min-width:140px;">
                                <label for="grp-vinc-<?php echo $vid; ?>" class="minor-info">Vinculação</label>
                                <select class="width-full height-md grp-principal-select" id="grp-vinc-<?php echo $vid; ?>" data-link-id="<?php echo $lid; ?>" <?php echo $arquivado ? 'disabled' : ''; ?>>
                                    <option value="principal"<?php echo $isPrin ? ' selected' : ''; ?>>Principal</option>
                                    <option value="secundario"<?php echo !$isPrin ? ' selected' : ''; ?><?php echo $grpProcCount === 1 ? ' disabled' : ''; ?>>Secundário</option>
                                </select>
                            </div>
                            <?php if ($isPrin): ?>
                            <div class="col" style="min-width:150px;">
                                <span class="minor-info">Data da prisão</span>
                                <input type="date" class="width-full height-md grp-dp" data-link-id="<?php echo $lid; ?>" value="<?php echo htmlspecialchars(grpFmtYmd($p['data_prisao'] ?? null), ENT_QUOTES); ?>" <?php echo $arquivado ? 'disabled' : ''; ?>>
                            </div>
                            <div class="col" style="min-width:150px;">
                                <span class="minor-info">Data do fato</span>
                                <input type="date" class="width-full height-md grp-df" data-link-id="<?php echo $lid; ?>" value="<?php echo htmlspecialchars(grpFmtYmd($p['data_fato'] ?? null), ENT_QUOTES); ?>" <?php echo $arquivado ? 'disabled' : ''; ?>>
                            </div>
                            <?php else: ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$arquivado): ?>
                        <div class="display-flex row row-fit justify-end gap-y-sm align-center" style="flex-shrink:0;align-self:flex-start;">
                            <?php if ($isPrin): ?>
                            <button type="button" class="btn btn-primary height-sm grp-save-link" data-link-id="<?php echo $lid; ?>">Salvar</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-danger-outline height-sm grp-unlink" data-link-id="<?php echo $lid; ?>">Remover</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="minor-info">Nenhum processo vinculado ainda.</p>
            <?php endif; ?>

            <?php if (!$arquivado): ?>
            <div class="display-flex col col-full gap-y-md border-top padding-top-md">
                <h3 style="margin:0;font-size:1rem;">Incluir processo</h3>
                <?php if (!empty($processos)): ?>
                <?php endif; ?>
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                    <div class="col">
                        <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                        <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off">
                    </div>
                    <div class="col">
                        <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                        <input type="text" class="width-500 height-md" id="filter-classe" data-datajud-related="true" placeholder="Preenchido pelo DataJud" readonly>
                    </div>
                </div>
                <input type="hidden" id="hidden-classe-codigo">
                <input type="hidden" id="hidden-classe-descricao">
                <input type="hidden" id="grp-new-sit" value="preso">
                <?php if (empty($processos)): ?>
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                    <div class="col">
                        <label for="grp-new-dp">Data da prisão</label>
                        <input type="date" class="height-md" id="grp-new-dp">
                    </div>
                    <div class="col">
                        <label for="grp-new-df">Data do fato</label>
                        <input type="date" class="height-md" id="grp-new-df">
                    </div>
                    <div class="col" style="min-width:140px;">
                        <label for="grp-new-vinc" class="minor-info">Vinculação</label>
                        <select class="width-400 height-md" id="grp-new-vinc">
                            <option value="principal" selected>Principal</option>
                            <option value="secundario">Secundário</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="grp-new-dp" value="">
                    <input type="hidden" id="grp-new-df" value="">
                    <input type="hidden" id="grp-new-vinc" value="secundario">
                    <?php endif; ?>
                    <button type="button" class="btn btn-primary-outline" id="btn-grp-add-proc">Adicionar processo</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Movimentações processuais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom">
            <?php if (!empty($movimentacoes)): ?>
            <ul class="row row-full display-flex" id="grp-mov-table-body" style="font-family: monospace; font-size:0.9rem;">
                <?php foreach ($movimentacoes as $m):
                    $movTipo = trim((string) ($m['tipo_label'] ?? ''));
                    $movTipoCodigo = trim((string) ($m['tipo_codigo'] ?? ''));
                    $movVirtual = !empty($m['is_virtual']);
                    $movObs = trim((string) ($m['observacoes'] ?? ''));
                    $movBase = grpFmtBr($m['data_movimentacao'] ?? null) . ' - ' . $movTipo;
                    $movLabel = $movObs !== '' ? ($movBase . ' (' . $movObs . ')') : $movBase;
                    $movExtraClass = ($movTipoCodigo === 'anotacao' || strpos($movTipoCodigo, 'audiencia_') === 0) ? ' status-current' : '';
                    ?>
                <li class="display-flex row row-full align-center justify-between border-bottom padding-y-sm grp-mov-row<?php echo $movExtraClass; ?>" style="gap:12px;">
                    <span class="minor-info text-ellipsis" style="min-width:0;flex:1; margin: 0 20px; font-size: 11px; padding-top: 3px;"><?php echo htmlspecialchars($movLabel, ENT_QUOTES); ?></span>
                    <span class="minor-info width-200" style="font-size:10px; margin: 0 10px; padding-top: 3px;"><?php echo htmlspecialchars(grpFmtDateTimeBr($m['created_at'] ?? null), ENT_QUOTES); ?></span>
                    <div class="display-flex row row-fit align-center justify-end">
                        <?php if (!$arquivado && !$movVirtual): ?>
                        <button
                            type="button"
                            class="btn btn-danger-outline height-sm grp-del-mov"
                            data-mov-id="<?php echo htmlspecialchars((string) ($m['id'] ?? ''), ENT_QUOTES); ?>"
                            title="Excluir movimentação"
                            aria-label="Excluir movimentação"
                            style="display: flex; align-items: center; justify-content: center; width: 20px; height: 20px !important; padding: 5px; margin-right: 10px; border-radius: 2px; border: 1px solid var(--color-border);"
                        >x</button>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="minor-info" style="margin:0;">Nenhuma movimentação registrada.</p>
            <?php endif; ?>

            <?php if (!$arquivado): ?>
            <div class="display-flex row row-full flex-wrap align-end gap-y-md padding-top-lg" style="gap:12px;">
                <div class="col">
                    <label for="grp-mov-tipo">Tipo</label>
                    <select class="width-600 height-md" id="grp-mov-tipo">
                        <?php foreach ($tiposMov as $cod => $lab): ?>
                        <option value="<?php echo htmlspecialchars($cod, ENT_QUOTES); ?>"><?php echo htmlspecialchars($lab, ENT_QUOTES); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label for="grp-mov-data">Data</label>
                    <input type="date" class="height-md" id="grp-mov-data" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col">
                    <label for="grp-mov-obs">Observações</label>
                    <input type="text" class="width-700 height-md" id="grp-mov-obs" maxlength="2000">
                </div>
                <button type="button" class="btn btn-primary-outline height-md" id="btn-grp-add-mov" style="margin-top:22px;">Registrar movimentação</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full justify-between padding-x-md padding-y-md gap-y-md flex-wrap">
            <?php if (!$arquivado): ?>
            <button type="button" class="btn btn-danger-outline" id="btn-grp-arquivar">Excluir</button>
            <button type="button" class="btn btn-secondary" id="btn-grp-voltar" data-allow-during-loading="true">Voltar</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($errorMessage === '' && $reu !== null && !$arquivado): ?>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <?php endif; ?>
    <script>
        (function () {
            if (!window.App || !App.GabineteReusPresosEdit || typeof App.GabineteReusPresosEdit.initialize !== 'function') {
                return;
            }

            App.GabineteReusPresosEdit.initialize();
        })();
    </script>
</body>
</html>
