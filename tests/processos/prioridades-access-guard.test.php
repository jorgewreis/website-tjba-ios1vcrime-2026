<?php
declare(strict_types=1);

// Exercita endpoints reais e ApiAccessGuard em cópia temporária com conexão inerte.
$source = dirname(__DIR__, 2);
$root = sys_get_temp_dir() . '/processos-prioridades-guard-' . bin2hex(random_bytes(12));
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
    foreach (['prioridades-list.php', 'prioridades-read.php', 'prioridades-remove.php'] as $endpoint) {
        $path = 'public/api/processos/' . $endpoint;
        $put($path, file_get_contents($source . '/' . $path));
    }
    foreach ([
        'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php',
        'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
        'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
    ] as $path) $put($path, file_get_contents($source . '/' . $path));

    $put('app/helpers/AutoloadHelper.php', '<?php');
    $put('app/helpers/Conection.php', <<<'PHP'
<?php
function appIsHttpsRequest(): bool { return false; }
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO {
        file_put_contents(getenv('TEST_DB_MARKER'), 'connected');
        return new class extends PDO { public function __construct() {} };
    }
}
PHP);
    $put('config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return getenv("TEST_AUTH") === "1"; } }');
    $put('app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("TEST_PROFILE")]; } }');
    $put('app/repositories/SalaAudienciasPautaParteRepository.php', <<<'PHP'
<?php
final class SalaAudienciasPautaParteRepository {
    public function __construct(PDO $pdo) {}
    public function countPartesMenoresAtivasPorProcesso(string $id): int { return 0; }
}
PHP);
    $put('app/services/ProcessoPrioridadesService.php', <<<'PHP'
<?php
final class ProcessoPrioridadesService {
    public function __construct(PDO $pdo) {}
    public function getEstadoEdicaoPorProcessoId(string $id, int $n): array { return ['processo_id' => $id]; }
    public function removerPorCodigoOuRotulo(string $id, string $codigo, int $n): void { file_put_contents(getenv('TEST_DISPATCH_MARKER'), 'remove'); }
}
PHP);
    $put('app/repositories/ProcessosRepository.php', <<<'PHP'
<?php
final class ProcessosRepository {
    public function __construct(PDO $pdo) {}
    public function findByNumero(string $numero): ?array { return getenv('TEST_PROCESS_EXISTS') === '1' ? ['id' => 'P1', 'numero_processo' => $numero] : null; }
    public function findById(string $id): ?array { return getenv('TEST_PROCESS_EXISTS') === '1' && $id === 'P1' ? ['id' => $id] : null; }
}
PHP);
    $put('app/repositories/ProcessoPrioridadesRepository.php', '<?php final class ProcessoPrioridadesRepository { public function __construct(PDO $pdo) {} public function findByProcessoId(string $id): array { return []; } }');
    $put('app/helpers/PrioridadesProcessuaisCatalog.php', '<?php final class PrioridadesProcessuaisCatalog { public static function labelForCodigo(string $codigo): string { return $codigo; } }');

    $cases = [
        // Listagem de edição: somente usuários com salapautaedit.
        ['prioridades-list.php', '0', 'SERVIDOR-AUDIENCIAS', 'GET', 'http://example.invalid', ['processo_id' => 'P1'], 401, false, false],
        ['prioridades-list.php', '1', 'CONSULTOR', 'GET', 'http://example.invalid', ['processo_id' => 'P1'], 403, false, false],
        ['prioridades-list.php', '1', 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', ['processo_id' => 'P1'], 403, false, false],
        ['prioridades-list.php', '1', 'SERVIDOR-AUDIENCIAS', 'POST', 'http://example.invalid', ['processo_id' => 'P1'], 405, false, false],
        ['prioridades-list.php', '1', 'SERVIDOR-AUDIENCIAS', 'GET', 'http://example.invalid', ['processo_id' => 'P1'], 200, true, false, true],
        ['prioridades-list.php', '1', 'SERVIDOR-AUDIENCIAS', 'GET', 'http://example.invalid', ['processo_id' => 'MISSING'], 404, true, false, false],
        // Leitura por CNJ na criação de carta: Cartório, somente GET.
        ['prioridades-read.php', '0', 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', ['numero' => '12345678901234567890'], 401, false, false],
        ['prioridades-read.php', '1', 'ESTUDANTE-AUDIENCIAS', 'GET', 'http://example.invalid', ['numero' => '12345678901234567890'], 403, false, false],
        ['prioridades-read.php', '1', 'SERVIDOR-AUDIENCIAS', 'GET', 'http://example.invalid', ['numero' => '12345678901234567890'], 403, false, false],
        ['prioridades-read.php', '1', 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', ['numero' => '12345678901234567890'], 405, false, false],
        ['prioridades-read.php', '1', 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', ['numero' => '12345678901234567890'], 200, true, false, true],
        ['prioridades-read.php', '1', 'SERVIDOR-CARTORIO', 'GET', 'http://example.invalid', ['numero' => '12345678901234567890'], 404, true, false, false],
        // Remoção exige salapautaedit e POST confiável.
        ['prioridades-remove.php', '0', 'SERVIDOR-AUDIENCIAS', 'POST', 'http://example.invalid', ['processo_id' => 'P1', 'codigo' => '1'], 401, false, false],
        ['prioridades-remove.php', '1', 'CONSULTOR', 'POST', 'http://example.invalid', ['processo_id' => 'P1', 'codigo' => '1'], 403, false, false],
        ['prioridades-remove.php', '1', 'SERVIDOR-CARTORIO', 'POST', 'http://example.invalid', ['processo_id' => 'P1', 'codigo' => '1'], 403, false, false],
        ['prioridades-remove.php', '1', 'SERVIDOR-AUDIENCIAS', 'GET', 'http://example.invalid', ['processo_id' => 'P1', 'codigo' => '1'], 403, false, false],
        ['prioridades-remove.php', '1', 'SERVIDOR-AUDIENCIAS', 'POST', 'http://evil.invalid', ['processo_id' => 'P1', 'codigo' => '1'], 403, false, false, true],
        ['prioridades-remove.php', '1', 'SERVIDOR-AUDIENCIAS', 'POST', 'http://example.invalid', [], 400, false, false, true],
    ];

    foreach ($cases as $case) {
        [$endpoint, $auth, $profile, $method, $origin, $query, $expected, $connectionExpected, $dispatchExpected] = array_slice($case, 0, 9);
        $processExists = $case[9] ?? true;
        $dbMarker = $root . '/db-marker';
        $dispatchMarker = $root . '/dispatch-marker';
        foreach ([$dbMarker, $dispatchMarker] as $marker) if (is_file($marker)) unlink($marker);
        $path = $root . '/public/api/processos/' . $endpoint;
        $payload = $endpoint === 'prioridades-remove.php' ? $query : [];
        $code = 'putenv(' . var_export('TEST_AUTH=' . $auth, true) . '); putenv(' . var_export('TEST_PROFILE=' . $profile, true) . '); putenv(' . var_export('TEST_PROCESS_EXISTS=' . ($processExists ? '1' : '0'), true) . '); putenv(' . var_export('TEST_DB_MARKER=' . $dbMarker, true) . '); putenv(' . var_export('TEST_DISPATCH_MARKER=' . $dispatchMarker, true) . '); $_SESSION=["id_usuario"=>"synthetic"]; $_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . '; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]=' . var_export($origin, true) . '; $_GET=' . var_export($query, true) . '; $_POST=' . var_export($payload, true) . '; http_response_code(200); register_shutdown_function(function(){fwrite(STDERR,"HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) throw new RuntimeException('Falha ao iniciar PHP.');
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($proc);
        if ($status !== 0 || !str_contains($err, 'HTTP:' . $expected)
            || is_file($dbMarker) !== $connectionExpected || is_file($dispatchMarker) !== $dispatchExpected) {
            throw new RuntimeException("Falha $endpoint/$profile/$method: expectedHTTP=$expected db=" . (is_file($dbMarker) ? 'yes' : 'no') . "/$connectionExpected dispatch=" . (is_file($dispatchMarker) ? 'yes' : 'no') . "/$dispatchExpected; status=$status; stderr=$err; stdout=$out");
        }
    }
    echo 'PASS prioridades processuais (' . count($cases) . " cenários; sessão/perfil sintéticos e PDO inerte)\n";
} finally {
    foreach ([$root . '/db-marker', $root . '/dispatch-marker'] as $marker) if (is_file($marker)) unlink($marker);
    foreach (array_reverse($files) as $file) if (is_file($file)) unlink($file);
    if (is_dir($root)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) if ($dir->isDir()) rmdir($dir->getPathname());
        rmdir($root);
    }
}
