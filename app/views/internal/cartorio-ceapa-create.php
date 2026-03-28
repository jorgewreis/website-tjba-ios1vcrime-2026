<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>CEAPA - Cadastro - Primeira Vara Crime de Ilhéus</title>
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
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro Ceapa</h1>
        <p class="description-page">Registre os dados para acompanhamento do cumprimento de decisão no CEAPA.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-reu">Nome do réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-800 height-md" id="filter-reu-select" style="display: none;" aria-label="Réus vinculados ao processo"></select>
                <input type="text" class="width-800 height-md" id="filter-reu" placeholder="Digite o nome completo" maxlength="120" required aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-tipo-suspensao">Tipo de suspensão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md" id="filter-tipo-suspensao" required>
                    <option value="" selected disabled>Selecione aqui</option>
                    <option value="Acordo de não Persecução Penal">Acordo de não Persecução Penal</option>
                    <option value="Suspensão Condicional do Processo">Suspensão Condicional do Processo</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-data-decisao">Data da decisão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-300 height-md" id="filter-data-decisao" required>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-decisao-cumprir">Decisão a cumprir <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-800 height-md" id="filter-decisao-cumprir" placeholder="Informe a pena definida na decisão" maxlength="500" required>
            </div>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary" id="cadastrar-btn">Cadastrar</button>
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
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script>
        (function () {
            var processoInput = document.getElementById('filter-processo');
            var reuSelect = document.getElementById('filter-reu-select');
            var reuInput = document.getElementById('filter-reu');

            function showReuInput() {
                if (reuSelect) {
                    reuSelect.style.display = 'none';
                    reuSelect.innerHTML = '';
                    reuSelect.value = '';
                }
                if (reuInput) {
                    reuInput.style.display = '';
                    reuInput.required = true;
                }
            }

            function showReuSelect(reus) {
                if (!reuSelect || !reuInput) return;
                reuSelect.innerHTML = '';
                reus.forEach(function (r) {
                    var opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.nome;
                    reuSelect.appendChild(opt);
                });
                var outro = document.createElement('option');
                outro.value = 'outro';
                outro.textContent = 'Outro';
                reuSelect.appendChild(outro);
                reuSelect.style.display = '';
                reuInput.style.display = 'none';
                reuInput.required = false;
                reuInput.value = '';
            }

            var lastSuggestionNome = '';
            function formatCpfDisplay(cpf) {
                var s = (cpf || '').replace(/\D/g, '');
                if (s.length !== 11) return cpf || '';
                return s.slice(0, 3) + '.' + s.slice(3, 6) + '.' + s.slice(6, 9) + '-' + s.slice(9);
            }
            function showReuSelectSugestoes(pessoas, nomeDigitado) {
                if (!reuSelect || !reuInput) return;
                reuSelect.innerHTML = '';
                pessoas.forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.id;
                    var label = (p.nome_completo || '').trim();
                    if ((p.cpf || '').replace(/\D/g, '').length === 11) {
                        label += ' - ' + formatCpfDisplay(p.cpf);
                    }
                    opt.textContent = label || p.id;
                    reuSelect.appendChild(opt);
                });
                var novo = document.createElement('option');
                novo.value = '__novo__';
                novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
                reuSelect.appendChild(novo);
                lastSuggestionNome = nomeDigitado || '';
                reuSelect.style.display = '';
                reuInput.style.display = 'none';
                reuInput.required = false;
            }

            window.addEventListener('cnjValidated', function (event) {
                var digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
                if (digits.length !== 20) {
                    showReuInput();
                    return;
                }
                fetch('/api/cartorio-guias/reus-por-processo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ numero: digits }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var reus = (data && data.reus) ? data.reus : [];
                    if (reus.length > 0) {
                        showReuSelect(reus);
                    } else {
                        showReuInput();
                    }
                })
                .catch(function () {
                    showReuInput();
                });
            });

            if (reuSelect) {
                reuSelect.addEventListener('change', function () {
                    if (this.value === 'outro') {
                        this.style.display = 'none';
                        if (reuInput) {
                            reuInput.style.display = '';
                            reuInput.required = true;
                            reuInput.focus();
                        }
                    } else if (this.value === '__novo__') {
                        reuSelect.style.display = 'none';
                        reuSelect.innerHTML = '';
                        reuSelect.value = '';
                        if (reuInput) {
                            reuInput.value = lastSuggestionNome;
                            reuInput.style.display = '';
                            reuInput.required = true;
                            reuInput.focus();
                        }
                    }
                });
            }

            if (reuInput) {
                reuInput.addEventListener('blur', function () {
                    var nome = (reuInput.value || '').trim();
                    if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                        nome = TextFormatter.formatProperName(nome, 120);
                        reuInput.value = nome;
                    }
                    if (nome.length < 2) return;
                    if (reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') return;
                    fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var pessoas = (data && data.pessoas) ? data.pessoas : [];
                            if (pessoas.length > 0) {
                                showReuSelectSugestoes(pessoas, nome);
                            }
                        })
                        .catch(function () {});
                });
            }

            if (processoInput) {
                processoInput.addEventListener('input', function () {
                    var digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        showReuInput();
                        var classeInput = document.getElementById('filter-classe');
                        if (classeInput) {
                            classeInput.value = '';
                            classeInput.readOnly = true;
                        }
                        var hCod = document.getElementById('hidden-classe-codigo');
                        var hDesc = document.getElementById('hidden-classe-descricao');
                        if (hCod) hCod.value = '';
                        if (hDesc) hDesc.value = '';
                    }
                });
            }
        })();
    </script>
    <script>
        (function () {
            var btn = document.getElementById('cadastrar-btn');
            var showFeedback = function (message, type) {
                if (typeof showToast === 'function') {
                    showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message: message });
                }
            };
            var getValue = function (id) {
                var el = document.getElementById(id);
                return (el && el.value) ? el.value.trim() : '';
            };
            var formatNomeReu = function (value) {
                if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                    return TextFormatter.formatProperName(value, 120);
                }
                return value;
            };

            var processoInput = document.getElementById('filter-processo');
            var reuInput = document.getElementById('filter-reu');
            var reuSelect = document.getElementById('filter-reu-select');
            if (reuInput) {
                reuInput.addEventListener('blur', function () {
                    reuInput.value = formatNomeReu(reuInput.value);
                });
            }

            function getReuPayload() {
                var useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
                if (useSelect) {
                    var opt = reuSelect.options[reuSelect.selectedIndex];
                    return {
                        reu_id: reuSelect.value,
                        nome_reu: opt ? opt.textContent : ''
                    };
                }
                return {
                    reu_id: '',
                    nome_reu: formatNomeReu(getValue('filter-reu'))
                };
            }

            if (!btn) return;

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.disabled) return;
                if (processoInput) processoInput.blur();
                if (reuInput) reuInput.blur();

                var numeroProcesso = getValue('filter-processo');
                var reuPayload = getReuPayload();
                var nomeReu = reuPayload.nome_reu;
                var classeVal = getValue('filter-classe');
                var classeCodigoField = document.getElementById('hidden-classe-codigo');
                var classeDescricaoField = document.getElementById('hidden-classe-descricao');
                var tipoSuspensao = getValue('filter-tipo-suspensao');
                var decisaoCumprir = getValue('filter-decisao-cumprir');
                var dataDecisao = getValue('filter-data-decisao');

                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo.', 'error');
                }
                if (processoInput && typeof window.normalizeAndValidateCNJValue === 'function') {
                    var normalized = window.normalizeAndValidateCNJValue(numeroProcesso, { toastOnInvalid: true });
                    if (!normalized.ok) {
                        processoInput.value = '';
                        processoInput.focus();
                        return;
                    }
                    processoInput.value = normalized.formatted;
                    numeroProcesso = normalized.formatted;
                }
                if (classeVal === 'Aguarde...') {
                    return showFeedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                }
                if (!nomeReu || nomeReu === '') {
                    return showFeedback('Informe o réu (selecione na lista ou digite em "Outro").', 'error');
                }
                if (tipoSuspensao === '') {
                    return showFeedback('Informe o tipo de suspensão.', 'error');
                }
                if (decisaoCumprir === '') {
                    return showFeedback('Informe a decisão a cumprir.', 'error');
                }
                if (dataDecisao === '') {
                    return showFeedback('Informe a data da decisão.', 'error');
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Cadastrando...' });

                var payload = {
                    numero_processo_origem: numeroProcesso,
                    nome_reu: nomeReu,
                    tipo_suspensao: tipoSuspensao,
                    decisao_cumprir: decisaoCumprir,
                    data_decisao: dataDecisao,
                    status_cumprimento: 'pendente'
                };
                if (reuPayload.reu_id) {
                    payload.reu_id = reuPayload.reu_id;
                }
                if (classeCodigoField && classeCodigoField.value.trim()) {
                    payload.classe_codigo = classeCodigoField.value.trim();
                }
                if (classeDescricaoField && classeDescricaoField.value.trim()) {
                    payload.classe_descricao = classeDescricaoField.value.trim();
                } else if (classeVal && classeVal !== 'Classe não informada' && classeVal !== 'Aguarde...') {
                    payload.classe_descricao = classeVal;
                    if (classeCodigoField && classeCodigoField.value.trim()) {
                        payload.classe_codigo = classeCodigoField.value.trim();
                    } else {
                        payload.classe_codigo = (classeVal || '').replace(/\s+/g, '').substring(0, 20);
                    }
                }

                fetch('/api/cartorio-ceapa/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.id) {
                        if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Cadastro CEAPA realizado com sucesso.' });
                        window.location.href = '/frame.php?page=ceapa';
                    } else {
                        showFeedback(data.error || 'Não foi possível cadastrar.', 'error');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    showFeedback('Erro de conexão.', 'error');
                    btn.disabled = false;
                });
            });
        })();

        document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/frame.php?page=ceapa';
        }, { capture: true });
    </script>
</body>
</html>
