<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/GabineteReusPresosService.php';

$connection = Conection::getInstance()->getConnection();
$service = new GabineteReusPresosService($connection);

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
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Réus Presos -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar réu preso</h1>
        <p class="description-page">Vincule processos (prioridade reu-preso quando preso), informe datas e movimentações que alimentam prazos.</p>
    </div>

    <?php if ($errorMessage !== '' || $reu === null): ?>
    <div class="container-full card-page">
        <div class="padding-x-md padding-y-md">
            <p class="major-info"><?php echo htmlspecialchars($errorMessage !== '' ? $errorMessage : 'Registro não encontrado.', ENT_QUOTES); ?></p>
            <button type="button" class="btn btn-secondary margin-top-md" id="btn-erro-voltar">Voltar à lista</button>
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
                <span class="minor-info">Próxima reavaliação (90 dias após decretada)</span>
                <span class="major-info" style="color:var(--primary-color-active);font-weight:600;"><?php echo htmlspecialchars($proxLabel, ENT_QUOTES); ?></span>
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
                    $sit = (string) ($p['situacao'] ?? 'preso');
                    $isPrin = !empty($p['principal']);
                    ?>
                <div class="display-flex col col-full register border-bottom grp-proc-link" data-link-id="<?php echo $lid; ?>" data-is-principal="<?php echo $isPrin ? '1' : '0'; ?>">
                    <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-start flex-wrap gap-y-md">
                        <div class="display-flex row row-fit flex-wrap gap-y-md align-end" style="flex:1 1 auto;min-width:0;">
                            <div class="col" style="min-width:140px;">
                                <select class="width-full height-md grp-principal-select" id="grp-vinc-<?php echo $vid; ?>" data-link-id="<?php echo $lid; ?>" <?php echo $arquivado ? 'disabled' : ''; ?>>
                                    <option value="principal"<?php echo $isPrin ? ' selected' : ''; ?>>Principal</option>
                                    <option value="secundario"<?php echo !$isPrin ? ' selected' : ''; ?><?php echo $grpProcCount === 1 ? ' disabled' : ''; ?>>Secundário</option>
                                </select>
                            </div>
                            <div class="col" style="min-width:200px;max-width:100%;">
                                <span class="minor-info">Processo</span>
                                <span class="major-info text-ellipsis grp-numero-processo" title="<?php echo $num; ?>"><?php echo $num; ?></span>
                            </div>
                            <?php if ($isPrin): ?>
                            <div class="col" style="min-width:140px;">
                                <span class="minor-info">Situação</span>
                                <select class="width-full height-md grp-situacao" data-link-id="<?php echo $lid; ?>" <?php echo $arquivado ? 'disabled' : ''; ?>>
                                    <option value="preso"<?php echo $sit === 'preso' ? ' selected' : ''; ?>>Preso</option>
                                    <option value="solto"<?php echo $sit === 'solto' ? ' selected' : ''; ?>>Solto</option>
                                </select>
                            </div>
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
                        <input type="text" class="width-500 height-md" id="filter-classe" placeholder="Preenchido pelo DataJud" readonly>
                    </div>
                </div>
                <input type="hidden" id="hidden-classe-codigo">
                <input type="hidden" id="hidden-classe-descricao">
                <?php if (empty($processos)): ?>
                <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                    <div class="col">
                        <label for="grp-new-sit">Situação neste processo</label>
                        <select class="width-300 height-md" id="grp-new-sit">
                            <option value="preso">Preso</option>
                            <option value="solto">Solto</option>
                        </select>
                    </div>
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
                            <option value="principal">Principal</option>
                            <option value="secundario" selected>Secundário</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" id="grp-new-sit" value="preso">
                    <input type="hidden" id="grp-new-dp" value="">
                    <input type="hidden" id="grp-new-df" value="">
                    <input type="hidden" id="grp-new-vinc" value="secundario">
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" id="btn-grp-add-proc">Adicionar processo</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Movimentações processuais</h2>
        </div>
        <div class="display-flex col col-full padding-x-md padding-y-md gap-y-md border-bottom">
            <?php if (!empty($movimentacoes)): ?>
            <ul class="row row-full display-flex" style="margin:0;padding:0;list-style:none;">
                <?php foreach ($movimentacoes as $m):
                    $movTipo = trim((string) ($m['tipo_label'] ?? ''));
                    $movObs = trim((string) ($m['observacoes'] ?? ''));
                    $movTexto = $movTipo;
                    if ($movObs !== '') {
                        $movTexto .= ' (' . $movObs . ')';
                    }
                    ?>
                <li class="display-flex row row-full align-center justify-between border-bottom padding-y-sm" style="gap:12px;">
                    <span class="minor-info text-ellipsis"><?php echo htmlspecialchars($movTexto, ENT_QUOTES); ?></span>
                    <div class="display-flex row row-fit align-center justify-end" style="gap:8px;flex-shrink:0;">
                        <span class="minor-info"><?php echo htmlspecialchars(grpFmtBr($m['data_movimentacao'] ?? null), ENT_QUOTES); ?></span>
                        <?php if (!$arquivado): ?>
                        <button
                            type="button"
                            class="btn btn-danger-outline height-sm grp-del-mov"
                            data-mov-id="<?php echo htmlspecialchars((string) ($m['id'] ?? ''), ENT_QUOTES); ?>"
                            title="Excluir movimentação"
                            aria-label="Excluir movimentação"
                            style="width: 25px; height: 20px !important; padding: 5px; border-radius: 2px; border: none; box-shadow: none;"
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
            <div class="display-flex row row-full flex-wrap align-end gap-y-md border-top padding-top-md" style="gap:12px;">
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
                    <input type="date" class="height-md" id="grp-mov-data">
                </div>
                <div class="col">
                    <label for="grp-mov-obs">Observações</label>
                    <input type="text" class="width-700 height-md" id="grp-mov-obs" placeholder="Opcional" maxlength="2000">
                </div>
                <button type="button" class="btn btn-primary height-md" id="btn-grp-add-mov" style="margin-top:22px;">Registrar movimentação</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="display-flex row row-full justify-end padding-x-md padding-y-md gap-y-md flex-wrap">
            <?php if (!$arquivado): ?>
            <button type="button" class="btn btn-secondary" id="btn-grp-voltar" onclick="window.location.href='/frame.php?page=reuspresos'">Voltar</button>
            <button type="button" class="btn btn-danger-outline" id="btn-grp-arquivar">Excluir</button>
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
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script>
        (function () {
            const reuId = document.getElementById('hidden-grp-id')?.value || '';
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };

            function postJson(url, body) {
                return fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
            }

            document.getElementById('btn-erro-voltar')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=reuspresos';
            });

            document.getElementById('btn-grp-salvar-nome')?.addEventListener('click', function () {
                let nome = (document.getElementById('grp-edit-nome')?.value || '').trim();
                if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                    nome = TextFormatter.formatProperName(nome, 255);
                    document.getElementById('grp-edit-nome').value = nome;
                }
                if (!nome) {
                    notify('error', 'Validação', 'Informe o nome.');
                    return;
                }
                postJson('/api/gabinete-reus-presos/update.php', { id: reuId, nome: nome }).then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notify('success', 'Sucesso', 'Nome atualizado.');
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao salvar.');
                    }
                }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
            });

            document.querySelectorAll('select.grp-principal-select').forEach(function (sel) {
                sel.setAttribute('data-prev', sel.value);
                sel.addEventListener('focus', function () {
                    sel.setAttribute('data-prev', sel.value);
                });
                sel.addEventListener('change', function () {
                    var old = sel.getAttribute('data-prev') || sel.value;
                    var next = sel.value;
                    var linkId = sel.getAttribute('data-link-id');
                    var row = sel.closest('.grp-proc-link');
                    var isPrincipalRow = row && row.getAttribute('data-is-principal') === '1';
                    var totalLinks = document.querySelectorAll('.grp-proc-link').length;
                    if (next === 'secundario') {
                        if (totalLinks < 2) {
                            sel.value = 'principal';
                            sel.setAttribute('data-prev', sel.value);
                            notify('error', 'Validação', 'O único processo vinculado deve ser o principal.');
                            return;
                        }
                        if (isPrincipalRow) {
                            sel.value = old;
                            sel.setAttribute('data-prev', old);
                            notify('info', 'Validação', 'Defina outro processo como principal antes de marcar este como secundário.');
                            return;
                        }
                        sel.setAttribute('data-prev', next);
                        return;
                    }
                    if (next === 'principal' && linkId) {
                        postJson('/api/gabinete-reus-presos/set-principal.php', {
                            reu_preso_id: reuId,
                            link_id: linkId
                        }).then(function (res) {
                            if (res.ok && res.j && res.j.success) {
                                notify('success', 'Sucesso', 'Processo principal atualizado. Os demais vínculos passam a ser secundários.');
                                window.location.reload();
                            } else {
                                notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                                sel.value = old;
                                sel.setAttribute('data-prev', old);
                            }
                        }).catch(function () {
                            notify('error', 'Erro', 'Erro de conexão.');
                            sel.value = old;
                            sel.setAttribute('data-prev', old);
                        });
                    }
                });
            });

            function collectLinkPayload(linkId, numeroProcesso, cc, cd) {
                const sit = document.querySelector('.grp-situacao[data-link-id="' + linkId + '"]');
                const dp = document.querySelector('.grp-dp[data-link-id="' + linkId + '"]');
                const df = document.querySelector('.grp-df[data-link-id="' + linkId + '"]');
                const vSel = document.querySelector('select.grp-principal-select[data-link-id="' + linkId + '"]');
                return {
                    reu_preso_id: reuId,
                    link_id: linkId,
                    numero_processo: numeroProcesso,
                    classe_codigo: cc || '',
                    classe_descricao: cd || '',
                    situacao: sit ? sit.value : 'preso',
                    data_prisao: dp && dp.value ? dp.value : null,
                    data_fato: df && df.value ? df.value : null,
                    principal: !!(vSel && vSel.value === 'principal')
                };
            }

            document.querySelectorAll('.grp-save-link').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const linkId = this.getAttribute('data-link-id');
                    const row = document.querySelector('.grp-proc-link[data-link-id="' + linkId + '"]');
                    const num = row ? (row.querySelector('.grp-numero-processo')?.textContent || '').trim() : '';
                    postJson('/api/gabinete-reus-presos/save-processo.php', collectLinkPayload(linkId, num, '', '')).then(function (res) {
                        if (res.ok && res.j && res.j.link_id) {
                            notify('success', 'Sucesso', 'Vínculo atualizado.');
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                        }
                    }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
                });
            });

            const pendingConfirmations = {};
            function requireSecondClick(key, message) {
                const now = Date.now();
                const validUntil = Number(pendingConfirmations[key] || 0);
                if (validUntil > now) {
                    delete pendingConfirmations[key];
                    return true;
                }
                pendingConfirmations[key] = now + 5000;
                notify('info', 'Confirmação', message);
                return false;
            }

            document.querySelectorAll('.grp-unlink').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const linkId = this.getAttribute('data-link-id');
                    if (!requireSecondClick('unlink-grp-' + linkId, 'Clique novamente em Remover em até 5 segundos para confirmar.')) return;
                    postJson('/api/gabinete-reus-presos/unlink-processo.php', { link_id: linkId }).then(function (res) {
                        if (res.ok && res.j && res.j.success) {
                            notify('success', 'Sucesso', 'Vínculo removido.');
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                        }
                    }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
                });
            });

            const procInput = document.getElementById('filter-processo');
            if (procInput) {
                procInput.addEventListener('input', function () {
                    var digits = (procInput.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        var classeInput = document.getElementById('filter-classe');
                        if (classeInput) { classeInput.value = ''; }
                        var h1 = document.getElementById('hidden-classe-codigo');
                        var h2 = document.getElementById('hidden-classe-descricao');
                        if (h1) h1.value = '';
                        if (h2) h2.value = '';
                    }
                });
            }

            document.getElementById('btn-grp-add-proc')?.addEventListener('click', function () {
                const proc = (document.getElementById('filter-processo')?.value || '').trim();
                const cc = document.getElementById('hidden-classe-codigo')?.value || '';
                const cd = document.getElementById('hidden-classe-descricao')?.value || '';
                const hasLinkedProcess = document.querySelectorAll('.grp-proc-link').length > 0;
                let situacao = document.getElementById('grp-new-sit')?.value || 'preso';
                let dp = document.getElementById('grp-new-dp')?.value || '';
                let df = document.getElementById('grp-new-df')?.value || '';
                const principal = (document.getElementById('grp-new-vinc')?.value || 'secundario') === 'principal';
                if (hasLinkedProcess) {
                    const inherited = getInheritedLinkData();
                    situacao = inherited.situacao || situacao;
                    dp = inherited.dataPrisao || dp;
                    df = inherited.dataFato || df;
                }
                if (!proc) {
                    notify('error', 'Validação', 'Informe o número do processo.');
                    return;
                }
                postJson('/api/gabinete-reus-presos/save-processo.php', {
                    reu_preso_id: reuId,
                    numero_processo: proc,
                    classe_codigo: cc,
                    classe_descricao: cd,
                    situacao: situacao,
                    data_prisao: dp || null,
                    data_fato: df || null,
                    principal: principal
                }).then(function (res) {
                    if (res.ok && res.j && res.j.link_id) {
                        notify('success', 'Sucesso', 'Processo vinculado.');
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
            });

            function getLinkSourceRowForInheritance() {
                const principalRow = document.querySelector('.grp-proc-link[data-is-principal="1"]');
                if (principalRow) {
                    return principalRow;
                }
                return document.querySelector('.grp-proc-link');
            }

            function getInheritedLinkData() {
                const row = getLinkSourceRowForInheritance();
                if (!row) {
                    return { situacao: 'preso', dataPrisao: '', dataFato: '' };
                }
                const situacao = row.querySelector('.grp-situacao')?.value || 'preso';
                const dataPrisao = row.querySelector('.grp-dp')?.value || '';
                const dataFato = row.querySelector('.grp-df')?.value || '';
                return { situacao: situacao, dataPrisao: dataPrisao, dataFato: dataFato };
            }

            function syncNewProcessInheritedFields() {
                const sitEl = document.getElementById('grp-new-sit');
                const dpEl = document.getElementById('grp-new-dp');
                const dfEl = document.getElementById('grp-new-df');
                if (!sitEl || !dpEl || !dfEl) {
                    return;
                }
                const hasLinkedProcess = document.querySelectorAll('.grp-proc-link').length > 0;
                if (!hasLinkedProcess) {
                    sitEl.disabled = false;
                    dpEl.disabled = false;
                    dfEl.disabled = false;
                    return;
                }
                const inherited = getInheritedLinkData();
                sitEl.value = inherited.situacao || 'preso';
                dpEl.value = inherited.dataPrisao || '';
                dfEl.value = inherited.dataFato || '';
                sitEl.disabled = true;
                dpEl.disabled = true;
                dfEl.disabled = true;
            }

            syncNewProcessInheritedFields();

            document.getElementById('btn-grp-add-mov')?.addEventListener('click', function () {
                const tipo = document.getElementById('grp-mov-tipo')?.value || '';
                const data = document.getElementById('grp-mov-data')?.value || '';
                const obs = (document.getElementById('grp-mov-obs')?.value || '').trim();
                if (!data) {
                    notify('error', 'Validação', 'Informe a data da movimentação.');
                    return;
                }
                const body = {
                    reu_preso_id: reuId,
                    tipo_codigo: tipo,
                    data_movimentacao: data,
                    observacoes: obs || undefined
                };
                postJson('/api/gabinete-reus-presos/add-movimentacao.php', body).then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notify('success', 'Sucesso', 'Movimentação registrada.');
                        window.location.reload();
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
            });

            document.querySelectorAll('.grp-del-mov').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const movId = this.getAttribute('data-mov-id') || '';
                    if (!movId) {
                        notify('error', 'Erro', 'Movimentação inválida.');
                        return;
                    }
                    if (!requireSecondClick('del-mov-' + movId, 'Clique novamente no x em até 5 segundos para confirmar a exclusão da movimentação.')) return;
                    postJson('/api/gabinete-reus-presos/delete-movimentacao.php', {
                        reu_preso_id: reuId,
                        movimentacao_id: movId
                    }).then(function (res) {
                        if (res.ok && res.j && res.j.success) {
                            notify('success', 'Sucesso', 'Movimentação excluída.');
                            window.location.reload();
                        } else {
                            notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                        }
                    }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
                });
            });

            document.getElementById('btn-grp-arquivar')?.addEventListener('click', function () {
                if (!requireSecondClick('arquivar-grp', 'Clique novamente em Arquivar em até 5 segundos para confirmar o arquivamento.')) return;
                postJson('/api/gabinete-reus-presos/archive.php', { id: reuId }).then(function (res) {
                    if (res.ok && res.j && res.j.success) {
                        notify('success', 'Sucesso', 'Registro arquivado.');
                        window.location.href = '/frame.php?page=reuspresos';
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha.');
                    }
                }).catch(function () { notify('error', 'Erro', 'Erro de conexão.'); });
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
