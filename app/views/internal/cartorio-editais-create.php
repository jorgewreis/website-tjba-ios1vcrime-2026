<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '?';
$ruleName = 'ALLOCATE-EDITAIS';
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
        <title>Editais - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Editais -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Edital</h1>
        <p class="description-page">Registre o edital vinculado ao processo para acompanhar expedição, envio ao DJEN, certificação da publicação e decurso do prazo.</p>
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
                <label for="filter-classe" id="label-classe">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-600 height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-tipo-edital">Tipo de edital <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-500 height-md" id="filter-tipo-edital" required>
                    <option value="">Selecione</option>
                    <option value="Citação">Citação</option>
                    <option value="Intimação">Intimação</option>
                    <option value="Notificação">Notificação</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="filter-prazo-dias">Prazo do edital (dias) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="number" class="width-300 height-md" id="filter-prazo-dias" min="1" placeholder="Ex.: 15" required title="Prazo em dias a partir da data de publicação">
            </div>
        </div>
        <div class="display-flex row row-full info padding-x-md padding-y-md">
            <span>*Conforme regra de alocação, os Editais serão alocados automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button class="btn btn-primary-outline" id="alterar-regra-btn" style="display: none;">Alterar regra de alocação</button>
            <button class="btn btn-secondary" id="voltar-btn">Voltar</button>
            <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
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
            window.location.href = '/frame.php?page=editais';
        }, { capture: true });
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script>
        (function () {
            var processoInput = document.getElementById('filter-processo');
            var classeInput = document.getElementById('filter-classe');
            var hCod = document.getElementById('hidden-classe-codigo');
            var hDesc = document.getElementById('hidden-classe-descricao');
            function limparClasse() {
                if (classeInput) classeInput.value = '';
                if (hCod) hCod.value = '';
                if (hDesc) hDesc.value = '';
            }
            function preencherClasse(codigo, descricao) {
                if (classeInput) classeInput.value = (codigo && descricao) ? (codigo + ' - ' + descricao) : (descricao || codigo || '');
                if (hCod) hCod.value = codigo || '';
                if (hDesc) hDesc.value = descricao || '';
            }
            if (processoInput) {
                processoInput.addEventListener('blur', function () {
                    var digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        limparClasse();
                        return;
                    }
                    fetch('/api/datajud/search-processo.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ numero: digits }),
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var d = data && data.data;
                        var classe = d && d.classe;
                        if (classe && (classe.codigo || classe.descricao)) {
                            preencherClasse(classe.codigo || '', classe.descricao || '');
                        } else {
                            limparClasse();
                        }
                    })
                    .catch(function () { limparClasse(); });
                });
                processoInput.addEventListener('input', function () {
                    if ((this.value || '').replace(/\D/g, '').length !== 20) limparClasse();
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
                                window.location.href = '/frame.php?page=editaisrules';
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
            var showFeedback = function (message, type) {
                if (typeof showToast === 'function') {
                    showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message: message });
                }
            };

            function onlyDigits(s) {
                return (s || '').replace(/\D/g, '');
            }

            btn.addEventListener('click', function () {
                var processoInput = document.getElementById('filter-processo');
                var processo = (processoInput?.value || '').trim();
                if (!processo) {
                    showFeedback('O número do processo é obrigatório.', 'error');
                    return;
                }
                if (processoInput && typeof window.normalizeAndValidateCNJValue === 'function') {
                    var normalized = window.normalizeAndValidateCNJValue(processo, { toastOnInvalid: true });
                    if (!normalized.ok) {
                        processoInput.value = '';
                        processoInput.focus();
                        return;
                    }
                    processoInput.value = normalized.formatted;
                    processo = normalized.formatted;
                } else {
                    var digits = onlyDigits(processo);
                    if (digits.length !== 20) {
                        showFeedback('O número do processo está incompleto ou inválido (20 dígitos).', 'error');
                        return;
                    }
                }
                var tipoEdital = (document.getElementById('filter-tipo-edital')?.value || '').trim();
                if (!tipoEdital) {
                    showFeedback('O tipo de edital é obrigatório.', 'error');
                    return;
                }
                var prazoDias = parseInt(document.getElementById('filter-prazo-dias')?.value || '0', 10);
                if (isNaN(prazoDias) || prazoDias < 1) {
                    showFeedback('O prazo do edital é obrigatório e deve ser de pelo menos 1 dia.', 'error');
                    return;
                }

                btn.disabled = true;
                var payload = {
                    processo: processo,
                    tipo_edital: tipoEdital,
                    prazo_dias: prazoDias
                };
                var hCod = document.getElementById('hidden-classe-codigo');
                var hDesc = document.getElementById('hidden-classe-descricao');
                if (hCod && hCod.value) payload.classe_codigo = hCod.value.trim();
                if (hDesc && hDesc.value) payload.classe_descricao = hDesc.value.trim();

                fetch('/api/cartorio-editais/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                })
                .then(function (r) {
                    if (!r.ok) return r.json().then(function (d) { throw new Error(d.error || 'Erro ao criar'); });
                    return r.json();
                })
                .then(function (data) {
                    var id = data && data.id;
                    if (!id) {
                        btn.disabled = false;
                        showFeedback('Resposta inválida do servidor.', 'error');
                        return;
                    }
                    return fetch('/api/cartorio-editais/criar-primeira-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ edital_id: id }),
                        credentials: 'same-origin'
                    })
                    .then(function (r2) {
                        if (!r2.ok) return r2.json().then(function (d) { throw new Error(d.error || 'Erro ao criar subtarefas'); });
                        return id;
                    });
                })
                .then(function (id) {
                    if (typeof showToast === 'function') {
                        showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Edital cadastrado.' });
                    }
                    window.location.href = '/frame.php?page=editaisedit&id=' + encodeURIComponent(id);
                })
                .catch(function (err) {
                    btn.disabled = false;
                    showFeedback(err && err.message ? err.message : 'Erro ao cadastrar.', 'error');
                });
            });
        })();
    </script>
</body>
</html>
