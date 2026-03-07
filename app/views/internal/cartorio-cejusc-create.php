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
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cejusc - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/variables.css">
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
                <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="Digite o número do processo" required>
            </div>
            <div class="col filter">
                <label for="filter-reu">Nome do réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-xl height-md" id="filter-reu" placeholder="Nome do denunciado/réu" maxlength="120" required>
            </div>
        </div>

        <div class="row info">
            <span>*Conforme regra de alocação, as tarefas Cejusc serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
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
    <!-- Padrão: número do processo = cnj.js (correção/formatação CNJ no blur); nome de pessoa/cidade = text-formatter.js (maiúsculas/minúsculas). -->
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
            if (reuInput) {
                reuInput.addEventListener('blur', function () {
                    reuInput.value = formatNomeReu(reuInput.value);
                });
            }

            if (!btn) return;

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (btn.disabled) return;
                // Dispara blur para aplicar correção automática (CNJ e nome) antes de ler os valores
                if (processoInput) processoInput.blur();
                if (reuInput) reuInput.blur();

                var numeroProcesso = getValue('filter-processo');
                var nomeReu = formatNomeReu(getValue('filter-reu'));

                if (numeroProcesso === '') {
                    return showFeedback('Informe o número do processo.', 'error');
                }
                if (nomeReu === '') {
                    return showFeedback('Informe o nome do réu.', 'error');
                }

                btn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'simple', message: 'Enviando dados...' });
                else showFeedback('Enviando dados...', 'success');

                fetch('/api/cartorio-cejusc/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        numero_processo_origem: numeroProcesso,
                        nome_reu: nomeReu
                    })
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.id) {
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
