<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';

Security::requireAuthentication();

$usuario = new Usuario();
$user = $usuario->validarToken($_SESSION['token'] ?? '');
if (!$user) {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: /index.php');
    exit;
}

$idUsuario = Security::getUserId();
$mensagemErro = '';
$mensagemSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCSRFToken($csrf)) {
        $mensagemErro = 'Sessão inválida. Recarregue a página e tente novamente.';
    } else {
        $senhaAtual = $_POST['senha_atual'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';

        if ($senhaAtual === '' || $novaSenha === '' || $confirmarSenha === '') {
            $mensagemErro = 'Preencha todos os campos.';
        } elseif ($novaSenha !== $confirmarSenha) {
            $mensagemErro = 'A nova senha e a confirmação não coincidem.';
        } else {
            $resultado = $usuario->alterarSenha($idUsuario, $senhaAtual, $novaSenha);
            if ($resultado['success']) {
                header('Location: /home.php?toast=success&toast_msg=' . rawurlencode('Senha alterada com sucesso.'));
                exit;
            }
            $mensagemErro = $resultado['message'] ?? 'Não foi possível alterar a senha.';
        }
    }
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alterar senha - Primeira Vara Crime de Ilhéus</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-straight/css/uicons-regular-straight.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    <script>
        (function() {
            var name = 'tjba_1vcrime_theme';
            var i = document.cookie.indexOf(name + '=');
            if (i === -1) return;
            i += name.length + 1;
            var end = document.cookie.indexOf(';', i);
            var t = decodeURIComponent((end === -1 ? document.cookie.slice(i) : document.cookie.slice(i, end)).trim());
            if (t === 'claro' || t === 'escuro' || t === 'auto') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>

    <div class="noview"></div>

    <div class="view layout-wraper">
        <div class="layout-container">
            <div class="layout-page">

                <div class="page-left">
                    <div class="title-system">
                        <span class="title">PRIMEIRA VARA</span>
                        <span class="info-title">CRIMINAL</span>
                        <p class="text">A excelência nasce de um fluxo inteligente, padronização segura, responsabilidade clara e foco na efetividade jurisdicional.</p>
                    </div>
                </div>

                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Alterar senha</span>
                        <span class="info-title">
                            Informe a senha atual e defina uma nova senha (mínimo 8 caracteres).
                        </span>
                    </div>

                    <form class="form-login" id="formAlterarSenha" method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="row">
                            <div class="col">
                                <label for="senha_atual">Senha atual</label>
                                <input class="width-200 white" type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="nova_senha">Nova senha</label>
                                <input class="width-200 white" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" minlength="8" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="confirmar_senha">Confirmar nova senha</label>
                                <input class="width-200 white" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" minlength="8" required>
                            </div>
                        </div>

                        <?php if ($mensagemErro !== ''): ?>
                        <div class="row">
                            <div class="col">
                                <div class="alert-heading danger width-200" role="alert">
                                    <div class="alert-header"><span class="text">Aviso</span></div>
                                    <span class="alert-text"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row" style="background-color: transparent; padding: 20px 0 10px 0; width: 100%; border-bottom: 1px solid var(--color-gray-400);">
                            <button class="btn btn-primary width-200" type="submit">Alterar senha</button>
                        </div>

                        <div class="row" style="border: none; padding-top: 10px;">
                            <div class="col">
                                <label class="link-login">
                                    <a href="/home.php">Voltar ao sistema</a>
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/toast.js" defer></script>
    <script>
        document.getElementById('formAlterarSenha').addEventListener('submit', function(e) {
            var nova = document.getElementById('nova_senha').value;
            var conf = document.getElementById('confirmar_senha').value;
            if (nova !== conf) {
                e.preventDefault();
                alert('A nova senha e a confirmação não coincidem.');
                return;
            }
            if (nova.length < 8) {
                e.preventDefault();
                alert('A nova senha deve ter no mínimo 8 caracteres.');
                return;
            }
        });
    </script>
</body>
</html>
