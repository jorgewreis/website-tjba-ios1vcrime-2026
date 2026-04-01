<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/services/PasswordResetService.php';

$service = new PasswordResetService();
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenData = $service->validateResetToken($token);
$mensagemErro = '';
$mensagemSucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
        $mensagemErro = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif ($tokenData === null) {
        $mensagemErro = 'O link de redefinição é inválido ou expirou.';
    } else {
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

        if ($novaSenha !== $confirmarSenha) {
            $mensagemErro = 'A nova senha e a confirmação não coincidem.';
        } else {
            $resultado = $service->resetPassword($token, $novaSenha);
            if (($resultado['success'] ?? false) === true) {
                $mensagemSucesso = (string) ($resultado['message'] ?? 'Senha redefinida com sucesso.');
                $tokenData = null;
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
    <title>Redefinir senha - Primeira Vara Crime de Ilhéus</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
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
                        <span class="title">Redefinir senha</span>
                        <span class="info-title">
                            Defina uma nova senha com <?php echo htmlspecialchars(Security::passwordPolicySummary(), ENT_QUOTES, 'UTF-8'); ?>.
                        </span>
                    </div>

                    <?php if ($tokenData !== null): ?>
                        <form class="form-login" id="formRedefinirSenha" method="post" action="">
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
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='/index.php'">Voltar</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="form-login">
                            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
                                <span>Esse link não está mais válido. Solicite uma nova recuperação de senha.</span>
                            </div>
                            <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='/recupera_senha.php'">Solicitar novo link</button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="layout-overlay"></div>
        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/toast.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var erroServidor = <?php echo json_encode($mensagemErro, JSON_UNESCAPED_UNICODE); ?>;
            var sucessoServidor = <?php echo json_encode($mensagemSucesso, JSON_UNESCAPED_UNICODE); ?>;
            var form = document.getElementById('formRedefinirSenha');

            if (erroServidor && window.App && App.Toast) {
                App.Toast.show({ type: 'error', variant: 'full', title: 'Erro', message: erroServidor });
            }
            if (sucessoServidor && window.App && App.Toast) {
                App.Toast.show({
                    type: 'success',
                    variant: 'full',
                    title: 'Sucesso',
                    message: sucessoServidor,
                    onClose: function () { window.location.href = '/index.php'; }
                });
            }

            if (!form) return;

            form.addEventListener('submit', function (e) {
                var nova = document.getElementById('nova_senha').value;
                var conf = document.getElementById('confirmar_senha').value;
                var senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{<?php echo PASSWORD_MIN_LENGTH; ?>,<?php echo PASSWORD_MAX_LENGTH; ?>}$/;

                if (nova !== conf) {
                    e.preventDefault();
                    if (window.App && App.Toast) {
                        App.Toast.show({ type: 'error', variant: 'full', title: 'Erro', message: 'A nova senha e a confirmação não coincidem.' });
                    }
                    return;
                }

                if (!senhaRegex.test(nova)) {
                    e.preventDefault();
                    if (window.App && App.Toast) {
                        App.Toast.show({ type: 'error', variant: 'full', title: 'Erro', message: 'A senha deve seguir a política: <?php echo addslashes(Security::passwordPolicySummary()); ?>.' });
                    }
                }
            });
        });
    </script>
</body>
</html>
