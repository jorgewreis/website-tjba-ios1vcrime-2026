<?php
declare(strict_types=1);

date_default_timezone_set('America/Bahia');

// Full endpoint HTTP test against a disposable MySQL 8.4 instance.
// Security, Usuario, profile and access classes are real. Database and sessions are synthetic.
// Bootstrap and database connection remain synthetic; this is not a full production integration.

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
$db->exec("CREATE TABLE usuarios (id_usuario VARCHAR(80) PRIMARY KEY, token_acesso VARCHAR(64), token_expira DATETIME, cadastro_ativo TINYINT) ENGINE=InnoDB");
$db->exec("CREATE TABLE usuario_perfil (usuario VARCHAR(80), perfil VARCHAR(80)) ENGINE=InnoDB");
$token = str_repeat('a', 64);
$insertUser = $db->prepare("INSERT INTO usuarios VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 1)");
foreach (['synthetic-student', 'synthetic-student-other', 'synthetic-internal', 'synthetic-mixed', 'synthetic-consultant', 'synthetic-expired', 'synthetic-inactive', 'synthetic-invalid-token'] as $user) $insertUser->execute([$user, hash('sha256', $token)]);
$db->exec("UPDATE usuarios SET token_expira = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id_usuario = 'synthetic-expired'");
$db->exec("UPDATE usuarios SET cadastro_ativo = 0 WHERE id_usuario = 'synthetic-inactive'");
$db->exec("UPDATE usuarios SET token_acesso = REPEAT('b', 64) WHERE id_usuario = 'synthetic-invalid-token'");
$db->exec("INSERT INTO usuario_perfil VALUES ('synthetic-student','ESTUDANTE'), ('synthetic-student-other','ESTUDANTE'), ('synthetic-internal','CARTORIO'), ('synthetic-mixed','ESTUDANTE'), ('synthetic-mixed','CARTORIO'), ('synthetic-consultant','CONSULTOR')");
$db->exec("CREATE TABLE processos (id VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY, sigiloso TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB");
$db->exec("CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci PRIMARY KEY, processo VARCHAR(80), status VARCHAR(32), data_audiencia DATE, hora_audiencia TIME, data_hora_inicio_efetivo DATETIME NULL, data_hora_fim_efetivo DATETIME NULL, duracao_estimada_minutos INT NOT NULL DEFAULT 0, ativo TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB");
$db->exec('CREATE TABLE sala_audiencias_estudante_inscricao (id BIGINT AUTO_INCREMENT PRIMARY KEY, pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, usuario VARCHAR(80)) ENGINE=InnoDB');
$db->exec("CREATE TABLE sala_audiencias_pauta_parte (id BIGINT AUTO_INCREMENT PRIMARY KEY, pauta VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci, ativo TINYINT NOT NULL DEFAULT 1, maioridade VARCHAR(32)) ENGINE=InnoDB");
$db->exec("CREATE TABLE processo_prioridades (id BIGINT AUTO_INCREMENT PRIMARY KEY, processo_id VARCHAR(80), codigo VARCHAR(80)) ENGINE=InnoDB");

