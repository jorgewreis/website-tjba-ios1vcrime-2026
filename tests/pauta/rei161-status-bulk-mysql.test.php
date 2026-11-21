<?php
declare(strict_types=1);

// Full endpoint HTTP test against a disposable MySQL 8.4 instance.
// Auth/profile classes are deliberately synthetic; query, joins, bindings,
// projection and JSON response are the real REI-161 endpoint code.

function rei161Assert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$dsn = getenv('REI161_MYSQL_DSN');
$password = getenv('REI161_MYSQL_PASSWORD');
$marker = getenv('REI161_RUN_MARKER');
rei161Assert(is_string($dsn) && str_starts_with($dsn, 'mysql:host=127.0.0.1;port=3306;dbname=rei161_test;'), 'MySQL DSN descartável inválido.');
rei161Assert(is_string($password) && preg_match('/^[a-f0-9]{64}$/D', $password) === 1, 'Senha efêmera inválida.');
rei161Assert(is_string($marker) && preg_match('/^[a-f0-9]{64}$/D', $marker) === 1, 'Marcador efêmero inválido.');

$db = new PDO($dsn, 'root', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
rei161Assert($db->query('SELECT DATABASE()')->fetchColumn() === 'rei161_test', 'Banco não é o schema descartável esperado.');
$runnerGuard = $db->prepare('SELECT marker FROM rei161_runner_guard WHERE marker = ?');
$runnerGuard->execute([$marker]);
rei161Assert(hash_equals($marker, (string) $runnerGuard->fetchColumn()), 'Marcador do runner não prova este banco descartável.');
$db->exec("CREATE TABLE processos (id VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY, sigiloso TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB");
$db->exec("CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY, processo VARCHAR(80), status VARCHAR(32), data_audiencia DATE, hora_audiencia TIME, data_hora_inicio_efetivo DATETIME NULL, data_hora_fim_efetivo DATETIME NULL, duracao_estimada_minutos INT NOT NULL DEFAULT 0, ativo TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB");
$db->exec('CREATE TABLE sala_audiencias_estudante_inscricao (id BIGINT AUTO_INCREMENT PRIMARY KEY, pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, usuario VARCHAR(80)) ENGINE=InnoDB');
$db->exec("CREATE TABLE sala_audiencias_pauta_parte (id BIGINT AUTO_INCREMENT PRIMARY KEY, pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, ativo TINYINT NOT NULL DEFAULT 1, maioridade VARCHAR(32)) ENGINE=InnoDB");
$db->exec("CREATE TABLE processo_prioridades (id BIGINT AUTO_INCREMENT PRIMARY KEY, processo_id VARCHAR(80), codigo VARCHAR(80)) ENGINE=InnoDB");

$process = $db->prepare('INSERT INTO processos (id, sigiloso) VALUES (?, ?)');
foreach ([['P-OPEN', 0], ['P-SECRET', 1], ['P-MINOR', 0], ['P-PRIORITY', 0], ['P-OBS', 0]] as [$id, $secret]) {
    $process->execute([$id, $secret]);
}
$pauta = $db->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, processo, status, data_audiencia, hora_audiencia, ativo, duracao_estimada_minutos) VALUES (?, ?, 'designada', '2099-01-01', '10:00:00', ?, 45)");
foreach ([['OPEN', 'P-OPEN', 1], ['SECRET', 'P-SECRET', 1], ['MINOR', 'P-MINOR', 1], ['PRIORITY', 'P-PRIORITY', 1], ['OBSERVED_OPEN', 'P-OBS', 1], ['INACTIVE', 'P-OPEN', 0], ['WATCH', 'P-OPEN', 1]] as [$id, $proc, $active]) {
    $pauta->execute([$id, $proc, $active]);
}
$part = $db->prepare("INSERT INTO sala_audiencias_pauta_parte (pauta, ativo, maioridade) VALUES ('MINOR', 1, 'menor')");
$part->execute();
$priority = $db->prepare("INSERT INTO processo_prioridades (processo_id, codigo) VALUES ('P-PRIORITY', 'medida_protetiva')");
$priority->execute();
$enroll = $db->prepare("INSERT INTO sala_audiencias_estudante_inscricao (pauta, usuario) VALUES (?, 'synthetic-student')");
$enroll->execute(['SECRET']);
$enroll->execute(['OBSERVED_OPEN']);

