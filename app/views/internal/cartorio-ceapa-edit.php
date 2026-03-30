<?php
declare(strict_types=1);

require_once __DIR__ . '/../../services/CeapaService.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ClassesRepository.php';

$connection = Conection::getInstance()->getConnection();

$idTarefa = trim((string) ($_GET['id'] ?? ''));
$errorMessage = '';
$tarefa = null;

if ($idTarefa === '') {
    $errorMessage = 'Identificador do registro CEAPA não informado.';
} else {
    try {
        $service = new CeapaService($connection);
        $tarefa = $service->find($idTarefa);
        if ($tarefa === null) {
            $errorMessage = 'Registro CEAPA não encontrado.';
        }
    } catch (Throwable $exception) {
        $errorMessage = 'Erro ao carregar o registro: ' . $exception->getMessage();
    }
}

$processoNumero = '';
$classeCodigo = '';
$classeNome = 'Classe não informada';
$reuNome = '';
$tipoSuspensao = '';
$decisaoCumprir = '';
$dataDecisao = '';
$observacoesCumprimento = '';
$statusCumprimento = 'pendente';

if ($tarefa !== null) {
    $processoNumero = trim((string) ($tarefa['numero_processo_origem'] ?? ''));
    $classeCodigo = trim((string) ($tarefa['classe_codigo'] ?? ''));
    $classeNome = trim((string) ($tarefa['classe_nome'] ?? 'Classe não informada'));
    $reuNome = trim((string) ($tarefa['reu_nome'] ?? ''));
    $tipoSuspensao = trim((string) ($tarefa['tipo_suspensao'] ?? ''));
    $decisaoCumprir = trim((string) ($tarefa['decisao_cumprir'] ?? ''));
    $dataDecisao = trim((string) ($tarefa['data_decisao'] ?? ''));
    $observacoesCumprimento = trim((string) ($tarefa['observacoes_cumprimento'] ?? ''));
    $statusCumprimento = trim((string) ($tarefa['status_cumprimento'] ?? 'pendente'));
    if ($statusCumprimento === '') {
        $statusCumprimento = 'pendente';
    }

    if ($classeNome === '' || $classeNome === 'Classe não informada') {
        $processoId = trim((string) ($tarefa['processo_origem_id'] ?? ''));
        if ($processoId !== '') {
            $processosRepo = new ProcessosRepository($connection);
            $processo = $processosRepo->findById($processoId);
            $classeId = trim((string) ($processo['classe_id'] ?? ''));
            if ($classeId !== '') {
                $classesRepo = new ClassesRepository($connection);
                $classe = $classesRepo->findById($classeId);
                if ($classe !== null) {
                    $classeNome = trim((string) ($classe['nome'] ?? 'Classe não informada'));
                    if ($classeNome === '') {
                        $classeNome = 'Classe não informada';
                    }
                    $classeCodigo = trim((string) ($classe['codigo'] ?? $classeCodigo));
                }
            }
        }
    }
}

