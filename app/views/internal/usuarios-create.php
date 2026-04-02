<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../helpers/DateHelper.php';
require_once __DIR__ . '/../../helpers/TextFormatter.php';
require_once __DIR__ . '/../../helpers/AvatarCatalog.php';
require_once __DIR__ . '/../../helpers/SetoresAccessHelper.php';
require_once __DIR__ . '/../../helpers/SexoHelper.php';
require_once __DIR__ . '/../../../config/security.php';

Security::requireAuthentication();

/** Acesso apenas para admin e chefia */
$perfisPermitidos = ['ADMIN', 'CHEFIA'];
$userPerfis = [];
$userId = Security::getUserId();
if ($userId !== null && $userId !== '') {
    $connection = Conection::getInstance()->getConnection();
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeCadastrar = count(array_intersect($userPerfis, $perfisPermitidos)) > 0;

if (!$podeCadastrar) {
    header('Location: /frame.php?page=usuarioslist');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$stHasSexo = $connection->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sexo'");
$stHasSexo->execute();
$hasSexoColumn = ((int) $stHasSexo->fetchColumn()) > 0;

$mensagemErro = '';
$mensagemSucesso = '';
$formValues = [
    'nome'    => '',
    'sobrenome' => '',
    'cpf'     => '',
    'email'   => '',
    'telefone' => '',
    'sexo'    => '',
    'tipo'    => 'interno',
    'cargo'   => '',
    'setor'   => '',
    'perfil_externo'  => 'ESTUDANTE',
    'data_nascimento' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!Security::verifyCSRFToken($csrf)) {
        $mensagemErro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $nome = TextFormatter::formatProperName(trim((string) ($_POST['nome'] ?? '')), 100);
        $sobrenome = TextFormatter::formatProperName(trim((string) ($_POST['sobrenome'] ?? '')), 100);
        $cpf = preg_replace('/\D/', '', trim((string) ($_POST['cpf'] ?? '')));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $sexo = SexoHelper::normalize(trim((string) ($_POST['sexo'] ?? '')));
        $dataNascimentoRaw = trim((string) ($_POST['data_nascimento'] ?? ''));
        $dataNascimentoIso = DateHelper::normalizeIsoDate($dataNascimentoRaw);
        $senha = (string) ($_POST['senha'] ?? '');
        $senhaConfirm = (string) ($_POST['senha_confirm'] ?? '');
        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? 'interno')));
        $isExterno = $tipo === 'externo';
        $cargo = trim((string) ($_POST['cargo'] ?? ''));
        $setorLabel = trim((string) ($_POST['setor'] ?? ''));
        $perfilExterno = trim((string) ($_POST['perfil_externo'] ?? ''));
        $perfil = '';
        if ($isExterno) {
            $cargo = '';
            $setorLabel = '';
            if ($perfilExterno !== 'ESTUDANTE' && $perfilExterno !== 'CONSULTOR') {
                $perfilExterno = 'ESTUDANTE';
            }
            $perfil = strtoupper($perfilExterno);
        } else {
            if ($cargo === 'Chefia' && $setorLabel === '') {
                $setorLabel = 'Gabinete';
            }
            if ($cargo === 'Admin') {
                $perfil = 'ADMIN';
            } elseif ($cargo === 'Chefia') {
                $perfil = 'CHEFIA';
            } elseif ($cargo === 'Estagiário' || $cargo === 'Técnico Judiciário' || $cargo === 'Analista Judiciário') {
                if ($setorLabel === '') {
                    $mensagemErro = 'Para estagiário ou servidor (técnico/analista), selecione o setor correspondente à área operacional.';
                } else {
                    $setorRow = SetoresAccessHelper::resolveFromLabel($connection, $setorLabel);
                    if ($setorRow === null || !SetoresAccessHelper::isOperationalCodigoArea($setorRow['codigo_area'])) {
                        $mensagemErro = 'Setor inválido ou sem codigo_area (CARTORIO, GABINETE ou AUDIENCIAS) na base. Verifique o cadastro de setores.';
                    } else {
                        $area = $setorRow['codigo_area'];
                        $perfil = ($cargo === 'Estagiário') ? ('ESTAGIARIO-' . $area) : ('SERVIDOR-' . $area);
                    }
                }
            }
        }

        $formValues = [
            'nome'     => $nome,
            'sobrenome' => $sobrenome,
            'cpf'      => strlen($cpf) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf) : $_POST['cpf'] ?? '',
            'email'    => $email,
            'telefone' => $telefone,
            'sexo'     => $sexo,
            'tipo'     => $isExterno ? 'externo' : 'interno',
            'cargo'    => $cargo,
            'setor'    => $setorLabel,
            'perfil_externo'   => $isExterno ? $perfil : 'ESTUDANTE',
            'data_nascimento' => $dataNascimentoRaw,
        ];

        if ($mensagemErro !== '') {
            // já definido (ex.: setor obrigatório para cargo operacional)
        } elseif ($nome === '' || $sobrenome === '') {
            $mensagemErro = 'Nome e sobrenome são obrigatórios.';
        } elseif (strlen($cpf) !== 11 || !validarCPFPHP($cpf)) {
            $mensagemErro = 'CPF inválido ou incompleto.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagemErro = 'E-mail obrigatório e válido.';
        } elseif (!SexoHelper::isValid($sexo)) {
            $mensagemErro = 'Selecione o sexo.';
        } elseif ($dataNascimentoRaw !== '' && $dataNascimentoIso === null) {
            $mensagemErro = 'Informe a data de nascimento no formato AAAA-MM-DD.';
        } elseif (($policy = Security::validatePasswordPolicy($senha))['valid'] !== true) {
            $mensagemErro = $policy['message'] ?? 'Senha inválida.';
        } elseif ($senha !== $senhaConfirm) {
            $mensagemErro = 'As senhas não coincidem.';
        } else {
            try {
                $connection->beginTransaction();

                $stmt = $connection->prepare('SELECT id_usuario FROM usuarios WHERE cpf = ? LIMIT 1');
                $stmt->execute([$cpf]);
                if ($stmt->fetch()) {
                    $mensagemErro = 'Já existe um cadastro com este CPF.';
                    $connection->rollBack();
                } else {
                    $idUsuario = 'USR-' . strtoupper(bin2hex(random_bytes(13)));
                    $senhaHash = Security::hashPassword($senha);
                    $cpfFormatado = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);

                    if ($hasSexoColumn) {
                        $stmt = $connection->prepare("
                            INSERT INTO usuarios (
                                id_usuario, nome, sobrenome, sexo, data_nascimento, cargo, instituicao, cpf, telefone, email,
                                senha_hash, meta, ultimo_acesso, token_acesso, token_expira, cadastro_ativo, created_at, updated_at,
                                avatar_slug
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, '-', NULL, NULL, NULL, 0, NOW(), NOW(),
                                ?
                            )
                        ");
                        $stmt->execute([
                            $idUsuario,
                            $nome,
                            $sobrenome,
                            $sexo,
                            $dataNascimentoIso,
                            $cargo !== '' ? $cargo : null,
                            $setorLabel !== '' ? $setorLabel : '-',
                            $cpfFormatado,
                            $telefone !== '' ? $telefone : null,
                            $email,
                            $senhaHash,
                            AvatarCatalog::getDefaultSlug(),
                        ]);
                    } else {
                        $stmt = $connection->prepare("
                            INSERT INTO usuarios (
                                id_usuario, nome, sobrenome, data_nascimento, cargo, instituicao, cpf, telefone, email,
                                senha_hash, meta, ultimo_acesso, token_acesso, token_expira, cadastro_ativo, created_at, updated_at,
                                avatar_slug
                            ) VALUES (
                                ?, ?, ?, ?, ?, ?, ?, ?, ?,
                                ?, '-', NULL, NULL, NULL, 0, NOW(), NOW(),
                                ?
                            )
                        ");
                        $stmt->execute([
                            $idUsuario,
                            $nome,
                            $sobrenome,
                            $dataNascimentoIso,
                            $cargo !== '' ? $cargo : null,
                            $setorLabel !== '' ? $setorLabel : '-',
                            $cpfFormatado,
                            $telefone !== '' ? $telefone : null,
                            $email,
                            $senhaHash,
                            AvatarCatalog::getDefaultSlug(),
                        ]);
                    }

                    if ($perfil !== '') {
                        $idUsuPerfil = 'USP-' . strtoupper(bin2hex(random_bytes(13)));
                        $stmt = $connection->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
                        $stmt->execute([$idUsuPerfil, $idUsuario, $perfil]);
                    }

                    if ($setorLabel !== '') {
                        $resolved = SetoresAccessHelper::resolveFromLabel($connection, $setorLabel);
                        if ($resolved !== null) {
                            $idUsuSetor = 'USO-' . strtoupper(bin2hex(random_bytes(13)));
                            $stmt = $connection->prepare('INSERT INTO usuario_setor (id_usu_setor, usuario, setor) VALUES (?, ?, ?)');
                            $stmt->execute([$idUsuSetor, $idUsuario, $resolved['id_setor']]);
                        }
                    }

                    $connection->commit();
                    $_SESSION['usuarios_create_success'] = 'Cadastro realizado com sucesso. O usuário ficará com status Pendente até ativação por admin ou diretora.';
                    header('Location: /frame.php?page=usuarioslist');
                    exit;
                }
            } catch (Throwable $e) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                error_log('usuarios-create: ' . $e->getMessage());
                $mensagemErro = 'Erro ao salvar. Tente novamente ou contate o suporte.';
            }
        }
    }
}

