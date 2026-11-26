<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/PessoasService.php';

$runId = (string) getenv('REI152_RUN_ID');
$marker = (string) getenv('REI152_MARKER');
$dsn = (string) getenv('REI152_MYSQL_DSN');
if (getenv('REI152_TEST_ISOLATION') !== 'disposable-rei152-mysql'
    || !preg_match('/^[a-f0-9]{32}$/D', $runId)
    || !preg_match('/^[a-f0-9]{64}$/D', $marker)
    || $dsn !== 'mysql:host=mysql;port=3306;dbname=rei152_test;charset=utf8mb4') {
    fwrite(STDERR, "FAIL CLOSED: fixture REI-152 não comprovado\n");
    exit(90);
}
$pdo = new PDO($dsn, 'root', (string) getenv('REI152_MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$guard = $pdo->prepare('SELECT COUNT(*) FROM rei152_disposable_guard WHERE run_id = ? AND marker = ?');
$guard->execute([$runId, $marker]);
if ((int) $guard->fetchColumn() !== 1 || (string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'rei152_test') {
    fwrite(STDERR, "FAIL CLOSED: guarda do banco REI-152 falhou\n");
    exit(91);
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
if (fgets(STDIN) !== "GO\n") {
    fwrite(STDERR, "Worker REI-152 não recebeu sinal\n");
    exit(92);
}

try {
    $partyId = trim((string) getenv('REI152_PARTY_ID')) ?: 'P1';
    (new PessoasService($pdo))->addDocumento($partyId, ['tipo' => 'CPF', 'numero' => '52998224725']);
    fwrite(STDERR, "Inserção duplicada foi aceita\n");
    exit(1);
} catch (InvalidArgumentException $exception) {
    fwrite(STDOUT, "PASS|duplicate rejected\n");
}
