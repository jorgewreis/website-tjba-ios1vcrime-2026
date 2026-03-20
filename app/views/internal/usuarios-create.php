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

/** Acesso apenas para admin, magistrada e diretora */
$perfisPermitidos = ['ADMIN', 'MAGISTRADA', 'DIRETORA'];
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

/** Mapeamento setor (label para usuário) → codigo na tabela setores */
$setorToCodigo = [
    'Cartório'           => 'CARTORIO',
    'Gabinete'           => 'GABINETE',
    'Sala de Audiências' => 'AUDIENCIA',
    'Vara'               => 'VARA',
];

/** Cargo → perfil (quando o select perfil vem desabilitado pelo JS, o POST não envia; derivar no servidor) */
$cargoToPerfil = [
    'Estagiário' => 'ESTAGIARIO',
    'Técnico Judiciário' => 'SERVIDOR',
    'Analista Judiciário' => 'SERVIDOR',
    'Diretora de Secretaria' => 'DIRETORA',
    'Magistrada' => 'MAGISTRADA',
    'Admin' => 'ADMIN',
];

$mensagemErro = '';
$mensagemSucesso = '';
$formValues = [
    'nome'    => '',
    'sobrenome' => '',
    'cpf'     => '',
    'email'   => '',
    'telefone' => '',
    'tipo'    => 'interno',
    'cargo'   => '',
    'setor'   => '',
    'perfil'  => '',
    'data_nascimento' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!Security::verifyCSRFToken($csrf)) {
        $mensagemErro = 'Token de segurança inválido. Tente novamente.';
    } else {
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $sobrenome = trim((string) ($_POST['sobrenome'] ?? ''));
        $cpf = preg_replace('/\D/', '', trim((string) ($_POST['cpf'] ?? '')));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefone = trim((string) ($_POST['telefone'] ?? ''));
        $dataNascimentoRaw = trim((string) ($_POST['data_nascimento'] ?? ''));
        $dataNascimentoIso = DateHelper::normalizeIsoDate($dataNascimentoRaw);
        $senha = (string) ($_POST['senha'] ?? '');
        $senhaConfirm = (string) ($_POST['senha_confirm'] ?? '');
        $tipo = strtolower(trim((string) ($_POST['tipo'] ?? 'interno')));
        $isExterno = $tipo === 'externo';
        $cargo = trim((string) ($_POST['cargo'] ?? ''));
        $setorLabel = trim((string) ($_POST['setor'] ?? ''));
        $perfil = trim((string) ($_POST['perfil'] ?? ''));
        if ($isExterno) {
            $cargo = '';
            $setorLabel = '';
            $perfil = 'ESTAGIARIO';
        }
        if ($perfil === '' && $cargo !== '' && isset($cargoToPerfil[$cargo])) {
            $perfil = $cargoToPerfil[$cargo];
        }
        if ($cargo === 'Magistrada' && $setorLabel === '') {
            $setorLabel = 'Gabinete';
        }

        $formValues = [
            'nome'     => $nome,
            'sobrenome' => $sobrenome,
            'cpf'      => strlen($cpf) === 11 ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf) : $_POST['cpf'] ?? '',
            'email'    => $email,
            'telefone' => $telefone,
            'tipo'     => $isExterno ? 'externo' : 'interno',
            'cargo'    => $cargo,
            'setor'    => $setorLabel,
            'perfil'   => $perfil,
            'data_nascimento' => $dataNascimentoRaw,
        ];

        if ($nome === '' || $sobrenome === '') {
            $mensagemErro = 'Nome e sobrenome são obrigatórios.';
        } elseif (strlen($cpf) !== 11 || !validarCPFPHP($cpf)) {
            $mensagemErro = 'CPF inválido ou incompleto.';
        } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagemErro = 'E-mail obrigatório e válido.';
        } elseif ($dataNascimentoRaw !== '' && $dataNascimentoIso === null) {
            $mensagemErro = 'Informe a data de nascimento no formato AAAA-MM-DD.';
        } elseif (strlen($senha) < 10 || !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{10,}$/', $senha)) {
            $mensagemErro = 'Senha deve ter no mínimo 10 caracteres, 1 maiúscula, 1 minúscula, 1 número e 1 símbolo.';
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

                    $stmt = $connection->prepare("
                        INSERT INTO usuarios (
                            id_usuario, nome, sobrenome, data_nascimento, cargo, instituicao, cpf, telefone, email,
                            senha_hash, meta, ultimo_acesso, token_acesso, token_expira, cadastro_ativo, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, '-', NULL, NULL, NULL, 0, NOW(), NOW()
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
                    ]);

                    if ($perfil !== '') {
                        $idUsuPerfil = 'USP-' . strtoupper(bin2hex(random_bytes(13)));
                        $stmt = $connection->prepare('INSERT INTO usuario_perfil (id_usu_perfil, usuario, perfil) VALUES (?, ?, ?)');
                        $stmt->execute([$idUsuPerfil, $idUsuario, $perfil]);
                    }

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
        <script src="/assets/js/shared/text-formatter.js"></script>
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
        <p class="description-page">Cadastre um servidor (tipo interno). O cadastro será criado com status Pendente; setor e perfis podem ser definidos na edição por admin ou diretora para liberar o acesso.</p>
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
                        <option value="externo"<?php echo ($formValues['tipo'] ?? 'interno') === 'externo' ? ' selected' : ''; ?>>Externo (Estudante)</option>
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
                        <option value="Diretora de Secretaria"<?php echo $formValues['cargo'] === 'Diretora de Secretaria' ? ' selected' : ''; ?>>Diretora de Secretaria</option>
                        <option value="Magistrada"<?php echo $formValues['cargo'] === 'Magistrada' ? ' selected' : ''; ?>>Magistrada</option>
                        <option value="Admin"<?php echo $formValues['cargo'] === 'Admin' ? ' selected' : ''; ?>>Admin</option>
                    </select>
                </div>
            </div>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label for="create-setor">Setor</label>
                    <select id="create-setor" name="setor" class="width-500 height-md white">
                        <option value="">Selecione</option>
                        <option value="Cartório"<?php echo $formValues['setor'] === 'Cartório' ? ' selected' : ''; ?>>Cartório</option>
                        <option value="Gabinete"<?php echo $formValues['setor'] === 'Gabinete' ? ' selected' : ''; ?>>Gabinete</option>
                        <option value="Sala de Audiências"<?php echo $formValues['setor'] === 'Sala de Audiências' ? ' selected' : ''; ?>>Sala de Audiências</option>
                    </select>
                </div>
                <div class="col">
                    <label for="create-perfil">Perfil</label>
                    <select id="create-perfil" name="perfil" class="width-400 height-md white">
                        <option value="">Selecione</option>
                        <option value="ESTAGIARIO"<?php echo $formValues['perfil'] === 'ESTAGIARIO' ? ' selected' : ''; ?>>Estagiário</option>
                        <option value="SERVIDOR"<?php echo $formValues['perfil'] === 'SERVIDOR' ? ' selected' : ''; ?>>Servidor</option>
                        <option value="DIRETORA"<?php echo $formValues['perfil'] === 'DIRETORA' ? ' selected' : ''; ?>>Diretora</option>
                        <option value="MAGISTRADA"<?php echo $formValues['perfil'] === 'MAGISTRADA' ? ' selected' : ''; ?>>Magistrada</option>
                        <option value="ADMIN"<?php echo $formValues['perfil'] === 'ADMIN' ? ' selected' : ''; ?>>Admin</option>
                    </select>
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

    <script>
    (function () {
        'use strict';

        var form = document.getElementById('form-create-usuario');
        var nomeInput = document.getElementById('create-nome');
        var sobrenomeInput = document.getElementById('create-sobrenome');
        var cpfInput = document.getElementById('create-cpf');
        var emailInput = document.getElementById('create-email');
        var senhaInput = document.getElementById('create-senha');
        var senhaConfirmInput = document.getElementById('create-senha-confirm');
        var tipoSelect = document.getElementById('create-tipo');
        var internoFields = document.getElementById('create-interno-fields');
        var cargoSelect = document.getElementById('create-cargo');
        var setorSelect = document.getElementById('create-setor');
        var dataNascimentoInput = document.getElementById('create-data-nascimento');
        var perfilSelect = document.getElementById('create-perfil');
        var cadastrarBtn = document.getElementById('cadastrar-btn');
        var voltarBtn = document.getElementById('voltar-btn');

        if (voltarBtn) {
            voltarBtn.addEventListener('click', function () { window.location.href = '/frame.php?page=usuarioslist'; });
        }

        var CARGO_TO_PERFIL = {
            'Estagiário': 'ESTAGIARIO',
            'Técnico Judiciário': 'SERVIDOR',
            'Analista Judiciário': 'SERVIDOR',
            'Diretora de Secretaria': 'DIRETORA',
            'Magistrada': 'MAGISTRADA',
            'Admin': 'ADMIN'
        };

        function isExternoSelected() {
            return tipoSelect && ((tipoSelect.value || '').trim() === 'externo');
        }

        function syncTipoFields() {
            var externo = isExternoSelected();
            if (internoFields) {
                internoFields.style.display = externo ? 'none' : '';
            }
            if (!externo) return;
            if (cargoSelect) cargoSelect.value = '';
            if (setorSelect) setorSelect.value = '';
            if (perfilSelect) perfilSelect.value = '';
        }

        function syncCargoToPerfilAndSetor() {
            if (!cargoSelect || !setorSelect || !perfilSelect) return;
            if (isExternoSelected()) return;
            var cargo = (cargoSelect && cargoSelect.value) ? cargoSelect.value.trim() : '';
            var perfilValue = CARGO_TO_PERFIL[cargo];
            if (perfilValue) {
                perfilSelect.value = perfilValue;
                perfilSelect.disabled = true;
                perfilSelect.classList.add('readonly');
            } else {
                perfilSelect.value = '';
                perfilSelect.disabled = false;
                perfilSelect.classList.remove('readonly');
            }
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

        if (tipoSelect) tipoSelect.addEventListener('change', function () {
            syncTipoFields();
            updateCadastrarButton();
        });
        if (cargoSelect) cargoSelect.addEventListener('change', syncCargoToPerfilAndSetor);

        /** Aplica o formatador padrão do sistema (TextFormatter.formatProperName) em nome e sobrenome no blur. */
        function initTextFormatter() {
            [nomeInput, sobrenomeInput].forEach(function (el) {
                if (!el) return;
                el.addEventListener('blur', function () {
                    if (typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                        this.value = TextFormatter.formatProperName(this.value, 100);
                    }
                    updateCadastrarButton();
                });
                el.addEventListener('input', function () { updateCadastrarButton(); });
            });
        }

        function formatCPF(value) {
            var digits = (value || '').replace(/\D/g, '');
            if (digits.length <= 3) return digits;
            if (digits.length <= 6) return digits.slice(0, 3) + '.' + digits.slice(3);
            if (digits.length <= 9) return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6);
            return digits.slice(0, 3) + '.' + digits.slice(3, 6) + '.' + digits.slice(6, 9) + '-' + digits.slice(9, 11);
        }

        function validarCPF(cpf) {
            var digits = (cpf || '').replace(/\D/g, '');
            if (digits.length !== 11) return false;
            if (/^(\d)\1{10}$/.test(digits)) return false;
            var sum = 0;
            for (var i = 0; i < 9; i++) sum += parseInt(digits.charAt(i), 10) * (10 - i);
            var d1 = (sum * 10) % 11; if (d1 === 10) d1 = 0;
            if (parseInt(digits.charAt(9), 10) !== d1) return false;
            sum = 0;
            for (var j = 0; j < 10; j++) sum += parseInt(digits.charAt(j), 10) * (11 - j);
            var d2 = (sum * 10) % 11; if (d2 === 10) d2 = 0;
            return parseInt(digits.charAt(10), 10) === d2;
        }

        function validarEmail(email) {
            return email && typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
        }

        var senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{10,}$/;
        function validarSenha(senha) { return senha && senhaRegex.test(senha); }

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

        function initCPFMask() {
            if (!cpfInput) return;
            cpfInput.addEventListener('blur', function () {
                var raw = this.value.replace(/\D/g, '');
                if (raw.length === 11) {
                    var ok = (typeof App !== 'undefined' && App.CPF && App.CPF.validar)
                        ? App.CPF.validar(raw)
                        : validarCPF(this.value);
                    showFieldError('cpf', ok ? '' : 'CPF inválido.');
                }
                else showFieldError('cpf', raw.length > 0 ? 'CPF deve ter 11 dígitos.' : '');
                updateCadastrarButton();
            });
            if (typeof App !== 'undefined' && App.CPF && App.CPF.init) {
                App.CPF.init('#create-cpf');
            }
            cpfInput.addEventListener('input', function () { updateCadastrarButton(); });
        }

        function initEmailValidation() {
            if (!emailInput) return;
            emailInput.addEventListener('blur', function () {
                var v = this.value.trim();
                showFieldError('email', !v ? '' : (validarEmail(v) ? '' : 'E-mail inválido.'));
                updateCadastrarButton();
            });
            emailInput.addEventListener('input', function () { updateCadastrarButton(); });
        }

        function initPasswordValidation() {
            if (!senhaInput) return;
            senhaInput.addEventListener('blur', function () {
                var v = this.value;
                if (!v) { showFieldError('senha', ''); updateCadastrarButton(); return; }
                if (v.length < 10) { showFieldError('senha', 'Mínimo 10 caracteres.'); updateCadastrarButton(); return; }
                showFieldError('senha', validarSenha(v) ? '' : 'Use pelo menos 1 maiúscula, 1 minúscula, 1 número e 1 símbolo.');
                updateCadastrarButton();
            });
            senhaInput.addEventListener('input', function () { updateCadastrarButton(); });
            if (senhaConfirmInput) {
                senhaConfirmInput.addEventListener('blur', function () {
                    var senha = (senhaInput && senhaInput.value) || '';
                    showFieldError('senha-confirm', this.value ? (senha === this.value ? '' : 'As senhas não coincidem.') : '');
                    updateCadastrarButton();
                });
                senhaConfirmInput.addEventListener('input', function () { updateCadastrarButton(); });
            }
        }

        if (dataNascimentoInput) {
            dataNascimentoInput.addEventListener('input', updateCadastrarButton);
            dataNascimentoInput.addEventListener('change', updateCadastrarButton);
        }

        function isFormValidForSubmit() {
            var nome = (nomeInput && nomeInput.value) ? nomeInput.value.trim() : '';
            var sobrenome = (sobrenomeInput && sobrenomeInput.value) ? sobrenomeInput.value.trim() : '';
            var cpf = (cpfInput && cpfInput.value) ? cpfInput.value : '';
            var email = (emailInput && emailInput.value) ? emailInput.value.trim() : '';
            var senha = (senhaInput && senhaInput.value) ? senhaInput.value : '';
            var conf = (senhaConfirmInput && senhaConfirmInput.value) ? senhaConfirmInput.value : '';
            if (!nome || !sobrenome) return false;
            var cpfDigits = cpf.replace(/\D/g, '');
            if (cpfDigits.length !== 11) return false;
            if (typeof App !== 'undefined' && App.CPF && App.CPF.validar) {
                if (!App.CPF.validar(cpfDigits)) return false;
            } else {
                if (!validarCPF(cpf)) return false;
            }
            if (!email || !validarEmail(email)) return false;
            if (!validarSenha(senha)) return false;
            if (isExternoSelected()) return senha === conf;
            return senha === conf;
        }

        function updateCadastrarButton() {
            if (cadastrarBtn) cadastrarBtn.disabled = !isFormValidForSubmit();
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                if (!isFormValidForSubmit()) {
                    e.preventDefault();
                    if (typeof showToast === 'function') {
                        showToast({ type: 'error', variant: 'full', title: 'Validação', message: 'Revise os campos obrigatórios e corrija os dados informados.' });
                    }
                    return;
                }
            });
        }

        if (typeof showToast === 'function') {
            showToast({ type: 'info', variant: 'full', title: 'Informação', message: 'O cadastro será criado com status Pendente.' });
        }
        syncTipoFields();
        syncCargoToPerfilAndSetor();
        initTextFormatter();
        initCPFMask();
        initEmailValidation();
        initPasswordValidation();
        updateCadastrarButton();
    })();
    </script>
</body>
</html>
