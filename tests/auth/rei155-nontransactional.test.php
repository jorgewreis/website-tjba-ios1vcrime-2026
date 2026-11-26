<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
if (DB_HOST !== 'mysql' || DB_NAME !== 'auth_test_e2e') { throw new RuntimeException('Banco descartável não confirmado.'); }
$pdo = Conection::getInstance()->getConnection();
$user = 'USR-11111111111111111111111111';
$id = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
$token = bin2hex(random_bytes(32));
$original = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '$user'")->fetchColumn();
$pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao,usuario,token,expira_em,usado,ip) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),0,?)')->execute([$id,$user,Security::hashAccessToken($token),'127.0.0.1']);
try {
    foreach (['historico_senha', 'recuperacao_senha', 'usuarios'] as $table) {
        $pdo->exec("ALTER TABLE `$table` ENGINE=MyISAM");
        try {
            try { $changed = (new Usuario())->alterarSenha($user, 'Ativo!12345', 'Nova!Engine1552026'); }
            catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'requer tabelas InnoDB')) throw $e; $changed = ['success' => false]; }
            if (!empty($changed['success'])) { throw new RuntimeException('Troca aceitou engine não transacional.'); }
            if (!empty((new PasswordResetService())->resetPassword($token, 'Nova!Engine1552026')['success'])) { throw new RuntimeException('Reset aceitou engine não transacional.'); }
            if ($table !== 'historico_senha') {
            $mailBefore = json_decode((string)file_get_contents('http://mailpit:8025/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
            $beforeRequests = (int)$pdo->query("SELECT COUNT(*) FROM recuperacao_senha WHERE usuario = '$user'")->fetchColumn();
            (new LoginRateLimiter($pdo, 'reset'))->clear('529.982.247-25', '127.0.0.1');
            (new PasswordResetService())->requestReset('529.982.247-25', 'ativo@auth-test.invalid');
            $afterRequests = (int)$pdo->query("SELECT COUNT(*) FROM recuperacao_senha WHERE usuario = '$user'")->fetchColumn();
            $mailAfter = json_decode((string)file_get_contents('http://mailpit:8025/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
            if ($beforeRequests !== $afterRequests || ($mailBefore['total'] ?? null) !== ($mailAfter['total'] ?? null)) throw new RuntimeException('Solicitação criou token ou enviou e-mail em engine não transacional.');
            (new LoginRateLimiter($pdo, 'reset'))->clear('529.982.247-25', '127.0.0.1');
            }
            $hash = (string) $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '$user'")->fetchColumn();
            $used = $pdo->prepare('SELECT usado FROM recuperacao_senha WHERE id_recuperacao = ?'); $used->execute([$id]);
            if ($hash !== $original || (int) $used->fetchColumn() !== 0) { throw new RuntimeException('Estado alterado apesar de engine não transacional.'); }
        } finally { $pdo->exec("ALTER TABLE `$table` ENGINE=InnoDB"); }
    }
    echo "PASS REI-155 bloqueio de engine não transacional\n";
} finally { $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao = ?')->execute([$id]); }