$lockProcesso = trim($processoNumero) !== '';
$lockClasse = trim($classeNome) !== '' && $classeNome !== 'Classe não informada';
$userPerfisEdit = [];
$userIdEdit = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($userIdEdit !== '') {
    $stmtPerfis = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmtPerfis->execute([$userIdEdit]);
    $userPerfisEdit = $stmtPerfis->fetchAll(PDO::FETCH_COLUMN);
}
$isEstagiarioEdit = in_array('ESTAGIARIO', $userPerfisEdit, true);
$isCriadorTarefa = $tarefa !== null && trim((string) ($tarefa['usuario_cadastro_id'] ?? '')) === $userIdEdit;
$podeExcluirTarefa = !$isEstagiarioEdit || $isCriadorTarefa;
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Edição CEAPA - Primeira Vara Crime de Ilhéus</title>
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
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar cadastro Ceapa</h1>
        <p class="description-page">Atualize o andamento de cumprimento da decisão e observações relacionadas ao acompanhamento.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($errorMessage !== ''): ?>
            <script>
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Erro', message: <?php echo json_encode($errorMessage, JSON_UNESCAPED_UNICODE); ?> });
                }
            </script>
        <?php else: ?>
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Dados do cadastro</h2>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-processo">Número do processo</label>
                    <input type="text" class="width-400 height-md<?php echo $lockProcesso ? ' readonly' : ''; ?>" id="filter-processo" value="<?php echo htmlspecialchars($processoNumero, ENT_QUOTES); ?>" placeholder="0000000-00.0000.0.00.0000" <?php echo $lockProcesso ? 'readonly' : ''; ?>>
                </div>
                <div class="col filter">
                    <label for="filter-classe" id="processo-ap-info" aria-live="polite">Classe processual</label>
                    <input type="text" class="width-600 height-md<?php echo $lockClasse ? ' readonly' : ''; ?>" id="filter-classe" data-datajud-related="true" value="<?php echo htmlspecialchars($lockClasse ? $classeNome : '', ENT_QUOTES); ?>" placeholder="Preenchido automaticamente pelo DataJud" <?php echo $lockClasse ? 'readonly' : ''; ?>>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo" value="<?php echo htmlspecialchars($classeCodigo, ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao" value="<?php echo htmlspecialchars($classeNome !== 'Classe não informada' ? $classeNome : '', ENT_QUOTES); ?>">

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-reu">Nome do réu</label>
                    <select class="width-800 height-md" id="filter-reu-select" style="display: none;" aria-label="Réus vinculados ao processo"></select>
                    <input type="text" class="width-800 height-md" id="filter-reu" value="<?php echo htmlspecialchars($reuNome, ENT_QUOTES); ?>" placeholder="Digite o nome completo" maxlength="120" aria-label="Nome do réu quando não há réus ou opção Outro">
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-tipo-suspensao">Tipo de suspensão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-600 height-md" id="filter-tipo-suspensao" required>
                        <option value="" <?php echo $tipoSuspensao === '' ? 'selected' : ''; ?> disabled>Selecione aqui</option>
                        <option value="Acordo de não Persecução Penal"<?php echo $tipoSuspensao === 'Acordo de não Persecução Penal' ? ' selected' : ''; ?>>Acordo de não Persecução Penal</option>
                        <option value="Suspensão Condicional do Processo"<?php echo $tipoSuspensao === 'Suspensão Condicional do Processo' ? ' selected' : ''; ?>>Suspensão Condicional do Processo</option>
                    </select>
                </div>
                <div class="col filter">
                    <label for="filter-data-decisao">Data da decisão <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" class="width-300 height-md" id="filter-data-decisao" value="<?php echo htmlspecialchars($dataDecisao, ENT_QUOTES); ?>" required>
                </div>
                <div class="col filter">
                    <label for="filter-status-cumprimento">Status de cumprimento <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select class="width-400 height-md" id="filter-status-cumprimento" required>
                        <option value="pendente"<?php echo $statusCumprimento === 'pendente' ? ' selected' : ''; ?>>Pendente</option>
                        <option value="em_andamento"<?php echo $statusCumprimento === 'em_andamento' ? ' selected' : ''; ?>>Em andamento</option>
                        <option value="finalizada"<?php echo $statusCumprimento === 'finalizada' ? ' selected' : ''; ?>>Finalizada</option>
                    </select>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-decisao-cumprir">Decisão a cumprir <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" class="width-800 height-md" id="filter-decisao-cumprir" value="<?php echo htmlspecialchars($decisaoCumprir, ENT_QUOTES); ?>" placeholder="Informe a pena definida na decisão" maxlength="500" required>
                </div>
            </div>

            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col filter">
                    <label for="filter-observacoes-cumprimento">Observações acerca do cumprimento da decisão</label>
                    <textarea class="width-800 height-md" id="filter-observacoes-cumprimento" placeholder="Descreva observações sobre o cumprimento"><?php echo htmlspecialchars($observacoesCumprimento, ENT_QUOTES); ?></textarea>
                </div>
            </div>

            <input type="hidden" id="hidden-ceapa-id" value="<?php echo htmlspecialchars($idTarefa, ENT_QUOTES); ?>">
        <?php endif; ?>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary" id="voltar-btn" data-allow-during-loading="true">Voltar</button>
            <?php if ($errorMessage === ''): ?>
                <?php if ($podeExcluirTarefa): ?>
                    <button type="button" class="btn btn-danger-outline" id="excluir-btn">Excluir</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" id="salvar-btn">Salvar alterações</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($tarefa !== null): ?>
    <script src="/assets/js/modules/datajud/cnj.js"></script>
    <script src="/assets/js/modules/datajud/datajud-cycle-helper.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/datajud/datajud-cycle-helper.js') ?: time(); ?>"></script>
    <script src="/assets/js/modules/datajud/datajud-api.js"></script>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script>
        (function () {
            var reuSelect = document.getElementById('filter-reu-select');
            var reuInput = document.getElementById('filter-reu');
            var processoInput = document.getElementById('filter-processo');
            var processoTravado = <?php echo $lockProcesso ? 'true' : 'false'; ?>;

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
                var nomeAtual = (reuInput.value || '').trim();
                reuSelect.innerHTML = '';
                var selectedByName = false;
                reus.forEach(function (r) {
                    var opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = r.nome;
                    if (!selectedByName && nomeAtual !== '' && opt.textContent.trim().toLowerCase() === nomeAtual.toLowerCase()) {
                        opt.selected = true;
                        selectedByName = true;
                    }
                    reuSelect.appendChild(opt);
                });
                var outro = document.createElement('option');
                outro.value = 'outro';
                outro.textContent = 'Outro';
                reuSelect.appendChild(outro);
                if (!selectedByName && nomeAtual !== '') {
                    outro.selected = true;
                }
                reuSelect.style.display = '';
                if (selectedByName) {
                    reuInput.style.display = 'none';
                    reuInput.required = false;
                    reuInput.value = '';
                } else {
                    reuInput.style.display = '';
                    reuInput.required = true;
                }
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

            function loadReusByProcessoDigits(digits) {
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
                    if (reus.length > 0) showReuSelect(reus);
                    else showReuInput();
                })
                .catch(function () {
                    showReuInput();
                });
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
                        this.style.display = 'none';
                        this.innerHTML = '';
                        this.value = '';
                        if (reuInput) {
                            reuInput.value = lastSuggestionNome;
                            reuInput.style.display = '';
                            reuInput.required = true;
                            reuInput.focus();
                        }
                    } else if (this.value !== '') {
                        if (reuInput) {
                            reuInput.style.display = 'none';
                            reuInput.required = false;
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
                    if (reuSelect && reuSelect.style.display !== 'none' && reuSelect.value && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__') return;
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

            if (!processoTravado && processoInput) {
                processoInput.addEventListener('input', function () {
                    var digits = (this.value || '').replace(/\D/g, '');
                    if (digits.length !== 20) {
                        var classeInput = document.getElementById('filter-classe');
                        if (classeInput) {
                            classeInput.value = '';
                            classeInput.readOnly = true;
                        }
                        var hCod = document.getElementById('hidden-classe-codigo');
                        var hDesc = document.getElementById('hidden-classe-descricao');
                        if (hCod) hCod.value = '';
                        if (hDesc) hDesc.value = '';
                        showReuInput();
                    }
                });
            }

            window.addEventListener('cnjValidated', function (event) {
                var digits = (event.detail && event.detail.digits) ? String(event.detail.digits).replace(/\D/g, '') : '';
                loadReusByProcessoDigits(digits);
            });

            var currentDigits = (processoInput?.value || '').replace(/\D/g, '');
            if (currentDigits.length === 20) {
                loadReusByProcessoDigits(currentDigits);
            }
        })();
    </script>
    <script>
        (function () {
            const pendingConfirmations = {};
            const showFeedback = function (message, type) {
                if (typeof showToast === 'function') {
                    showToast({ type: type === 'error' ? 'error' : 'success', variant: 'full', title: type === 'error' ? 'Erro' : 'Sucesso', message: message });
                }
            };
            function requireSecondClick(key, message) {
                const now = Date.now();
                const validUntil = Number(pendingConfirmations[key] || 0);
                if (validUntil > now) {
                    delete pendingConfirmations[key];
                    return true;
                }
                pendingConfirmations[key] = now + 5000;
                if (typeof showToast === 'function') {
                    showToast({ type: 'info', variant: 'full', title: 'Confirmação', message: message });
                }
                return false;
            }
            const getValue = function (id) {
                const el = document.getElementById(id);
                return (el && el.value) ? el.value.trim() : '';
            };
            const salvarBtn = document.getElementById('salvar-btn');
            const processoInput = document.getElementById('filter-processo');
            const reuInput = document.getElementById('filter-reu');
            const reuSelect = document.getElementById('filter-reu-select');
            const processoTravado = <?php echo $lockProcesso ? 'true' : 'false'; ?>;

            function getReuPayload() {
                const useSelect = reuSelect && reuSelect.style.display !== 'none' && reuSelect.value !== '' && reuSelect.value !== 'outro' && reuSelect.value !== '__novo__';
                if (useSelect) {
                    const opt = reuSelect.options[reuSelect.selectedIndex];
                    return {
                        reu_id: reuSelect.value,
                        nome_reu: opt ? opt.textContent : ''
                    };
                }
                return { reu_id: '', nome_reu: getValue('filter-reu') };
            }

            salvarBtn?.addEventListener('click', function () {
                const id = getValue('hidden-ceapa-id');
                if (!id) {
                    return showFeedback('Identificador do cadastro não informado.', 'error');
                }

                if (!processoTravado && processoInput && typeof window.normalizeAndValidateCNJValue === 'function') {
                    const normalized = window.normalizeAndValidateCNJValue(getValue('filter-processo'), { toastOnInvalid: true });
                    if (!normalized.ok) {
                        processoInput.value = '';
                        processoInput.focus();
                        return;
                    }
                    processoInput.value = normalized.formatted;
                }

                const numeroProcesso = getValue('filter-processo');
                const classeVal = getValue('filter-classe');
                const classeCodigo = getValue('hidden-classe-codigo');
                const classeDescricao = getValue('hidden-classe-descricao');
                const tipoSuspensao = getValue('filter-tipo-suspensao');
                const decisaoCumprir = getValue('filter-decisao-cumprir');
                const dataDecisao = getValue('filter-data-decisao');
                const observacoes = getValue('filter-observacoes-cumprimento');
                const statusCumprimento = getValue('filter-status-cumprimento');
                const reuPayload = getReuPayload();

                if (numeroProcesso === '') return showFeedback('Informe o número do processo.', 'error');
                if (classeVal === 'Aguarde...') return showFeedback('Aguarde a consulta ao DataJud terminar.', 'error');
                if (!reuPayload.nome_reu) return showFeedback('Informe o réu.', 'error');
                if (tipoSuspensao === '') return showFeedback('Informe o tipo de suspensão.', 'error');
                if (decisaoCumprir === '') return showFeedback('Informe a decisão a cumprir.', 'error');
                if (dataDecisao === '') return showFeedback('Informe a data da decisão.', 'error');

                const payload = {
                    id: id,
                    numero_processo_origem: numeroProcesso,
                    nome_reu: reuPayload.nome_reu,
                    tipo_suspensao: tipoSuspensao,
                    decisao_cumprir: decisaoCumprir,
                    data_decisao: dataDecisao,
                    observacoes_cumprimento: observacoes,
                    status_cumprimento: statusCumprimento
                };
                if (reuPayload.reu_id) payload.reu_id = reuPayload.reu_id;
                if (classeCodigo) payload.classe_codigo = classeCodigo;
                if (classeDescricao) payload.classe_descricao = classeDescricao;

                salvarBtn.disabled = true;
                if (typeof showToast === 'function') showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'Salvando alterações...' });

                fetch('/api/cartorio-ceapa/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        showFeedback('Cadastro CEAPA atualizado com sucesso.', 'success');
                        window.location.href = '/frame.php?page=ceapa';
                    } else {
                        showFeedback(data.error || 'Não foi possível atualizar.', 'error');
                        salvarBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showFeedback('Erro de conexão.', 'error');
                    salvarBtn.disabled = false;
                });
            });

            const excluirBtn = document.getElementById('excluir-btn');
            excluirBtn?.addEventListener('click', function () {
                const id = getValue('hidden-ceapa-id');
                if (!id) return showFeedback('Identificador do cadastro não informado.', 'error');
                if (!requireSecondClick('excluir-ceapa', 'Clique novamente em Excluir em até 5 segundos para confirmar a exclusão.')) return;
                excluirBtn.disabled = true;
                fetch('/api/cartorio-ceapa/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.deleted) {
                        showFeedback('Cadastro CEAPA excluído com sucesso.', 'success');
                        window.location.href = '/frame.php?page=ceapa';
                    } else {
                        showFeedback(data.error || 'Não foi possível excluir.', 'error');
                        excluirBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showFeedback('Erro de conexão.', 'error');
                    excluirBtn.disabled = false;
                });
            });

            document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = '/frame.php?page=ceapa';
            }, { capture: true });
        })();
    </script>
    <?php else: ?>
    <script>
        document.getElementById('voltar-btn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.location.href = '/frame.php?page=ceapa';
        }, { capture: true });
    </script>
    <?php endif; ?>
</body>
</html>
