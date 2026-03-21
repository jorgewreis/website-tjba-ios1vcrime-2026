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
        <script src="/assets/js/shared/text-formatter.js"></script>
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

            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Réu</h2>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="gab-mon-reu-row-principal">
                <div class="col filter">
                    <label for="gab-mon-reu-select">Réu / Denunciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-800 height-md" id="gab-mon-reu-select" name="reu_id" style="display: none;" aria-label="Réus vinculados ao processo ou nome livre"></select>
                    <input type="text" class="width-800 height-md" id="gab-mon-reu-nome" name="reu_nome_livre" placeholder="Digite o nome completo" maxlength="255" autocomplete="off" aria-label="Nome do réu quando não há réus na lista ou opção Outro">
                </div>
            </div>
            <p class="minor-info" id="gab-mon-reu-hint" style="margin:0;">Com processo válido, o sistema carrega os réus vinculados; se não houver cadastro, informe o nome completo.</p>

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

            var reuSelect = document.getElementById('gab-mon-reu-select');
            var reuNome = document.getElementById('gab-mon-reu-nome');
            var processoInputEl = document.getElementById('gab-mon-processo');

            function showReuInput() {
                if (reuSelect) {
                    reuSelect.style.display = 'none';
                    reuSelect.innerHTML = '';
                    reuSelect.value = '';
                }
                if (reuNome) {
                    reuNome.style.display = '';
                    reuNome.required = true;
                }
            }

            function showReuSelect(reus) {
                if (!reuSelect || !reuNome) return;
                reuSelect.innerHTML = '';
                reus.forEach(function (r) {
                    var opt = document.createElement('option');
                    opt.value = r.id || '';
                    opt.textContent = (r.nome || '') + (r.tipo_parte ? ' (' + r.tipo_parte + ')' : '');
                    reuSelect.appendChild(opt);
                });
                var outro = document.createElement('option');
                outro.value = 'outro';
                outro.textContent = 'Outro';
                reuSelect.appendChild(outro);
                reuSelect.style.display = '';
                reuNome.style.display = 'none';
                reuNome.required = false;
                reuNome.value = '';
            }

            function carregarReus(numeroFormatado) {
                if (!reuSelect) return;
                showReuInput();
                if (reuNome) {
                    reuNome.placeholder = 'Carregando réus…';
                    reuNome.disabled = true;
                    reuNome.value = '';
                }
                var cc = document.getElementById('hidden-classe-codigo')?.value || '';
                var cd = document.getElementById('hidden-classe-descricao')?.value || '';
                var qs = 'numero_processo=' + encodeURIComponent(numeroFormatado);
                if (cc !== '') qs += '&classe_codigo=' + encodeURIComponent(cc);
                if (cd !== '') qs += '&classe_descricao=' + encodeURIComponent(cd);
                fetch('/api/gabinete-monitoramento-eletronico/reus-partes.php?' + qs, { credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json().then(function (body) {
                            if (!r.ok) {
                                throw new Error((body && body.error) ? body.error : 'Erro ao listar réus.');
                            }
                            return body;
                        });
                    })
                    .then(function (data) {
                        if (reuNome) {
                            reuNome.placeholder = 'Digite o nome completo';
                            reuNome.disabled = false;
                        }
                        var reus = (data && data.reus) ? data.reus : [];
                        if (reus.length > 0) {
                            showReuSelect(reus);
                        } else {
                            showReuInput();
                        }
                    })
                    .catch(function (err) {
                        if (reuNome) {
                            reuNome.placeholder = 'Digite o nome completo';
                            reuNome.disabled = false;
                        }
                        notify('error', 'Réus', err && err.message ? err.message : 'Erro ao carregar réus.');
                        showReuInput();
                    });
            }

            if (reuSelect) {
                reuSelect.addEventListener('change', function () {
                    if (this.value === 'outro') {
                        reuSelect.style.display = 'none';
                        reuSelect.innerHTML = '';
                        reuSelect.value = '';
                        if (reuNome) {
                            reuNome.style.display = '';
                            reuNome.required = true;
                            reuNome.value = '';
                            reuNome.focus();
                        }
                    } else if (this.value !== '') {
                        if (reuNome) {
                            reuNome.style.display = 'none';
                            reuNome.required = false;
                        }
                    }
                });
            }

            if (reuNome) {
                reuNome.addEventListener('blur', function () {
                    var nome = (reuNome.value || '').trim();
                    if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                        nome = TextFormatter.formatProperName(nome, 255);
                        reuNome.value = nome;
                    }
                });
            }

            if (processoInputEl) {
                processoInputEl.addEventListener('input', function () {
                    if ((processoInputEl.value || '').replace(/\D/g, '').length !== 20) {
                        showReuInput();
                        if (reuNome) {
                            reuNome.placeholder = 'Digite o nome completo';
                            reuNome.disabled = false;
                        }
                    }
                });
            }

            window.addEventListener('cnjValidated', function (event) {
                var digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
                if (digits.length !== 20) {
                    showReuInput();
                    return;
                }
                carregarReus(formatCnjFromDigits(digits));
            });

            window.addEventListener('gabineteMonDatajudClassePronta', function () {
                var proc = document.getElementById('gab-mon-processo')?.value?.trim() || '';
                if (proc) carregarReus(proc);
            });

            document.getElementById('btn-gab-mon-registrar')?.addEventListener('click', function () {
                var procEl = document.getElementById('gab-mon-processo');
                var proc = procEl?.value?.trim() || '';
                if (procEl && procEl.disabled) {
                    notify('info', 'Aguarde', 'Aguarde a consulta ao DataJud terminar antes de registrar.');
                    return;
                }
                var classeTxt = document.getElementById('gab-mon-classe')?.value?.trim() || '';
                if (classeTxt === 'Aguarde...') {
                    notify('info', 'Aguarde', 'Aguarde a consulta ao DataJud terminar antes de registrar.');
                    return;
                }
                var cc = document.getElementById('hidden-classe-codigo')?.value || '';
                var cd = document.getElementById('hidden-classe-descricao')?.value || '';
                var selEl = document.getElementById('gab-mon-reu-select');
                var nomeEl = document.getElementById('gab-mon-reu-nome');
                var reuSel = selEl && selEl.style.display !== 'none' ? (selEl.value || '') : '';
                var nomeLivre = nomeEl?.value?.trim() || '';
                if (!proc) {
                    notify('error', 'Validação', 'Informe o número do processo.');
                    return;
                }
                var body = {
                    numero_processo: proc,
                    classe_codigo: cc,
                    classe_descricao: cd,
                    reu_complemento: ''
                };
                if (reuSel === 'outro') {
                    if (!nomeLivre) {
                        notify('error', 'Validação', 'Informe o nome completo do réu.');
                        return;
                    }
                    body.reu_complemento = nomeLivre;
                } else if (reuSel !== '') {
                    body.processo_parte_id = reuSel;
                } else {
                    if (!nomeLivre) {
                        notify('error', 'Validação', 'Informe o nome completo do réu.');
                        return;
                    }
                    body.reu_complemento = nomeLivre;
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
