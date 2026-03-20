<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();
$userId = $userId ?? ($_SESSION['id_usuario'] ?? '');
$dados = null;
$perfis = [];
$regrasAlocacao = [];
$ultimaAlteracaoDados = 'não registrada';
$mensagemErro = '';
$mensagemSucesso = '';

if ($userId !== '') {
    $dados = $usuario->getDadosPessoais($userId);
    $perfis = $usuario->listarPerfisUsuario($userId);
    if (!is_array($perfis)) {
        $perfis = [];
    }
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $regrasAlocacao = $regrasRepo->findByUsuarioId($userId);
    if ($dados !== null && !empty($dados['updated_at'])) {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dados['updated_at']);
        if ($dt === false) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dados['updated_at']);
        }
        $ultimaAlteracaoDados = $dt ? $dt->format('d/m/Y H:i') : trim($dados['updated_at']);
    }
}

$isUsuarioExterno = false;
foreach ($perfis as $perfilItem) {
    $perfilTexto = trim((string) $perfilItem);
    $perfilTexto = function_exists('mb_strtolower') ? mb_strtolower($perfilTexto, 'UTF-8') : strtolower($perfilTexto);
    if (strpos($perfilTexto, 'estudante') !== false || strpos($perfilTexto, 'extern') !== false) {
        $isUsuarioExterno = true;
        break;
    }
}

if ($isUsuarioExterno) {
    $regrasAlocacao = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && Security::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    // Cargo e setor não são editáveis nesta tela (apenas ADMIN/CHEFIA em usuários).
    $cargo = trim((string) ($dados['cargo'] ?? ''));
    $instituicao = trim((string) ($dados['instituicao'] ?? ''));

    $resultado = $usuario->atualizarDadosPessoais($userId, [
        'email' => $email,
        'telefone' => $telefone,
        'cargo' => $cargo,
        'instituicao' => $instituicao,
    ]);
    if ($resultado['success']) {
        $dados = $usuario->getDadosPessoais($userId);
        if ($dados !== null && !empty($dados['updated_at'])) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dados['updated_at']);
            if ($dt === false) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dados['updated_at']);
            }
            $ultimaAlteracaoDados = $dt ? $dt->format('d/m/Y H:i') : trim($dados['updated_at']);
        } else {
            $ultimaAlteracaoDados = 'não registrada';
        }
        $mensagemSucesso = 'Dados atualizados com sucesso.';
    } else {
        $mensagemErro = $resultado['message'] ?? 'Não foi possível atualizar os dados.';
    }
}

$csrfToken = Security::generateCSRFToken();

$cargoOpcoes = ['Estagiário', 'Técnico Judiciário', 'Analista Judiciário', 'Magistrado'];
$setorOpcoes = ['Cartório', 'Gabinete', 'Sala de Audiências', 'Vara'];

$perfisTexto = count($perfis) > 0 ? implode(', ', $perfis) : 'nenhum perfil vinculado';
$perfisTexto = function_exists('mb_strtolower') ? mb_strtolower($perfisTexto, 'UTF-8') : strtolower($perfisTexto);
$ultimaAlteracaoDadosLower = function_exists('mb_strtolower') ? mb_strtolower($ultimaAlteracaoDados, 'UTF-8') : strtolower($ultimaAlteracaoDados);

