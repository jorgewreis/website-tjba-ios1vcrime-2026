<?php
declare(strict_types=1);
require __DIR__ . '/sandbox-guard.php';
require dirname(__DIR__, 2) . '/config/config.php';
require dirname(__DIR__, 2) . '/config/security.php';
require dirname(__DIR__, 2) . '/app/models/Usuario.php';
require dirname(__DIR__, 2) . '/app/services/PasswordResetService.php';
$pdo = Conection::getInstance()->getConnection();
$user = 'USR-11111111111111111111111111';
$original = $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '$user'")->fetchColumn();
$token = bin2hex(random_bytes(32));
$id = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
try {
    $pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)')->execute([$id, $user, Security::hashAccessToken($token), '127.0.0.1']);
    $result = (new Usuario())->alterarSenha($user, 'Ativo!12345', 'Nova!Senha1552026');
    if (($result['success'] ?? false) !== true || (new PasswordResetService())->validateResetToken($token) !== null) {
        throw new RuntimeException('Troca deve invalidar link anterior.');
    }
    $later = bin2hex(random_bytes(32));
    $laterId = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
    $pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)')->execute([$laterId, $user, Security::hashAccessToken($later), '127.0.0.1']);
    if ((new PasswordResetService())->validateResetToken($later) === null) {
        throw new RuntimeException('Token posterior deve permanecer válido.');
    }
    $pdo->exec("CREATE TRIGGER rei155_fail_invalidation BEFORE UPDATE ON recuperacao_senha FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic failure'");
    try {
        (new Usuario())->alterarSenha($user, 'Nova!Senha1552026', 'Outra!Senha1552026');
        throw new RuntimeException('Falha de invalidação deveria abortar a troca.');
    } catch (PDOException $e) {
        $hash = $pdo->query("SELECT senha_hash FROM usuarios WHERE id_usuario = '$user'")->fetchColumn();
        if (!Security::verifyPassword('Nova!Senha1552026', (string) $hash) || (new PasswordResetService())->validateResetToken($later) === null) {
            throw new RuntimeException('Rollback não preservou senha e token.');
        }
    } finally {
        $pdo->exec('DROP TRIGGER IF EXISTS rei155_fail_invalidation');
        $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao = ?')->execute([$laterId]);
    }
    // Simultaneous change and reset: only one may commit from the old state.
    $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id_usuario = ?')->execute([$original, $user]);
    $raceToken = bin2hex(random_bytes(32));
    $raceId = 'REC-' . strtoupper(bin2hex(random_bytes(13)));
    $pdo->prepare('INSERT INTO recuperacao_senha (id_recuperacao, usuario, token, expira_em, usado, ip) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?)')->execute([$raceId, $user, Security::hashAccessToken($raceToken), '127.0.0.1']);
    $barrier = __DIR__ . '/.rei155-barrier.runtime';
    @unlink($barrier);
    $workers = [];
    try {
        foreach (['change', 'reset'] as $mode) {
            $pipes = [];
            $proc = proc_open([PHP_BINARY, __DIR__ . '/rei155-concurrency.worker.php', $mode, $user, $raceToken, $barrier], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            if (!is_resource($proc)) { throw new RuntimeException('Worker não iniciou.'); }
            $workers[] = [$proc, $pipes];
        }
        $deadline = microtime(true) + 20;
        while (!is_file($barrier . '.change.ready') || !is_file($barrier . '.reset.ready')) {
            if (microtime(true) >= $deadline) { throw new RuntimeException('Workers não ficaram prontos.'); }
            usleep(1000);
        }
        file_put_contents($barrier, 'release');
        $successes = 0;
        foreach ($workers as [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($proc) !== 0) { throw new RuntimeException('Worker falhou: ' . $err); }
            $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            $successes += !empty($result['success']) ? 1 : 0;
        }
        if ($successes !== 1 || (new PasswordResetService())->validateResetToken($raceToken) !== null) {
            throw new RuntimeException('Troca e reset concorrentes devem ter um vencedor e token inválido.');
        }
    } finally {
        @unlink($barrier);
        @unlink($barrier . '.change.ready');
        @unlink($barrier . '.reset.ready');
        $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao = ?')->execute([$raceId]);
    }
    // Real simultaneous change and request, with a readiness handshake.
    $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id_usuario = ?')->execute([$original, $user]);
    (new LoginRateLimiter($pdo, 'reset'))->clear('529.982.247-25', '127.0.0.1');
    $mailUrl = (string) envValue('AUTH_TEST_MAILPIT_URL', 'http://127.0.0.1:38025');
    if (!in_array($mailUrl, ['http://127.0.0.1:38025', 'http://mailpit:8025'], true)) { throw new RuntimeException('Mailpit inválido.'); }
    $before = json_decode((string) file_get_contents($mailUrl . '/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
    $beforeIds = array_column($before['messages'] ?? [], 'ID');
    $barrier = __DIR__ . '/.rei155-issue.runtime';
    foreach ([$barrier, $barrier . '.change.ready', $barrier . '.request.ready'] as $path) { @unlink($path); }
    $workers = [];
    try {
        foreach (['change', 'request'] as $mode) {
            $pipes = [];
            $proc = proc_open([PHP_BINARY, __DIR__ . '/rei155-concurrency.worker.php', $mode, $user, '-', $barrier], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
            if (!is_resource($proc)) { throw new RuntimeException('Worker não iniciou.'); }
            $workers[$mode] = [$proc, $pipes];
        }
        $deadline = microtime(true) + 20;
        while (!is_file($barrier . '.change.ready') || !is_file($barrier . '.request.ready')) {
            if (microtime(true) >= $deadline) { throw new RuntimeException('Workers não ficaram prontos.'); }
            usleep(1000);
        }
        file_put_contents($barrier, 'release');
        foreach ($workers as $mode => [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($proc) !== 0) { throw new RuntimeException('Worker falhou: ' . $err); }
            $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            if (empty($result['success'])) { throw new RuntimeException('Operação falhou: ' . $mode); }
        }
        $after = json_decode((string) file_get_contents($mailUrl . '/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);
        $new = array_values(array_filter($after['messages'] ?? [], static fn($m) => !in_array($m['ID'] ?? null, $beforeIds, true)));
        if (count($new) !== 1 || !preg_match('/^[A-Za-z0-9_-]+$/', (string) ($new[0]['ID'] ?? ''))) { throw new RuntimeException('E-mail sintético ausente ou ambíguo.'); }
        $message = json_decode((string) file_get_contents($mailUrl . '/api/v1/message/' . $new[0]['ID']), true, 512, JSON_THROW_ON_ERROR);
        if (!preg_match('/[?&]token=([a-f0-9]{64})/i', (string) ($message['Text'] ?? ''), $matches)) { throw new RuntimeException('Token sintético não localizado.'); }
        $issuedToken = $matches[1];
        $query = $pdo->prepare('SELECT usado FROM recuperacao_senha WHERE token = ? AND usuario = ?');
        $query->execute([Security::hashAccessToken($issuedToken), $user]);
        $used = $query->fetchColumn();
        $validated = (new PasswordResetService())->validateResetToken($issuedToken);
        if ($used === false || (($validated !== null) !== ((int) $used === 0))) { throw new RuntimeException('Token emitido diverge do estado persistido: usado=' . var_export($used, true) . ' validado=' . var_export($validated !== null, true)); }
        if ((int) $used === 0 && empty((new PasswordResetService())->resetPassword($issuedToken, 'Emitida!Senha1552026')['success'])) { throw new RuntimeException('Token emitido após troca deve funcionar.'); }
        if ((int) $used === 1 && !empty((new PasswordResetService())->resetPassword($issuedToken, 'Emitida!Senha1552026')['success'])) { throw new RuntimeException('Token emitido antes da troca deve ser recusado.'); }
        $pdo->prepare('DELETE FROM recuperacao_senha WHERE token = ?')->execute([Security::hashAccessToken($issuedToken)]);
    } finally {
        foreach ([$barrier, $barrier . '.change.ready', $barrier . '.request.ready'] as $path) { @unlink($path); }
    }
    echo "PASS REI-155 troca, rollback e concorrência\n";
} finally {
    $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id_usuario = ?')->execute([$original, $user]);
    $pdo->prepare('DELETE FROM recuperacao_senha WHERE id_recuperacao = ?')->execute([$id]);
}
