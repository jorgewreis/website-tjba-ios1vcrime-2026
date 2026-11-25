<?php
declare(strict_types=1);
// Apenas depois do schema sintético criado pelo runner REI-152. Não faz DDL nem limpeza de banco.
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, "Falha sanitizada na fixture CPF port.\n"); exit(1); });
$expectedCpfPortDsn = 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4';
$cpfPortMarker = (string) getenv('REI152_CI_MARKER');
if (!is_file('/.dockerenv') || realpath(__DIR__) !== '/repo/tests'
    || getenv('REI152_CI_REQUIRED') !== '1' || getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql'
    || getenv('REI152_MYSQL_DSN') !== $expectedCpfPortDsn || getenv('REI152_MYSQL_USER') !== 'root'
    || (string) getenv('REI152_MYSQL_PASSWORD') !== '' || !preg_match('/^[a-f0-9]{64}$/D', $cpfPortMarker)
    || !isset($pdo, $makeFreshCpf) || !$pdo instanceof PDO || !is_callable($makeFreshCpf)
    || !function_exists('rei152AssertCiConnection')) throw new RuntimeException('Identidade do laboratório CPF recusada.');
rei152AssertCiConnection($pdo);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei152_test' || $pdo->inTransaction()) throw new RuntimeException('Estado do laboratório CPF recusado.');
$cpfPortSnapshot = static fn(): array => [
    $pdo->query('SELECT * FROM partes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    $pdo->query('SELECT * FROM partes_documentos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
];
$cpfPortExpectReject = static function (callable $action, string $expectedClass, string $messageFragment, string $label) use ($pdo, $cpfPortSnapshot): void {
    $before = $cpfPortSnapshot(); $accepted = false;
    try { $action(); } catch (Throwable $error) {
        if (!$error instanceof $expectedClass || !str_contains($error->getMessage(), $messageFragment)) throw new RuntimeException('Erro inesperado: ' . $label);
        $accepted = true;
    }
    if (!$accepted || $pdo->inTransaction() || $before !== $cpfPortSnapshot()) throw new RuntimeException('Recusa não preservou estado: ' . $label);
};
$cpfPortPrefix = 'P152' . strtoupper(bin2hex(random_bytes(5)));
$cpfPortIds = [$cpfPortPrefix.'A', $cpfPortPrefix.'B', $cpfPortPrefix.'C', $cpfPortPrefix.'D'];
$cpfPortSeed = $pdo->prepare('INSERT INTO partes (id, cpf, nome_completo) VALUES (?, ?, ?)');
$cpfPortDoc = $pdo->prepare('INSERT INTO partes_documentos (id, parte_id, tipo, numero) VALUES (?, ?, ?, ?)');
$cpfPortA = $makeFreshCpf();
$cpfPortFormat = substr($cpfPortA, 0, 3) . '.' . substr($cpfPortA, 3, 3) . '.' . substr($cpfPortA, 6, 3) . '-' . substr($cpfPortA, 9, 2);
$cpfPortSeed->execute([$cpfPortIds[0], $cpfPortFormat, 'Pessoa Sintetica Legado']);
$cpfPortSeed->execute([$cpfPortIds[1], null, 'Pessoa Sintetica Destino']);
$cpfPortService = new PessoasService($pdo);
$cpfPortBeforeRead = $cpfPortSnapshot();
$cpfPortFound = (new PartesRepository($pdo))->findByCpf($cpfPortA);
if (($cpfPortFound['id'] ?? null) !== $cpfPortIds[0] || $cpfPortSnapshot() !== $cpfPortBeforeRead) throw new RuntimeException('Busca de CPF formatado único falhou ou alterou dados.');
$cpfPortExpectReject(fn() => $cpfPortService->addDocumento($cpfPortIds[1], ['tipo'=>'CPF','numero'=>$cpfPortA]), InvalidArgumentException::class, 'outra pessoa', 'legado formatado');
$cpfPortExpectReject(fn() => $cpfPortService->create(['nome_completo'=>'Pessoa Sintetica Nova', 'cpf'=>$cpfPortA]), InvalidArgumentException::class, 'outra pessoa', 'create legado formatado');
// Dois donos em fontes distintas devem recusar a consulta e a mutação sem escolher um deles.
$cpfPortDoc->execute([$cpfPortPrefix.'DOC', $cpfPortIds[1], 'CPF', $cpfPortA]);
$cpfPortExpectReject(fn() => (new PartesRepository($pdo))->findByCpf($cpfPortA), InvalidArgumentException::class, 'ambígua', 'colisão entre tabelas');
$cpfPortExpectReject(fn() => (new PartesRepository($pdo))->ensureParteByNomeOuCpf('Pessoa Sintetica Busca', $cpfPortA), InvalidArgumentException::class, 'ambígua', 'reuso entre tabelas');
// Duas representações diferentes da mesma pessoa devem ser preservadas.
$cpfPortB = $makeFreshCpf(); $cpfPortC = $makeFreshCpf();
while ($cpfPortC === $cpfPortB) $cpfPortC = $makeFreshCpf();
$cpfPortSeed->execute([$cpfPortIds[2], $cpfPortB, 'Pessoa Sintetica Divergente']);
$cpfPortDoc->execute([$cpfPortPrefix.'D2', $cpfPortIds[2], 'CPF', $cpfPortC]);
$cpfPortExpectReject(fn() => (new PartesRepository($pdo))->findByCpf($cpfPortB), InvalidArgumentException::class, 'inconsistente', 'divergência legado/documento');
$cpfPortExpectReject(fn() => $cpfPortService->deleteDocumento($cpfPortPrefix.'D2'), InvalidArgumentException::class, 'reconciliação', 'remoção divergente');
// Setter deve exigir snapshot anterior correto sob lock do CPF.
$cpfPortSeed->execute([$cpfPortIds[3], $cpfPortB, 'Pessoa Sintetica Snapshot']);
$cpfPortExpectReject(static function () use ($pdo, $cpfPortIds, $cpfPortB): void {
    ParteDocumentoLock::run($pdo, 'CPF', $cpfPortB, static function () use ($pdo, $cpfPortIds): void {
        $pdo->beginTransaction();
        try { (new PartesRepository($pdo))->setCpfLegado($cpfPortIds[3], null, null); }
        finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    });
}, RuntimeException::class, 'alterado durante', 'setter snapshot anterior');
$cpfPortExpectReject(static function () use ($pdo, $cpfPortIds, $cpfPortB): void {
    $pdo->beginTransaction();
    try { (new PartesRepository($pdo))->setCpfLegado($cpfPortIds[3], null, $cpfPortB); }
    finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
}, LogicException::class, 'Lock documental', 'setter sem lock');
// O lock ativo impede outra conexão de adquiri-lo; teste apenas de exclusão mútua, não de corrida completa.
$cpfPortOther = new PDO($expectedCpfPortDsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
rei152AssertCiConnection($cpfPortOther);
ParteDocumentoLock::run($pdo, 'CPF', $cpfPortB, static function () use ($pdo, $cpfPortOther, $cpfPortB): void {
    $lockName = 'rei250_' . substr(hash('sha256', 'rei152_test:' . $cpfPortB), 0, 52);
    $primaryId = $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $ownerQuery = $cpfPortOther->prepare('SELECT IS_USED_LOCK(?)'); $ownerQuery->execute([$lockName]);
    $owner = $ownerQuery->fetchColumn();
    if ((!is_int($primaryId) && !is_string($primaryId)) || !ctype_digit((string)$primaryId) || (int)$primaryId <= 0
        || (!is_int($owner) && !is_string($owner)) || (string)$owner !== (string)$primaryId) {
        throw new RuntimeException('Proprietário do lock documental não foi confirmado.');
    }
    $query = $cpfPortOther->prepare('SELECT GET_LOCK(?, 0)'); $query->execute([$lockName]);
    $acquired = $query->fetchColumn();
    if ($acquired === 1 || $acquired === '1') {
        $release = $cpfPortOther->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
        $released = $release->fetchColumn();
        if ($released !== 1 && $released !== '1') throw new RuntimeException('Liberação inesperada do lock não confirmada.');
    }
    if ($acquired !== 0 && $acquired !== '0') throw new RuntimeException('Lock documental não impediu concorrente.');
});
echo "PASS REI-152 CPF port: formato, colisão, divergência, setter e lock\n";