$process = $db->prepare('INSERT INTO processos (id, sigiloso) VALUES (?, ?)');
foreach ([['P-OPEN', 0], ['P-SECRET', 1], ['P-MINOR', 0], ['P-PRIORITY', 0], ['P-SIG-PRIORITY', 0], ['P-CHILD-PRIORITY', 0], ['P-DV-PRIORITY', 0], ['P-NONBLOCKING', 0], ['P-OBS', 0]] as [$id, $secret]) {
    $process->execute([$id, $secret]);
}
// Use the same real Bahia clock as the service. Near midnight, wait at most
// one minute so a run started at 23:59 does not fail just because of the date.
while (date('H:i') === '23:59') {
    sleep(min(30, max(1, strtotime('tomorrow') - time())));
}
$hoje = date('Y-m-d');
$dataFixa = DateTimeImmutable::createFromFormat('!Y-m-d', $hoje);
rei161Assert($dataFixa instanceof DateTimeImmutable && $dataFixa->format('Y-m-d') === $hoje, 'Data fixa da fixture não é válida.');
$amanha = $dataFixa->modify('+1 day')->format('Y-m-d');
$ontem = $dataFixa->modify('-1 day')->format('Y-m-d');
$pauta = $db->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, processo, status, data_audiencia, hora_audiencia, ativo, duracao_estimada_minutos) VALUES (?, ?, 'designada', ?, '23:59:59', ?, 45)");
foreach ([['OPEN', 'P-OPEN', $hoje, 1], ['SECRET', 'P-SECRET', $hoje, 1], ['MINOR', 'P-MINOR', $hoje, 1], ['PRIORITY', 'P-PRIORITY', $hoje, 1], ['SIG_PRIORITY', 'P-SIG-PRIORITY', $hoje, 1], ['CHILD_PRIORITY', 'P-CHILD-PRIORITY', $hoje, 1], ['DV_PRIORITY', 'P-DV-PRIORITY', $hoje, 1], ['NONBLOCKING', 'P-NONBLOCKING', $hoje, 1], ['OBSERVED_OPEN', 'P-OBS', $hoje, 1], ['INACTIVE', 'P-OPEN', $hoje, 0], ['WATCH', 'P-OPEN', $hoje, 1], ['CLOSED', 'P-OPEN', $hoje, 1], ['CLOSED_ENROLLED', 'P-OPEN', $hoje, 1], ['IN_PROGRESS', 'P-OPEN', $hoje, 1], ['IN_PROGRESS_ENROLLED', 'P-OPEN', $hoje, 1], ['DONE', 'P-OPEN', $hoje, 1], ['DONE_ENROLLED', 'P-OPEN', $hoje, 1], ['FINISHED', 'P-OPEN', $hoje, 1], ['FINISHED_ENROLLED', 'P-OPEN', $hoje, 1], ['RESCHEDULED', 'P-OPEN', $hoje, 1], ['RESCHEDULED_ENROLLED', 'P-OPEN', $hoje, 1], ['POSTPONED_ALIAS', 'P-OPEN', $hoje, 1], ['CLOSED_ALIAS', 'P-OPEN', $hoje, 1], ['FUTURE', 'P-OPEN', $amanha, 1], ['FUTURE_ENROLLED', 'P-OPEN', $amanha, 1], ['PAST', 'P-OPEN', $ontem, 1], ['PAST_ENROLLED', 'P-OPEN', $ontem, 1], ['LATE', 'P-OPEN', $ontem, 1]] as [$id, $proc, $data, $active]) {
    $pauta->execute([$id, $proc, $data, $active]);
}
// Keep LATE as designada with an elapsed date; the isolated endpoint exercises its status derivation.
$db->exec("UPDATE sala_audiencias_pauta SET status = 'cancelada' WHERE id_pauta IN ('CLOSED', 'CLOSED_ENROLLED')");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'em_andamento' WHERE id_pauta IN ('IN_PROGRESS', 'IN_PROGRESS_ENROLLED')");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'realizada' WHERE id_pauta IN ('DONE', 'DONE_ENROLLED')");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'finalizada' WHERE id_pauta IN ('FINISHED', 'FINISHED_ENROLLED')");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'redesignada' WHERE id_pauta IN ('RESCHEDULED', 'RESCHEDULED_ENROLLED')");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'adiada' WHERE id_pauta = 'POSTPONED_ALIAS'");
$db->exec("UPDATE sala_audiencias_pauta SET status = 'encerrada' WHERE id_pauta = 'CLOSED_ALIAS'");
$part = $db->prepare("INSERT INTO sala_audiencias_pauta_parte (pauta, ativo, maioridade) VALUES ('MINOR', 1, 'menor')");
$part->execute();
$priority = $db->prepare("INSERT INTO processo_prioridades (processo_id, codigo) VALUES ('P-PRIORITY', 'medida_protetiva')");
$priority->execute();
$db->exec("INSERT INTO processo_prioridades (processo_id, codigo) VALUES ('P-SIG-PRIORITY','sigiloso'), ('P-CHILD-PRIORITY','crianca_adolescente'), ('P-DV-PRIORITY','violencia_domestica'), ('P-NONBLOCKING','reu_preso')");
$enroll = $db->prepare("INSERT INTO sala_audiencias_estudante_inscricao (pauta, usuario) VALUES (?, 'synthetic-student')");
$enroll->execute(['SECRET']);
$enroll->execute(['MINOR']);
$enroll->execute(['PRIORITY']);
foreach (['SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY'] as $id) $enroll->execute([$id]);
$enroll->execute(['OBSERVED_OPEN']);
$enroll->execute(['CLOSED_ENROLLED']);
$enroll->execute(['IN_PROGRESS_ENROLLED']);
$enroll->execute(['DONE_ENROLLED']);
$enroll->execute(['FINISHED_ENROLLED']);
$enroll->execute(['RESCHEDULED_ENROLLED']);
$enroll->execute(['POSTPONED_ALIAS']);
$enroll->execute(['CLOSED_ALIAS']);
$enroll->execute(['FUTURE_ENROLLED']);
$enroll->execute(['PAST_ENROLLED']);