// Populate 60 request IDs plus 15 session-only observation IDs to exercise
// the real MySQL statement's 80 bindings (priorities + user + derived IDs).
$many = $db->prepare("INSERT INTO processos (id, sigiloso) VALUES (?, 0)");
$manyPauta = $db->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, processo, status, data_audiencia, hora_audiencia, ativo, duracao_estimada_minutos) VALUES (?, ?, 'designada', '2099-01-01', '10:00:00', 1, 30)");
for ($i = 0; $i < 75; $i++) {
    $n = str_pad((string) ($i < 60 ? $i : $i - 60), 2, '0', STR_PAD_LEFT);
    $id = ($i < 60 ? 'REQ' : 'OBS') . $n;
    $processId = 'P-' . $id;
    $many->execute([$processId]);
    $manyPauta->execute([$id, $processId]);
}
$enroll->execute(['OBS00']);

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rei161-http-' . bin2hex(random_bytes(10));
$sessionPath = $root . DIRECTORY_SEPARATOR . 'sessions';
$public = $root . DIRECTORY_SEPARATOR . 'public';
$endpoint = $public . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'sala-audiencias-pauta' . DIRECTORY_SEPARATOR . 'status-bulk.php';
$config = $root . DIRECTORY_SEPARATOR . 'config';
$appHelpers = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers';
$appModels = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'models';
$appAccess = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'access';
$appServices = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'services';
foreach ([$sessionPath, dirname($endpoint), $config, $appHelpers, $appModels, $appAccess, $appServices] as $dir) {
    rei161Assert(mkdir($dir, 0700, true) || is_dir($dir), 'Não foi possível criar a fixture temporária.');
}
$sourceEndpoint = getenv('REI161_ENDPOINT_SOURCE');
$sourcePrivacy = getenv('REI161_PRIVACY_SOURCE');
rei161Assert(is_string($sourceEndpoint) && is_file($sourceEndpoint), 'Endpoint fonte ausente.');
rei161Assert(is_string($sourcePrivacy) && is_file($sourcePrivacy), 'Helper de privacidade fonte ausente.');
rei161Assert(copy($sourceEndpoint, $endpoint), 'Não foi possível copiar endpoint para fixture isolada.');
rei161Assert(copy($sourcePrivacy, $appHelpers . DIRECTORY_SEPARATOR . 'SalaAudienciasEstudantePrivacidade.php'), 'Não foi possível copiar helper de privacidade.');

