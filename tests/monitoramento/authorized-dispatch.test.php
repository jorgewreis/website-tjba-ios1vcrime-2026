<?php
declare(strict_types=1);

// Executes copies of the actual endpoints and guard with an inert service;
// no configuration, database, network or real user data are accessed.
$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'monitoramento-dispatch-' . bin2hex(random_bytes(16));
$files = [];
$put = static function (string $relative, string $content) use ($tmp, &$files): void {
    $path = $tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Falha ao criar fixture.');
    }
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Falha ao gravar fixture.');
    }
    $files[] = $path;
};

try {
    foreach (['create.php', 'concluir-subtarefa.php', 'cancel.php', 'delete.php', 'reus-partes.php'] as $endpoint) {
        $path = 'public/api/gabinete-monitoramento-eletronico/' . $endpoint;
        $put($path, (string) file_get_contents($root . '/' . $path));
    }
    foreach (['app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php', 'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php'] as $path) {
        $put($path, (string) file_get_contents($root . '/' . $path));
    }
    $put('app/helpers/AutoloadHelper.php', '<?php');
    $put('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return false; } final class Conection { public static function getInstance(): self { return new self(); } public function getConnection(): PDO { return new class extends PDO { public function __construct() {} }; } }');
    $put('config/security.php', '<?php final class Security { public static function isAuthenticated(): bool { return true; } }');
    $put('app/helpers/UsuarioPerfisAutorizacao.php', '<?php final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv("MOCK_PROFILE") ?: "SERVIDOR-GABINETE"]; } }');
    $put('app/services/GabineteMonitoramentoEletronicoService.php', <<<'PHP'
<?php
final class GabineteMonitoramentoEletronicoService {
    public function __construct(PDO $pdo) {}
    private function record(string $method, array $args): void { file_put_contents(getenv('DISPATCH_FILE'), json_encode([$method, $args])); }
    public function create(array $data): string { $this->record('create', [$data]); return 'synthetic'; }
    public function concluirSubtarefa(string $id, ?string $date, string $user): array { $this->record('concluir', [$id, $date, $user]); return []; }
    public function cancel(string $id): bool { $this->record('cancel', [$id]); return true; }
    public function delete(string $id): array { $this->record('delete', [$id]); return ['deleted' => true]; }
    public function listarReusDoProcesso(string $id): array { $this->record('listar', [$id]); return []; }
    public function ensureProcessoEListarReusParaCadastro(string $numero, string $codigo, string $descricao): array { $this->record('sincronizar', [$numero, $codigo, $descricao]); return ['processo_id' => 'PROC-SINTETICO', 'reus' => []]; }
}
PHP);
    $cases = [
        'create.php' => [['nome' => 'sintetico', 'usuario_cadastro_id' => 'forged-user'], 'create', 201],
        'concluir-subtarefa.php' => [['subtarefa_id' => 'SUB1', 'data_inicio' => '2024-01-01'], 'concluir', 200],
        'cancel.php' => [['id' => 'MON1'], 'cancel', 200],
        'delete.php' => [['id' => 'MON1'], 'delete', 200],
        'reus-partes.php' => [['processo_id' => 'PROC1'], 'listar', 200],
    ];
    foreach ($cases as $endpoint => [$payload, $method, $expectedStatus]) {
        $record = $tmp . '/dispatch.json';
        if (is_file($record)) { unlink($record); }
        $path = $tmp . '/public/api/gabinete-monitoramento-eletronico/' . $endpoint;
        $code = 'putenv(' . var_export('DISPATCH_FILE=' . $record, true) . '); $_SESSION=["id_usuario"=>"synthetic-user"]; $_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]="http://example.invalid"; $_POST=' . var_export($payload, true) . '; http_response_code(200); register_shutdown_function(function(){fwrite(STDERR,"HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar processo.'); }
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($proc) !== 0 || !str_contains($err, 'HTTP:' . $expectedStatus) || !is_file($record)) {
            throw new RuntimeException("Falha no despacho {$endpoint}: {$err} {$out}");
        }
        $actual = json_decode((string) file_get_contents($record), true, 512, JSON_THROW_ON_ERROR);
        if ($actual[0] !== $method || ($method === 'concluir' && $actual[1][2] !== 'synthetic-user') || ($method === 'create' && ($actual[1][0]['usuario_cadastro_id'] ?? null) !== 'synthetic-user')) {
            throw new RuntimeException("Argumentos incorretos em {$endpoint}.");
        }
        unlink($record);
    }
    // The consultation dispatches a potentially mutating sync only after the
    // real guard accepts each existing authorized internal profile and POST.
    foreach (['SERVIDOR-GABINETE', 'ESTAGIARIO-GABINETE', 'SERVIDOR-CARTORIO', 'ESTAGIARIO-CARTORIO', 'SERVIDOR-AUDIENCIAS', 'ESTAGIARIO-AUDIENCIAS', 'ADMIN', 'CHEFIA'] as $profile) {
        $record = $tmp . '/dispatch.json';
        $path = $tmp . '/public/api/gabinete-monitoramento-eletronico/reus-partes.php';
        $code = 'putenv(' . var_export('DISPATCH_FILE=' . $record, true) . '); putenv(' . var_export('MOCK_PROFILE=' . $profile, true) . '); $_SESSION=["id_usuario"=>"synthetic-user"]; $_SERVER["REQUEST_METHOD"]="POST"; $_SERVER["HTTP_HOST"]="example.invalid"; $_SERVER["HTTP_ORIGIN"]="http://example.invalid"; $_POST=["numero_processo"=>"0000001-69.2020.8.05.0001"]; http_response_code(200); register_shutdown_function(function(){fwrite(STDERR,"HTTP:".http_response_code());}); require ' . var_export($path, true) . ';';
        $proc = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('Falha ao iniciar processo.'); }
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($proc) !== 0 || !str_contains($err, 'HTTP:200') || !is_file($record)) {
            throw new RuntimeException("Falha na consulta {$profile}: {$err} {$out}");
        }
        $actual = json_decode((string) file_get_contents($record), true, 512, JSON_THROW_ON_ERROR);
        if ($actual[0] !== 'sincronizar') { throw new RuntimeException("Consulta não despachada para {$profile}."); }
        unlink($record);
    }
    echo "PASS despacho autorizado dos cinco endpoints (serviço simulado)\n";
} finally {
    if (is_file($tmp . '/dispatch.json')) { unlink($tmp . '/dispatch.json'); }
    foreach (array_reverse($files) as $file) { if (is_file($file)) { unlink($file); } }
    if (is_dir($tmp)) {
        $dirs = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($dirs as $dir) { if ($dir->isDir()) { rmdir($dir->getPathname()); } }
        rmdir($tmp);
    }
}
