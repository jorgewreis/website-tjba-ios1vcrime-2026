<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '?';
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
            if ($ruleServidor === '') {
                $ruleServidor = '?';
            }
        }
    }
} catch (\Throwable $e) {
    // mantém padrão
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cejusc - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
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

    <div class="card-page">
        <div class="row table header">Informações obrigatórias</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="Digite o número do processo" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-lg height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="row filter">
            <div class="col filter" id="reu-col">
                <label for="filter-reu">Nome do réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-xl height-md" id="filter-reu-select" style="display: none;" aria-label="Réus vinculados ao processo">
                    <!-- preenchido via JS quando houver réus -->
                </select>
                <input type="text" class="width-xl height-md" id="filter-reu" placeholder="Nome do denunciado" maxlength="120" required aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
        </div>

        <div class="row info">
            <span>*Conforme regra de alocação, o envio de Decisões ao Cejusc será alocado automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
        </div>

        <div class="row buttons">
            <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de alocação</button>
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
        </div>

        <div class="row pagination">
            <div class="col">
                <label id="cadastro-feedback" class="status-placeholder" aria-live="polite"></label>
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
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
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

            if (reuSelect) {
                reuSelect.addEventListener('change', function () {
                    if (this.value === 'outro') {
                        this.style.display = 'none';
                        if (reuInput) {
                            reuInput.style.display = '';
                            reuInput.required = true;
                            reuInput.focus();
                        }
                    }
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
            var PERFIS_ALTERAR_REGRA = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];
            function hasPermissionToChangeRule(perfis) {
                if (!Array.isArray(perfis) || perfis.length === 0) return false;
                var allowed = PERFIS_ALTERAR_REGRA.map(function (p) { return p.toUpperCase(); });
                return perfis.some(function (perfil) {
                    var p = (perfil && String(perfil)).toUpperCase().trim();
                    return allowed.indexOf(p) !== -1;
                });
            }
            window.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('alterar-regra-btn');
                if (!btn) return;
                fetch('/admin/api/getPerfis.php', { credentials: 'same-origin' })
                    .then(function (r) { if (!r.ok) throw new Error(); return r.json(); })
                    .then(function (data) {
                        var perfis = (data && data.perfis) ? data.perfis : [];
                        if (hasPermissionToChangeRule(perfis)) {
                            btn.style.display = '';
                            btn.addEventListener('click', function () {
                                window.location.href = '/frame.php?page=cejuscrules';
                            });
                        }
                    })
                    .catch(function () { btn.style.display = 'none'; });
            });
        })();
    </script>
    <script>
        (function () {
            var btn = document.getElementById('cadastrar-btn');
            var feedback = document.getElementById('cadastro-feedback');
            var showFeedback = function (message, type) {
                if (typeof showToast === 'function') {
                    showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message: message });
                } else if (feedback) {
                    feedback.innerHTML = '<label class="status ' + (type === 'error' ? 'status-cancelled' : 'status-finished') + '">' + message + '</label>';
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
                var useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro';
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

                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo.', 'error');
                }
                if (classeVal === 'Aguarde...') {
                    return showFeedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                }
                if (!nomeReu || nomeReu === '') {
                    return showFeedback('Informe o réu (selecione na lista ou digite em "Outro").', 'error');
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Cadastrando...' });
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
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    showFeedback('Erro de conexão.', 'error');
                    btn.disabled = false;
                });
            });
        })();
    </script>
</body>
</html>