// Capacity boundaries with and without the requesting student's enrollment.
$capacityInsert=$db->prepare("INSERT INTO sala_audiencias_estudante_inscricao (pauta, usuario) VALUES (?, ?)");
foreach (['CAP4'=>4,'CAP5'=>5,'CAP6'=>6,'CAP5I'=>5,'CAP6I'=>6] as $id=>$count) {
    $pauta->execute([$id, 'P-OPEN', $hoje, 1]);
    for ($n=0; $n<$count; $n++) $capacityInsert->execute([$id, str_ends_with($id,'I') && $n===0 ? 'synthetic-student' : 'synthetic-other-'.$n]);
}

// Populate 60 request IDs plus 15 session-only observation IDs to exercise
// the real MySQL statement's 80 bindings (priorities + user + derived IDs).
$many = $db->prepare("INSERT INTO processos (id, sigiloso) VALUES (?, 0)");
$manyPauta = $db->prepare("INSERT INTO sala_audiencias_pauta (id_pauta, processo, status, data_audiencia, hora_audiencia, ativo, duracao_estimada_minutos) VALUES (?, ?, 'designada', ?, '23:59:59', 1, 30)");
for ($i = 0; $i < 75; $i++) {
    $n = str_pad((string) ($i < 60 ? $i : $i - 60), 2, '0', STR_PAD_LEFT);
    $id = ($i < 60 ? 'REQ' : 'OBS') . $n;
    $processId = 'P-' . $id;
    $many->execute([$processId]);
    $manyPauta->execute([$id, $processId, $hoje]);
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

];
$sourceRoot = getenv('REI161_REPO_SOURCE');
rei161Assert($sourceRoot === '/workspace', 'Raiz da aplicação sintética inválida.');
$realFiles = ['config/security.php','app/models/Usuario.php','app/helpers/UsuarioPerfisAutorizacao.php','app/helpers/EffectiveAccessProfiles.php','app/helpers/UserAccessHelper.php','app/helpers/ApiErrorReporter.php','app/access/AccessEvaluator.php','app/access/ProfileNormalizer.php','app/access/PageAccessRegistry.php','app/access/AccessAreas.php','app/access/AccessPolicyVersion.php', 'app/services/SalaAudienciasAtaService.php','app/services/SalaAudienciasAtaStorage.php','app/helpers/SalaAudienciasAtaSigiloHelper.php','app/helpers/PrioridadesProcessuaisCatalog.php','app/helpers/ProcessoBlocoListagemHelper.php','app/helpers/ClasseProcessualListagemHelper.php','app/helpers/IdGenerator.php','app/helpers/ProcessoPrioridadeOrigem.php','app/helpers/ProcessoAssuntoOrigem.php','app/helpers/RegistroMetadataHelper.php','app/repositories/SalaAudienciasPautaRepository.php','app/repositories/SalaAudienciasAtaRepository.php','app/repositories/SalaAudienciasEstudanteInscricaoRepository.php','app/repositories/ProcessoPrioridadesRepository.php','app/repositories/ProcessosRepository.php','app/repositories/ClassesRepository.php','app/repositories/ProcessoAssuntosRepository.php','app/repositories/ProcessosPartesRepository.php','app/helpers/CnjValidator.php','app/helpers/ParentRecordLock.php','app/helpers/TextFormatter.php','app/helpers/CartasExpedidasEstadoFluxo.php','app/helpers/CartasExpedidasUiCopy.php','app/helpers/CartasRecebidasUiCopy.php','app/helpers/CartorioTimelineHelper.php','app/helpers/EditaisUiCopy.php','app/helpers/OficioRegistroMetadataHelper.php','app/helpers/SalaAudienciasInscricaoIndisponivel.php','app/repositories/CartasExpedidasSubtarefasRepository.php','app/repositories/CartasRecebidasSubtarefasRepository.php','app/repositories/EditaisSubtarefasRepository.php','app/config/prioridades_processuais_catalog.php'];
foreach ($realFiles as $file) {
    $destination = $root . '/' . $file;
    $directory = dirname($destination);
    rei161Assert(is_dir($directory) || mkdir($directory, 0700, true), 'Falha ao criar diretório da dependência.');
    rei161Assert(copy($sourceRoot . '/' . $file, $destination), 'Dependência real ausente: ' . $file);
}
$prepend = <<<'PHP'
<?php
define('SESSION_IDLE_TIMEOUT', 1800);
session_start();
$id = $_SERVER['HTTP_X_SYNTHETIC_USER'] ?? 'synthetic-student';
if (!in_array($id, ['synthetic-student','synthetic-student-other','synthetic-internal','synthetic-mixed','synthetic-consultant','synthetic-expired','synthetic-inactive','synthetic-invalid-token','anonymous'], true)) exit(1);
$_SESSION = [];
if ($id !== 'anonymous') {
 $_SESSION['id_usuario'] = $id;
 $_SESSION['token'] = str_repeat('a',64);
 $_SESSION['last_activity_at'] = time() - 10;
 $_SESSION['rei161_observacoes'][str_repeat('b',32)] = ['usuario'=>$id,'ids'=>['OBSERVED_OPEN'],'created'=>time()];
 $_SESSION['rei161_observacoes'][str_repeat('c',32)] = ['usuario'=>$id,'ids'=>array_map(static fn($i)=>'OBS'.str_pad((string)$i,2,'0',STR_PAD_LEFT), range(0,14)),'created'=>time()];
}
PHP;
rei161Assert(file_put_contents($root . '/prepend.php', $prepend) !== false, 'Falha ao criar sessão sintética.');
foreach ($stubs as $path => $content) {
    rei161Assert(file_put_contents($path, $content) !== false, 'Falha ao criar dependência sintética.');
}