$stubs = [
    $appHelpers . DIRECTORY_SEPARATOR . 'Conection.php' => <<<'PHP'
<?php
final class Conection { public static function getInstance(): self { return new self(); } public function getConnection(): PDO { return new PDO((string)getenv('REI161_MYSQL_DSN'), 'root', (string)getenv('REI161_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]); } }
PHP,
    $appHelpers . DIRECTORY_SEPARATOR . 'BootstrapHelper.php' => <<<'PHP'
<?php
function bootstrapRequireTrustedPostRequest(): bool { return true; }
PHP,
    $config . DIRECTORY_SEPARATOR . 'security.php' => <<<'PHP'
<?php
final class Security { public static function isAuthenticatedWithoutActivity(): bool { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); $_SESSION['id_usuario'] = 'synthetic-student'; $_SESSION['rei161_observacoes'] ??= []; $_SESSION['rei161_observacoes'][str_repeat('b', 32)] = ['usuario'=>'synthetic-student','ids'=>['OBSERVED_OPEN'],'created'=>time()]; $_SESSION['rei161_observacoes'][str_repeat('c', 32)] = ['usuario'=>'synthetic-student','ids'=>array_map(static fn($i)=>'OBS'.str_pad((string)$i,2,'0',STR_PAD_LEFT), range(0,14)),'created'=>time()]; return true; } }
PHP,
    $appHelpers . DIRECTORY_SEPARATOR . 'UsuarioPerfisAutorizacao.php' => <<<'PHP'
<?php
final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return ['ESTUDANTE']; } }
PHP,
    $appModels . DIRECTORY_SEPARATOR . 'Usuario.php' => <<<'PHP'
<?php
final class Usuario { public function listarPerfisUsuario(string $id): array { return ['ESTUDANTE']; } }
PHP,
    $appAccess . DIRECTORY_SEPARATOR . 'AccessEvaluator.php' => <<<'PHP'
<?php
final class AccessEvaluator { public static function canAccessPage(string $page, array $profiles): bool { return $page === 'estudapainel'; } }
PHP,
    $appAccess . DIRECTORY_SEPARATOR . 'ProfileNormalizer.php' => <<<'PHP'
<?php
final class ProfileNormalizer { public static function normalize(array $profiles): array { return $profiles; } public static function hasEstudante(array $profiles): bool { return in_array('ESTUDANTE', $profiles, true); } }
PHP,
    $appHelpers . DIRECTORY_SEPARATOR . 'UserAccessHelper.php' => <<<'PHP'
<?php
final class UserAccessHelper { public static function isUsuarioExterno(array $profiles): bool { return true; } }
PHP,
    $appServices . DIRECTORY_SEPARATOR . 'SalaAudienciasAtaService.php' => <<<'PHP'
<?php
final class SalaAudienciasAtaService { public const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA = 10; }
PHP,
    $appHelpers . DIRECTORY_SEPARATOR . 'PrioridadesProcessuaisCatalog.php' => <<<'PHP'
<?php
final class PrioridadesProcessuaisCatalog { public static function codigosBloqueantesInscricaoEstudante(): array { return ['sigiloso','crianca_adolescente','violencia_domestica','medida_protetiva']; } }
PHP,
    $appHelpers . DIRECTORY_SEPARATOR . 'SalaAudienciasAtaSigiloHelper.php' => <<<'PHP'
<?php
final class SalaAudienciasAtaSigiloHelper { public static function sqlProcessoSigilosoEfetivo(string $p='p', string $a='a'): string { return "CASE WHEN COALESCE($p.`sigiloso`,0)=1 OR EXISTS (SELECT 1 FROM `processo_prioridades` pri WHERE pri.`processo_id`=$a.`processo` AND pri.`codigo`='sigiloso') THEN 1 ELSE 0 END"; } }
PHP,
];
foreach ($stubs as $path => $content) {
    rei161Assert(file_put_contents($path, $content) !== false, 'Falha ao criar dependência sintética.');
}

$portSocket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
rei161Assert(is_resource($portSocket), 'Não foi possível reservar porta local efêmera.');
$address = stream_socket_get_name($portSocket, false);
fclose($portSocket);
$port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
$command = [PHP_BINARY, '-d', 'session.save_path=' . $sessionPath, '-d', 'session.use_strict_mode=0', '-S', '127.0.0.1:' . $port, '-t', $public];
$pipes = [];
$server = proc_open($command, [0 => ['pipe','r'], 1 => ['file', $root . '/server.out', 'a'], 2 => ['file', $root . '/server.err', 'a']], $pipes, $root, null);
rei161Assert(is_resource($server), 'Servidor PHP sintético não iniciou.');

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($socket)) { fclose($socket); $ready = true; break; }
        usleep(100000);
    }
    rei161Assert($ready, 'Servidor PHP sintético não ficou pronto.');
    $url = 'http://127.0.0.1:' . $port . '/api/sala-audiencias-pauta/status-bulk.php';
    $request = static function (array $payload) use ($url): array {
        $context = stream_context_create(['http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\nCookie: PHPSESSID=rei161synthetic\r\n",'content'=>json_encode($payload, JSON_UNESCAPED_UNICODE),'ignore_errors'=>true,'timeout'=>5]]);
        $body = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) $status = (int) $match[1];
        return [$status, json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR)];
    };

    [$status, $body] = $request(['pautas'=>['OPEN','SECRET','MINOR','PRIORITY','INACTIVE','MISSING']]);
    rei161Assert($status === 200 && ($body['success'] ?? false) === true, 'Resposta HTTP/MySQL inesperada.');
    rei161Assert(isset($body['items']['OPEN']['pode_inscrever']) && $body['items']['OPEN']['pode_inscrever'] === true, 'Pauta sintética elegível não foi projetada corretamente.');
    foreach (['SECRET','MINOR','PRIORITY'] as $id) rei161Assert(($body['items'][$id] ?? null) === ['id_pauta'=>$id,'indisponivel'=>true], 'Resposta sensível não foi minimizada para ' . $id . '.');
    rei161Assert(!isset($body['items']['INACTIVE'], $body['items']['MISSING']), 'Pauta inativa/inexistente apareceu no resultado.');
    rei161Assert(!str_contains(json_encode($body), 'processo_sigiloso') && !str_contains(json_encode($body), 'qtd_menor') && !str_contains(json_encode($body), 'medida_protetiva'), 'Resposta revelou atributo sensível.');

    [$status, $body] = $request(['pautas'=>['watch']]);
    rei161Assert($status === 200 && isset($body['items']['watch']) && $body['items']['watch']['id_pauta'] === 'watch', 'JOIN case-insensitive não preservou a grafia solicitada.');
    $db->exec('ALTER TABLE sala_audiencias_pauta CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    [$status, $body] = $request(['pautas'=>['watch']]);
    rei161Assert($status === 200 && !isset($body['items']['watch']), 'Collation binária associou IDs distintos apenas por caixa.');
    $db->exec('ALTER TABLE sala_audiencias_pauta CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

    [$status, $body] = $request(['pautas'=>[], 'observacao'=>str_repeat('b', 32)]);
    rei161Assert($status === 200 && ($body['observacao_alterada'] ?? false) === true && ($body['items'] ?? []) === [] && !str_contains(json_encode($body), 'OBSERVED_OPEN'), 'ID observado escapou para resposta ou alteração não foi sinalizada.');
    [$status, $body] = $request(['pautas'=>['OBS00'], 'observacao'=>str_repeat('c', 32)]);
    rei161Assert($status === 200 && ($body['items']['OBS00']['ja_inscrito'] ?? false) === true && ($body['observacao_alterada'] ?? false) === true, 'Inscrição MySQL dos 15 IDs não foi detectada (HTTP ' . $status . ', body=' . json_encode($body, JSON_UNESCAPED_UNICODE) . ').');
    $requested = array_map(static fn($i)=>'REQ'.str_pad((string)$i,2,'0',STR_PAD_LEFT), range(0,59));
    [$status, $body] = $request(['pautas'=>$requested, 'observacao'=>str_repeat('c', 32)]);
    rei161Assert($status === 200 && count($body['items'] ?? []) === 60 && ($body['observacao_alterada'] ?? false) === true, 'Consulta combinada de 75 IDs e bindings MySQL incorreta (HTTP ' . $status . ', items=' . count($body['items'] ?? []) . ', observacao=' . (string) json_encode($body['observacao_alterada'] ?? null) . ', inscricoes=' . (string) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta='OBS00' AND usuario='synthetic-student'")->fetchColumn() . ').');
    rei161Assert(!str_contains(json_encode($body), 'OBS00') && count($body['consultados'] ?? []) === 60, 'IDs da sessão apareceram na resposta pública.');
    [$status, $body] = $request(['pautas'=>array_fill(0,61,'OPEN')]);
    rei161Assert($status === 413 && ($body['success'] ?? true) === false, 'Limite de IDs não foi aplicado no endpoint integrado.');
    [$status, $body] = $request(['pautas'=>[], 'padding'=>str_repeat('x',16385)]);
    rei161Assert($status === 413 && ($body['success'] ?? true) === false, 'Limite do corpo não foi aplicado no endpoint integrado.');

    $guardCheck = $db->prepare('SELECT marker FROM rei161_runner_guard WHERE marker = ?');
    $guardCheck->execute([$marker]);
    rei161Assert(hash_equals($marker, (string) $guardCheck->fetchColumn()), 'Marcador de banco descartável não confere ao final.');
    echo "PASS REI-161 endpoint/MySQL 8.4: fixtures sintéticas, join/collations, binds 80, resposta mínima, observações e limites\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
