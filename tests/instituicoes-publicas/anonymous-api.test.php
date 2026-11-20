<?php
declare(strict_types=1);

// Sem conexão: sessão ausente e configurações sintéticas apontando a porta inválida.
$root = dirname(__DIR__, 2);
$envPath = $root . '/tests/auth/.instituicoes-' . bin2hex(random_bytes(12)) . '.runtime';
$env = "APP_ENV=testing\nAUTH_TEST_ISOLATION=isolated-auth-test\nDB_HOST=127.0.0.1\nDB_PORT=1\nDB_NAME=auth_test_instituicoes\nDB_USER=fixture\nDB_PASS=fixture\nMAIL_DRIVER=smtp\nMAIL_HOST=127.0.0.1\nMAIL_USER=fixture\nMAIL_ENCRYPTION=none\nMAIL_FROM_ADDRESS=fixture@auth-test.invalid\nAPP_URL=https://auth-test.invalid\n";
if (file_put_contents($envPath, $env, LOCK_EX) === false) {
    throw new RuntimeException('Não foi possível criar configuração sintética.');
}
try {
    $oldEnv = getenv('APP_ENV');
    $oldFile = getenv('AUTH_TEST_ENV_FILE');
    putenv('APP_ENV=testing');
    putenv('AUTH_TEST_ENV_FILE=' . $envPath);
    foreach (['create.php', 'update.php', 'get.php', 'list.php', 'search.php', 'setores.php', 'setores/create.php', 'setores/update.php', 'setores/delete.php', 'telefones/create.php', 'telefones/update.php', 'telefones/delete.php'] as $endpoint) {
        $path = $root . '/public/api/instituicoes-publicas/' . $endpoint;
        $code = '$_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]="http://example.invalid"; register_shutdown_function(function(){fwrite(STDERR, "HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Não foi possível iniciar PHP.');
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        if ($status !== 0 || !str_contains($err, 'HTTP:401') || !str_contains($out, 'não autenticada')) {
            throw new RuntimeException("Recusa anônima falhou em {$endpoint}: status={$status} stderr={$err} resposta={$out}");
        }
        echo "PASS {$endpoint}: anônimo recusado\n";
    }
} finally {
    if (isset($oldEnv)) { $oldEnv === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $oldEnv); }
    if (isset($oldFile)) { $oldFile === false ? putenv('AUTH_TEST_ENV_FILE') : putenv('AUTH_TEST_ENV_FILE=' . $oldFile); }
    unlink($envPath);
}
