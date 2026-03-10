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
            <span class="all">Interno - Medidas Protetivas -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Cadastro de Medida Protetiva</h1>
        <p class="description-page">Registre o processo, identifique o réu, relacione as vítimas e prepare automaticamente o vínculo posterior com o fluxo do Cejusc.</p>
    </div>

    <div class="card-page">
        <div class="row table header">Informações obrigatórias</div>
        <div class="row filter">
            <div class="col filter">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-md height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-lg height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="row table header">Réu</div>
        <div class="row filter">
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

        <div class="row table header">Vítimas</div>
        <div class="row filter">
            <div class="col filter">
                <button class="btn btn-secondary-outline" id="add-vitima-btn" type="button">Adicionar vítima</button>
            </div>
        </div>
        <div id="vitimas-container"></div>

        <div class="row info">
            <span>*Conforme regra de alocação, as medidas protetivas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
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

    <template id="vitima-template">
        <div class="vitima-card">
            <div class="row table header"><span class="vitima-card-index">Vítima</span></div>
            <div class="row filter">
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
            <div class="row filter">
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-mulher"> Mulher</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-crianca"> Criança/Adolescente</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-idoso"> Idoso</label>
                </div>
                <div class="col filter">
                    <label><input type="checkbox" class="vitima-pcd"> Pessoa com deficiência</label>
                </div>
            </div>
            <div class="row filter">
                <div class="col filter">
                    <button class="btn btn-secondary-outline btn-remover-vitima" type="button">Remover</button>
                </div>
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