function validarCPFPHP(string $digits): bool {
    if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += (int) $digits[$i] * (10 - $i);
    }
    $d1 = ($sum * 10) % 11;
    if ($d1 === 10) $d1 = 0;
    if ((int) $digits[9] !== $d1) return false;
    $sum = 0;
    for ($j = 0; $j < 10; $j++) {
        $sum += (int) $digits[$j] * (11 - $j);
    }
    $d2 = ($sum * 10) % 11;
    if ($d2 === 10) $d2 = 0;
    return (int) $digits[10] === $d2;
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Novo cadastro - Usuários - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <script src="/assets/js/shared/cpf.js"></script>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Usuários -</span>
            <span class="specific">Novo cadastro</span>
        </div>
        <h1 class="title-page">Novo cadastro de usuário</h1>
        <p class="description-page">Cadastre usuário interno ou externo. Internos operacionais recebem perfil composto (ex.: ESTAGIARIO-CARTORIO, SERVIDOR-GABINETE) conforme cargo e setor. O cadastro inicia como Pendente até ativação.</p>
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

        <form id="form-create-usuario" method="post" action="" style="width: 100%;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Dados obrigatórios</h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="create-nome" name="nome" class="width-600 height-md" placeholder="Digite o nome completo" required maxlength="100" value="<?php echo htmlspecialchars($formValues['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col">
                    <label for="create-sobrenome">Sobrenome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="create-sobrenome" name="sobrenome" class="width-600 height-md" placeholder="Sobrenome" required maxlength="100" value="<?php echo htmlspecialchars($formValues['sobrenome'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-cpf">CPF <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="create-cpf" name="cpf" class="width-300 height-md" placeholder="000.000.000-00" required maxlength="14" value="<?php echo htmlspecialchars($formValues['cpf'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col">
                    <label for="create-email">E-mail <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="email" id="create-email" name="email" class="width-600 height-md" placeholder="email@exemplo.com" required maxlength="255" value="<?php echo htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8'); ?>">                    
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col full-line">
                    <label for="create-sexo">Sexo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <select id="create-sexo" name="sexo" class="width-300 height-md white" required>
                        <option value="">Selecione</option>
                        <option value="Feminino"<?php echo ($formValues['sexo'] ?? '') === 'Feminino' ? ' selected' : ''; ?>>Feminino</option>
                        <option value="Masculino"<?php echo ($formValues['sexo'] ?? '') === 'Masculino' ? ' selected' : ''; ?>>Masculino</option>
                        <option value="Outro"<?php echo ($formValues['sexo'] ?? '') === 'Outro' ? ' selected' : ''; ?>>Outro</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col full-line">
                    <label for="create-data-nascimento">Data de nascimento</label>
                    <input type="date" id="create-data-nascimento" name="data_nascimento" class="width-300 height-md white" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($formValues['data_nascimento'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-senha">Senha <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="password" id="create-senha" name="senha" class="width-300 height-md" placeholder="Mín. 10 caracteres, 1 maiúscula, 1 minúscula, 1 número, 1 símbolo" required minlength="10" maxlength="255">
                </div>
                <div class="col">
                    <label for="create-senha-confirm">Confirmar senha <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="password" id="create-senha-confirm" name="senha_confirm" class="width-300 height-md white" placeholder="Repita a senha" required minlength="10" maxlength="255">
                </div>
            </div>

            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Dados complementares</h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-telefone">Telefone</label>
                    <input type="text" id="create-telefone" name="telefone" class="width-400 height-md" placeholder="(00) 00000-0000" maxlength="50" value="<?php echo htmlspecialchars($formValues['telefone'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col">
                    <label for="create-tipo">Tipo de usuário</label>
                    <select id="create-tipo" name="tipo" class="width-400 height-md white">
                        <option value="interno"<?php echo ($formValues['tipo'] ?? 'interno') === 'interno' ? ' selected' : ''; ?>>Interno</option>
                        <option value="externo"<?php echo ($formValues['tipo'] ?? 'interno') === 'externo' ? ' selected' : ''; ?>>Externo (Estudante / Consultor)</option>
                    </select>
                </div>
            </div>
            <div id="create-externo-fields" class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md" style="display: none;">
                <div class="col">
                    <label for="create-perfil-externo">Perfil externo</label>
                    <select id="create-perfil-externo" name="perfil_externo" class="width-400 height-md white">
                        <option value="ESTUDANTE"<?php echo ($formValues['perfil_externo'] ?? 'ESTUDANTE') === 'ESTUDANTE' ? ' selected' : ''; ?>>Estudante</option>
                        <option value="CONSULTOR"<?php echo ($formValues['perfil_externo'] ?? '') === 'CONSULTOR' ? ' selected' : ''; ?>>Consultor</option>
                    </select>
                </div>
            </div>
            <div id="create-interno-fields">
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-cargo">Cargo</label>
                    <select id="create-cargo" name="cargo" class="width-500 height-md white">
                        <option value="">Selecione</option>
                        <option value="Estagiário"<?php echo $formValues['cargo'] === 'Estagiário' ? ' selected' : ''; ?>>Estagiário</option>
                        <option value="Técnico Judiciário"<?php echo $formValues['cargo'] === 'Técnico Judiciário' ? ' selected' : ''; ?>>Técnico Judiciário</option>
                        <option value="Analista Judiciário"<?php echo $formValues['cargo'] === 'Analista Judiciário' ? ' selected' : ''; ?>>Analista Judiciário</option>
                        <option value="Chefia"<?php echo $formValues['cargo'] === 'Chefia' ? ' selected' : ''; ?>>Chefia</option>
                        <option value="Admin"<?php echo $formValues['cargo'] === 'Admin' ? ' selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col full-line">
                    <label for="create-setor">Setor (área operacional)</label>
                    <select id="create-setor" name="setor" class="width-500 height-md white">
                        <option value="">Selecione</option>
                        <option value="Cartório"<?php echo $formValues['setor'] === 'Cartório' ? ' selected' : ''; ?>>Cartório</option>
                        <option value="Gabinete"<?php echo $formValues['setor'] === 'Gabinete' ? ' selected' : ''; ?>>Gabinete</option>
                        <option value="Sala de Audiências"<?php echo $formValues['setor'] === 'Sala de Audiências' ? ' selected' : ''; ?>>Sala de Audiências</option>
                    </select>
                    <span class="minor-info">Obrigatório para estagiário, técnico ou analista: define o perfil composto (ex.: SERVIDOR-GABINETE). Chefia assume Gabinete se vazio; admin pode ficar sem setor.</span>
                </div>
            </div>
            </div>


            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <button type="submit" class="btn btn-primary" id="cadastrar-btn" disabled>Cadastrar</button>
            </div>
        </form>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>if (window.App && App.UsuariosCreate && typeof App.UsuariosCreate.initialize === 'function') { App.UsuariosCreate.initialize(); }</script>
</body>
</html>
