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
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Medidas Protetivas - Cadastro - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <?php include __DIR__ . '/_toast_script.php'; ?>
        <script src="/assets/js/shared/cpf.js"></script>
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

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Informações obrigatórias</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <label for="filter-processo">Processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-200 height-md" id="filter-processo" placeholder="0000000-00.0000.0.00.0000" required>
            </div>
            <div class="col filter">
                <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual <span class="label-hint-automatizado">*automatizado</span></label>
                <input type="text" class="width-400 height-md" id="filter-classe" placeholder="Preenchido automaticamente pelo DataJud" readonly>
            </div>
        </div>
        <input type="hidden" id="hidden-classe-codigo" name="classe_codigo">
        <input type="hidden" id="hidden-classe-descricao" name="classe_descricao">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Réu</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="reu-row-principal">
            <div class="col filter">
                <label for="reu-select">Réu / Denunciado <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-600 height-md" id="reu-select" style="display: none;" aria-label="Réus vinculados ao processo ou sugestões por nome">
                    <!-- preenchido via JS: réus do processo ou sugestões (nome + CPF) + Outro / Cadastrar novo -->
                </select>
                <input type="text" class="width-600 height-md" id="reu-nome" placeholder="Nome do réu (digite para buscar ou cadastrar)" maxlength="255" aria-label="Nome do réu quando não há réus ou opção Outro">
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" id="reu-campos-complementares" style="display: none;">
            <div class="col filter">
                <label for="reu-cpf">CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-200 height-md" id="reu-cpf" placeholder="Somente números">
            </div>
            <div class="col filter">
                <label for="reu-sexo">Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md" id="reu-sexo">
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="col filter">
                <label for="reu-data-nascimento">Data de nascimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-200 height-md" id="reu-data-nascimento">
            </div>
        </div>

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start">
                <h2>Vítimas</h2>
            </div>
            <div class="display-flex row row-fit justify-end gap-y-md"></div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col filter">
                <button class="btn btn-secondary-outline" id="add-vitima-btn" type="button">Adicionar vítima</button>
            </div>
        </div>
        <div id="vitimas-container"></div>

        <div class="row info padding-tb-md">
            <span>*Conforme regra de alocação, as medidas protetivas serão alocadas automaticamente para <strong style="margin-left: 3px"><?php echo htmlspecialchars($ruleServidor, ENT_QUOTES); ?></strong></span>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
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
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2><span class="vitima-card-index">Vítima</span></h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md vitima-row-principal">
                <div class="col filter">
                    <label>Vítima (nome)</label>
                    <select class="width-600 height-md vitima-select" style="display: none;" aria-label="Sugestões por nome"></select>
                    <input type="text" class="width-600 height-md vitima-nome" placeholder="Digite o nome para buscar ou cadastrar" maxlength="255">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md vitima-campos-complementares" style="display: none;">
                <div class="col filter">
                    <label>CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-200 height-md vitima-cpf" placeholder="Somente números">
                </div>
                <div class="col filter">
                    <label>Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-200 height-md vitima-sexo">
                        <option value="">Selecione</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>
                <div class="col filter">
                    <label>Data de nascimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-200 height-md vitima-data-nascimento">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
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
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
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
                    if (typeof window.validarCNJ === 'function' && !window.validarCNJ(digits)) {
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
            var processoInput = document.getElementById('filter-processo');
            var reuSelect = document.getElementById('reu-select');
            var reuNome = document.getElementById('reu-nome');
            var reuComplementares = document.getElementById('reu-campos-complementares');
            var lastReuSuggestionNome = '';

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
                if (reuComplementares) reuComplementares.style.display = 'none';
            }

            function showReuSelect(reus) {
                if (!reuSelect || !reuNome) return;
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
                reuNome.style.display = 'none';
                reuNome.required = false;
                reuNome.value = '';
                if (reuComplementares) reuComplementares.style.display = 'none';
            }

            function formatCpfDisplay(cpf) {
                var s = (cpf || '').replace(/\D/g, '');
                if (s.length !== 11) return cpf || '';
                return s.slice(0, 3) + '.' + s.slice(3, 6) + '.' + s.slice(6, 9) + '-' + s.slice(9);
            }

            function showReuSelectSugestoes(pessoas, nomeDigitado) {
                if (!reuSelect || !reuNome) return;
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
                lastReuSuggestionNome = nomeDigitado || '';
                reuSelect.style.display = '';
                reuNome.style.display = 'none';
                reuNome.required = false;
                if (reuComplementares) reuComplementares.style.display = 'none';
            }

            function carregarReusDoProcesso() {
                var digits = (processoInput && processoInput.value || '').replace(/\D/g, '');
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
                .catch(function () { showReuInput(); });
            }

            if (processoInput) {
                processoInput.addEventListener('blur', function () {
                    carregarReusDoProcesso();
                });
                processoInput.addEventListener('input', function () {
                    if ((processoInput.value || '').replace(/\D/g, '').length !== 20) {
                        showReuInput();
                    }
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
                            reuNome.focus();
                        }
                        if (reuComplementares) reuComplementares.style.display = 'none';
                    } else if (this.value === '__novo__') {
                        reuSelect.style.display = 'none';
                        reuSelect.innerHTML = '';
                        reuSelect.value = '';
                        if (reuNome) {
                            reuNome.value = lastReuSuggestionNome;
                            reuNome.style.display = '';
                            reuNome.required = true;
                            reuNome.focus();
                        }
                        if (reuComplementares) reuComplementares.style.display = '';
                    } else if (this.value !== '') {
                        if (reuComplementares) reuComplementares.style.display = 'none';
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
                    if (nome.length < 2) return;
                    if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') return;
                    fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var pessoas = (data && data.pessoas) ? data.pessoas : [];
                            if (pessoas.length > 0) {
                                showReuSelectSugestoes(pessoas, nome);
                            } else {
                                if (reuComplementares) reuComplementares.style.display = '';
                            }
                        })
                        .catch(function () {
                            if (reuComplementares) reuComplementares.style.display = '';
                        });
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

            function formatCpfDisplay(cpf) {
                var s = (cpf || '').replace(/\D/g, '');
                if (s.length !== 11) return cpf || '';
                return s.slice(0, 3) + '.' + s.slice(3, 6) + '.' + s.slice(6, 9) + '-' + s.slice(9);
            }

            function updateVitimaTitles() {
                Array.from(container.querySelectorAll('.vitima-card')).forEach(function (card, index) {
                    var title = card.querySelector('.vitima-card-index');
                    if (title) {
                        title.textContent = 'Vítima ' + (index + 1);
                    }
                });
            }

            function showVitimaComplementares(card, show) {
                var comp = card.querySelector('.vitima-campos-complementares');
                if (comp) comp.style.display = show ? '' : 'none';
            }

            function attachCardEvents(card) {
                var nomeInput = card.querySelector('.vitima-nome');
                var selectEl = card.querySelector('.vitima-select');
                var lastVitimaSuggestionNome = '';
                formatNome(nomeInput);

                function showVitimaInput() {
                    if (selectEl) {
                        selectEl.style.display = 'none';
                        selectEl.innerHTML = '';
                        selectEl.value = '';
                    }
                    if (nomeInput) nomeInput.style.display = '';
                    showVitimaComplementares(card, false);
                }

                function showVitimaSelectSugestoes(pessoas, nomeDigitado) {
                    if (!selectEl || !nomeInput) return;
                    selectEl.innerHTML = '';
                    pessoas.forEach(function (p) {
                        var opt = document.createElement('option');
                        opt.value = p.id;
                        var label = (p.nome_completo || '').trim();
                        if ((p.cpf || '').replace(/\D/g, '').length === 11) {
                            label += ' - ' + formatCpfDisplay(p.cpf);
                        }
                        opt.textContent = label || p.id;
                        selectEl.appendChild(opt);
                    });
                    var novo = document.createElement('option');
                    novo.value = '__novo__';
                    novo.textContent = 'Cadastrar novo: ' + (nomeDigitado || '');
                    selectEl.appendChild(novo);
                    lastVitimaSuggestionNome = nomeDigitado || '';
                    selectEl.style.display = '';
                    nomeInput.style.display = 'none';
                    showVitimaComplementares(card, false);
                }

                if (nomeInput) {
                    nomeInput.addEventListener('blur', function () {
                        var nome = (nomeInput.value || '').trim();
                        if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                            nome = TextFormatter.formatProperName(nome, 255);
                            nomeInput.value = nome;
                        }
                        if (nome.length < 2) return;
                        if (selectEl && selectEl.style.display !== 'none' && selectEl.value !== '' && selectEl.value !== '__novo__') return;
                        fetch('/api/pessoas/search-by-name.php?nome=' + encodeURIComponent(nome) + '&limit=20', { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                var pessoas = (data && data.pessoas) ? data.pessoas : [];
                                if (pessoas.length > 0) {
                                    showVitimaSelectSugestoes(pessoas, nome);
                                } else {
                                    showVitimaComplementares(card, true);
                                }
                            })
                            .catch(function () { showVitimaComplementares(card, true); });
                    });
                }

                if (selectEl) {
                    selectEl.addEventListener('change', function () {
                        if (this.value === '__novo__') {
                            selectEl.style.display = 'none';
                            selectEl.innerHTML = '';
                            selectEl.value = '';
                            if (nomeInput) {
                                nomeInput.value = lastVitimaSuggestionNome;
                                nomeInput.style.display = '';
                                nomeInput.focus();
                            }
                            showVitimaComplementares(card, true);
                        } else if (this.value !== '') {
                            showVitimaComplementares(card, false);
                        }
                    });
                }

                var cpfInput = card.querySelector('.vitima-cpf');
                if (cpfInput && typeof App !== 'undefined' && App.CPF && App.CPF.init) {
                    var uid = 'vitima-cpf-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
                    cpfInput.id = uid;
                    App.CPF.init('#' + uid);
                }

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

            if (typeof App !== 'undefined' && App.CPF && App.CPF.init) {
                App.CPF.init('#reu-cpf');
            }

            addBtn?.addEventListener('click', function () {
                addVitima();
            });

            addVitima();
        })();
    </script>
    <script>
        (function () {
            function idadeEmAnos(dataStr) {
                if (!dataStr || dataStr.length < 10) return null;
                var d = new Date(dataStr);
                if (isNaN(d.getTime())) return null;
                var hoje = new Date();
                var anos = hoje.getFullYear() - d.getFullYear();
                var m = hoje.getMonth() - d.getMonth();
                if (m < 0 || (m === 0 && hoje.getDate() < d.getDate())) anos--;
                return anos;
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('vitima-data-nascimento')) {
                    var card = e.target.closest('.vitima-card');
                    if (card) {
                        var anos = idadeEmAnos(e.target.value);
                        var check = card.querySelector('.vitima-crianca');
                        if (check) {
                            if (anos !== null && anos < 18) {
                                check.checked = true;
                                check.disabled = true;
                            } else {
                                check.disabled = false;
                            }
                        }
                    }
                }
                if (e.target && e.target.classList && e.target.classList.contains('vitima-sexo')) {
                    var card = e.target.closest('.vitima-card');
                    if (card) {
                        var check = card.querySelector('.vitima-mulher');
                        if (check) {
                            if (e.target.value === 'Feminino') {
                                check.checked = true;
                                check.disabled = true;
                            } else {
                                check.disabled = false;
                            }
                        }
                    }
                }
            });
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

            var reuSelect = document.getElementById('reu-select');
            var reuNome = document.getElementById('reu-nome');

            function collectReu() {
                var useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
                if (useSelect) {
                    return { parte_id: reuSelect.value };
                }
                var nomeVal = (reuNome && reuNome.value || '').trim();
                if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                    nomeVal = TextFormatter.formatProperName(nomeVal, 255);
                }
                return {
                    parte_id: '',
                    cpf: onlyDigits(document.getElementById('reu-cpf')?.value || ''),
                    nome_completo: nomeVal,
                    sexo: (document.getElementById('reu-sexo')?.value || '').trim(),
                    data_nascimento: (document.getElementById('reu-data-nascimento')?.value || '').trim()
                };
            }

            function collectVitimas() {
                return Array.from(document.querySelectorAll('.vitima-card')).map(function (card) {
                    var selectEl = card.querySelector('.vitima-select');
                    var nomeInput = card.querySelector('.vitima-nome');
                    var useSelect = selectEl && selectEl.style.display !== 'none' && selectEl.value !== '' && selectEl.value !== '__novo__';
                    var nomeVal = (nomeInput && nomeInput.value || '').trim();
                    if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                        nomeVal = TextFormatter.formatProperName(nomeVal, 255);
                    }
                    var base = {
                        mulher: !!card.querySelector('.vitima-mulher')?.checked,
                        crianca_adolescente: !!card.querySelector('.vitima-crianca')?.checked,
                        idoso: !!card.querySelector('.vitima-idoso')?.checked,
                        pessoa_deficiencia: !!card.querySelector('.vitima-pcd')?.checked
                    };
                    if (useSelect) {
                        return Object.assign({ parte_id: selectEl.value, cpf: '', nome_completo: '', sexo: '', data_nascimento: '' }, base);
                    }
                    return Object.assign({
                        parte_id: '',
                        cpf: onlyDigits(card.querySelector('.vitima-cpf')?.value || ''),
                        nome_completo: nomeVal,
                        sexo: (card.querySelector('.vitima-sexo')?.value || '').trim(),
                        data_nascimento: (card.querySelector('.vitima-data-nascimento')?.value || '').trim()
                    }, base);
                }).filter(function (vitima) {
                    return vitima.parte_id || vitima.cpf || vitima.nome_completo || vitima.sexo || vitima.data_nascimento || vitima.mulher || vitima.crianca_adolescente || vitima.idoso || vitima.pessoa_deficiencia;
                });
            }

            function validateReu(reu) {
                if (reu.parte_id) return '';
                if (!reu.cpf || !reu.nome_completo || !reu.sexo || !reu.data_nascimento) {
                    return 'Preencha CPF, nome completo, sexo e data de nascimento do réu (ou selecione um réu existente).';
                }
                return '';
            }

            function validateVitimas(vitimas) {
                for (var i = 0; i < vitimas.length; i += 1) {
                    var vitima = vitimas[i];
                    if (vitima.parte_id) continue;
                    if (!vitima.cpf || !vitima.nome_completo || !vitima.sexo || !vitima.data_nascimento) {
                        return 'Preencha CPF, nome completo, sexo e data de nascimento para cada vítima informada (ou selecione uma pessoa existente).';
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

                var reu = collectReu();
                var reuError = validateReu(reu);
                if (reuError) {
                    var reuComp = document.getElementById('reu-campos-complementares');
                    if (reuComp && reuComp.style.display === 'none' && reuNome && (reuNome.value || '').trim().length >= 2) {
                        reuComp.style.display = '';
                        document.getElementById('reu-cpf')?.focus();
                    }
                    showFeedback(reuError, 'error');
                    return;
                }

                if (!reu.parte_id && reu.cpf && reu.cpf.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(reu.cpf)) {
                    showFeedback('CPF do réu inválido. Verifique os dígitos.', 'error');
                    document.getElementById('reu-cpf')?.focus();
                    return;
                }

                var vitimas = collectVitimas();
                var vitimasError = validateVitimas(vitimas);
                if (vitimasError) {
                    showFeedback(vitimasError, 'error');
                    return;
                }

                for (var vi = 0; vi < vitimas.length; vi++) {
                    var v = vitimas[vi];
                    if (!v.parte_id && v.cpf && v.cpf.length === 11 && typeof App !== 'undefined' && App.CPF && !App.CPF.validar(v.cpf)) {
                        showFeedback('CPF inválido na vítima ' + (vi + 1) + '. Verifique os dígitos.', 'error');
                        return;
                    }
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
