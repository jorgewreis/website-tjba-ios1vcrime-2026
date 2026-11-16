<?php
declare(strict_types=1);

// Exercita guard e endpoints reais em cópia isolada; sessão, perfis e banco são simulados.
$source = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/processos-partes-guard-' . bin2hex(random_bytes(12));
$files = [];
$put = static function (string $path, string $content) use ($root, &$files): void {
    $target = $root . '/' . $path;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) {
        throw new RuntimeException('Falha ao criar laboratório.');
    }
    if (file_put_contents($target, $content, LOCK_EX) === false) throw new RuntimeException('Falha ao criar fixture.');
    $files[] = $target;
};
try {
    $endpoints = ['list.php', 'create.php', 'update.php', 'delete.php'];
    foreach ($endpoints as $endpoint) {
        $path = 'public/api/processos-partes/' . $endpoint;
        $put($path, file_get_contents($source . '/' . $path));
    }
    foreach (['app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php'] as $path) {
        $put($path, file_get_contents($source . '/' . $path));
    }
    $put('app/helpers/AutoloadHelper.php', '<?php');
    $put('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return false; } final class Conection { public static function getInstance(): self { file_put_contents(getenv("TEST_MARKER"), "attempt"); throw new RuntimeException("DB_SENTINEL"); } }');
    $put('config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return getenv("TEST_AUTH") === "1"; } }');
    $put('app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("TEST_PROFILE")]; } }');
    $put('app/repositories/ProcessosPartesRepository.php', '<?php');
    $put('app/repositories/PartesRepository.php', '<?php');
    $cases = [];
    foreach ($endpoints as $endpoint) {
        $write = $endpoint !== 'list.php';
        $method = $write ? 'POST' : 'GET';
        $cases[] = [$endpoint, '0', 'SERVIDOR-CARTORIO', $method, 'http://example.invalid', 401];
        $cases[] = [$endpoint, '1', 'ESTUDANTE', $method, 'http://example.invalid', 403];
        $cases[] = [$endpoint, '1', 'SERVIDOR', $method, 'http://example.invalid', 403];
        if ($write) {
            $cases[] = [$endpoint, '1', 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', 403];
            $cases[] = [$endpoint, '1', 'SERVIDOR-CARTORIO', 'POST', 'http://evil.invalid', 403];
        } else {
            $cases[] = [$endpoint, '1', 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', 405];
        }
        $cases[] = [$endpoint, '1', 'SERVIDOR-CARTORIO', $method, 'http://example.invalid', 500];
    }
    // Consulta de processos é acessível a outras áreas internas. Na edição, a
    // tela atual cria e remove vínculos; editar vínculos diretamente é Cartório.
    foreach (['list.php', 'create.php', 'delete.php'] as $endpoint) {
        $cases[] = [$endpoint, '1', 'SERVIDOR-GABINETE', $endpoint === 'list.php' ? 'GET' : 'POST', 'http://example.invalid', 500];
    }
    $cases[] = ['update.php', '1', 'SERVIDOR-GABINETE', 'POST', 'http://example.invalid', 403];
    foreach ($cases as [$endpoint, $auth, $profile, $method, $origin, $expected]) {
        $path = $root . '/public/api/processos-partes/' . $endpoint;
        $marker = $root . '/connection-marker';
        if (is_file($marker)) unlink($marker);
        $code = 'putenv(' . var_export('TEST_AUTH=' . $auth, true) . '); putenv(' . var_export('TEST_PROFILE=' . $profile, true) . '); putenv(' . var_export('TEST_MARKER=' . $marker, true) . '); $_SESSION=["id_usuario"=>"synthetic"]; $_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]=' . var_export($origin, true) . '; register_shutdown_function(function(){fwrite(STDERR,"HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) throw new RuntimeException('Falha ao iniciar PHP.');
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($proc);
        if ($status !== 0 || !str_contains($err, 'HTTP:' . $expected) || str_contains($out . $err, 'DB_SENTINEL') || is_file($marker) !== ($expected === 500)) {
            throw new RuntimeException("Falha $endpoint/$profile/$method: status=$status; stderr=$err; stdout=$out");
        }
        if (is_file($marker)) unlink($marker);
    }
    echo 'PASS guard processos-partes (' . count($cases) . " cenários; dependências simuladas)\n";
} finally {
    foreach (array_reverse($files) as $file) if (is_file($file)) unlink($file);
    if (is_dir($root)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) if ($dir->isDir()) rmdir($dir->getPathname());
        rmdir($root);
    }
}
