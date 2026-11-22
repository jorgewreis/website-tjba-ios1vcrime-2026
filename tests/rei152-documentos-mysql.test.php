<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';
require_once __DIR__ . '/../app/repositories/PartesRepository.php';

$dsn = getenv('REI152_MYSQL_DSN');
if (getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql' || !is_string($dsn) || !str_contains($dsn, 'unix_socket=')) {
    throw new RuntimeException('Teste requer MySQL descartável isolado por socket local.');
}
$user = getenv('REI152_MYSQL_USER') ?: 'root';
$password = getenv('REI152_MYSQL_PASSWORD') ?: '';
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'rei152_test') {
    throw new RuntimeException('Banco descartável inesperado.');
}

$pdo->exec('CREATE TABLE partes (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    cpf VARCHAR(15) NULL,
    nome_completo VARCHAR(255) NOT NULL,
    sexo VARCHAR(30) NULL,
    data_nascimento DATE NULL,
    profissao VARCHAR(120) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE partes_documentos (
    id VARCHAR(30) NOT NULL PRIMARY KEY,
    parte_id VARCHAR(30) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    numero VARCHAR(40) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_tipo_numero (tipo, numero)
) ENGINE=InnoDB');
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('PRT-FAIL-CASE', NULL, 'Caso de falha')");
$service = new PessoasService($pdo);
$pdo->exec("CREATE TRIGGER rei152_fail_cpf BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='rei152 falha injetada'");
try {
    $service->addDocumento('PRT-FAIL-CASE', ['tipo' => 'CPF', 'numero' => '12345678909']);
    throw new RuntimeException('Falha injetada deveria abortar a operação.');
} catch (PDOException $error) {
    if (!str_contains($error->getMessage(), 'rei152 falha injetada')) {
        throw $error;
    }
}
if ((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-FAIL-CASE'")->fetchColumn() !== 0
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== null) {
    throw new RuntimeException('Falha após inserir documento não foi revertida no MySQL.');
}
$pdo->exec('DROP TRIGGER rei152_fail_cpf');
$retryId = $service->addDocumento('PRT-FAIL-CASE', ['tipo' => 'CPF', 'numero' => '12345678909']);
if ($retryId === '' || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '12345678909') {
    throw new RuntimeException('Retentativa após falha não persistiu documento e CPF legado.');
}

$runId = bin2hex(random_bytes(12));
$barrier = sys_get_temp_dir() . '/rei152-' . $runId;
if (!mkdir($barrier, 0700)) {
    throw new RuntimeException('Não foi possível criar barreira concorrente.');
}

$runRace = static function (string $case, string $type, string $number, array $modes) use ($barrier, $runId): array {
    $workers = [];
    try {
        foreach ($modes as $index => $mode) {
            $command = [PHP_BINARY, __DIR__ . '/rei152-documentos-mysql-worker.php', $case, $type, $number, $mode, $barrier, (string) $index];
            $pipes = [];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Não foi possível iniciar worker concorrente.');
            }
            fclose($pipes[0]);
            $workers[] = ['process' => $process, 'pipes' => $pipes, 'index' => $index];
        }

        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline && (!is_file($barrier . '/' . $case . '-ready-0') || !is_file($barrier . '/' . $case . '-ready-1'))) {
            usleep(10000);
        }
        if (!is_file($barrier . '/' . $case . '-ready-0') || !is_file($barrier . '/' . $case . '-ready-1')) {
            throw new RuntimeException('Workers não alcançaram a barreira.');
        }
        file_put_contents($barrier . '/' . $case . '-go', $runId, LOCK_EX);

        $results = [];
        foreach ($workers as &$worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);
            if ($exit !== 0 || trim((string) $stderr) !== '') {
                throw new RuntimeException('Worker falhou: ' . trim((string) $stderr));
            }
            $results[] = trim((string) $stdout);
        }
        unset($worker);
        return $results;
    } finally {
        foreach ($workers as &$worker) {
            if (is_resource($worker['process'])) {
                foreach ([1, 2] as $index) {
                    if (isset($worker['pipes'][$index]) && is_resource($worker['pipes'][$index])) fclose($worker['pipes'][$index]);
                }
                proc_terminate($worker['process']);
                proc_close($worker['process']);
            }
        }
        unset($worker);
        foreach (glob($barrier . '/' . $case . '-*') ?: [] as $path) @unlink($path);
    }
};

$cpf1 = '52998224725';
$results1 = $runRace('service', 'CPF', $cpf1, ['service', 'service']);
if (count(array_filter($results1, static fn(string $result): bool => str_starts_with($result, 'created:'))) !== 1
    || count(array_filter($results1, static fn(string $result): bool => $result === 'duplicate')) !== 1) {
    throw new RuntimeException('Duas criações concorrentes deveriam resultar em uma identidade e uma rejeição de duplicidade.');
}
if ((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpf1'")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpf1'")->fetchColumn() !== 1) {
    throw new RuntimeException('Persistência concorrente deixou documentos ou CPF legado duplicados.');
}

$cpf2 = '11144477735';
$results2 = $runRace('cross', 'CPF', $cpf2, ['service', 'repository']);
if (count(array_filter($results2, static fn(string $result): bool => str_starts_with($result, 'part:'))) !== 1
    || count(array_filter($results2, static fn(string $result): bool => $result === 'duplicate' || str_starts_with($result, 'created:'))) !== 1) {
    throw new RuntimeException('Escritores de Pessoas e PartesRepository não foram serializados de forma coerente: ' . implode(', ', $results2));
}
if ((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpf2'")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpf2'")->fetchColumn() !== 1) {
    throw new RuntimeException('Corrida entre serviço e repositório deixou CPF legado/documento divergentes.');
}

$rg = 'REI152-RG-001';
$results3 = $runRace('rgdocs', 'RG', $rg, ['service', 'service']);
if (count(array_filter($results3, static fn(string $result): bool => str_starts_with($result, 'created:'))) !== 1
    || count(array_filter($results3, static fn(string $result): bool => $result === 'duplicate')) !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='RG' AND numero='$rg'")->fetchColumn() !== 1) {
    throw new RuntimeException('Lock de documento não CPF não serializou os escritores concorrentes.');
}

$lockName = 'rei250_' . substr(hash('sha256', $database . ':' . $cpf2), 0, 52);
$lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
$lock->execute([$lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    throw new RuntimeException('Lock não foi liberado ao final da operação concorrente.');
}
$release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
$release->execute([$lockName]);
if ((int) $release->fetchColumn() !== 1) {
    throw new RuntimeException('Lock de verificação não foi liberado.');
}

foreach (glob($barrier . '/*') ?: [] as $path) @unlink($path);
@rmdir($barrier);
echo "PASS REI-152 MySQL/InnoDB: rollback, retry, conflitos CPF/RG e reconciliação do CPF legado\n";
