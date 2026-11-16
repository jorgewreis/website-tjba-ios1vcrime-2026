<?php
declare(strict_types=1);

// Isolated copies exercise the real access guard without loading config or connecting to a database.
$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monitoramento-guard-' . bin2hex(random_bytes(16));
$files = [];
$put = static function (string $relative, string $content) use ($tmp, &$files): void {
    $path = $tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Falha ao criar diretório de teste.');
    }
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Falha ao escrever fixture.');
    }
    $files[] = $path;
};
$endpoints = ['create.php', 'concluir-subtarefa.php', 'cancel.php', 'delete.php', 'reus-partes.php'];
try {
    foreach ($endpoints as $endpoint) {
        $relative = 'public/api/gabinete-monitoramento-eletronico/' . $endpoint;
        $put($relative, (string) file_get_contents($root . '/' . $relative));
    }
    foreach (['app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php'] as $relative) {
        $put($relative, (string) file_get_contents($root . '/' . $relative));
    }
    $put('app/helpers/AutoloadHelper.php', '<?php');
    $put('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return false; } final class Conection { public static function getInstance(): self { fwrite(STDERR, "CONNECTION_ATTEMPT\n"); throw new RuntimeException("stub"); } }');
    $put('config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return getenv("MOCK_AUTH") === "1"; } }');
    $put('app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("MOCK_PROFILE")]; } }');
    $put('app/services/GabineteMonitoramentoEletronicoService.php', '<?php');

    $cases = [
        ['0', 'SERVIDOR-GABINETE', 'POST', 'http://example.invalid', 401, false],
        ['1', 'ESTUDANTE', 'POST', 'http://example.invalid', 403, false],
        ['1', 'CONSULTOR', 'POST', 'http://example.invalid', 403, false],
        ['1', 'SERVIDOR', 'POST', 'http://example.invalid', 403, false],
        ['1', 'ESTAGIARIO', 'POST', 'http://example.invalid', 403, false],
        ['1', 'CARTORIO', 'POST', 'http://example.invalid', 403, false],
        ['1', 'GABINETE', 'POST', 'http://example.invalid', 403, false],
        ['1', 'AUDIENCIA', 'POST', 'http://example.invalid', 403, false],
        ['1', 'SERVIDOR-GABINETE', 'GET', 'http://example.invalid', 403, false],
        ['1', 'SERVIDOR-GABINETE', 'POST', 'http://evil.invalid', 403, false],
        ['1', 'SERVIDOR-GABINETE', 'POST', 'http://example.invalid', 500, true],
        ['1', 'ESTAGIARIO-GABINETE', 'POST', 'http://example.invalid', 500, true],
        ['1', 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', 500, true],
        ['1', 'ESTAGIARIO-CARTORIO', 'POST', 'http://example.invalid', 500, true],
        ['1', 'SERVIDOR-AUDIENCIAS', 'POST', 'http://example.invalid', 500, true],
        ['1', 'ESTAGIARIO-AUDIENCIAS', 'POST', 'http://example.invalid', 500, true],
        ['1', 'ADMIN', 'POST', 'http://example.invalid', 500, true],
        ['1', 'CHEFIA', 'POST', 'http://example.invalid', 500, true],
    ];
    foreach ($endpoints as $endpoint) {
        foreach ($cases as [$auth, $profile, $method, $origin, $expected, $connectionExpected]) {
            $path = $tmp . '/public/api/gabinete-monitoramento-eletronico/' . $endpoint;
            $code = 'putenv(' . var_export('MOCK_AUTH=' . $auth, true) . '); putenv(' . var_export('MOCK_PROFILE=' . $profile, true) . '); $_SESSION=["id_usuario"=>"synthetic"]; $_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]=' . var_export($origin, true) . '; register_shutdown_function(function(){fwrite(STDERR, "HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
            $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar subprocesso.'); }
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $status = proc_close($proc);
            if ($status !== 0 || !str_contains($err, 'HTTP:' . $expected) || str_contains($err, 'CONNECTION_ATTEMPT') !== $connectionExpected) {
                throw new RuntimeException("Falha {$endpoint}/{$profile}/{$method}: status={$status}; stderr={$err}; stdout={$out}");
            }
        }
    }
    echo 'PASS guard de monitoramento (' . count($endpoints) * count($cases) . " cenários simulados)\n";
} finally {
    foreach (array_reverse($files) as $file) { if (is_file($file)) { unlink($file); } }
    if (is_dir($tmp)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) { if ($dir->isDir()) { rmdir($dir->getPathname()); } }
        rmdir($tmp);
    }
}
