<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$routePage = trim((string) ($_GET['page'] ?? 'monitoramentogabinetecreate'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Monitoramento Eletrônico (Gabinete) — Cadastro</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Monitoramento Eletrônico -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Novo monitoramento eletrônico</h1>
        <p class="description-page">Vincule o registro ao processo em contexto e ao réu. Processo e classe são definidos pelo sistema (DataJud).</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Dados do registro</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="gab-mon-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-400 height-md" id="gab-mon-processo" name="processo" placeholder="0000000-00.0000.0.00.0000" autocomplete="off" data-cnj-input="true">
                </div>
                <div class="col">
                    <label for="gab-mon-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-600 height-md readonly" id="gab-mon-classe" name="classe" placeholder="Classe (automático DataJud)" readonly>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

            <div class="display-flex row row-full flex-wrap align-end gap-y-md" style="gap:12px;">
                <div class="col">
                    <label for="gab-mon-reu-select">Réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-700 height-md" id="gab-mon-reu-select" name="reu_id" aria-describedby="gab-mon-reu-hint">
                        <option value="">Selecione o réu…</option>
                        <option value="outro">Outro (informar abaixo)</option>
                    </select>
                </div>
                <div class="col">
                    <label for="gab-mon-reu-input">Complemento / nome (se necessário)</label>
                    <input type="text" class="width-700 height-md" id="gab-mon-reu-input" name="reu_complemento" placeholder="Ex.: alcunha, qualificação adicional ou nome completo se 'Outro'" autocomplete="off" aria-describedby="gab-mon-reu-hint">
                </div>
            </div>
            <p class="minor-info" id="gab-mon-reu-hint" style="margin:0;">Implementação: popular o select via API com as partes réus do processo; o input cobre casos não listados ou detalhes.</p>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="btn-gab-mon-voltar">Voltar</button>
                <button type="button" class="btn btn-primary" id="btn-gab-mon-registrar">Registrar</button>
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

    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script>
        (function () {
            const notify = (type, title, message) => {
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };

            document.getElementById('btn-gab-mon-voltar')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=monitoramentogabinete';
            });

            function formatCnjFromDigits(digits) {
                if (!digits || digits.length !== 20) return '';
                return digits.substr(0, 7) + '-' + digits.substr(7, 2) + '.' + digits.substr(9, 4) + '.' + digits.substr(13, 1) + '.' + digits.substr(14, 2) + '.' + digits.substr(16, 4);
            }

            function carregarReus(numeroFormatado) {
                const sel = document.getElementById('gab-mon-reu-select');
                if (!sel) return;
                sel.innerHTML = '<option value="">Carregando…</option>';
                fetch('/api/gabinete-monitoramento-eletronico/reus-partes.php?numero_processo=' + encodeURIComponent(numeroFormatado), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const reus = (data && data.reus) ? data.reus : [];
                        sel.innerHTML = '';
                        var opt0 = document.createElement('option');
                        opt0.value = '';
                        opt0.textContent = 'Selecione…';
                        sel.appendChild(opt0);
                        reus.forEach(function (r) {
                            var o = document.createElement('option');
                            o.value = r.id || '';
                            o.textContent = (r.nome || '') + (r.tipo_parte ? ' (' + r.tipo_parte + ')' : '');
                            sel.appendChild(o);
                        });
                        var outro = document.createElement('option');
                        outro.value = 'outro';
                        outro.textContent = 'Outro (informar abaixo)';
                        sel.appendChild(outro);
                    })
                    .catch(function () {
                        sel.innerHTML = '<option value="">Erro ao carregar réus</option><option value="outro">Outro (informar abaixo)</option>';
                    });
            }

            window.addEventListener('cnjValidated', function (event) {
                var digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
                if (digits.length !== 20) {
                    var sel = document.getElementById('gab-mon-reu-select');
                    if (sel) sel.innerHTML = '<option value="">Informe o processo válido…</option><option value="outro">Outro (informar abaixo)</option>';
                    return;
                }
                carregarReus(formatCnjFromDigits(digits));
            });

            document.getElementById('btn-gab-mon-registrar')?.addEventListener('click', function () {
                var proc = document.getElementById('gab-mon-processo')?.value?.trim() || '';
                var cc = document.getElementById('hidden-classe-codigo')?.value || '';
                var cd = document.getElementById('hidden-classe-descricao')?.value || '';
                var reuSel = document.getElementById('gab-mon-reu-select')?.value || '';
                var comp = document.getElementById('gab-mon-reu-input')?.value?.trim() || '';
                if (!proc) {
                    notify('error', 'Validação', 'Informe o número do processo.');
                    return;
                }
                if (!reuSel) {
                    notify('error', 'Validação', 'Selecione o réu ou a opção Outro.');
                    return;
                }
                if (reuSel === 'outro' && !comp) {
                    notify('error', 'Validação', 'Informe o nome do réu no complemento quando usar Outro.');
                    return;
                }
                var body = {
                    numero_processo: proc,
                    classe_codigo: cc,
                    classe_descricao: cd,
                    reu_complemento: comp
                };
                if (reuSel !== 'outro') {
                    body.processo_parte_id = reuSel;
                }
                fetch('/api/gabinete-monitoramento-eletronico/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.id) {
                        try {
                            sessionStorage.setItem('gme.postReloadToast', JSON.stringify([{ type: 'success', title: 'Sucesso', message: 'Monitoramento registrado.' }]));
                        } catch (e) {}
                        window.location.href = '/frame.php?page=monitoramentogabinete';
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao registrar.');
                    }
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                });
            });
        })();
    </script>
</body>
</html>