// Let the kernel choose and bind the port within this process, avoiding a
// release/rebind race against an unrelated listener.
$command = [PHP_BINARY, '-d', 'date.timezone=America/Bahia', '-d', 'auto_prepend_file=' . $root . '/prepend.php', '-d', 'session.save_path=' . $sessionPath, '-d', 'session.use_strict_mode=0', '-S', '127.0.0.1:0', '-t', $public];
$pipes = [];
$server = proc_open($command, [0 => ['pipe','r'], 1 => ['file', $root . '/server.out', 'a'], 2 => ['file', $root . '/server.err', 'a']], $pipes, $root, null);
rei161Assert(is_resource($server), 'Servidor PHP sintético não iniciou.');

try {
    $port = 0;
    for ($i = 0; $i < 50; $i++) {
        $state = proc_get_status($server);
        rei161Assert($state['running'], 'Servidor PHP sintético encerrou antes de iniciar.');
        $log = @file_get_contents($root . '/server.err');
        if (is_string($log) && preg_match('~Development Server \(http://127\.0\.0\.1:([0-9]{1,5})\) started~', $log, $match)) {
            $port = (int) $match[1];
            break;
        }
        usleep(100000);
    }
    rei161Assert($port > 0 && $port <= 65535, 'Servidor PHP não informou porta efêmera vinculada.');
    $url = 'http://127.0.0.1:' . $port . '/api/sala-audiencias-pauta/status-bulk.php';
    $request = static function (array $payload, string $user = 'synthetic-student') use ($url): array {
        $context = stream_context_create(['http' => ['method'=>'POST','header'=>"Content-Type: application/json\r\nCookie: PHPSESSID=rei161synthetic\r\nX-Synthetic-User: $user\r\n",'content'=>json_encode($payload, JSON_UNESCAPED_UNICODE),'ignore_errors'=>true,'timeout'=>5]]);
        $body = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) $status = (int) $match[1];
        return [$status, json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR)];
    };

    foreach (['anonymous'=>401,'synthetic-expired'=>401,'synthetic-inactive'=>401,'synthetic-invalid-token'=>401,'synthetic-internal'=>403,'synthetic-mixed'=>403,'synthetic-consultant'=>403] as $user=>$expected) {
        [$status, $body] = $request(['pautas'=>['OPEN']], $user);
        rei161Assert($status === $expected, 'Autorização incorreta para ' . $user . ': HTTP ' . $status);
    }
    [$status, $body] = $request(['pautas'=>['OPEN','SECRET','MINOR','PRIORITY','CLOSED','CLOSED_ENROLLED','IN_PROGRESS','IN_PROGRESS_ENROLLED','DONE','DONE_ENROLLED','FINISHED','FINISHED_ENROLLED','RESCHEDULED','RESCHEDULED_ENROLLED','POSTPONED_ALIAS','CLOSED_ALIAS','FUTURE','FUTURE_ENROLLED','PAST','PAST_ENROLLED','LATE','INACTIVE','MISSING']]);
    rei161Assert($status === 200 && ($body['success'] ?? false) === true, 'Resposta HTTP/MySQL inesperada.');
    rei161Assert(isset($body['items']['OPEN']['pode_inscrever']) && $body['items']['OPEN']['pode_inscrever'] === true, 'Pauta sintética elegível não foi projetada corretamente.');
    foreach (['SECRET','MINOR','PRIORITY','CLOSED','IN_PROGRESS','DONE','FINISHED','RESCHEDULED','FUTURE','PAST','LATE'] as $id) rei161Assert(($body['items'][$id] ?? null) === ['id_pauta'=>$id,'indisponivel'=>true], 'Resposta inelegível não foi minimizada para ' . $id . '.');
    rei161Assert((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta IN ('SECRET','MINOR','PRIORITY') AND usuario='synthetic-student'")->fetchColumn() === 3, 'Fixture não confirma inscritos nos três bloqueios explícitos de privacidade.');
    foreach (['CLOSED_ENROLLED'=>'cancelada','IN_PROGRESS_ENROLLED'=>'em_andamento','DONE_ENROLLED'=>'realizada','FINISHED_ENROLLED'=>'finalizada','RESCHEDULED_ENROLLED'=>'redesignada','POSTPONED_ALIAS'=>'redesignada','CLOSED_ALIAS'=>'finalizada','FUTURE_ENROLLED'=>'designada','PAST_ENROLLED'=>'atrasada'] as $id=>$expectedStatus) {
        rei161Assert(($body['items'][$id]['status'] ?? null) === $expectedStatus && ($body['items'][$id]['ja_inscrito'] ?? false) === true && ($body['items'][$id]['pode_inscrever'] ?? true) === false && isset($body['items'][$id]['data_audiencia']), 'Status/data removeu metadados do inscrito em ' . $id . '.');
    }
    rei161Assert(!array_key_exists('INACTIVE', $body['items']) && !array_key_exists('MISSING', $body['items']), 'Pauta inativa/inexistente apareceu no resultado.');
    rei161Assert(!str_contains(json_encode($body), 'processo_sigiloso') && !str_contains(json_encode($body), 'qtd_menor') && !str_contains(json_encode($body), 'medida_protetiva'), 'Resposta revelou atributo sensível.');

    // A second student has no enrollment in the three blocked hearings.
    // Exact equality excludes all operational metadata and sensitive indicators.
    rei161Assert((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta IN ('SECRET','MINOR','PRIORITY') AND usuario='synthetic-student-other'")->fetchColumn() === 0, 'Fixture do segundo estudante contém inscrição inesperada.');
    [$status, $body] = $request(['pautas'=>['OPEN','SECRET','MINOR','PRIORITY']], 'synthetic-student-other');
    rei161Assert($status === 200 && ($body['success'] ?? false) === true && ($body['items']['OPEN']['pode_inscrever'] ?? false) === true, 'Segundo estudante não passou no controle elegível.');
    $topLevelKeys = array_keys($body);
    sort($topLevelKeys);
    $expectedTopLevelKeys = ['completo','consultados','items','observacao_alterada','success'];
    sort($expectedTopLevelKeys);
    rei161Assert($topLevelKeys === $expectedTopLevelKeys, 'Resposta expôs campos extras no nível superior.');
    foreach (['SECRET','MINOR','PRIORITY'] as $id) {
        rei161Assert(($body['items'][$id] ?? null) === ['id_pauta'=>$id,'indisponivel'=>true], 'Pauta bloqueada expôs metadados para estudante não inscrito: ' . $id);
    }

    rei161Assert((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta IN ('SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY') AND usuario='synthetic-student'")->fetchColumn() === 3, 'Fixture não confirma inscrição nas três prioridades novas.');
    rei161Assert((int) $db->query("SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta IN ('SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY') AND usuario='synthetic-student-other'")->fetchColumn() === 0, 'Fixture do outro estudante contém inscrição nas prioridades novas.');
    [$status, $body] = $request(['pautas'=>['SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY','NONBLOCKING']]);
    rei161Assert($status === 200 && ($body['items']['NONBLOCKING']['pode_inscrever'] ?? false) === true, 'Prioridade não bloqueante restringiu indevidamente.');
    foreach (['SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY'] as $id) rei161Assert(($body['items'][$id] ?? null) === ['id_pauta'=>$id,'indisponivel'=>true], 'Prioridade bloqueante real expôs metadados de inscrito: ' . $id);
    [$status, $body] = $request(['pautas'=>['SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY']], 'synthetic-student-other');
    foreach (['SIG_PRIORITY','CHILD_PRIORITY','DV_PRIORITY'] as $id) rei161Assert($status === 200 && ($body['items'][$id] ?? null) === ['id_pauta'=>$id,'indisponivel'=>true], 'Prioridade bloqueante real expôs metadados de não inscrito: ' . $id);

    [$status, $body] = $request(['pautas'=>['CAP4','CAP5','CAP6','CAP5I','CAP6I']]);
    rei161Assert($status===200 && ($body['items']['CAP4']['pode_inscrever'] ?? false)===true, 'Capacidade 4 não permitiu inscrição.');
    foreach (['CAP5','CAP6'] as $id) rei161Assert(($body['items'][$id] ?? null)===['id_pauta'=>$id,'indisponivel'=>true], 'Pauta lotada não foi minimizada.');
    foreach (['CAP5I','CAP6I'] as $id) rei161Assert(($body['items'][$id]['ja_inscrito'] ?? false)===true && ($body['items'][$id]['vagas_esgotadas'] ?? false)===true && isset($body['items'][$id]['data_audiencia']), 'Inscrito perdeu metadados em pauta lotada.');

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

    rei161Assert(date('Y-m-d') === $hoje, 'A execução atravessou a meia-noite; resultado temporal inconclusivo, repita o teste.');
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
