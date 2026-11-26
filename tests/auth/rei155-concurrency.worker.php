<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
[$script, $mode, $user, $token, $barrier] = $argv;
file_put_contents($barrier . '.' . $mode . '.ready', 'ready');
$deadline = microtime(true) + 20;
while (!is_file($barrier)) {
    if (microtime(true) >= $deadline) { throw new RuntimeException('Barreira expirou.'); }
    usleep(1000);
}
$result = match ($mode) {
    'change' => (new Usuario())->alterarSenha($user, 'Ativo!12345', 'Troca!Senha1552026'),
    'request' => (new PasswordResetService())->requestReset('529.982.247-25', 'ativo@auth-test.invalid'),
    default => (new PasswordResetService())->resetPassword($token, 'Reset!Senha1552026'),
};
echo json_encode($result, JSON_THROW_ON_ERROR);