$nomeCompletoLeitura = '';
if ($dados !== null) {
    $nomeCompletoLeitura = trim(trim((string) ($dados['nome'] ?? '')) . ' ' . trim((string) ($dados['sobrenome'] ?? '')));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Alterar dados pessoais - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            .regras-alocacao-list { font-size: 12px; }
            .regras-alocacao-list .regras-alocacao-item {
                width: 100%;
                padding: 10px 12px;
                margin-bottom: 0;
                border-bottom: 1px solid var(--color-border);
                box-sizing: border-box;
            }
            .regras-alocacao-list .regras-alocacao-item:last-child { border-bottom: none; }
            .regras-alocacao-regra { font-weight: 600; }
            .regras-alocacao-desc { color: var(--color-text-secondary); }
        </style>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Perfil -</span>
            <span class="specific">Dados pessoais</span>
        </div>
        <h1 class="title-page">Alterar dados pessoais</h1>
        <p class="description-page">Visualize e altere seus dados cadastrais. Alguns campos não podem ser alterados por você.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($dados === null): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label class="status status-cancelled">Não foi possível carregar seus dados.</label>
                </div>
            </div>
            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <form id="form-dados-pessoais" method="post" action="" style="width: 100%;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados cadastrais</h2></div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-cpf">CPF</label>
                        <input type="text" id="edit-cpf" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($dados['cpf'] ?? '', ENT_QUOTES); ?>" readonly>
                    </div>
                </div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-nome-completo">Nome completo</label>
                        <input type="text" id="edit-nome-completo" class="width-800 height-md readonly" value="<?php echo htmlspecialchars($nomeCompletoLeitura, ENT_QUOTES, 'UTF-8'); ?>" readonly aria-readonly="true" title="Somente leitura">
                    </div>
                </div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-email">E-mail</label>
                        <input type="email" id="edit-email" name="email" class="width-600 height-md" value="<?php echo htmlspecialchars($dados['email'] ?? '', ENT_QUOTES); ?>" maxlength="255">
                    </div>
                    <div class="col">
                        <label for="edit-telefone">Telefone</label>
                        <input type="text" id="edit-telefone" name="telefone" class="width-300 height-md" value="<?php echo htmlspecialchars($dados['telefone'] ?? '', ENT_QUOTES); ?>" maxlength="50">
                    </div>
                </div>

                <?php if (!$isUsuarioExterno): ?>
                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                        <div class="col">
                            <label for="edit-cargo">Cargo</label>
                            <p class="minor-info" style="margin: 0 0 6px 0;">Alteração apenas por administrador ou chefia (cadastro de usuários).</p>
                            <select id="edit-cargo" class="width-400 height-md readonly" data-readonly="true" title="Somente leitura" aria-readonly="true" disabled tabindex="-1">
                                <option value="">Selecione (opcional)</option>
                                <?php foreach ($cargoOpcoes as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($dados['cargo'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label for="edit-setor">Setor</label>
                            <p class="minor-info" style="margin: 0 0 6px 0;">Alteração apenas por administrador ou chefia (cadastro de usuários).</p>
                            <select id="edit-setor" class="width-400 height-md readonly" data-readonly="true" title="Somente leitura" aria-readonly="true" disabled tabindex="-1">
                                <option value="">Selecione (opcional)</option>
                                <?php foreach ($setorOpcoes as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($dados['instituicao'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações do perfil (somente leitura)</h2></div>

                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col">
                                <label for="edit-perfis">Perfis vinculados</label>
                                <input type="text" id="edit-perfis" class="width-800 height-md readonly" value="<?php echo htmlspecialchars($perfisTexto, ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col">
                                <label for="edit-ultima-dados">Última alteração de dados</label>
                                <input type="text" id="edit-ultima-dados" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($ultimaAlteracaoDadosLower, ENT_QUOTES); ?>" readonly>
                            </div>
                    </div>

                    <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Regras de alocação definidas para você</h2></div>

                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col full-line">
                                <?php if (count($regrasAlocacao) > 0): ?>
                                    <div class="regras-alocacao-list" style="width: 100%;">
                                        <?php foreach ($regrasAlocacao as $idx => $regra): ?>
                                            <div class="col full-line register regras-alocacao-item">
                                                <span class="regras-alocacao-regra"><?php echo htmlspecialchars($regra['regra'] ?? '', ENT_QUOTES); ?></span>
                                                <?php if (!empty(trim((string) ($regra['descricao'] ?? '')))): ?>
                                                    <span class="regras-alocacao-desc"> — <?php echo htmlspecialchars($regra['descricao'], ENT_QUOTES); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="minor-info">Nenhuma regra de alocação definida para seu usuário.</span>
                                <?php endif; ?>
                            </div>
                    </div>
                <?php endif; ?>

                <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                    <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                    <button type="submit" class="btn btn-primary" id="salvar-btn" disabled>Salvar alterações</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <?php if ($dados !== null): ?>
    <script>
    (function () {
        var serverError = <?php echo json_encode($mensagemErro, JSON_UNESCAPED_UNICODE); ?>;
        var serverSuccess = <?php echo json_encode($mensagemSucesso, JSON_UNESCAPED_UNICODE); ?>;
        if (typeof showToast === 'function') {
            if (serverError) {
                showToast({ type: 'error', variant: 'full', title: 'Erro', message: serverError });
            } else if (serverSuccess) {
                showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: serverSuccess });
            }
        }
        var initial = {
            email: <?php echo json_encode($dados['email'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
            telefone: <?php echo json_encode($dados['telefone'] ?? '', JSON_UNESCAPED_UNICODE); ?>
        };

        function getCurrent() {
            var emailEl = document.getElementById('edit-email');
            var telEl = document.getElementById('edit-telefone');
            return {
                email: emailEl ? (emailEl.value || '').trim() : '',
                telefone: telEl ? (telEl.value || '').trim() : ''
            };
        }
        function hasChanges() {
            var cur = getCurrent();
            return (initial.email || '') !== (cur.email || '') ||
                (initial.telefone || '') !== (cur.telefone || '');
        }
        function updateSalvarState() {
            var btn = document.getElementById('salvar-btn');
            if (btn) btn.disabled = !hasChanges();
        }

        document.getElementById('voltar-btn')?.addEventListener('click', function () {
            window.location.href = '/frame.php?page=dashboard';
        });

        var observedFields = ['edit-email', 'edit-telefone'];
        observedFields.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateSalvarState);
                el.addEventListener('change', updateSalvarState);
            }
        });
        updateSalvarState();
    })();
    </script>
    <?php endif; ?>
</body>
</html>
