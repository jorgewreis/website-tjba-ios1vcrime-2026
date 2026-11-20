<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/helpers/Conection.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
if (DB_HOST !== 'mysql' || DB_NAME !== 'auth_test_e2e') throw new RuntimeException('Sandbox inválido.');
$pdo = Conection::getInstance()->getConnection();
$user = 'USR-11111111111111111111111111';
$old = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id_usuario = ?');
$old->execute([$user]); $original = $old->fetchColumn();
if (!is_string($original)) throw new RuntimeException('Usuário sintético ausente.');
$barrier = __DIR__ . '/.rei155-ordered-' . bin2hex(random_bytes(8));
$processes = [];
try {
    foreach ([['change', 'reset'], ['reset', 'change']] as [$first, $second]) {
        foreach (['.locked', '.release', '.change.connection', '.reset.connection'] as $suffix) @unlink($barrier . $suffix);
        $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id_usuario = ?')->execute([$original, $user]);
        $token = bin2hex(random_bytes(32)); $id = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
        $pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao,usuario,token,expira_em,usado,ip) VALUES (?,?,?,DATE_ADD(NOW(), INTERVAL 10 MINUTE),0,?)')->execute([$id, $user, Security::hashAccessToken($token), '127.0.0.1']);
        $start = static function(string $mode, bool $hold) use ($user, $token, $barrier, &$processes): void {
            $p = proc_open([PHP_BINARY, __DIR__ . '/rei155-ordered.worker.php', $mode, $user, $token, $barrier, $hold ? 'yes' : 'no'], [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__, 2));
            if (!is_resource($p)) throw new RuntimeException('Worker não iniciou.');
            $processes[$mode] = [$p, $pipes];
        };
        $waitFile = static function(string $file): void {
            $deadline = microtime(true) + 20;
            while (!is_file($file)) { if (microtime(true) > $deadline) throw new RuntimeException('Barreira não atingida.'); usleep(10000); }
        };
        $start($first, true); $waitFile($barrier . '.locked');
        $start($second, false); $waitFile($barrier . '.' . $second . '.connection');
        $thread = (int)file_get_contents($barrier . '.' . $second . '.connection');
        $query = $pdo->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON t.THREAD_ID=w.REQUESTING_THREAD_ID WHERE t.PROCESSLIST_ID=?');
        $deadline = microtime(true) + 15; $waiting = false;
        do { $query->execute([$thread]); $waiting = (int)$query->fetchColumn() > 0; if (!$waiting) usleep(10000); } while (!$waiting && microtime(true) < $deadline);
        if (!$waiting) throw new RuntimeException('Espera de linha não observada.');
        file_put_contents($barrier . '.release', 'release');
        $results = [];
        foreach ([$first, $second] as $mode) {
            [$proc, $pipes] = $processes[$mode]; $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $code = proc_close($proc); unset($processes[$mode]);
            if ($code !== 0) throw new RuntimeException('Worker falhou.');
            $results[$mode] = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        }
        if (empty($results[$first]['success']) || !empty($results[$second]['success']) || (new PasswordResetService())->validateResetToken($token) !== null) throw new RuntimeException('Resultado não respeitou a ordem serial.');
        $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao=?')->execute([$id]);
        echo "PASS ordem $first antes de $second com espera observada\n";
    }
} finally {
    file_put_contents($barrier . '.release', 'release');
    foreach ($processes as [$proc, $pipes]) { foreach ($pipes as $pipe) fclose($pipe); proc_terminate($proc); proc_close($proc); }
    foreach (['.locked', '.release', '.change.connection', '.reset.connection'] as $suffix) @unlink($barrier . $suffix);
    $pdo->prepare('UPDATE usuarios SET senha_hash=? WHERE id_usuario=?')->execute([$original, $user]);
}
