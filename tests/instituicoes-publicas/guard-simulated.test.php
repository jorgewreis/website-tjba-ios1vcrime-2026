<?php
declare(strict_types=1);

// Exercise the real API guard and request-origin helper on isolated copies.
// Session validation, profile lookup and the database are replaced with test doubles.
$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'instituicoes-guard-' . bin2hex(random_bytes(12));
$files = [];
function putFixture(string $base, string $relative, string $content): void {
    global $files;
    $file = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { throw new RuntimeException('mkdir failed'); }
    if (file_put_contents($file, $content, LOCK_EX) === false) { throw new RuntimeException('write failed'); }
    $files[] = $file;
}
$endpoints = ['create.php', 'update.php', 'get.php', 'list.php', 'search.php', 'setores.php', 'setores/create.php', 'setores/update.php', 'setores/delete.php', 'telefones/create.php', 'telefones/update.php', 'telefones/delete.php'];
try {
    foreach ($endpoints as $endpoint) {
        putFixture($tmp, 'public/api/instituicoes-publicas/' . $endpoint, file_get_contents($root . '/public/api/instituicoes-publicas/' . $endpoint));
    }
    foreach (['app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php', 'app/helpers/InstituicoesConsultaPolicy.php', 'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php'] as $file) {
        putFixture($tmp, $file, file_get_contents($root . '/' . $file));
    }
    putFixture($tmp, 'app/helpers/AutoloadHelper.php', '<?php');
    putFixture($tmp, 'app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return false; } final class Conection { public static function getInstance(): self { throw new RuntimeException("GUARD_PASSED"); } }');
    putFixture($tmp, 'config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return getenv("MOCK_AUTH") === "1"; } }');
    putFixture($tmp, 'app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("MOCK_PROFILE")]; } }');
    foreach (['app/services/InstituicoesPublicasService.php', 'app/repositories/InstituicoesPublicasRepository.php', 'app/repositories/InstituicoesSetoresRepository.php'] as $stub) { putFixture($tmp, $stub, '<?php'); }
    $cases = [];
    foreach (['create.php', 'update.php', 'setores/create.php', 'setores/update.php', 'setores/delete.php', 'telefones/create.php', 'telefones/update.php', 'telefones/delete.php'] as $endpoint) {
        $cases[] = [$endpoint, 'CONSULTOR', 'POST', 'http://example.invalid', 'HTTP:403'];
        $cases[] = [$endpoint, 'SERVIDOR-GABINETE', 'POST', 'http://example.invalid', 'HTTP:403'];
        $cases[] = [$endpoint, 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', 'HTTP:403'];
        $cases[] = [$endpoint, 'SERVIDOR-CARTORIO', 'POST', 'http://evil.invalid', 'HTTP:403'];
        $cases[] = [$endpoint, 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', 'HTTP:500'];
    }
    foreach (['get.php', 'list.php', 'search.php', 'setores.php'] as $endpoint) {
        $cases[] = [$endpoint, 'ESTUDANTE', 'GET', 'http://example.invalid', 'HTTP:403'];
        $cases[] = [$endpoint, 'CONSULTOR', 'GET', 'http://example.invalid', 'HTTP:500'];
        $cases[] = [$endpoint, 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', 'HTTP:405'];
    }
    foreach ($cases as [$endpoint, $profile, $method, $origin, $expected]) {
        $path = $tmp . '/public/api/instituicoes-publicas/' . $endpoint;
        $code = 'putenv(' . var_export('MOCK_AUTH=1', true) . '); putenv(' . var_export('MOCK_PROFILE=' . $profile, true) . '); $_SESSION=["id_usuario"=>"synthetic"]; $_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]=' . var_export($origin, true) . '; register_shutdown_function(function(){fwrite(STDERR, "HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('proc_open failed'); }
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($proc);
        if (!str_contains($err, $expected) || $status !== 0 || str_contains($out . $err, 'GUARD_PASSED')) {
            throw new RuntimeException("Falha {$endpoint}/{$profile}/{$method}: status={$status}; stderr={$err}; stdout={$out}");
        }
    }
    echo 'PASS guard com dependências simuladas (' . count($cases) . " cenários)\n";
} finally {
    foreach (array_reverse($files) as $file) { if (is_file($file)) { unlink($file); } }
    if (is_dir($tmp)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) { if ($dir->isDir()) { rmdir($dir->getPathname()); } }
        rmdir($tmp);
    }
}
