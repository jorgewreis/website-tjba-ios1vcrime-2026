<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/DateHelper.php';
require_once __DIR__ . '/../../helpers/TextFormatter.php';
require_once __DIR__ . '/../../helpers/SetoresAccessHelper.php';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';
require_once __DIR__ . '/../../../config/security.php';

Security::requireAuthentication();

/** Só admin ou chefia podem editar/excluir */
$perfisPermitidos = ['ADMIN', 'CHEFIA'];
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

/** Perfis permitidos na edição (compostos + elevados). */
$allowedPerfisInterno = [
    'ADMIN',
    'CHEFIA',
    'ESTAGIARIO-CARTORIO',
    'ESTAGIARIO-GABINETE',
    'ESTAGIARIO-AUDIENCIAS',
    'SERVIDOR-CARTORIO',
    'SERVIDOR-GABINETE',
    'SERVIDOR-AUDIENCIAS',
];

$opcoesPerfilInterno = [
    ['ADMIN', 'Admin'],
    ['CHEFIA', 'Chefia / direção'],
    ['ESTAGIARIO-CARTORIO', 'Estagiário — Cartório'],
    ['ESTAGIARIO-GABINETE', 'Estagiário — Gabinete'],
    ['ESTAGIARIO-AUDIENCIAS', 'Estagiário — Sala de audiências'],
    ['SERVIDOR-CARTORIO', 'Servidor — Cartório'],
    ['SERVIDOR-GABINETE', 'Servidor — Gabinete'],
    ['SERVIDOR-AUDIENCIAS', 'Servidor — Sala de audiências'],
];

$mensagemErro = '';
$usuario = null;
$perfisAtuais = [];
$setorAtual = '';
$statusAtual = 'ativo';
$dataNascimentoVal = '';

