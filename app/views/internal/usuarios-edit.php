<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/DateHelper.php';
require_once __DIR__ . '/../../../config/security.php';

Security::requireAuthentication();

/** Só admin, diretora ou magistrada podem editar/excluir */
$perfisPermitidos = ['ADMIN', 'MAGISTRADA', 'DIRETORA'];
$userPerfis = [];
$userId = Security::getUserId();
if ($userId !== null && $userId !== '') {
    $connection = Conection::getInstance()->getConnection();
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeEditarExcluir = count(array_intersect($userPerfis, $perfisPermitidos)) > 0;

if (!$podeEditarExcluir) {
    header('Location: /frame.php?page=usuarioslist');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$idUsuario = trim((string) ($_GET['id'] ?? ''));
if ($idUsuario === '') {
    header('Location: /frame.php?page=usuarioslist');
    exit;
}

/** Mapeamento setor (label) → codigo na tabela setores */
$setorToCodigo = [
    'Cartório'           => 'CARTORIO',
    'Gabinete'           => 'GABINETE',
    'Sala de Audiências' => 'AUDIENCIA',
];

$mensagemErro = '';
$usuario = null;
$perfisAtuais = [];
$setorAtual = '';
$statusAtual = 'ativo';
$dataNascimentoVal = '';

// Carregar usuário (apenas internos – tabela usuarios)
$stmt = $connection->prepare('SELECT id_usuario, nome, sobrenome, cpf, email, telefone, cargo, instituicao, cadastro_ativo, data_nascimento, ultimo_acesso FROM usuarios WHERE id_usuario = ? LIMIT 1');
$stmt->execute([$idUsuario]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if ($usuario === false || empty($usuario['id_usuario'])) {
    header('Location: /frame.php?page=usuarioslist');
    exit;
}

$dataNascimentoVal = DateHelper::formatForInput($usuario['data_nascimento'] ?? '');

$stmt = $connection->prepare('SELECT perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
$stmt->execute([$idUsuario]);
$perfisAtuais = $stmt->fetchAll(PDO::FETCH_COLUMN);
$perfisAtuais = array_map('strtoupper', array_map('trim', $perfisAtuais));

$setorRaw = trim((string) ($usuario['instituicao'] ?? ''));
if ($setorRaw !== '' && $setorRaw !== '-') {
    $setorAtual = $setorRaw;
} else {
    $stmt = $connection->prepare('SELECT s.nome FROM usuario_setor us INNER JOIN setores s ON us.setor = s.id_setor WHERE us.usuario = ? LIMIT 1');
    $stmt->execute([$idUsuario]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['nome'])) {
        $setorAtual = trim($row['nome']);
    }
}

// Bloqueio por tentativas_login
$cpfUsuario = trim((string) ($usuario['cpf'] ?? ''));
$bloqueadoLogin = false;
if ($cpfUsuario !== '') {
    $stmt = $connection->prepare('SELECT 1 FROM tentativas_login WHERE cpf = ? AND bloqueado = 1 LIMIT 1');
    $stmt->execute([$cpfUsuario]);
    $bloqueadoLogin = $stmt->fetch() !== false;
}
$statusAtual = deriveUsuarioStatus([
    'cadastro_ativo' => $usuario['cadastro_ativo'] ?? null,
    'bloqueado_login' => $bloqueadoLogin ? 1 : 0,
    'ultimo_acesso' => $usuario['ultimo_acesso'] ?? null,
]);

// POST: salvar ou excluir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!Security::verifyCSRFToken($csrf)) {
        $mensagemErro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        $postId = trim((string) ($_POST['id_usuario'] ?? ''));
        if ($postId !== $idUsuario) {
            $mensagemErro = 'Identificador do usuário não confere.';
        } elseif ($action === 'excluir') {
            try {
                $connection->beginTransaction();
                $stmt = $connection->prepare('UPDATE usuarios SET cadastro_ativo = 0, updated_at = NOW() WHERE id_usuario = ?');
                $stmt->execute([$idUsuario]);
                if ($cpfUsuario !== '') {
                    $stmt = $connection->prepare('UPDATE tentativas_login SET bloqueado = 0 WHERE cpf = ?');
                    $stmt->execute([$cpfUsuario]);
                }
                $connection->commit();
                $_SESSION['usuarios_edit_message'] = 'Usuário desativado com sucesso.';
                header('Location: /frame.php?page=usuarioslist');
                exit;
            } catch (Throwable $e) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('usuarios-edit excluir: ' . $e->getMessage());
                $mensagemErro = 'Erro ao desativar. Tente novamente.';
            }
        } elseif ($action === 'salvar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $sobrenome = trim((string) ($_POST['sobrenome'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telefone = trim((string) ($_POST['telefone'] ?? ''));
            $dataNascimentoRaw = trim((string) ($_POST['data_nascimento'] ?? ''));
            $dataNascimentoIso = DateHelper::normalizeIsoDate($dataNascimentoRaw);
            $dataNascimentoVal = $dataNascimentoRaw;
            $cargo = trim((string) ($_POST['cargo'] ?? ''));
            $setorLabel = trim((string) ($_POST['setor'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'ativo'));
            $perfisPost = isset($_POST['perfil']) && is_array($_POST['perfil']) ? $_POST['perfil'] : [];
            $perfisPost = array_map('trim', array_map('strtoupper', $perfisPost));
            $perfisPost = array_filter($perfisPost);

            if ($nome === '' || $sobrenome === '') {
                $mensagemErro = 'Nome e sobrenome são obrigatórios.';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensagemErro = 'E-mail obrigatório e válido.';
            } elseif ($dataNascimentoIso === null) {
                $mensagemErro = 'Informe a data de nascimento no formato AAAA-MM-DD.';
            } else {
                if ($cargo === 'Magistrada' && $setorLabel === '') {
                    $setorLabel = 'Gabinete';
                }
                $cadastroAtivoValue = ($status === 'ativo' || $status === 'bloqueado') ? 1 : 0;
                try {
                    $connection->beginTransaction();

                    $stmt = $connection->prepare('UPDATE usuarios SET nome = ?, sobrenome = ?, data_nascimento = ?, email = ?, telefone = ?, cargo = ?, instituicao = ?, cadastro_ativo = ?, updated_at = NOW() WHERE id_usuario = ?');
                    $stmt->execute([
                        $nome,
                        $sobrenome,
                        $dataNascimentoIso,
                        $email,
                        $telefone !== '' ? $telefone : null,
                        $cargo !== '' ? $cargo : null,
                        $setorLabel !== '' ? $setorLabel : '-',
                        $cadastroAtivoValue,
                        $idUsuario,
                    ]);

                    if ($status === 'bloqueado' && $cpfUsuario !== '') {
                        $stmt = $connection->prepare('SELECT id_tentativa FROM tentativas_login WHERE cpf = ? LIMIT 1');
                        $stmt->execute([$cpfUsuario]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $stmt = $connection->prepare('UPDATE tentativas_login SET bloqueado = 1, data_bloqueio = NOW() WHERE cpf = ?');
                            $stmt->execute([$cpfUsuario]);
                        } else {
                            $idTent = 'TNT-' . strtoupper(bin2hex(random_bytes(13)));
                            $stmt = $connection->prepare('INSERT INTO tentativas_login (id_tentativa, cpf, tentativas, bloqueado, data_bloqueio, ip) VALUES (?, ?, 0, 1, NOW(), ?)');
                            $stmt->execute([$idTent, $cpfUsuario, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
                        }
                    } elseif ($status === 'ativo' && $cpfUsuario !== '') {
                        $stmt = $connection->prepare('UPDATE tentativas_login SET bloqueado = 0 WHERE cpf = ?');
                        $stmt->execute([$cpfUsuario]);
                    }

                    $stmt = $connection->prepare('DELETE FROM usuario_perfil WHERE usuario = ?');
                    $stmt->execute([$idUsuario]);
                    foreach ($perfisPost as $perfil) {
                        if ($perfil === '') {
                            continue;
                        }
                        $idUsuPerfil = 'USP-' . strtoupper(bin2hex(random_bytes(13)));
                        $stmt = $connection->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
                        $stmt->execute([$idUsuPerfil, $idUsuario, $perfil]);
                    }

                    $stmt = $connection->prepare('DELETE FROM usuario_setor WHERE usuario = ?');
                    $stmt->execute([$idUsuario]);
                    if ($setorLabel !== '') {
                        $idSetor = null;
                        if (isset($setorToCodigo[$setorLabel])) {
                            $codigo = $setorToCodigo[$setorLabel];
                            $stmt = $connection->prepare('SELECT id_setor FROM setores WHERE codigo = ? LIMIT 1');
                            $stmt->execute([$codigo]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($row && !empty($row['id_setor'])) {
                                $idSetor = trim($row['id_setor']);
                            }
                        }
                        if ($idSetor === null) {
                            $stmt = $connection->prepare('SELECT id_setor FROM setores WHERE nome = ? LIMIT 1');
                            $stmt->execute([$setorLabel]);
                            $row = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($row && !empty($row['id_setor'])) {
                                $idSetor = trim($row['id_setor']);
                            }
                        }
                        if ($idSetor !== null) {
                            $idUsuSetor = 'USO-' . strtoupper(bin2hex(random_bytes(13)));
                            $stmt = $connection->prepare('INSERT INTO usuario_setor (id_usu_setor, usuario, setor) VALUES (?, ?, ?)');
                            $stmt->execute([$idUsuSetor, $idUsuario, $idSetor]);
                        }
                    }

                    $connection->commit();
                    $_SESSION['usuarios_edit_message'] = 'Alterações salvas com sucesso.';
                    header('Location: /frame.php?page=usuarioslist');
                    exit;
                } catch (Throwable $e) {
                    if ($connection->inTransaction()) {
                        $connection->rollBack();
                    }
                    error_log('usuarios-edit salvar: ' . $e->getMessage());
                    $mensagemErro = 'Erro ao salvar. Tente novamente ou contate o suporte.';
                }
            }
        }
    }
}

$csrfToken = Security::generateCSRFToken();
$nomeVal = htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8');
$sobrenomeVal = htmlspecialchars($usuario['sobrenome'] ?? '', ENT_QUOTES, 'UTF-8');
$cpfVal = htmlspecialchars($usuario['cpf'] ?? '', ENT_QUOTES, 'UTF-8');
$emailVal = htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8');
$telefoneVal = htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8');
$cargoVal = trim((string) ($usuario['cargo'] ?? ''));
$perfisValidos = ['ESTAGIARIO', 'SERVIDOR', 'DIRETORA', 'MAGISTRADA', 'ADMIN'];

function deriveUsuarioStatus(array $usuario): string
{
    if (empty($usuario['cadastro_ativo'])) {
        return 'pendente';
    }
    if (!empty($usuario['bloqueado_login'])) {
        return 'bloqueado';
    }
    $ultimoAcesso = $usuario['ultimo_acesso'] ?? null;
    if ($ultimoAcesso === null || trim((string) $ultimoAcesso) === '') {
        return 'pendente';
    }
    return 'ativo';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Editar usuário - Usuários - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <script src="/assets/js/shared/text-formatter.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Usuários -</span>
            <span class="specific">Edição</span>
        </div>
        <h1 class="title-page">Editar usuário</h1>
        <p class="description-page">Altere os dados do cadastro. CPF e tipo não podem ser alterados. A alteração de status e o salvamento só podem ser feitos por admin, diretora ou magistrada.</p>
    </div>

    <div class="card-page">
        <?php if ($mensagemErro !== ''): ?>
        <div class="row table left padding-lr-lg padding-tb-md border-bottom">
            <p class="status status-cancelled padding-lr-lg"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <?php endif; ?>

        <form id="form-edit-usuario" method="post" action="" style="width: 100%;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="salvar">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($idUsuario, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="row table subtitle padding-lr-lg padding-tb-lg">Identificação</div>
            <div class="row table between padding-lr-md padding-tb-sm full-line">
                <div class="row table left fit row-gap-10">
                    <div class="col">
                        <label for="edit-cpf">CPF</label>
                        <input type="text" id="edit-cpf" class="width-sm height-md readonly" value="<?php echo $cpfVal; ?>" readonly>
                    </div>
                    <div class="col">
                        <label for="edit-tipo">Tipo</label>
                        <input type="text" id="edit-tipo" class="width-sm height-md readonly" value="Interno" readonly>
                    </div>
                </div>
                <div class="row table right fit">
                    <div class="col">
                        <label for="edit-status">Status</label>
                        <select id="edit-status" name="status" class="width-md height-md white">
                            <option value="pendente"<?php echo $statusAtual === 'pendente' ? ' selected' : ''; ?>>Pendente</option>
                            <option value="ativo"<?php echo $statusAtual === 'ativo' ? ' selected' : ''; ?>>Ativo</option>
                            <option value="bloqueado"<?php echo $statusAtual === 'bloqueado' ? ' selected' : ''; ?>>Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row table subtitle padding-lr-lg padding-tb-lg border-bottom">Dados obrigatórios</div>
            <div class="row table left padding-lr-lg padding-tb-sm border-bottom full-line row-gap-10">
                <div class="col">
                    <label for="edit-nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="edit-nome" name="nome" class="width-md height-md white readonly" placeholder="Nome" required maxlength="100" value="<?php echo $nomeVal; ?>" readonly>
                </div>
                <div class="col">
                    <label for="edit-sobrenome">Sobrenome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="edit-sobrenome" name="sobrenome" class="width-lg height-md white" placeholder="Sobrenome" required maxlength="100" value="<?php echo $sobrenomeVal; ?>">
                </div>
            </div>
            <div class="row table left padding-lr-lg padding-tb-sm border-bottom full-line row-gap-10">
                <div class="col">
                    <label for="edit-email">E-mail <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="email" id="edit-email" name="email" class="width-lg height-md white" placeholder="email@exemplo.com" required maxlength="255" value="<?php echo $emailVal; ?>">
                    <span class="error-message" id="edit-email-error" aria-live="polite"></span>
                </div>
                <div class="col">
                    <label for="edit-telefone">Telefone</label>
                    <input type="text" id="edit-telefone" name="telefone" class="width-sm height-md white" placeholder="(00) 00000-0000" maxlength="50" value="<?php echo htmlspecialchars($telefoneVal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="row table left padding-lr-lg padding-tb-sm border-bottom full-line row-gap-10">
                <div class="col full-line">
                    <label for="edit-data-nascimento">Data de nascimento</label>
                    <input type="date" id="edit-data-nascimento" name="data_nascimento" class="width-md height-md white" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($dataNascimentoVal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <div class="row table subtitle padding-lr-lg padding-tb-lg border-bottom">Status e dados complementares</div>
            <div class="row table left padding-lr-lg padding-tb-sm border-bottom full-line row-gap-10">
                <div class="col">
                    <label for="edit-cargo">Cargo</label>
                    <select id="edit-cargo" name="cargo" class="width-lg height-md white">
                        <option value="">Selecione (opcional)</option>
                        <option value="Estagiário"<?php echo $cargoVal === 'Estagiário' ? ' selected' : ''; ?>>Estagiário</option>
                        <option value="Técnico Judiciário"<?php echo $cargoVal === 'Técnico Judiciário' ? ' selected' : ''; ?>>Técnico Judiciário</option>
                        <option value="Analista Judiciário"<?php echo $cargoVal === 'Analista Judiciário' ? ' selected' : ''; ?>>Analista Judiciário</option>
                        <option value="Diretora de Secretaria"<?php echo $cargoVal === 'Diretora de Secretaria' ? ' selected' : ''; ?>>Diretora de Secretaria</option>
                        <option value="Magistrada"<?php echo $cargoVal === 'Magistrada' ? ' selected' : ''; ?>>Magistrada</option>
                        <option value="Admin"<?php echo $cargoVal === 'Admin' ? ' selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col">
                    <label for="edit-setor">Setor</label>
                    <select id="edit-setor" name="setor" class="width-lg height-md white">
                        <option value="">Selecione (opcional)</option>
                        <option value="Cartório"<?php echo $setorAtual === 'Cartório' ? ' selected' : ''; ?>>Cartório</option>
                        <option value="Gabinete"<?php echo $setorAtual === 'Gabinete' ? ' selected' : ''; ?>>Gabinete</option>
                        <option value="Sala de Audiências"<?php echo $setorAtual === 'Sala de Audiências' ? ' selected' : ''; ?>>Sala de Audiências</option>
                    </select>
                </div>
            </div>
            <div class="row table left padding-lr-lg padding-tb-sm border-bottom full-line row-gap-10">
                <div class="col full-line">
                    <span class="minor-info">Perfis (um usuário pode ter vários)</span>
                    <div class="row table left padding-tb-sm row-gap-10" style="flex-wrap: wrap;">
                        <?php foreach ($perfisValidos as $p): ?>
                        <label class="row left row-gap-10" style="align-items: center; margin-right: 1rem;">
                            <input type="checkbox" name="perfil[]" value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"<?php echo in_array($p, $perfisAtuais, true) ? ' checked' : ''; ?>>
                            <?php
                            $label = $p === 'ESTAGIARIO' ? 'Estagiário' : ($p === 'SERVIDOR' ? 'Servidor' : ($p === 'DIRETORA' ? 'Diretora' : ($p === 'MAGISTRADA' ? 'Magistrada' : 'Admin')));
                            echo htmlspecialchars($label, ENT_QUOTES);
                            ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="row table right padding-lr-lg padding-tb-md border-bottom full-line row-gap-10">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-danger" id="excluir-btn">Excluir</button>
                <button type="submit" class="btn btn-primary" id="salvar-btn">Salvar</button>
            </div>
        </form>

        <form id="form-excluir-usuario" method="post" action="" style="display: none;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="excluir">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($idUsuario, ENT_QUOTES, 'UTF-8'); ?>">
        </form>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var form = document.getElementById('form-edit-usuario');
        var formExcluir = document.getElementById('form-excluir-usuario');
        var nomeInput = document.getElementById('edit-nome');
        var sobrenomeInput = document.getElementById('edit-sobrenome');
        var emailInput = document.getElementById('edit-email');
        var statusSelect = document.getElementById('edit-status');
        var cargoSelect = document.getElementById('edit-cargo');
        var setorSelect = document.getElementById('edit-setor');
        var salvarBtn = document.getElementById('salvar-btn');
        var voltarBtn = document.getElementById('voltar-btn');
        var excluirBtn = document.getElementById('excluir-btn');

        if (voltarBtn) {
            voltarBtn.addEventListener('click', function () { window.location.href = '/frame.php?page=usuarioslist'; });
        }

        function syncCargoToSetor() {
            var cargo = (cargoSelect && cargoSelect.value) ? cargoSelect.value.trim() : '';
            if (cargo === 'Magistrada') {
                setorSelect.value = 'Gabinete';
                setorSelect.disabled = true;
                setorSelect.classList.add('readonly');
            } else {
                setorSelect.disabled = false;
                setorSelect.classList.remove('readonly');
                if (!setorSelect.value) setorSelect.value = '';
            }
        }

        if (cargoSelect) {
            cargoSelect.addEventListener('change', function () { syncCargoToSetor(); updateSalvarButton(); });
        }
        if (setorSelect) setorSelect.addEventListener('change', updateSalvarButton);
        if (statusSelect) statusSelect.addEventListener('change', updateSalvarButton);

        function initTextFormatter() {
            [nomeInput, sobrenomeInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('blur', function () {
                    if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                        this.value = TextFormatter.formatProperName(this.value, 100);
                    }
                    updateSalvarButton();
                });
                el.addEventListener('input', function () { updateSalvarButton(); });
            });
        }

        function validarEmail(email) {
            return email && typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
        }

        function showFieldError(idSuffix, message) {
            var el = document.getElementById('edit-' + idSuffix + '-error');
            if (el) { el.textContent = message || ''; el.style.display = message ? 'block' : 'none'; }
        }

        function initEmailValidation() {
            if (!emailInput) return;
            emailInput.addEventListener('blur', function () {
                var v = this.value.trim();
                showFieldError('email', !v ? '' : (validarEmail(v) ? '' : 'E-mail inválido.'));
                updateSalvarButton();
            });
            emailInput.addEventListener('input', function () { updateSalvarButton(); });
        }

        function isFormValidForSubmit() {
            var nome = (nomeInput && nomeInput.value) ? nomeInput.value.trim() : '';
            var sobrenome = (sobrenomeInput && sobrenomeInput.value) ? sobrenomeInput.value.trim() : '';
            var email = (emailInput && emailInput.value) ? emailInput.value.trim() : '';
            if (!nome || !sobrenome) return false;
            if (!email || !validarEmail(email)) return false;
            return true;
        }

        function updateSalvarButton() {
            if (salvarBtn) salvarBtn.disabled = !isFormValidForSubmit();
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                if (!isFormValidForSubmit()) {
                    e.preventDefault();
                    return;
                }
            });
        }

        if (excluirBtn && formExcluir) {
            excluirBtn.addEventListener('click', function () {
                if (window.confirm('Tem certeza que deseja desativar este usuário? Ele não poderá mais acessar o sistema até reativação por admin, diretora ou magistrada.')) {
                    formExcluir.submit();
                }
            });
        }

        syncCargoToSetor();
        initTextFormatter();
        initEmailValidation();
        document.querySelectorAll('input[name="perfil[]"]').forEach(function (cb) {
            cb.addEventListener('change', updateSalvarButton);
        });
        updateSalvarButton();
    })();
    </script>
</body>
</html>
