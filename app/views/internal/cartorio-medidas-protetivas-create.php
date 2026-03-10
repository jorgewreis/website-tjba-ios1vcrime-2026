<?php
declare(strict_types=1);

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();

$ruleServidor = '?';
$ruleName = 'ALLOCATE-MEDIDASPROTETIVAS';
try {
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $allocatedUserId = $ruleRow['usuario_id'] ?? null;
    if ($allocatedUserId !== null && $allocatedUserId !== '') {
        $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = ? LIMIT 1');
        $stmt->execute([$allocatedUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user !== false) {
            $nome = trim((string) ($user['nome'] ?? ''));
            $sobrenome = trim((string) ($user['sobrenome'] ?? ''));
            $ruleServidor = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
            if ($ruleServidor === '') {
                $ruleServidor = '?';
            }
        }
    }
} catch (\Throwable $exception) {
    // Mantém o padrão visual.
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Medidas Protetivas - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            .create-section {
                width: 100%;
                margin-bottom: 1.5rem;
            }

            .create-section:last-of-type {
                margin-bottom: 0;
            }

            .section-subtitle {
                margin: 0.35rem 0 0.9rem 0;
                color: var(--color-text-secondary);
                font-size: 0.95rem;
                line-height: 1.5;
            }

            .summary-grid,
            .partes-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1rem;
                width: 100%;
            }

            .summary-card,
            .person-card,
            .allocation-card,
            .vitima-card {
                width: 100%;
                border: 1px solid var(--color-gray-400);
                border-radius: 12px;
                background: var(--color-background);
            }

            .summary-card,
            .person-card,
            .allocation-card {
                padding: 1rem;
            }

            .summary-card-title,
            .person-card-title,
            .allocation-card-title {
                display: block;
                margin-bottom: 0.35rem;
                font-size: 0.95rem;
                font-weight: 600;
                color: var(--color-text-primary);
            }

            .summary-card p,
            .allocation-card p {
                margin: 0;
                color: var(--color-text-secondary);
                line-height: 1.5;
                font-size: 0.92rem;
            }

            .person-card .partes-grid {
                gap: 0.85rem;
            }

            .section-toolbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
                margin-bottom: 0.9rem;
            }

            .section-toolbar .section-subtitle {
                margin: 0;
                flex: 1;
                min-width: 240px;
            }

            .vitima-card {
                padding: 1rem;
                margin-top: 1rem;
            }

            .vitima-card-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 0.75rem;
                flex-wrap: wrap;
            }

            .vitima-card-index {
                margin: 0;
                font-size: 1rem;
                font-weight: 600;
                color: var(--color-text-primary);
            }

            .checkbox-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.85rem 1.25rem;
                margin-top: 1rem;
                padding-top: 0.9rem;
                border-top: 1px solid var(--color-gray-300);
            }

            .checkbox-group label {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                font-size: 0.95rem;
                color: var(--color-text-secondary);
            }

            .allocation-card strong {
                color: var(--color-text-primary);
            }

            .actions-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                flex-wrap: wrap;
                margin-top: 0.5rem;
            }

            .actions-bar-left,
            .actions-bar-right {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                flex-wrap: wrap;
            }

            .field-hint {
                margin-top: 0.35rem;
                font-size: 0.82rem;
                color: var(--color-text-secondary);
            }

            @media (max-width: 768px) {
                .actions-bar {
                    align-items: stretch;
                }

                .actions-bar-left,
                .actions-bar-right {
                    width: 100%;
                }
            }
        </style>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Medida Protetiva</h1>
        <p class="description-page">Registre o processo, identifique o réu, relacione as vítimas e prepare automaticamente o vínculo posterior com o fluxo do Cejusc.</p>
    </div>

    <div class="card-page">
        <div class="create-section">
            <div class="summary-grid">
                <div class="summary-card">
                    <span class="summary-card-title">Cadastro inicial</span>
                    <p>Informe o processo e os dados do polo passivo para gerar a medida protetiva com estrutura padronizada no sistema.</p>
                </div>
                <div class="summary-card">
                    <span class="summary-card-title">Vítimas e prioridades</span>
                    <p>As vítimas podem ser incluídas com dados pessoais e marcadores de prioridade para facilitar o tratamento posterior.</p>
                </div>
                <div class="summary-card">
                    <span class="summary-card-title">Fluxo automático</span>
                    <p>Após o cadastro, a medida seguirá para as subtarefas internas e poderá originar o fluxo vinculado do Cejusc.</p>
                </div>
            </div>
        </div>

        <div class="create-section">
            <div class="row table header">Informações obrigatórias</div>
            <p class="section-subtitle">Comece pelo processo. A classe processual é preenchida automaticamente quando o número CNJ é validado.</p>
            <div class="row filter">
                <div class="col filter">
                    <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-md height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
                    <span class="field-hint">Use o número CNJ completo com 20 dígitos.</span>
                </div>
                <div class="col filter">
                    <label for="filter-classe" id="processo-ap-info">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" class="width-lg height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
                    <span class="field-hint">Campo apenas informativo, preenchido automaticamente quando o processo for localizado.</span>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">
        </div>

        <div class="create-section">
            <div class="row table header">Réu</div>
            <p class="section-subtitle">Preencha os dados principais da parte do polo passivo vinculada à medida protetiva.</p>
            <div class="person-card">
                <div class="person-card-title">Parte principal</div>
                <div class="partes-grid">
                    <div class="col filter">
                        <label for="reu-cpf">CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                        <input type="text" class="width-md height-md" id="reu-cpf" placeholder="Somente números">
                    </div>
                    <div class="col filter">
                        <label for="reu-nome">Nome completo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                        <input type="text" class="width-xl height-md" id="reu-nome" placeholder="Nome completo do réu">
                    </div>
                    <div class="col filter">
                        <label for="reu-sexo">Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                        <select class="width-md height-md" id="reu-sexo">
                            <option value="">Selecione</option>
                            <option value="Feminino">Feminino</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div class="col filter">
                        <label for="reu-data-nascimento">Data de nascimento</label>
                        <input type="date" class="width-md height-md" id="reu-data-nascimento">
                    </div>
                </div>
            </div>
        </div>

        <div class="create-section">
            <div class="row table header">Vítimas</div>
            <div class="section-toolbar">
                <p class="section-subtitle">Inclua uma ou mais vítimas quando necessário. Campos parcialmente preenchidos exigem CPF, nome completo e sexo.</p>
                <button class="btn btn-secondary-outline" id="add-vitima-btn" type="button">Adicionar vítima</button>
            </div>
            <div id="vitimas-container"></div>
        </div>

        <div class="create-section">
            <div class="allocation-card">
                <span class="allocation-card-title">Alocação automática</span>
                <p>Conforme a regra ativa do módulo, as medidas protetivas serão alocadas automaticamente para <strong><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong>.</p>
            </div>
        </div>

        <div class="create-section">
            <div class="actions-bar">
                <div class="actions-bar-left">
                    <button class="btn btn-primary-outline" id="alterar-regra-btn" type="button" style="display: none;">Alterar regra de alocação</button>
                </div>
                <div class="actions-bar-right">
                    <button class="btn btn-secondary" id="voltar-btn" type="button">Voltar</button>
                    <button class="btn btn-primary" id="cadastrar-btn" type="button">Cadastrar</button>
                </div>
            </div>
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

    <template id="vitima-template">
        <div class="vitima-card">
            <div class="vitima-card-header">
                <h3 class="vitima-card-index">Vítima</h3>
                <button class="btn btn-secondary-outline btn-remover-vitima" type="button">Remover</button>
            </div>
            <div class="partes-grid">
                <div class="col filter">
                    <label>CPF</label>
                    <input type="text" class="width-md height-md vitima-cpf" placeholder="Somente números">
                </div>
                <div class="col filter">
                    <label>Nome completo</label>
                    <input type="text" class="width-xl height-md vitima-nome" placeholder="Nome completo da vítima">
                </div>
                <div class="col filter">
                    <label>Sexo</label>
                    <select class="width-md height-md vitima-sexo">
                        <option value="">Selecione</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="col filter">
                    <label>Data de nascimento</label>
                    <input type="date" class="width-md height-md vitima-data-nascimento">
                </div>
            </div>
            <div class="checkbox-group">
                <label><input type="checkbox" class="vitima-mulher"> Mulher</label>
                <label><input type="checkbox" class="vitima-crianca"> Criança/Adolescente</label>
                <label><input type="checkbox" class="vitima-idoso"> Idoso</label>
                <label><input type="checkbox" class="vitima-pcd"> Pessoa com deficiência</label>
            </div>
        </div>
    </template>

    <script>
        document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/frame.php?page=medidasprotetivas';
        }, { capture: true });
    </script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/cnj.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/datajud-api.js"></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
    <script>
        (function () {
            var PERFIS_ALTERAR_REGRA = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

            function hasPermissionToChangeRule(perfis) {
                if (!Array.isArray(perfis) || perfis.length === 0) return false;
                var allowed = PERFIS_ALTERAR_REGRA.map(function (perfil) { return perfil.toUpperCase(); });
                return perfis.some(function (perfil) {
                    return allowed.indexOf(String(perfil || '').toUpperCase().trim()) !== -1;
                });
            }

            window.addEventListener('DOMContentLoaded', function () {
                var btn = document.getElementById('alterar-regra-btn');
                if (!btn) return;
                fetch('/admin/api/getPerfis.php', { credentials: 'same-origin' })
                    .then(function (response) { if (!response.ok) throw new Error(); return response.json(); })
                    .then(function (data) {
                        var perfis = (data && data.perfis) ? data.perfis : [];
                        if (hasPermissionToChangeRule(perfis)) {
                            btn.style.display = '';
                            btn.addEventListener('click', function () {
                                window.location.href = '/frame.php?page=medidasprotetivasrules';
                            });
                        }
                    })
                    .catch(function () {
                        btn.style.display = 'none';
                    });
            });
        })();
    </script>
    <script>
        (function () {
            var container = document.getElementById('vitimas-container');
            var template = document.getElementById('vitima-template');
            var addBtn = document.getElementById('add-vitima-btn');

            function onlyDigitsInput(input) {
                if (!input) return;
                input.addEventListener('input', function () {
                    input.value = (input.value || '').replace(/\D/g, '');
                });
            }

            function formatNome(input) {
                if (!input) return;
                input.addEventListener('blur', function () {
                    if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                        input.value = TextFormatter.formatProperName(input.value, 255);
                    }
                });
            }

            function updateVitimaTitles() {
                Array.from(container.querySelectorAll('.vitima-card')).forEach(function (card, index) {
                    var title = card.querySelector('.vitima-card-index');
                    if (title) {
                        title.textContent = 'Vítima ' + (index + 1);
                    }
                });
            }

            function attachCardEvents(card) {
                formatNome(card.querySelector('.vitima-nome'));
                onlyDigitsInput(card.querySelector('.vitima-cpf'));
                card.querySelector('.btn-remover-vitima')?.addEventListener('click', function () {
                    card.remove();
                    updateVitimaTitles();
                });
            }

            function addVitima() {
                if (!container || !template) return;
                var clone = template.content.firstElementChild.cloneNode(true);
                attachCardEvents(clone);
                container.appendChild(clone);
                updateVitimaTitles();
            }

            formatNome(document.getElementById('reu-nome'));
            onlyDigitsInput(document.getElementById('reu-cpf'));

            addBtn?.addEventListener('click', function () {
                addVitima();
            });

            addVitima();
        })();
    </script>
    <script>
        (function () {
            function showFeedback(message, type) {
                var feedback = document.getElementById('cadastro-feedback');
                if (typeof showToast === 'function') {
                    showToast({
                        type: type === 'error' ? 'error' : 'success',
                        variant: 'full',
                        title: type === 'error' ? 'Erro' : 'Sucesso',
                        message: message
                    });
                } else if (feedback) {
                    feedback.innerHTML = '<label class="status ' + (type === 'error' ? 'status-cancelled' : 'status-finished') + '">' + message + '</label>';
                }
            }

            function onlyDigits(value) {
                return (value || '').replace(/\D/g, '');
            }

            function collectParte(prefix) {
                return {
                    cpf: onlyDigits(document.getElementById(prefix + '-cpf')?.value || ''),
                    nome_completo: (document.getElementById(prefix + '-nome')?.value || '').trim(),
                    sexo: (document.getElementById(prefix + '-sexo')?.value || '').trim(),
                    data_nascimento: (document.getElementById(prefix + '-data-nascimento')?.value || '').trim()
                };
            }

            function collectVitimas() {
                return Array.from(document.querySelectorAll('.vitima-card')).map(function (card) {
                    return {
                        cpf: onlyDigits(card.querySelector('.vitima-cpf')?.value || ''),
                        nome_completo: (card.querySelector('.vitima-nome')?.value || '').trim(),
                        sexo: (card.querySelector('.vitima-sexo')?.value || '').trim(),
                        data_nascimento: (card.querySelector('.vitima-data-nascimento')?.value || '').trim(),
                        mulher: !!card.querySelector('.vitima-mulher')?.checked,
                        crianca_adolescente: !!card.querySelector('.vitima-crianca')?.checked,
                        idoso: !!card.querySelector('.vitima-idoso')?.checked,
                        pessoa_deficiencia: !!card.querySelector('.vitima-pcd')?.checked
                    };
                }).filter(function (vitima) {
                    return vitima.cpf || vitima.nome_completo || vitima.sexo || vitima.data_nascimento || vitima.mulher || vitima.crianca_adolescente || vitima.idoso || vitima.pessoa_deficiencia;
                });
            }

            function validateVitimas(vitimas) {
                for (var i = 0; i < vitimas.length; i += 1) {
                    var vitima = vitimas[i];
                    if (!vitima.cpf || !vitima.nome_completo || !vitima.sexo) {
                        return 'Preencha CPF, nome completo e sexo para cada vítima informada.';
                    }
                }
                return '';
            }

            var btn = document.getElementById('cadastrar-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                var processo = (document.getElementById('filter-processo')?.value || '').trim();
                if (!processo) {
                    showFeedback('O número do processo é obrigatório.', 'error');
                    return;
                }
                if (onlyDigits(processo).length !== 20) {
                    showFeedback('O número do processo está incompleto ou inválido (20 dígitos).', 'error');
                    return;
                }

                var reu = collectParte('reu');
                if (!reu.cpf || !reu.nome_completo || !reu.sexo) {
                    showFeedback('Preencha CPF, nome completo e sexo do réu.', 'error');
                    return;
                }

                var vitimas = collectVitimas();
                var vitimasError = validateVitimas(vitimas);
                if (vitimasError) {
                    showFeedback(vitimasError, 'error');
                    return;
                }

                var payload = {
                    processo: processo,
                    reu: reu,
                    vitimas: vitimas
                };

                var classeCodigoField = document.getElementById('hidden-classe-codigo');
                var classeDescricaoField = document.getElementById('hidden-classe-descricao');
                if (classeCodigoField && classeCodigoField.value.trim()) {
                    payload.classe_codigo = classeCodigoField.value.trim();
                }
                if (classeDescricaoField && classeDescricaoField.value.trim()) {
                    payload.classe_descricao = classeDescricaoField.value.trim();
                }

                btn.disabled = true;
                fetch('/api/cartorio-medidas-protetivas/create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (body) {
                            throw new Error(body.error || 'Erro ao criar');
                        });
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data.id) {
                        throw new Error('Resposta inválida do servidor.');
                    }
                    return fetch('/api/cartorio-medidas-protetivas/criar-primeira-subtarefa.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ medida_id: data.id }),
                        credentials: 'same-origin'
                    }).then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (body) {
                                throw new Error(body.error || 'Erro ao criar subtarefas');
                            });
                        }
                        return data.id;
                    });
                })
                .then(function (id) {
                    if (typeof showToast === 'function') {
                        showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: 'Medida protetiva cadastrada.' });
                    }
                    window.location.href = '/frame.php?page=medidasprotetivasedit&id=' + encodeURIComponent(id);
                })
                .catch(function (error) {
                    btn.disabled = false;
                    showFeedback(error && error.message ? error.message : 'Erro ao cadastrar medida protetiva.', 'error');
                });
            });
        })();
    </script>
</body>
</html>
