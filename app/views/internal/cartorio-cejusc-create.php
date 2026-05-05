<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '';
$hasRuleAllocation = false;
$ruleName = 'ALLOCATE-CEJUSC';
try {
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $allocatedUserId = $ruleRow['usuario_id'] ?? null;
    if ($allocatedUserId !== null && $allocatedUserId !== '') {
        $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmt->execute([$allocatedUserId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u !== false) {
            $nome = trim((string) ($u['nome'] ?? ''));
            $sobrenome = trim((string) ($u['sobrenome'] ?? ''));
            $ruleServidor = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            $hasRuleAllocation = $ruleServidor !== '';
        }
    }
} catch (\Throwable $e) {
    // mantém padrão
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cejusc - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cejusc -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro Cejusc</h1>
        <p class="description-page">Registre o envio da decisão ao Cejusc (número do processo e réu que deve participar do curso restaurativo).</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-400 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" data-datajud-related="true" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter" id="reu-col">
                <label for="filter-reu">Nome do réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-800 height-md" id="filter-reu-select" style="display: none;" aria-label="Réus vinculados ao processo">
                    <!-- preenchido via JS quando houver réus -->
                </select>
                <input type="text" class="width-800 height-md" id="filter-reu" placeholder="Digite o nome completo" maxlength="120" required aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
        </div>

        <?php $cartorioAlocacaoPrefix = 'cejusc'; include __DIR__ . '/_cartorio_alocacao_direcionada_create.php'; ?>

        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <?php if ($hasRuleAllocation): ?>
                <span>*Conforme regra de alocação, o envio de Decisões ao Cejusc será alocado automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
            <?php else: ?>
                <span>*Não existe nenhum usuário alocado automaticamente para esta tarefa no momento.</span>
            <?php endif; ?>
        </div>

        <div class="card-buttons">
            <div class="display-flex row row-fit gap-y-md align-center justify-start">
                <button class="btn btn-secondary" id="voltar-btn" type="button" data-allow-during-loading="true">Voltar</button>
            </div>
            <div class="display-flex row row-fit gap-y-md align-center justify-end">
                <button class="btn btn-primary-outline" id="alterar-regra-btn" type="button" style="display: none;">Alterar regra de alocação</button>
                <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
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

    <script>
        document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/frame.php?page=cejusc';
        }, { capture: true });
    </script>
    <!-- Padrão: número do processo = cnj.js (correção/formatação CNJ no blur); DataJud = classe processual; nome de pessoa = text-formatter.js. -->
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
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
            window.addEventListener('DOMContentLoaded', function () {
                if (!window.App || !App.User || typeof App.User.setupRuleButton !== 'function') return;
                App.User.setupRuleButton({
                    buttonId: 'alterar-regra-btn',
                    targetPage: 'cejuscrules'
                });
            });
        })();
    </script>
    <script>
        (function () {
            var alocState = (window.App && App.CartorioAlocacaoDirecionadaCreate && typeof App.CartorioAlocacaoDirecionadaCreate.init === 'function')
                ? App.CartorioAlocacaoDirecionadaCreate.init('cejusc')
                : null;
            var btn = document.getElementById('cadastrar-btn');
            var setBtnState = function (label, disabled) {
                if (!btn) return;
                btn.textContent = label;
                btn.disabled = disabled === true;
            };
            var showFeedback = function (message, type) {
                if (window.App && App.Page && typeof App.Page.notify === 'function') {
                    App.Page.notify(type === 'error' ? 'error' : 'success', type === 'error' ? 'Erro' : 'Sucesso', message);
                    return;
                }
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
            setBtnState('Aguarde...', true);
            if (processoInput) {
                processoInput.addEventListener('input', function () { setBtnState('Aguarde...', true); });
            }
            window.addEventListener('cnjInvalid', function (event) {
                var id = event && event.detail ? event.detail.inputId : '';
                if (!id || id === 'filter-processo') setBtnState('Erro', true);
            });
            window.addEventListener('datajudClasseResolved', function (event) {
                var detail = event && event.detail ? event.detail : {};
                if (!detail.processoInputId || detail.processoInputId === 'filter-processo') setBtnState('Cadastrar', false);
            });

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

                if (numeroProcesso === '') {
                    setBtnState('Erro', true);
                    return showFeedback('Informe o número do processo.', 'error');
                }
                if (processoInput && typeof window.normalizeAndValidateCNJValue === 'function') {
                    var normalized = window.normalizeAndValidateCNJValue(numeroProcesso, { toastOnInvalid: true });
                    if (!normalized.ok) {
                        setBtnState('Erro', true);
                        processoInput.value = '';
                        processoInput.focus();
                        return;
                    }
                    processoInput.value = normalized.formatted;
                    numeroProcesso = normalized.formatted;
                }
                if (classeVal === 'Aguarde...') {
                    setBtnState('Aguarde...', true);
                    return showFeedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                }
                if (!nomeReu || nomeReu === '') {
                    setBtnState('Erro', true);
                    return showFeedback('Informe o réu (selecione na lista ou digite em "Outro").', 'error');
                }

                setBtnState('Aguarde...', true);
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Cadastrando...' });
                else showFeedback('Cadastrando...', 'success');

                var payload = {
                    numero_processo_origem: numeroProcesso,
                    nome_reu: nomeReu
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

                if (alocState && typeof alocState.getIds === 'function') {
                    var extraIds = alocState.getIds();
                    if (extraIds && extraIds.length) {
                        payload.alocar_usuario_ids = extraIds;
                    }
                }

                fetch('/api/cartorio-cejusc/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.id) {
                        if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Tarefa Cejusc cadastrada com sucesso.' });
                        window.location.href = '/frame.php?page=cejusc';
                    } else {
                        showFeedback(data.error || 'Não foi possível cadastrar.', 'error');
                        setBtnState('Cadastrar', false);
                    }
                })
                .catch(function () {
                    showFeedback('Erro de conexão.', 'error');
                    setBtnState('Cadastrar', false);
                });
            });
        })();
    </script>
</body>
</html>
