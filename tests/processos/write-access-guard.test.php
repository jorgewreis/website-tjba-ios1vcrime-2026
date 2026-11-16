<?php
declare(strict_types=1);

// Exercita os endpoints reais em laboratório isolado; nenhuma conexão é feita.
$source = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/processos-write-guard-' . bin2hex(random_bytes(12));
$files = [];
$put = static function (string $path, string $content) use ($root, &$files): void {
    $target = $root . '/' . $path;
    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) throw new RuntimeException('Falha ao criar laboratório.');
    if (file_put_contents($target, $content, LOCK_EX) === false) throw new RuntimeException('Falha ao criar fixture.');
    $files[] = $target;
};
try {
    foreach (['prioridades-add.php', 'update-classe.php'] as $endpoint) {
        $put('public/api/processos/' . $endpoint, file_get_contents($source . '/public/api/processos/' . $endpoint));
    }
    foreach (['app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php'] as $path) {
        $put($path, file_get_contents($source . '/' . $path));
    }
    $put('app/helpers/AutoloadHelper.php', '<?php');
    $put('app/repositories/ProcessosRepository.php', '<?php final class ProcessosRepository {}');
    $put('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return false; } final class Conection { public static function getInstance(): self { throw new RuntimeException("DB_SENTINEL"); } }');
    $put('config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return getenv("TEST_AUTH") === "1"; } }');
    $put('app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("TEST_PROFILE")]; } }');
    foreach (['app/repositories/SalaAudienciasPautaParteRepository.php', 'app/services/ProcessoPrioridadesService.php', 'app/services/ProcessoClasseSyncService.php'] as $path) $put($path, '<?php');

    $authorizedProfiles = ['prioridades-add.php' => 'SERVIDOR-AUDIENCIAS', 'update-classe.php' => 'SERVIDOR-CARTORIO'];
    foreach ($authorizedProfiles as $endpoint => $authorizedProfile) {
        foreach ([['0', 'ESTUDANTE', 'http://example.invalid', 401], ['1', 'ESTUDANTE', 'http://example.invalid', 403], ['1', $authorizedProfile, 'http://evil.invalid', 403]] as [$auth, $profile, $origin, $expected]) {
            $code = 'putenv(' . var_export('TEST_AUTH=' . $auth, true) . '); putenv(' . var_export('TEST_PROFILE=' . $profile, true) . '); $_SESSION=["id_usuario"=>"synthetic"]; $_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]=' . var_export($origin, true) . '; register_shutdown_function(function(){fwrite(STDERR,"HTTP:".http_response_code());}); require ' . var_export($root . '/public/api/processos/' . $endpoint, true) . ';';
            $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) throw new RuntimeException('Falha ao iniciar PHP.');
            fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($proc);
            if ($status !== 0 || !str_contains($err, 'HTTP:' . $expected) || str_contains($out . $err, 'DB_SENTINEL')) throw new RuntimeException("Falha $endpoint/$profile: status=$status; stderr=$err; stdout=$out");
        }
    }
    echo "PASS guard de escrita em processos (6 cenários; dependências simuladas)\n";
} finally {
    foreach (array_reverse($files) as $file) if (is_file($file)) unlink($file);
    if (is_dir($root)) { $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($dirs as $dir) if ($dir->isDir()) rmdir($dir->getPathname()); rmdir($root); }
}