// Carregar usuário (apenas internos – tabela usuarios)
$stmt = $connection->prepare('SELECT id_usuario, nome, sobrenome, cpf, email, telefone, cargo, instituicao, cadastro_ativo, data_nascimento FROM usuarios WHERE id_usuario = ? LIMIT 1');
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
$isUsuarioExterno = in_array('ESTUDANTE', $perfisAtuais, true) || in_array('CONSULTOR', $perfisAtuais, true);
$perfisFormChecked = $isUsuarioExterno
    ? []
    : SetoresAccessHelper::expandPerfisForFormUi($perfisAtuais, $connection, $idUsuario);

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
            $nome = TextFormatter::formatProperName(trim((string) ($_POST['nome'] ?? '')), 100);
            $sobrenome = TextFormatter::formatProperName(trim((string) ($_POST['sobrenome'] ?? '')), 100);
            $email = trim((string) ($_POST['email'] ?? ''));
            $telefone = trim((string) ($_POST['telefone'] ?? ''));
            $dataNascimentoRaw = trim((string) ($_POST['data_nascimento'] ?? ''));
            $dataNascimentoIso = DateHelper::normalizeIsoDate($dataNascimentoRaw);
            $dataNascimentoVal = $dataNascimentoRaw;
            $cargo = $isUsuarioExterno ? trim((string) ($usuario['cargo'] ?? '')) : trim((string) ($_POST['cargo'] ?? ''));
            $setorLabel = $isUsuarioExterno ? $setorAtual : trim((string) ($_POST['setor'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'ativo'));
            if ($isUsuarioExterno) {
                $perfisPost = $perfisAtuais;
            } else {
                $perfisPostRaw = isset($_POST['perfil']) && is_array($_POST['perfil']) ? $_POST['perfil'] : [];
                $perfisPostRaw = array_values(array_unique(array_map('trim', array_map('strtoupper', $perfisPostRaw))));
                $perfisPostRaw = array_filter($perfisPostRaw, static fn ($p): bool => trim((string) $p) !== '');
                $perfisPost = [];
                foreach ($perfisPostRaw as $p) {
                    $p = strtoupper(trim((string) $p));
                    if (in_array($p, $allowedPerfisInterno, true)) {
                        $perfisPost[] = $p;
                    }
                }
                $perfisPost = ProfileNormalizer::normalize($perfisPost);
            }

            if ($nome === '' || $sobrenome === '') {
                $mensagemErro = 'Nome e sobrenome são obrigatórios.';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensagemErro = 'E-mail obrigatório e válido.';
            } else {
                if ($cargo === 'Magistrada' && $setorLabel === '') {
                    $setorLabel = 'Gabinete';
                }
                $cadastroAtivoValue = ($status === 'ativo' || $status === 'bloqueado') ? 1 : 0;
                try {
                    $connection->beginTransaction();

                    $dataNascimentoSave = $dataNascimentoRaw !== '' && $dataNascimentoIso !== null ? $dataNascimentoIso : null;
                    $stmt = $connection->prepare('UPDATE usuarios SET nome = ?, sobrenome = ?, data_nascimento = ?, email = ?, telefone = ?, cargo = ?, instituicao = ?, cadastro_ativo = ?, updated_at = NOW() WHERE id_usuario = ?');
                    $stmt->execute([
                        $nome,
                        $sobrenome,
                        $dataNascimentoSave,
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

                    $areasLinks = [];
                    foreach ($perfisPost as $p) {
                        if (preg_match('/^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', (string) $p, $m) === 1) {
                            $areasLinks[$m[2]] = true;
                        }
                    }
                    if (!empty($areasLinks)) {
                        foreach (array_keys($areasLinks) as $area) {
                            $tuple = SetoresAccessHelper::resolveFromCodigoArea($connection, $area);
                            if ($tuple !== null) {
                                $idUsuSetor = 'USO-' . strtoupper(bin2hex(random_bytes(13)));
                                $stmt = $connection->prepare('INSERT INTO usuario_setor (id_usu_setor, usuario, setor) VALUES (?, ?, ?)');
                                $stmt->execute([$idUsuSetor, $idUsuario, $tuple['id_setor']]);
                            }
                        }
                    } elseif ($setorLabel !== '') {
                        $resolved = SetoresAccessHelper::resolveFromLabel($connection, $setorLabel);
                        if ($resolved !== null) {
                            $idUsuSetor = 'USO-' . strtoupper(bin2hex(random_bytes(13)));
                            $stmt = $connection->prepare('INSERT INTO usuario_setor (id_usu_setor, usuario, setor) VALUES (?, ?, ?)');
                            $stmt->execute([$idUsuSetor, $idUsuario, $resolved['id_setor']]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isUsuarioExterno) {
    $actionPost = trim((string) ($_POST['action'] ?? ''));
    if ($actionPost === 'salvar' && $mensagemErro !== '') {
        $rawPf = isset($_POST['perfil']) && is_array($_POST['perfil']) ? $_POST['perfil'] : [];
        $perfisFormChecked = array_values(array_unique(array_map('strtoupper', array_map('trim', $rawPf))));
    }
}

$csrfToken = Security::generateCSRFToken();
$nomeVal = htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8');
$sobrenomeVal = htmlspecialchars($usuario['sobrenome'] ?? '', ENT_QUOTES, 'UTF-8');
$cpfVal = htmlspecialchars($usuario['cpf'] ?? '', ENT_QUOTES, 'UTF-8');
$emailVal = htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8');
$telefoneVal = htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8');
$cargoVal = trim((string) ($usuario['cargo'] ?? ''));

function deriveUsuarioStatus(array $usuario): string
{
    if (empty($usuario['cadastro_ativo'])) {
        return 'pendente';
    }
    if (!empty($usuario['bloqueado_login'])) {
        return 'bloqueado';
    }
    return 'ativo';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Editar usuário - Usuários - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

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
        <p class="description-page">Altere os dados do cadastro. CPF e tipo não podem ser alterados. Perfis internos usam o modelo composto (ex.: SERVIDOR-GABINETE) alinhado ao setor e à política de acesso.</p>
    </div>

    <div class="container-full card-page">
        <?php if ($mensagemErro !== ''): ?>
        <script>
            if (typeof showToast === 'function') {
                showToast({
                    type: 'error',
                    variant: 'full',
                    title: 'Erro',
                    message: <?php echo json_encode($mensagemErro, JSON_UNESCAPED_UNICODE); ?>
                });
            }
        </script>
        <?php endif; ?>

        <form id="form-edit-usuario" method="post" action="" style="width: 100%;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="salvar">
            <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($idUsuario, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Identificação</h2></div>
            <div class="display-flex row row-full justify-between padding-x-md padding-y-md full-line">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col">
                        <label for="edit-cpf">CPF</label>
                        <input type="text" id="edit-cpf" class="width-400 height-md readonly" value="<?php echo $cpfVal; ?>" readonly>
                    </div>
                    <div class="col">
                        <label for="edit-tipo">Tipo</label>
                        <input type="text" id="edit-tipo" class="width-300 height-md readonly" value="<?php echo $isUsuarioExterno ? 'Externo' : 'Interno'; ?>" readonly>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end">
                    <div class="col">
                        <label for="edit-status">Status</label>
                        <select id="edit-status" name="status" class="width-300 height-md">
                            <option value="pendente"<?php echo $statusAtual === 'pendente' ? ' selected' : ''; ?>>Pendente</option>
                            <option value="ativo"<?php echo $statusAtual === 'ativo' ? ' selected' : ''; ?>>Ativo</option>
                            <option value="bloqueado"<?php echo $statusAtual === 'bloqueado' ? ' selected' : ''; ?>>Bloqueado</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados obrigatórios</h2></div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="edit-nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="edit-nome" name="nome" class="width-600 height-md white readonly" placeholder="Digite o nome completo" required maxlength="100" value="<?php echo $nomeVal; ?>" readonly>
                </div>
                <div class="col">
                    <label for="edit-sobrenome">Sobrenome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="edit-sobrenome" name="sobrenome" class="width-600 height-md white" placeholder="Sobrenome" required maxlength="100" value="<?php echo $sobrenomeVal; ?>">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="edit-email">E-mail <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="email" id="edit-email" name="email" class="width-600 height-md white" placeholder="email@exemplo.com" required maxlength="255" value="<?php echo $emailVal; ?>">
                </div>
                <div class="col">
                    <label for="edit-telefone">Telefone</label>
                    <input type="text" id="edit-telefone" name="telefone" class="width-300 height-md white" placeholder="(00) 00000-0000" maxlength="50" value="<?php echo htmlspecialchars($telefoneVal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col full-line">
                    <label for="edit-data-nascimento">Data de nascimento</label>
                    <input type="date" id="edit-data-nascimento" name="data_nascimento" class="width-300 height-md white" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($dataNascimentoVal, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <?php if (!$isUsuarioExterno): ?>
                <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Status e dados complementares</h2></div>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-cargo">Cargo</label>
                        <select id="edit-cargo" name="cargo" class="width-500 height-md">
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
                        <label for="edit-setor">Setor (lotação / exibição)</label>
                        <select id="edit-setor" name="setor" class="width-500 height-md">
                            <option value="">Selecione (opcional)</option>
                            <option value="Cartório"<?php echo $setorAtual === 'Cartório' ? ' selected' : ''; ?>>Cartório</option>
                            <option value="Gabinete"<?php echo $setorAtual === 'Gabinete' ? ' selected' : ''; ?>>Gabinete</option>
                            <option value="Sala de Audiências"<?php echo $setorAtual === 'Sala de Audiências' ? ' selected' : ''; ?>>Sala de Audiências</option>
                        </select>
                    </div>
                </div>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col full-line">
                        <span class="minor-info">Perfis de acesso (vários permitidos). Quem não é admin/chefia precisa de pelo menos um perfil ESTAGIARIO-* ou SERVIDOR-* para o painel interno.</span>
                        <div class="display-flex row row-full justify-start padding-y-sm gap-y-md" style="flex-wrap: wrap;">
                            <?php foreach ($opcoesPerfilInterno as [$pVal, $pLabel]): ?>
                            <label class="row left row-gap-10" style="align-items: center; margin-right: 1rem;">
                                <input type="checkbox" name="perfil[]" value="<?php echo htmlspecialchars($pVal, ENT_QUOTES, 'UTF-8'); ?>"<?php echo in_array($pVal, $perfisFormChecked, true) ? ' checked' : ''; ?>>
                                <?php echo htmlspecialchars($pLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-bottom">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="button" class="btn btn-danger" id="excluir-btn">Excluir</button>
                <button type="submit" class="btn btn-primary" id="salvar-btn" disabled>Salvar</button>
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
        var pendingConfirmations = {};
        function requireSecondClick(key, message) {
            var now = Date.now();
            var validUntil = Number(pendingConfirmations[key] || 0);
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
        'use strict';

        var form = document.getElementById('form-edit-usuario');
        var formExcluir = document.getElementById('form-excluir-usuario');
        var nomeInput = document.getElementById('edit-nome');
        var sobrenomeInput = document.getElementById('edit-sobrenome');
        var emailInput = document.getElementById('edit-email');
        var telefoneInput = document.getElementById('edit-telefone');
        var dataNascimentoInput = document.getElementById('edit-data-nascimento');
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
            if (!cargoSelect || !setorSelect) return;
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

        var lastFieldToastByKey = {};
        function showFieldError(idSuffix, message) {
            var key = String(idSuffix || '');
            var text = (message || '').trim();
            if (!text) {
                lastFieldToastByKey[key] = '';
                return;
            }
            if (lastFieldToastByKey[key] === text) return;
            lastFieldToastByKey[key] = text;
            if (typeof showToast === 'function') {
                showToast({ type: 'error', variant: 'full', title: 'Validação', message: text });
            }
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

        function getCurrentState() {
            var perfisSelecionados = [];
            document.querySelectorAll('input[name="perfil[]"]:checked').forEach(function (cb) {
                perfisSelecionados.push((cb.value || '').trim().toUpperCase());
            });
            perfisSelecionados.sort();
            return {
                nome: nomeInput ? (nomeInput.value || '').trim() : '',
                sobrenome: sobrenomeInput ? (sobrenomeInput.value || '').trim() : '',
                email: emailInput ? (emailInput.value || '').trim() : '',
                telefone: telefoneInput ? (telefoneInput.value || '').trim() : '',
                dataNascimento: dataNascimentoInput ? (dataNascimentoInput.value || '').trim() : '',
                status: statusSelect ? (statusSelect.value || '').trim() : '',
                cargo: cargoSelect ? (cargoSelect.value || '').trim() : '',
                setor: setorSelect ? (setorSelect.value || '').trim() : '',
                perfis: perfisSelecionados
            };
        }

        var initialState = getCurrentState();
        function hasChanges() {
            return JSON.stringify(getCurrentState()) !== JSON.stringify(initialState);
        }

        function updateSalvarButton() {
            if (salvarBtn) salvarBtn.disabled = !isFormValidForSubmit() || !hasChanges();
        }

        function aplicarFormatadorNomeSobrenome() {
            if (typeof TextFormatter === 'undefined' || !TextFormatter.formatProperName) return;
            if (nomeInput) nomeInput.value = TextFormatter.formatProperName(nomeInput.value || '', 100);
            if (sobrenomeInput) sobrenomeInput.value = TextFormatter.formatProperName(sobrenomeInput.value || '', 100);
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                aplicarFormatadorNomeSobrenome();
                if (!isFormValidForSubmit()) {
                    e.preventDefault();
                    if (typeof showToast === 'function') {
                        showToast({ type: 'error', variant: 'full', title: 'Validação', message: 'Revise os campos obrigatórios e corrija os dados informados.' });
                    }
                    return;
                }
            });
        }

        if (excluirBtn && formExcluir) {
            excluirBtn.addEventListener('click', function () {
                if (!requireSecondClick('desativar-usuario', 'Clique novamente em Excluir em até 5 segundos para confirmar a desativação.')) return;
                formExcluir.submit();
            });
        }

        syncCargoToSetor();
        initTextFormatter();
        initEmailValidation();
        if (telefoneInput) {
            telefoneInput.addEventListener('input', updateSalvarButton);
            telefoneInput.addEventListener('change', updateSalvarButton);
        }
        if (dataNascimentoInput) {
            dataNascimentoInput.addEventListener('input', updateSalvarButton);
            dataNascimentoInput.addEventListener('change', updateSalvarButton);
        }
        document.querySelectorAll('input[name="perfil[]"]').forEach(function (cb) {
            cb.addEventListener('change', updateSalvarButton);
        });
        updateSalvarButton();
    })();
    </script>
</body>
</html>
