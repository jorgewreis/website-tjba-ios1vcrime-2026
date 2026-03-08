<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '?';
$ruleName = 'ALLOCATE-GUIAS';
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
        <title>Guias - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Guia</h1>
        <p class="description-page">Registre uma nova guia de execução ou recolhimento (processo e denunciado).</p>
    </div>

    <div class="card-page">
        <div class="row table header">Informações obrigatórias</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-lg height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="row filter">
            <div class="col filter" id="denunciado-col">
                <label for="filter-denunciado">Sentenciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-xl height-md" id="filter-denunciado-select" style="display: none;" aria-label="Réus vinculados ao processo">
                    <!-- preenchido via JS quando houver réus -->
                </select>
                <input type="text" class="width-xl height-md" id="filter-denunciado" placeholder="Nome do denunciado/réu" maxlength="255" aria-label="Nome do denunciado quando não há réus ou opção Outro">
            </div>
            <div class="col filter">
                <label for="filter-tipo">Tipo de guia <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-lg height-md" id="filter-tipo" required>
                    <option value="Guia - Tipo não informado" selected>Guia - Tipo não informado</option>
                    <option value="Guia de Execução Definitiva">Guia de Execução Definitiva</option>
                    <option value="Guia de Recolhimento Provisória">Guia de Recolhimento Provisória</option>
                    <option value="Guia de Recolhimento Definitiva">Guia de Recolhimento Definitiva</option>
                    <option value="Guia (corrigir)">Guia (corrigir)</option>
                    <option value="Guia (reenviar)">Guia (reenviar)</option>
                </select>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-regime">Regime</label>
                <select class="width-md height-md" id="filter-regime">
                    <option value="">Selecione (opcional)</option>
                    <option value="Aberto">Aberto</option>
                    <option value="Semiaberto">Semiaberto</option>
                    <option value="Fechado">Fechado</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-data-fato">Data do fato</label>
                <input type="date" class="width-md height-md" id="filter-data-fato" max="">
            </div>
            <div class="col filter">
                <label for="filter-dias-multa">Dias-multa</label>
                <input type="number" class="width-sm height-md" id="filter-dias-multa" min="0" placeholder="—">
            </div>
        </div>

        <div class="row info">
            <span>*Conforme regra de alocação, a expedição e envio das Guias serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
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
            window.location.href = '/frame.php?page=guias';
        }, { capture: true });
        (function () {
            var dataFato = document.getElementById('filter-data-fato');
            if (dataFato) {
                var today = new Date();
                today.setDate(today.getDate() - 1);
                dataFato.setAttribute('max', today.toISOString().split('T')[0]);
            }
        })();
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
    <script>
        (function () {
            var processoInput = document.getElementById('filter-processo');
            var denunciadoSelect = document.getElementById('filter-denunciado-select');
            var denunciadoInput = document.getElementById('filter-denunciado');

            function showDenunciadoInput() {
                if (denunciadoSelect) {
                    denunciadoSelect.style.display = 'none';
                    denunciadoSelect.innerHTML = '';
                    denunciadoSelect.value = '';
                }
                if (denunciadoInput) {
                    denunciadoInput.style.display = '';
                    denunciadoInput.required = true;
                }
            }

            function showDenunciadoSelect(reus) {
                if (!denunciadoSelect || !denunciadoInput) return;
                denunciadoSelect.innerHTML = '';
                reus.forEach(function (r) {
                    var opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.nome;
                    denunciadoSelect.appendChild(opt);
                });
                var outro = document.createElement('option');
                outro.value = 'outro';
                outro.textContent = 'Outro';
                denunciadoSelect.appendChild(outro);
                denunciadoSelect.style.display = '';
                denunciadoInput.style.display = 'none';
                denunciadoInput.required = false;
                denunciadoInput.value = '';
            }

            window.addEventListener('cnjValidated', function (event) {
                var digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
                if (digits.length !== 20) {
                    showDenunciadoInput();
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
                        showDenunciadoSelect(reus);
                    } else {
                        showDenunciadoInput();
                    }
                })
                .catch(function () {
                    showDenunciadoInput();
                });
            });

            if (denunciadoSelect) {
                denunciadoSelect.addEventListener('change', function () {
                    if (this.value === 'outro') {
                        this.style.display = 'none';
                        if (denunciadoInput) {
                            denunciadoInput.style.display = '';
                            denunciadoInput.required = true;
                            denunciadoInput.focus();
                        }
                    }
                });
            }

            if (processoInput) {
                processoInput.addEventListener('input', function () {
                    var digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        showDenunciadoInput();
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
                                window.location.href = '/frame.php?page=guiasrules';
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
            var formatNome = function (value) {
                if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                    return TextFormatter.formatProperName(value, 255);
                }
                return value;
            };

            var processoInput = document.getElementById('filter-processo');
            var denunciadoInput = document.getElementById('filter-denunciado');
            var denunciadoSelect = document.getElementById('filter-denunciado-select');
            if (denunciadoInput) {
                denunciadoInput.addEventListener('blur', function () {
                    denunciadoInput.value = formatNome(denunciadoInput.value);
                });
            }

            if (!btn) return;

            var classeCodigoField = document.getElementById('hidden-classe-codigo');
            var classeDescricaoField = document.getElementById('hidden-classe-descricao');
            var filterClasse = document.getElementById('filter-classe');

            function getDenunciadoPayload() {
                var useSelect = denunciadoSelect && denunciadoSelect.style.display !== 'none' && denunciadoSelect.value !== '' && denunciadoSelect.value !== 'outro';
                if (useSelect) {
                    var opt = denunciadoSelect.options[denunciadoSelect.selectedIndex];
                    return {
                        reu_id: denunciadoSelect.value,
                        denunciado: opt ? opt.textContent : ''
                    };
                }
                return {
                    reu_id: '',
                    denunciado: formatNome(getValue('filter-denunciado'))
                };
            }

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.disabled) return;
                if (processoInput) processoInput.blur();
                if (denunciadoInput) denunciadoInput.blur();

                var numeroProcesso = getValue('filter-processo');
                var denunciadoPayload = getDenunciadoPayload();
                var denunciado = denunciadoPayload.denunciado;
                var tipo = getValue('filter-tipo') || 'Guia - Tipo não informado';
                var regime = getValue('filter-regime');
                var dataFato = getValue('filter-data-fato');
                var diasMulta = document.getElementById('filter-dias-multa');
                var diasMultaVal = diasMulta && diasMulta.value !== '' ? parseInt(diasMulta.value, 10) : null;

                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo.', 'error');
                }
                if (typeof window.validarCNJ === 'function') {
                    var digits = (numeroProcesso || '').replace(/\D/g, '');
                    if (digits.length === 20 && !window.validarCNJ(numeroProcesso)) {
                        return showFeedback('O número do processo é inválido. Verifique os dígitos e o dígito verificador.', 'error');
                    }
                }
                var classeVal = getValue('filter-classe');
                if (classeVal === 'Aguarde...') {
                    return showFeedback('Aguarde a consulta ao DataJud terminar antes de cadastrar.', 'error');
                }
                if (dataFato) {
                    var hoje = new Date();
                    hoje.setHours(0, 0, 0, 0);
                    var dtFato = new Date(dataFato);
                    if (dtFato >= hoje) {
                        return showFeedback('A data do fato deve ser anterior à data de hoje.', 'error');
                    }
                }
                if (regime && ['Aberto', 'Semiaberto', 'Fechado'].indexOf(regime) === -1) {
                    return showFeedback('Regime inválido. Selecione Aberto, Semiaberto ou Fechado.', 'error');
                }
                if (!denunciado || denunciado === '') {
                    return showFeedback('Informe o denunciado/réu (selecione na lista ou digite em "Outro").', 'error');
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Enviando dados...' });
                else showFeedback('Enviando dados...', 'success');

                var payload = {
                    numero_processo: numeroProcesso,
                    denunciado: denunciado,
                    tipo: tipo
                };
                if (denunciadoPayload.reu_id) {
                    payload.reu_id = denunciadoPayload.reu_id;
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
                        payload.classe_codigo = classeVal.replace(/\s+/g, '').substring(0, 20);
                    }
                }
                if (regime) payload.regime = regime;
                if (dataFato) payload.data_fato = dataFato;
                if (diasMultaVal !== null && !isNaN(diasMultaVal)) payload.dias_multa = diasMultaVal;

                // #region agent log
                fetch('http://127.0.0.1:7304/ingest/df211406-901f-4e15-ab75-9fa46f53cfac',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'64730a'},body:JSON.stringify({sessionId:'64730a',location:'cartorio-guias-create.php:beforeFetch',message:'Guias create submit',data:{payloadKeys:Object.keys(payload),hasClasseCodigo:!!payload.classe_codigo,hasDenunciado:!!payload.denunciado},timestamp:Date.now(),hypothesisId:'A,B'})}).catch(function(){});
                // #endregion

                fetch('/api/cartorio-guias/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    // #region agent log
                    fetch('http://127.0.0.1:7304/ingest/df211406-901f-4e15-ab75-9fa46f53cfac',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'64730a'},body:JSON.stringify({sessionId:'64730a',location:'cartorio-guias-create.php:then',message:'Guias create response',data:{hasId:!!data.id,hasError:!!data.error,errorMsg:data.error||null},timestamp:Date.now(),hypothesisId:'E'})}).catch(function(){});
                    // #endregion
                    if (data.id) {
                        if (typeof showToast === 'function') {
                            if (data.valor_multa_calculado) {
                                showToast({ type: 'success', variant: 'full', title: 'Cálculo automático', message: 'Cálculo realizado automaticamente pelo sistema. Valor da pena de multa: ' + data.valor_multa_calculado + '.' });
                            } else {
                                showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Guia cadastrada com sucesso.' });
                            }
                        }
                        window.location.href = '/frame.php?page=guias';
                    } else {
                        showFeedback(data.error || 'Não foi possível cadastrar.', 'error');
                        btn.disabled = false;
                    }
                })
                .catch(function (err) {
                    // #region agent log
                    fetch('http://127.0.0.1:7304/ingest/df211406-901f-4e15-ab75-9fa46f53cfac',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'64730a'},body:JSON.stringify({sessionId:'64730a',location:'cartorio-guias-create.php:catch',message:'Guias create fetch failed',data:{errMsg:err&&err.message||String(err)},timestamp:Date.now(),hypothesisId:'B'})}).catch(function(){});
                    // #endregion
                    showFeedback('Erro de conexão.', 'error');
                    btn.disabled = false;
                });
            });
        })();
    </script>
</body>
</html>
