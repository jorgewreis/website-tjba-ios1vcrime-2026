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
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Guias - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
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
                <label for="filter-processo">Número do processo</label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-denunciado">Denunciado</label>
                <input type="text" class="width-xl height-md" id="filter-denunciado" placeholder="Nome do denunciado/réu" maxlength="255">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-tipo">Tipo de guia</label>
                <select class="width-lg height-md" id="filter-tipo" required>
                    <option value="Guia - Tipo não informado" selected>Guia - Tipo não informado</option>
                    <option value="Guia de Execução Definitiva">Guia de Execução Definitiva</option>
                    <option value="Guia de Recolhimento Provisória">Guia de Recolhimento Provisória</option>
                    <option value="Guia de Recolhimento Definitiva">Guia de Recolhimento Definitiva</option>
                    <option value="Guia (corrigir)">Guia (corrigir)</option>
                    <option value="Guia (reenviar)">Guia (reenviar)</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-etapa">Etapa</label>
                <input type="text" class="width-md height-md" id="filter-etapa" placeholder="Opcional" maxlength="100">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-regime">Regime</label>
                <input type="text" class="width-md height-md" id="filter-regime" placeholder="Opcional" maxlength="100">
            </div>
            <div class="col filter">
                <label for="filter-data-fato">Data do fato</label>
                <input type="date" class="width-md height-md" id="filter-data-fato">
            </div>
            <div class="col filter">
                <label for="filter-dias-multa">Dias-multa</label>
                <input type="number" class="width-sm height-md" id="filter-dias-multa" min="0" placeholder="—">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-observacoes">Observações</label>
                <textarea class="width-xl height-md" id="filter-observacoes" rows="2" placeholder="Opcional" maxlength="2000"></textarea>
            </div>
        </div>

        <div class="row info">
            <span>*Conforme regra de alocação, as Guias serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
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
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
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
            if (denunciadoInput) {
                denunciadoInput.addEventListener('blur', function () {
                    denunciadoInput.value = formatNome(denunciadoInput.value);
                });
            }

            if (!btn) return;

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.disabled) return;
                if (processoInput) processoInput.blur();
                if (denunciadoInput) denunciadoInput.blur();

                var numeroProcesso = getValue('filter-processo');
                var denunciado = formatNome(getValue('filter-denunciado'));
                var tipo = getValue('filter-tipo') || 'Guia - Tipo não informado';
                var etapa = getValue('filter-etapa');
                var regime = getValue('filter-regime');
                var dataFato = getValue('filter-data-fato');
                var diasMulta = document.getElementById('filter-dias-multa');
                var diasMultaVal = diasMulta && diasMulta.value !== '' ? parseInt(diasMulta.value, 10) : null;
                var observacoes = getValue('filter-observacoes');

                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo.', 'error');
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Enviando dados...' });
                else showFeedback('Enviando dados...', 'success');

                var payload = {
                    numero_processo: numeroProcesso,
                    denunciado: denunciado,
                    tipo: tipo
                };
                if (etapa) payload.etapa = etapa;
                if (regime) payload.regime = regime;
                if (dataFato) payload.data_fato = dataFato;
                if (diasMultaVal !== null && !isNaN(diasMultaVal)) payload.dias_multa = diasMultaVal;
                if (observacoes) payload.observacoes = observacoes;

                fetch('/api/cartorio-guias/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.id) {
                        if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Guia cadastrada com sucesso.' });
                        window.location.href = '/frame.php?page=guias';
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
