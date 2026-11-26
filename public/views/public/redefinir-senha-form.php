<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/bootstrap.php';
require_once __DIR__ . '/../../../app/services/PasswordResetService.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
}

$service = new PasswordResetService();

// O token é recebido pelo bootstrap same-origin e mantido em sessão; o formulário
// envia a credencial somente no corpo POST.
$token = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = isset($_POST['token']) && is_string($_POST['token'])
        ? trim($_POST['token'])
        : trim((string) ($_SESSION['password_reset_form_token'] ?? ''));
} else {
    $token = trim((string) ($_SESSION['password_reset_form_token'] ?? ''));
}

$tokenData = $service->validateResetToken($token);
$formAction = '/views/public/redefinir-senha-form.php';
if ($tokenData === null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    unset($_SESSION['password_reset_form_token']);
}

$mensagemErro    = '';
$mensagemSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
        $mensagemErro = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif ($tokenData === null) {
        $mensagemErro = 'O link de redefinição é inválido ou expirou.';
    } else {
        $novaSenha      = (string) ($_POST['nova_senha']      ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

        if ($novaSenha !== $confirmarSenha) {
            $mensagemErro = 'A nova senha e a confirmação não coincidem.';
        } else {
            $resultado = $service->resetPassword($token, $novaSenha);
            if (($resultado['success'] ?? false) === true) {
                $mensagemSucesso = (string) ($resultado['message'] ?? 'Senha redefinida com sucesso.');
                $tokenData = null;
                unset($_SESSION['password_reset_form_token']);
            } else {
                $mensagemErro = (string) ($resultado['message'] ?? 'Não foi possível redefinir a senha.');
                $tokenData = $service->validateResetToken($token);
            }
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
    <meta name="referrer" content="no-referrer">
    <title>Redefinir senha</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">

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

    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: var(--color-background-elevated);
            overflow-x: hidden;
            overflow-y: auto;
        }
    </style>
</head>
<body>

    <div class="title-page" style="margin-top: 30px;">
        <span class="title">Redefinir senha</span>
        <span class="info-title">Defina uma nova senha com <?php echo htmlspecialchars(Security::passwordPolicySummary(), ENT_QUOTES, 'UTF-8'); ?>.</span>
    </div>

    <?php if ($mensagemSucesso !== ''): ?>
        <div class="form-login">
            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
                <span>Senha redefinida com sucesso. Você já pode fechar esta janela e entrar com a nova senha.</span>
            </div>
            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
                <button type="button" class="btn btn-primary" data-nav-href="/index.php">Ir para o login</button>
            </div>
        </div>
    <?php elseif ($tokenData !== null): ?>
        <form class="form-login" id="formRedefinirSenha" method="post" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="display-flex row row-full justify-start align-center padding-x-sm">
                <div class="col">
                    <label for="nova_senha">Nova senha</label>
                    <input class="width-400" type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" required>
                </div>
            </div>

            <div class="display-flex row row-full justify-start align-center padding-x-sm">
                <div class="col">
                    <label for="confirmar_senha">Confirmar nova senha</label>
                    <input class="width-400" type="password" id="confirmar_senha" name="confirmar_senha" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" required>
                </div>
            </div>

            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
                <button class="btn btn-primary" type="submit">Salvar nova senha</button>
            </div>

            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
                <button type="button" class="btn btn-secondary" data-nav-href="/index.php">Voltar</button>
            </div>
        </form>
    <?php else: ?>
        <div class="form-login">
            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
                <span>Esse link não é válido ou já foi utilizado. Solicite uma nova recuperação de senha.</span>
            </div>
            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
                <button type="button" class="btn btn-secondary" data-nav-href="/recupera_senha.php">Solicitar novo link</button>
            </div>
        </div>
    <?php endif; ?>

    <script src="/assets/js/shared/navigate-buttons-iframe.js" defer></script>
    <script src="/assets/js/shared/toast-helper.js" defer></script>
    <script defer>
        // Shim para compatibilidade com libs que usam App.Toast
        window.App = window.App || {};
        App.Toast = { show: function(opts) { showToast(opts); } };

        document.addEventListener('DOMContentLoaded', function () {
            var erroServidor    = <?php echo json_encode($mensagemErro,    JSON_UNESCAPED_UNICODE); ?>;
            var sucessoServidor = <?php echo json_encode($mensagemSucesso, JSON_UNESCAPED_UNICODE); ?>;
            var form = document.getElementById('formRedefinirSenha');
            var senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{<?php echo PASSWORD_MIN_LENGTH; ?>,<?php echo PASSWORD_MAX_LENGTH; ?>}$/;

            if (erroServidor) {
                showToast({ type: 'error', title: 'Erro', message: erroServidor });
            }

            if (sucessoServidor) {
                showToast({
                    type: 'success',
                    title: 'Sucesso',
                    message: sucessoServidor,
                    onClose: function () {
                        if (window.parent !== window) {
                            window.parent.location.href = '/index.php';
                        } else {
                            window.location.href = '/index.php';
                        }
                    }
                });
            }

            if (!form) return;

            form.addEventListener('submit', function (e) {
                var nova = document.getElementById('nova_senha').value;
                var conf = document.getElementById('confirmar_senha').value;

                if (nova !== conf) {
                    e.preventDefault();
                    showToast({ type: 'error', title: 'Erro', message: 'A nova senha e a confirmação não coincidem.' });
                    return;
                }

                if (!senhaRegex.test(nova)) {
                    e.preventDefault();
                    showToast({ type: 'error', title: 'Erro', message: 'A senha deve seguir a política: <?php echo addslashes(Security::passwordPolicySummary()); ?>.' });
                }
            });
        });
    </script>

</body>
</html>
