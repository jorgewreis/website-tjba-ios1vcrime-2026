<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';
require_once __DIR__ . '/../app/repositories/PartesRepository.php';
require_once __DIR__ . '/../app/services/Guias.php';

final class Rei152RollbackFaultPdo extends PDO
{
    private string $rollbackFault = '';

    public function failNextRollback(string $fault): void
    {
        if (!in_array($fault, ['false', 'throw'], true)) {
            throw new InvalidArgumentException('Falha de rollback de fixture inválida.');
        }
        $this->rollbackFault = $fault;
    }

    public function rollBack(): bool
    {
        $fault = $this->rollbackFault;
        $this->rollbackFault = '';
        if ($fault === 'false') {
            return false;
        }
        if ($fault === 'throw') {
            throw new RuntimeException('falha de rollback injetada');
        }
        return parent::rollBack();
    }

    public function forceRollbackForFixture(): bool
    {
        return parent::rollBack();
    }
}

/** Falhas sintéticas sem alterar o comportamento do servidor MySQL. */
final class Rei152CommitReleaseFaultPdo extends PDO
{
    public bool $failRelease = false;
    public bool $throwAfterCommit = false;
    public bool $commitSucceeded = false;
    public bool $releaseFaultInjected = false;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failRelease && $query === 'SELECT RELEASE_LOCK(?)') {
            $this->failRelease = false;
            $this->releaseFaultInjected = true;
            throw new RuntimeException('falha de liberação sintética');
        }
        return parent::prepare($query, $options);
    }

    public function commit(): bool
    {
        $result = parent::commit();
        $this->commitSucceeded = $result;
        if ($result && $this->throwAfterCommit) {
            $this->throwAfterCommit = false;
            throw new RuntimeException('resposta de commit sinteticamente perdida');
        }
        return $result;
    }
}

/** Pausa determinística entre a leitura do snapshot e a aquisição do primeiro lock. */
final class Rei152SnapshotPdo extends PDO
{
    public ?Closure $beforeFirstLockName = null;
    public int $transactionStarts = 0;

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($query === 'SELECT DATABASE()' && $this->beforeFirstLockName !== null) {
            $callback = $this->beforeFirstLockName;
            $this->beforeFirstLockName = null;
            $callback();
        }
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function beginTransaction(): bool
    {
        $this->transactionStarts++;
        return parent::beginTransaction();
    }
}

$dsn = getenv('REI152_MYSQL_DSN');
if (getenv('REI152_TEST_ISOLATION') !== 'rei152-disposable-mysql' || !is_string($dsn) || !str_contains($dsn, 'unix_socket=')) {
    throw new RuntimeException('Teste requer MySQL descartável isolado por socket local.');
}
$user = getenv('REI152_MYSQL_USER') ?: 'root';
$password = getenv('REI152_MYSQL_PASSWORD') ?: '';
if (getenv('REI152_CI_REQUIRED') === '1'
    && ($dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4'
        || $user !== 'root' || $password !== '' || !preg_match('/^[a-f0-9]{64}$/D', (string) getenv('REI152_CI_MARKER')))) {
    throw new RuntimeException('Identidade CI da fixture recusada.');
}
function rei152AssertCiConnection(PDO $connection): void
{
    if (getenv('REI152_CI_REQUIRED') !== '1') return;
    $marker = (string) getenv('REI152_CI_MARKER');
    if (!preg_match('/^[a-f0-9]{64}$/D', $marker)) throw new RuntimeException('Identidade CI da fixture recusada.');
    $tokens = $connection->query('SELECT token FROM `_sandbox_proof`')->fetchAll(PDO::FETCH_COLUMN);
    if (count($tokens) !== 1 || !hash_equals($marker, (string) $tokens[0])) {
        throw new RuntimeException('Marcador CI da fixture recusado.');
    }
}
$pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
rei152AssertCiConnection($pdo);
$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'rei152_test') {
    throw new RuntimeException('Banco descartável inesperado.');
}
$pdo->exec("UPDATE performance_schema.setup_instruments SET ENABLED='YES', TIMED='YES' WHERE NAME='wait/lock/metadata/sql/mdl'");
$mdlInstrument = $pdo->query("SELECT ENABLED FROM performance_schema.setup_instruments WHERE NAME='wait/lock/metadata/sql/mdl'")->fetchColumn();
if (strtoupper((string) $mdlInstrument) !== 'YES') {
    throw new RuntimeException('Não foi possível habilitar a observação dos locks nomeados na fixture descartável.');
}

$pdo->exec('CREATE TABLE classes (
    id_classe VARCHAR(40) NOT NULL PRIMARY KEY,
    cod VARCHAR(40) NOT NULL UNIQUE,
    descricao VARCHAR(255) NOT NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE processos (
    id_proc VARCHAR(40) NOT NULL PRIMARY KEY,
    numero VARCHAR(40) NOT NULL UNIQUE,
    classe VARCHAR(40) NULL,
    data_cadastro DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE processos_partes (
    id VARCHAR(40) NOT NULL PRIMARY KEY,
    processo_id VARCHAR(40) NOT NULL,
    parte_id VARCHAR(40) NOT NULL,
    tipo_parte VARCHAR(40) NOT NULL,
    polo VARCHAR(40) NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE processo_prioridades (
    id VARCHAR(40) NOT NULL PRIMARY KEY,
    processo_id VARCHAR(40) NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    origem VARCHAR(40) NOT NULL,
    regra_detalhe TEXT NULL,
    created_at DATETIME NULL
) ENGINE=InnoDB');
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
    numero VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_tipo_numero (tipo, numero)
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE guia (
    id_guia VARCHAR(40) NOT NULL PRIMARY KEY,
    tipo VARCHAR(80) NOT NULL,
    processo VARCHAR(40) NOT NULL,
    parte_id VARCHAR(30) NULL,
    etapa VARCHAR(80) NOT NULL,
    status VARCHAR(80) NOT NULL,
    regime VARCHAR(80) NOT NULL,
    data_fato DATE NULL,
    dias_multa INT NULL,
    observacoes TEXT NULL,
    data_cadastro DATETIME NOT NULL,
    data_atualizacao DATETIME NOT NULL,
    user_cadastro VARCHAR(80) NOT NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE guia_subtarefas (
    id_guia VARCHAR(40) NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    status VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE audit_log (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    user_id VARCHAR(80) NOT NULL,
    action VARCHAR(80) NOT NULL,
    context TEXT NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE guiasUsuarios (
    id_alocacao VARCHAR(40) NOT NULL PRIMARY KEY,
    id_usuario VARCHAR(80) NOT NULL,
    id_guia VARCHAR(40) NOT NULL,
    data_cadastro DATE NOT NULL
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
$partesRepository = new PartesRepository($pdo);
foreach ([['cpf' => '52998224725'], ['cpf' => null]] as $directCpfMutation) {
    try {
        $partesRepository->update('PRT-FAIL-CASE', $directCpfMutation);
        throw new RuntimeException('Alteração direta de CPF pelo repositório foi aceita.');
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), 'operação de documentos')) throw $error;
    }
}
if ($pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '12345678909'
    || (int)$pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-FAIL-CASE' AND tipo='CPF' AND numero='12345678909'")->fetchColumn() !== 1) {
    throw new RuntimeException('Recusa de mutação direta divergiu o CPF legado do documento.');
}
$pdo->exec("UPDATE partes SET cpf='123.456.789-09' WHERE id='PRT-FAIL-CASE'");
$rgForCpfHolder = $service->addDocumento('PRT-FAIL-CASE', ['tipo' => 'RG', 'numero' => '1234567']);
if ($rgForCpfHolder === ''
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '123.456.789-09'
    || (int)$pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-FAIL-CASE' AND tipo='RG' AND numero='1234567'")->fetchColumn() !== 1) {
    throw new RuntimeException('Inclusão de RG em pessoa com CPF legado pontuado não preservou ambos os documentos.');
}
if (!$service->deleteDocumento($rgForCpfHolder)
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '123.456.789-09'
    || (int)$pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-FAIL-CASE' AND tipo='RG'")->fetchColumn() !== 0) {
    throw new RuntimeException('Exclusão de RG em pessoa com CPF legado pontuado não preservou o CPF legado.');
}
// Chamadas diretas ao setter não podem limpar sem lock do CPF anterior,
// nem enquanto o documento correspondente ainda estiver presente.
$pdo->beginTransaction();
try {
    (new PartesRepository($pdo))->setCpfLegado('PRT-FAIL-CASE', null, '12345678909');
    throw new RuntimeException('Limpeza direta sem lock deveria falhar.');
} catch (LogicException $error) {
    if (!str_contains($error->getMessage(), 'Lock documental')) throw $error;
} finally {
    $pdo->rollBack();
}
ParteDocumentoLock::run($pdo, 'CPF', '12345678909', static function () use ($pdo): void {
    $pdo->beginTransaction();
    try {
        (new PartesRepository($pdo))->setCpfLegado('PRT-FAIL-CASE', null, '12345678909');
        throw new RuntimeException('Limpeza direta com documento presente deveria falhar.');
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), 'ainda presente')) throw $error;
    } finally {
        $pdo->rollBack();
    }
});
if ($pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '123.456.789-09'
    || (int)$pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id='$retryId' AND tipo='CPF'")->fetchColumn() !== 1) {
    throw new RuntimeException('Limpeza direta recusada alterou representações.');
}
$pdo->exec("CREATE TRIGGER rei152_delete_cpf_doc_fail BEFORE UPDATE ON partes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='rei152 falha exclusao cpf'");
try {
    $service->deleteDocumento($retryId);
    throw new RuntimeException('Falha de sincronização deveria reverter exclusão do CPF.');
} catch (PDOException $error) {
    if (!str_contains($error->getMessage(), 'rei152 falha exclusao cpf')) throw $error;
} finally {
    $pdo->exec('DROP TRIGGER rei152_delete_cpf_doc_fail');
}
if ($pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== '123.456.789-09'
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id='$retryId' AND tipo='CPF' AND numero='12345678909'")->fetchColumn() !== 1) {
    throw new RuntimeException('Falha na exclusão do CPF não preservou ambas as representações.');
}
if (!$service->deleteDocumento($retryId) || $service->deleteDocumento($retryId)
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-FAIL-CASE'")->fetchColumn() !== null
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id='$retryId'")->fetchColumn() !== 0) {
    throw new RuntimeException('Retry ou repetição da exclusão de CPF deixou representações inconsistentes.');
}
$retryId = $service->addDocumento('PRT-FAIL-CASE', ['tipo' => 'CPF', 'numero' => '12345678909']);
if ($retryId === '') throw new RuntimeException('Fixture não restaurou CPF para os cenários seguintes.');
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('PRT-DIVERGENT', NULL, 'Pessoa sintética')");
$pdo->exec("INSERT INTO partes_documentos (id, parte_id, tipo, numero) VALUES ('DOC-DIVERGENT-CPF', 'PRT-DIVERGENT', 'CPF', '70000000001')");
$divergentRg = $service->addDocumento('PRT-DIVERGENT', ['tipo' => 'RG', 'numero' => 'RG-DIVERGENT']);
if ($pdo->query("SELECT cpf FROM partes WHERE id='PRT-DIVERGENT'")->fetchColumn() !== null) {
    throw new RuntimeException('Inclusão de RG alterou CPF legado nulo.');
}
$pdo->exec("UPDATE partes SET cpf='70000000002' WHERE id='PRT-DIVERGENT'");
if (!$service->deleteDocumento($divergentRg)
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-DIVERGENT'")->fetchColumn() !== '70000000002'
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-DIVERGENT' AND tipo='RG'")->fetchColumn() !== 0
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id='DOC-DIVERGENT-CPF' AND numero='70000000001'")->fetchColumn() !== 1) {
    throw new RuntimeException('Exclusão de RG não preservou CPF legado e documental divergentes.');
}
try {
    $service->deleteDocumento('DOC-DIVERGENT-CPF');
    throw new RuntimeException('Exclusão de CPF divergente deveria ser recusada.');
} catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), 'reconciliação')) throw $error;
}
if ($pdo->query("SELECT cpf FROM partes WHERE id='PRT-DIVERGENT'")->fetchColumn() !== '70000000002'
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE id='DOC-DIVERGENT-CPF' AND numero='70000000001'")->fetchColumn() !== 1) {
    throw new RuntimeException('Recusa de CPF divergente alterou representações.');
}
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('PRT-SNAPSHOT', '70000000003', 'Pessoa sintética snapshot')");
$pdo->exec("INSERT INTO partes_documentos (id, parte_id, tipo, numero) VALUES ('DOC-SNAPSHOT-CPF', 'PRT-SNAPSHOT', 'CPF', '70000000003')");
$snapshotPdo = new Rei152SnapshotPdo($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
rei152AssertCiConnection($snapshotPdo);
$snapshotPdo->beforeFirstLockName = static function () use ($pdo): void {
    ParteDocumentoLock::runMany($pdo, [
        ['tipo' => 'CPF', 'numero' => '70000000003'],
        ['tipo' => 'CPF', 'numero' => '70000000002'],
    ], static function () use ($pdo): void {
        $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE partes SET cpf='70000000002' WHERE id='PRT-SNAPSHOT'");
            $pdo->exec("UPDATE partes_documentos SET numero='70000000002' WHERE id='DOC-SNAPSHOT-CPF'");
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    });
};
$snapshotRg = (new PessoasService($snapshotPdo))->addDocumento('PRT-SNAPSHOT', ['tipo' => 'RG', 'numero' => 'RG-SNAPSHOT']);
if ($snapshotPdo->transactionStarts !== 2 || $snapshotRg === ''
    || $pdo->query("SELECT cpf FROM partes WHERE id='PRT-SNAPSHOT'")->fetchColumn() !== '70000000002'
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='PRT-SNAPSHOT' AND tipo='RG'")->fetchColumn() !== 1) {
    throw new RuntimeException('Snapshot alterado não resultou em rollback e retentativa única consistente.');
}
$pdo->exec("INSERT INTO partes (id, cpf, nome_completo) VALUES ('PRT-CASE-LOWER', NULL, 'Caso caixa')");
$pdo->exec("INSERT INTO partes_documentos (id, parte_id, tipo, numero) VALUES ('DOC-LEGACY-CASE', 'PRT-CASE-LOWER', 'RG', 'abc-42')");
$partesDocumentosRepository = new PartesDocumentosRepository($pdo);
try {
    $service->addDocumento('PRT-FAIL-CASE', ['tipo' => 'RG', 'numero' => 'AbC-42']);
    throw new RuntimeException('Documento não CPF com caixa diferente deveria ser tratado como duplicado.');
} catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), 'Já existe outra pessoa')) throw $error;
}
if ($partesDocumentosRepository->findParteIdByTipoNumero('RG', 'ABC-42') !== 'PRT-CASE-LOWER') {
    throw new RuntimeException('Consulta não encontrou documento legado minúsculo sob comparação case-insensitive.');
}
$pdo->exec("INSERT INTO partes_documentos (id, parte_id, tipo, numero) VALUES ('DOC-ACCENT-CASE', 'PRT-CASE-LOWER', 'RG', 'CAFÉ-42')");
if ($partesDocumentosRepository->findParteIdByTipoNumero('RG', 'CAFE-42') !== null) {
    throw new RuntimeException('Comparação case-insensitive também ignorou acentos fora da decisão.');
}
echo "PASS REI-152: documentos não CPF ignoram caixa, preservam acentos e encontram legado\n";

$runId = bin2hex(random_bytes(12));
$barrier = sys_get_temp_dir() . '/rei152-' . $runId;
if (!mkdir($barrier, 0700)) {
    throw new RuntimeException('Não foi possível criar barreira concorrente.');
}

$runRace = static function (string $case, string $type, string $number, array $modes, bool $startSecondAfterLegacyLock = false, array $workerNumbers = []) use ($barrier, $runId, $pdo, $database): array {
    $workers = [];
    try {
        foreach ($modes as $index => $mode) {
            $workerNumber = (string) ($workerNumbers[$index] ?? $number);
            $command = [PHP_BINARY, __DIR__ . '/rei152-documentos-mysql-worker.php', $case, $type, $workerNumber, $mode, $barrier, (string) $index];
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
        if ($startSecondAfterLegacyLock) {
            $firstMode = (string) ($modes[0] ?? '');
            if (!in_array($firstMode, ['legacy_guide', 'legacy_update'], true)) {
                throw new RuntimeException('O cenário sequencial exige um worker de guia legado primeiro.');
            }
            file_put_contents($barrier . '/' . $case . '-go-0', $runId, LOCK_EX);
            $lockName = 'rei250_' . substr(hash('sha256', $database . ':' . $number), 0, 52);
            $lockOwner = $pdo->prepare('SELECT IS_USED_LOCK(?)');
            $deadline = microtime(true) + 10;
            $observedCpfLockOwner = false;
            $lastOwnerId = null;
            while (microtime(true) < $deadline) {
                $lockOwner->execute([$lockName]);
                $ownerId = $lockOwner->fetchColumn();
                $lastOwnerId = $ownerId;
                $identityFile = $barrier . '/' . $case . '-connection-0';
                $expectedOwner = is_file($identityFile) ? (int) file_get_contents($identityFile) : 0;
                if ($expectedOwner > 0 && (int) $ownerId === $expectedOwner) {
                    $observedCpfLockOwner = true;
                    break;
                }
                usleep(10000);
            }
            if (!$observedCpfLockOwner) {
                throw new RuntimeException('Não foi possível observar o lock CPF do worker legado antes de liberar o escritor concorrente (owner=' . var_export($lastOwnerId, true) . ', iniciado=' . (is_file($barrier . '/' . $case . '-started-0') ? 'sim' : 'não') . ').');
            }
            file_put_contents($barrier . '/' . $case . '-go-1', $runId, LOCK_EX);
            $pendingLock = $pdo->prepare("SELECT COUNT(*) FROM performance_schema.metadata_locks ml JOIN performance_schema.threads t ON t.THREAD_ID=ml.OWNER_THREAD_ID WHERE ml.OBJECT_TYPE='USER LEVEL LOCK' AND ml.OBJECT_NAME=? AND ml.LOCK_STATUS='PENDING' AND t.PROCESSLIST_ID=?");
            $deadline = microtime(true) + 10;
            $observedWaiter = false;
            while (microtime(true) < $deadline) {
                $waiterFile = $barrier . '/' . $case . '-connection-1';
                $expectedWaiter = is_file($waiterFile) ? (int) file_get_contents($waiterFile) : 0;
                if ($expectedWaiter <= 0) { usleep(10000); continue; }
                $pendingLock->execute([$lockName, $expectedWaiter]);
                if ((int) $pendingLock->fetchColumn() > 0) {
                    $lockOwner->execute([$lockName]);
                    $ownerId = $lockOwner->fetchColumn();
                    if ((int) $ownerId === $expectedOwner) {
                        $observedWaiter = true;
                        break;
                    }
                }
                usleep(10000);
            }
            if (!$observedWaiter) {
                throw new RuntimeException('O concorrente não apareceu aguardando o lock USER LEVEL LOCK enquanto o worker legado ainda era proprietário.');
            }
        } else {
            file_put_contents($barrier . '/' . $case . '-go', $runId, LOCK_EX);
        }

        $results = [];
        foreach ($workers as &$worker) {
            $deadline = microtime(true) + 20;
            do {
                $status = proc_get_status($worker['process']);
                if (!$status['running']) break;
                usleep(10000);
            } while (microtime(true) < $deadline);
            if ($status['running']) throw new RuntimeException('Timeout no worker concorrente.');
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            proc_close($worker['process']);
            $exit = $status['exitcode'];
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

$lockNames = [
    'rei152_' . substr(hash('sha256', $database . ':RG:A152'), 0, 56),
    'rei152_' . substr(hash('sha256', $database . ':RG:B152'), 0, 56),
    'rei250_' . substr(hash('sha256', $database . ':52998224725'), 0, 52),
];
sort($lockNames, SORT_STRING);
$heldName = $lockNames[2];
$hold = $pdo->prepare('SELECT GET_LOCK(?, 0)');
$hold->execute([$heldName]);
if ((int) $hold->fetchColumn() !== 1) throw new RuntimeException('Fixture não obteve o último lock.');
$orderProcess = null;
$orderPipes = [];
try {
    $orderProcess = proc_open([PHP_BINARY, __DIR__ . '/rei152-documentos-mysql-worker.php', 'lockorder', 'RG', 'A152', 'lock_many', $barrier, '0'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $orderPipes);
    if (!is_resource($orderProcess)) throw new RuntimeException('Falha ao iniciar worker de ordem.');
    fclose($orderPipes[0]);
    $deadline = microtime(true) + 4;
    while (microtime(true) < $deadline && !is_file($barrier . '/lockorder-ready-0')) usleep(10000);
    if (!is_file($barrier . '/lockorder-ready-0')) throw new RuntimeException('Timeout na barreira do worker de ordem.');
    file_put_contents($barrier . '/lockorder-go-0', $runId, LOCK_EX);
    $owner = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    $pending = $pdo->prepare("SELECT COUNT(*) FROM performance_schema.metadata_locks ml JOIN performance_schema.threads t ON t.THREAD_ID=ml.OWNER_THREAD_ID WHERE ml.OBJECT_TYPE='USER LEVEL LOCK' AND ml.OBJECT_NAME=? AND ml.LOCK_STATUS='PENDING' AND t.PROCESSLIST_ID=?");
    $observed = false;
    $deadline = microtime(true) + 4;
    while (microtime(true) < $deadline) {
        $owner->execute([$lockNames[0]]);
        $firstOwner = $owner->fetchColumn();
        $owner->execute([$lockNames[1]]);
        $secondOwner = $owner->fetchColumn();
        $identityFile = $barrier . '/lockorder-connection-0';
        $workerId = is_file($identityFile) ? (int) file_get_contents($identityFile) : 0;
        if ($workerId <= 0) { usleep(10000); continue; }
        $pending->execute([$heldName, $workerId]);
        if ((int) $firstOwner === $workerId && (int) $secondOwner === $workerId && (int) $pending->fetchColumn() > 0) {
            $observed = true;
            break;
        }
        usleep(10000);
    }
    if (!$observed) throw new RuntimeException('Ordem dos locks finais não foi observada antes do timeout.');
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$heldName]);
    if ((int) $release->fetchColumn() !== 1) throw new RuntimeException('Falha ao liberar lock da fixture.');
    $heldName = '';
    stream_set_blocking($orderPipes[1], false);
    stream_set_blocking($orderPipes[2], false);
    $deadline = microtime(true) + 10;
    do {
        $status = proc_get_status($orderProcess);
        if (!$status['running']) break;
        usleep(10000);
    } while (microtime(true) < $deadline);
    if ($status['running']) throw new RuntimeException('Timeout ao encerrar worker de ordem.');
    $stdout = stream_get_contents($orderPipes[1]);
    $stderr = stream_get_contents($orderPipes[2]);
    fclose($orderPipes[1]);
    fclose($orderPipes[2]);
    proc_close($orderProcess);
    $orderProcess = null;
    if ($status['exitcode'] !== 0 || trim((string) $stdout) !== 'locks-ok') {
        throw new RuntimeException('Worker de ordem falhou: ' . trim((string) $stderr));
    }
} finally {
    if ($heldName !== '') {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$heldName]);
    }
    if (is_resource($orderProcess)) {
        proc_terminate($orderProcess);
        foreach ([1, 2] as $index) if (isset($orderPipes[$index]) && is_resource($orderPipes[$index])) fclose($orderPipes[$index]);
        proc_close($orderProcess);
    }
}
$orderRace = $runRace('lockrace', 'RG', 'A152', ['lock_many', 'lock_many']);
if ($orderRace !== ['locks-ok', 'locks-ok']) throw new RuntimeException('Locks em ordem inversa não terminaram sem conflito.');

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

$cpfOuter = '93541134780';
$pdo->beginTransaction();
try {
    $partesRepository->ensureParteByNomeOuCpf('Transação externa sem lock', $cpfOuter);
    throw new RuntimeException('Mutação documental sem lock externo deveria ser recusada.');
} catch (LogicException $error) {
    if (!str_contains($error->getMessage(), 'lock documental precisa ser adquirido antes')) throw $error;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
if ((int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfOuter'")->fetchColumn() !== 0) {
    throw new RuntimeException('A recusa de transação sem lock persistiu dados documentais.');
}
$resultsOuter = $runRace('outertxn', 'CPF', $cpfOuter, ['repository_outer', 'service']);
$outerResult = array_values(array_filter($resultsOuter, static fn(string $result): bool => str_starts_with($result, 'outer:')));
$serviceResults = array_values(array_filter($resultsOuter, static fn(string $result): bool => $result === 'duplicate' || str_starts_with($result, 'created:')));
$outerId = $outerResult !== [] ? substr($outerResult[0], strlen('outer:')) : '';
$serviceCreatedId = $serviceResults !== [] && str_starts_with($serviceResults[0], 'created:')
    ? substr($serviceResults[0], strlen('created:'))
    : null;
if (count($outerResult) !== 1 || count($serviceResults) !== 1 || $outerId === ''
    || ($serviceCreatedId !== null && $serviceCreatedId !== $outerId)
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfOuter'")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpfOuter'")->fetchColumn() !== 1) {
    throw new RuntimeException('Lock do escritor externo não foi mantido até o commit da transação.');
}

$cpfLegacyGuide = '23456789092';
if ((int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpfLegacyGuide'")->fetchColumn() !== 0
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfLegacyGuide'")->fetchColumn() !== 0) {
    throw new RuntimeException('CPF inicial do teste legado de criação já existe.');
}
$pdo->exec("CREATE TRIGGER rei152_legacy_guide_pause BEFORE INSERT ON guia FOR EACH ROW DO SLEEP(3)");
try {
    $legacyResults = $runRace('legacyguide', 'CPF', $cpfLegacyGuide, ['legacy_guide', 'service'], true);
} finally {
    $pdo->exec('DROP TRIGGER rei152_legacy_guide_pause');
}
if (count(array_filter($legacyResults, static fn(string $result): bool => str_starts_with($result, 'legacy-guide:'))) !== 1
    || count(array_filter($legacyResults, static fn(string $result): bool => $result === 'duplicate')) !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM guia WHERE parte_id IN (SELECT id FROM partes WHERE cpf='$cpfLegacyGuide')")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfLegacyGuide'")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpfLegacyGuide'")->fetchColumn() !== 1) {
    throw new RuntimeException('Fluxo legado de Guias não manteve o lock CPF até o commit concorrente. Resultados: ' . implode(', ', $legacyResults));
}
echo "PASS REI-152: Guias::cadastrarGuia mantém lock CPF até commit concorrente.\n";

$cpfLegacyUpdate = '34567890175';
if ((int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpfLegacyUpdate'")->fetchColumn() !== 0
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfLegacyUpdate'")->fetchColumn() !== 0) {
    throw new RuntimeException('CPF inicial do teste legado de edição já existe.');
}
$updateGuia = $pdo->prepare("INSERT INTO guia (id_guia,tipo,processo,parte_id,etapa,status,regime,data_fato,dias_multa,observacoes,data_cadastro,data_atualizacao,user_cadastro) VALUES ('REI152-LEGACY-UPDATE','Teste inicial','',NULL,'Teste','Não iniciada','',NULL,NULL,NULL,NOW(),NOW(),'fixture')");
$updateGuia->execute();
$pdo->exec("CREATE TRIGGER rei152_legacy_update_pause BEFORE UPDATE ON guia FOR EACH ROW BEGIN IF OLD.parte_id IS NULL AND NEW.parte_id IS NOT NULL THEN DO SLEEP(3); END IF; END");
try {
    $legacyUpdateResults = $runRace('legacyupdate', 'CPF', $cpfLegacyUpdate, ['legacy_update', 'service'], true);
} finally {
    $pdo->exec('DROP TRIGGER rei152_legacy_update_pause');
}
if (count(array_filter($legacyUpdateResults, static fn(string $result): bool => str_starts_with($result, 'legacy-update:'))) !== 1
    || count(array_filter($legacyUpdateResults, static fn(string $result): bool => $result === 'duplicate')) !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM guia WHERE id_guia='REI152-LEGACY-UPDATE' AND parte_id IN (SELECT id FROM partes WHERE cpf='$cpfLegacyUpdate')")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpfLegacyUpdate'")->fetchColumn() !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpfLegacyUpdate'")->fetchColumn() !== 1) {
    throw new RuntimeException('Fluxo legado de Guias::atualizarGuia não foi serializado com escritor concorrente. Resultados: ' . implode(', ', $legacyUpdateResults));
}
echo "PASS REI-152: Guias::atualizarGuia mantém lock CPF até commit concorrente.\n";

$makeFreshCpf = static function () use ($pdo): string {
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $digits = (string) random_int(100000000, 999999999);
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += (int) $digits[$index] * (10 - $index);
        }
        $remainder = $sum % 11;
        $firstCheck = $remainder < 2 ? 0 : 11 - $remainder;
        $digits .= (string) $firstCheck;
        $sum = 0;
        for ($index = 0; $index < 10; $index++) {
            $sum += (int) $digits[$index] * (11 - $index);
        }
        $remainder = $sum % 11;
        $secondCheck = $remainder < 2 ? 0 : 11 - $remainder;
        $cpf = $digits . (string) $secondCheck;
        if ((int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpf'")->fetchColumn() === 0
            && (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpf'")->fetchColumn() === 0) {
            return $cpf;
        }
    }
    throw new RuntimeException('Não foi possível gerar CPF sintético livre para a fixture.');
};

$verifyPublicGuideRollbackFailure = static function (string $operation, string $rollbackFault) use ($dsn, $user, $password, $pdo, $database, $makeFreshCpf): void {
    $cpf = $makeFreshCpf();
    $trigger = $operation === 'create' ? 'rei152_guide_create_rollback_fail' : 'rei152_guide_update_rollback_fail';
    $guideId = 'REI152-ROLLBACK-' . strtoupper($operation);
    $lockName = 'rei250_' . substr(hash('sha256', $database . ':' . $cpf), 0, 52);
    $faultPdo = null;
    $service = null;
    $observer = null;
    $lockProbe = null;
    $releaseObserver = null;
    $releaseFault = null;
    $observerOwnsLock = false;
    $applicationOwnsLock = false;
    $applicationLockReleased = false;
    $operationError = null;
    $cleanupErrors = [];
    try {
        // Cover every resource acquisition and side effect with the cleanup below.
        $pdo->exec("CREATE TRIGGER `$trigger` BEFORE " . ($operation === 'create' ? 'INSERT' : 'UPDATE') . " ON guia FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='rei152 falha na guia'");
        $faultPdo = new Rei152RollbackFaultPdo($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        rei152AssertCiConnection($faultPdo);
        $observer = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        rei152AssertCiConnection($observer);
        $lockProbe = $observer->prepare('SELECT GET_LOCK(?, 0)');
        $releaseObserver = $observer->prepare('SELECT RELEASE_LOCK(?)');

        if ($operation === 'update') {
            $pdo->prepare("INSERT INTO guia (id_guia,tipo,processo,parte_id,etapa,status,regime,data_fato,dias_multa,observacoes,data_cadastro,data_atualizacao,user_cadastro) VALUES (?, 'Teste inicial', '', NULL, 'Teste', 'Não iniciada', '', NULL, NULL, NULL, NOW(), NOW(), 'fixture')")->execute([$guideId]);
        }

        $faultPdo->failNextRollback($rollbackFault);
        $guideData = [
            'tipo' => 'Teste REI-152 rollback',
            'processo' => '',
            'reu' => 'Pessoa com rollback inseguro ' . strtoupper($operation),
            'cpf' => $cpf,
            'etapa' => 'Teste',
            'status' => 'Não iniciada',
            'dias_multa' => '',
            'user_cadastro' => 'rei152-fixture',
        ];
        if ($operation === 'update') {
            $guideData['id_guia'] = $guideId;
        }
        $service = new Guias($faultPdo);
        $result = $operation === 'create'
            ? $service->cadastrarGuia($guideData)
            : $service->atualizarGuia($guideData);
        $connectionId = (int) $faultPdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $ownerCheck = $pdo->prepare('SELECT IS_USED_LOCK(?)');
        $ownerCheck->execute([$lockName]);
        $ownerId = $ownerCheck->fetchColumn();
        $applicationOwnsLock = $connectionId > 0 && $ownerId !== null && $ownerId !== false && (int) $ownerId === $connectionId;
        if (($result['ok'] ?? null) !== false
            || ($result['resultado'] ?? null) !== 'verificacao_necessaria'
            || ($result['estado_gravacao'] ?? null) !== 'nao_confirmada'
            || !is_string($result['id_referencia'] ?? null)
            || trim($result['id_referencia']) === ''
            || ($operation === 'update' && $result['id_referencia'] !== $guideId)
            || ($operation === 'create' && ($result['id'] ?? null) !== null)
            || !str_contains((string) ($result['message'] ?? ''), 'Consulte a guia antes de tentar novamente')) {
            throw new RuntimeException('Fluxo público não retornou resultado incerto e referência para consulta.');
        }

        if (!$applicationOwnsLock || !$faultPdo->inTransaction() || !ParteDocumentoLock::isConnectionUnsafe($faultPdo)) {
            throw new RuntimeException('Fluxo público não isolou a conexão ou não preservou a transação após falha de rollback.');
        }
        $lockProbe->execute([$lockName]);
        $lockResult = (int) $lockProbe->fetchColumn();
        if ($lockResult === 1) {
            $observerOwnsLock = true;
        }
        if ($lockResult !== 0) {
            throw new RuntimeException('Outra conexão adquiriu o lock CPF após falha de rollback pelo fluxo público.');
        }

        if (!$faultPdo->forceRollbackForFixture()) {
            throw new RuntimeException('Não foi possível limpar a transação da conexão insegura da fixture.');
        }
        $releaseFault = $faultPdo->prepare('SELECT RELEASE_LOCK(?)');
        $releaseFault->execute([$lockName]);
        if ((int) $releaseFault->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível liberar o lock CPF após rollback manual da fixture.');
        }
        $applicationLockReleased = true;
        $applicationOwnsLock = false;
        $lockProbe->execute([$lockName]);
        if ((int) $lockProbe->fetchColumn() !== 1) {
            throw new RuntimeException('Lock CPF não foi liberado após limpeza manual da conexão insegura.');
        }
        $observerOwnsLock = true;
        $releaseObserver->execute([$lockName]);
        if ((int) $releaseObserver->fetchColumn() !== 1) {
            throw new RuntimeException('Conexão observadora não liberou seu lock de verificação.');
        }
        $observerOwnsLock = false;

        if ((int) $pdo->query("SELECT COUNT(*) FROM partes WHERE cpf='$cpf'")->fetchColumn() !== 0
            || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF' AND numero='$cpf'")->fetchColumn() !== 0) {
            throw new RuntimeException('Falha de rollback deixou parte ou documento CPF persistido.');
        }
        if ($operation === 'update'
            && $pdo->query("SELECT parte_id FROM guia WHERE id_guia='$guideId'")->fetchColumn() !== null) {
            throw new RuntimeException('Falha de rollback alterou a guia pré-existente.');
        }
    } catch (Throwable $error) {
        $operationError = $error;
    } finally {
        if ($faultPdo instanceof PDO) {
            try {
                if ($faultPdo->inTransaction() && !$faultPdo->forceRollbackForFixture()) {
                    throw new RuntimeException('Rollback de cleanup da conexão de falha retornou false.');
                }
                if ($faultPdo->inTransaction()) {
                    throw new RuntimeException('Transação da conexão de falha permaneceu aberta durante cleanup.');
                }
            } catch (Throwable $cleanupError) {
                $cleanupErrors[] = 'rollback da conexão de falha: ' . get_class($cleanupError);
                error_log('REI-152 fixture: ' . end($cleanupErrors));
                // Closing the session is the fallback rollback and releases its named lock.
                $service = null;
                $releaseFault = null;
                $faultPdo = null;
            }

            if ($faultPdo instanceof PDO) {
                if ($applicationOwnsLock && !$applicationLockReleased) {
                    try {
                        $releaseFault = $faultPdo->prepare('SELECT RELEASE_LOCK(?)');
                        $releaseFault->execute([$lockName]);
                        $releaseResult = (int) $releaseFault->fetchColumn();
                        if ($releaseResult !== 1) {
                            throw new RuntimeException('Cleanup não liberou o lock CPF mantido após falha de rollback.');
                        }
                        $applicationOwnsLock = false;
                    } catch (Throwable $cleanupError) {
                        $cleanupErrors[] = 'liberação do lock CPF: ' . get_class($cleanupError);
                        error_log('REI-152 fixture: ' . end($cleanupErrors));
                        $service = null;
                        $releaseFault = null;
                        $faultPdo = null;
                    }
                }
            }
        }

        if ($observer instanceof PDO && $observerOwnsLock && $releaseObserver instanceof PDOStatement) {
            try {
                $releaseObserver->execute([$lockName]);
                if ((int) $releaseObserver->fetchColumn() !== 1) {
                    throw new RuntimeException('Cleanup não liberou o lock de verificação da conexão observadora.');
                }
            } catch (Throwable $cleanupError) {
                $cleanupErrors[] = 'liberação do lock observador: ' . get_class($cleanupError);
                error_log('REI-152 fixture: ' . end($cleanupErrors));
                $releaseObserver = null;
                $lockProbe = null;
                $observer = null;
                $observerOwnsLock = false;
            }
        }

        try {
            $pdo->exec("DROP TRIGGER IF EXISTS `$trigger`");
        } catch (Throwable $cleanupError) {
            $cleanupErrors[] = 'remoção do trigger: ' . get_class($cleanupError);
            error_log('REI-152 fixture: ' . end($cleanupErrors));
        }
        if ($operation === 'update') {
            try {
                $pdo->prepare('DELETE FROM guia WHERE id_guia = ?')->execute([$guideId]);
            } catch (Throwable $cleanupError) {
                $cleanupErrors[] = 'remoção da guia sintética: ' . get_class($cleanupError);
                error_log('REI-152 fixture: ' . end($cleanupErrors));
            }
        }

        $releaseFault = null;
        $releaseObserver = null;
        $lockProbe = null;
        $service = null;
        $faultPdo = null;
        $observer = null;
    }

    if ($operationError instanceof Throwable) {
        if ($cleanupErrors !== []) {
            error_log('REI-152 fixture: cleanup adicional falhou: ' . implode('; ', $cleanupErrors));
        }
        throw $operationError;
    }
    if ($cleanupErrors !== []) {
        throw new RuntimeException('Cleanup da fixture REI-152 falhou: ' . implode('; ', $cleanupErrors));
    }
};

foreach (['false', 'throw'] as $rollbackFault) {
    $verifyPublicGuideRollbackFailure('create', $rollbackFault);
    echo "PASS REI-152: Guias::cadastrarGuia conserva lock após rollBack() {$rollbackFault}.\n";
}
foreach (['false', 'throw'] as $rollbackFault) {
    $verifyPublicGuideRollbackFailure('update', $rollbackFault);
    echo "PASS REI-152: Guias::atualizarGuia conserva lock após rollBack() {$rollbackFault}.\n";
}

$verifyCommitRelease = static function (string $operation, string $fault) use ($dsn, $user, $password, $pdo, $database, $makeFreshCpf): void {
    $cpf = $makeFreshCpf();
    $guideId = 'REI152-' . strtoupper($operation) . '-' . strtoupper($fault);
    $lockName = 'rei250_' . substr(hash('sha256', $database . ':' . $cpf), 0, 52);
    $faultPdo = new Rei152CommitReleaseFaultPdo($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    rei152AssertCiConnection($faultPdo);
    $faultConnectionId = (int) $faultPdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $ownerQuery = $pdo->prepare('SELECT IS_USED_LOCK(?)');
    try {
        if ($operation === 'update') {
            $pdo->prepare("INSERT INTO guia (id_guia,tipo,processo,parte_id,etapa,status,regime,data_fato,dias_multa,observacoes,data_cadastro,data_atualizacao,user_cadastro) VALUES (?, 'Teste inicial', '', NULL, 'Teste', 'Não iniciada', '', NULL, NULL, NULL, NOW(), NOW(), 'fixture')")->execute([$guideId]);
        }
        $faultPdo->failRelease = $fault === 'release';
        $faultPdo->throwAfterCommit = $fault === 'commit';
        $guideData = [
            'tipo' => 'Teste sintético REI-152', 'processo' => '',
            'reu' => 'Pessoa sintética commit ' . $operation . ' ' . $fault,
            'cpf' => $cpf, 'etapa' => 'Teste', 'status' => 'Não iniciada',
            'dias_multa' => '', 'user_cadastro' => 'rei152-fixture',
        ];
        if ($operation === 'update') $guideData['id_guia'] = $guideId;
        $service = new Guias($faultPdo);
        $result = $operation === 'create' ? $service->cadastrarGuia($guideData) : $service->atualizarGuia($guideData);
        $reference = $result['id_referencia'] ?? null;
        $expectedState = $fault === 'release' ? 'confirmada' : 'nao_confirmada';
        if (!$faultPdo->commitSucceeded || $faultPdo->inTransaction() || !ParteDocumentoLock::isConnectionUnsafe($faultPdo)
            || ($result['ok'] ?? null) !== false || ($result['resultado'] ?? null) !== 'verificacao_necessaria'
            || ($result['estado_gravacao'] ?? null) !== $expectedState || !is_string($reference) || $reference === ''
            || ($operation === 'create' && ($result['id'] ?? null) !== null)
            || ($operation === 'update' && ($reference !== $guideId || ($result['id'] ?? null) !== $guideId))
            || !str_contains((string) ($result['message'] ?? ''), 'Consulte a guia antes de tentar novamente')) {
            throw new RuntimeException('Contrato público de commit/liberação incertos divergiu.');
        }
        if (($fault === 'release' && !$faultPdo->releaseFaultInjected) || ($fault === 'commit' && $faultPdo->throwAfterCommit)) {
            throw new RuntimeException('Falha de fixture esperada não foi injetada.');
        }
        $ownerQuery->execute([$lockName]);
        $owner = $ownerQuery->fetchColumn();
        if (($fault === 'release' && (int) $owner !== $faultConnectionId)
            || ($fault === 'commit' && $owner !== null && $owner !== false)) {
            throw new RuntimeException('Posse do lock após commit/liberação divergiu.');
        }
        $persisted = $pdo->prepare('SELECT COUNT(*) FROM guia WHERE id_guia=? AND parte_id IN (SELECT id FROM partes WHERE cpf=?)');
        $persisted->execute([$reference, $cpf]);
        if ((int) $persisted->fetchColumn() !== 1) {
            throw new RuntimeException('Observador não encontrou a guia confirmada pelo MySQL sintético.');
        }
        // No cenário de commit, o servidor confirmou, mas a aplicação não recebeu a confirmação.
        echo "PASS REI-152: {$operation} preserva referência após falha sintética de {$fault}.\n";
    } finally {
        $faultPdo->failRelease = false;
        try {
            if ($faultPdo->inTransaction() && !$faultPdo->rollBack()) {
                throw new RuntimeException('Rollback da fixture retornou false.');
            }
            if ($faultPdo->inTransaction()) {
                throw new RuntimeException('Transação da fixture permanece ativa após rollback.');
            }
        } catch (Throwable $cleanupError) {
            $service = null;
            $faultPdo = null;
            throw new RuntimeException('Cleanup inseguro: conexão fechada sem liberar lock manualmente.', 0, $cleanupError);
        }
        $ownerQuery->execute([$lockName]);
        $owner = $ownerQuery->fetchColumn();
        if ($faultConnectionId > 0 && $owner !== null && $owner !== false && (int) $owner === $faultConnectionId) {
            $release = $faultPdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
            if ((int) $release->fetchColumn() !== 1) throw new RuntimeException('Falha ao liberar lock sintético após teste.');
        }
        $service = null;
        $faultPdo = null;
    }
};
foreach (['create', 'update'] as $operation) {
    foreach (['release', 'commit'] as $fault) $verifyCommitRelease($operation, $fault);
}

$rg = 'REI152-RG-001';
$results3 = $runRace('rgdocs', 'RG', $rg, ['service', 'service']);
if (count(array_filter($results3, static fn(string $result): bool => str_starts_with($result, 'created:'))) !== 1
    || count(array_filter($results3, static fn(string $result): bool => $result === 'duplicate')) !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='RG' AND numero='$rg'")->fetchColumn() !== 1) {
    throw new RuntimeException('Lock de documento não CPF não serializou os escritores concorrentes.');
}
$caseRaceNumbers = ['AbC-RACE-42', 'abc-race-42'];
$caseRace = $runRace('casefold', 'RG', $caseRaceNumbers[0], ['service', 'service'], false, $caseRaceNumbers);
if (count(array_filter($caseRace, static fn(string $result): bool => str_starts_with($result, 'created:'))) !== 1
    || count(array_filter($caseRace, static fn(string $result): bool => $result === 'duplicate')) !== 1
    || (int) $pdo->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='RG' AND BINARY UPPER(numero)=BINARY UPPER('AbC-RACE-42')")->fetchColumn() !== 1) {
    throw new RuntimeException('Lock e comparação SQL não serializaram variantes de caixa concorrentes.');
}
echo "PASS REI-152: variantes de caixa concorrentes compartilham a mesma identidade\n";

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

$unsafePdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
rei152AssertCiConnection($unsafePdo);
$unsafeDatabase = (string) $unsafePdo->query('SELECT DATABASE()')->fetchColumn();
$unsafeCpf = '98765432100';
$unsafeLockName = 'rei250_' . substr(hash('sha256', $unsafeDatabase . ':' . $unsafeCpf), 0, 52);
$activeTransactionRejected = false;
try {
    ParteDocumentoLock::run($unsafePdo, 'CPF', $unsafeCpf, static function () use ($unsafePdo): void {
        if (!$unsafePdo->beginTransaction()) {
            throw new RuntimeException('A fixture não conseguiu abrir a transação sintética.');
        }
    });
} catch (DocumentoResultadoIncerto $error) {
    $activeTransactionRejected = $error->estadoGravacao === 'nao_confirmada';
}
if (!$activeTransactionRejected || !$unsafePdo->inTransaction() || !ParteDocumentoLock::isConnectionUnsafe($unsafePdo)) {
    throw new RuntimeException('Lock documental foi liberado ou a conexão não foi isolada com transação ativa.');
}
$observer = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
rei152AssertCiConnection($observer);
$lockProbe = $observer->prepare('SELECT GET_LOCK(?, 0)');
$lockProbe->execute([$unsafeLockName]);
if ((int) $lockProbe->fetchColumn() !== 0) {
    throw new RuntimeException('Outra conexão adquiriu o lock enquanto a transação insegura permanecia ativa.');
}
$unsafePdo->rollBack();
$releaseUnsafe = $unsafePdo->prepare('SELECT RELEASE_LOCK(?)');
$releaseUnsafe->execute([$unsafeLockName]);
if ((int) $releaseUnsafe->fetchColumn() !== 1) {
    throw new RuntimeException('Fixture não conseguiu liberar o lock retido após rollback manual.');
}
$lockProbe->execute([$unsafeLockName]);
if ((int) $lockProbe->fetchColumn() !== 1) {
    throw new RuntimeException('Lock documental não ficou disponível após fechar a transação insegura.');
}
$releaseObserver = $observer->prepare('SELECT RELEASE_LOCK(?)');
$releaseObserver->execute([$unsafeLockName]);
if ((int) $releaseObserver->fetchColumn() !== 1) {
    throw new RuntimeException('Observer não conseguiu liberar o lock de verificação da transação insegura.');
}

foreach (glob($barrier . '/*') ?: [] as $path) @unlink($path);
@rmdir($barrier);
echo "PASS REI-152 MySQL/InnoDB: rollback, retry, mutação direta recusada, conflitos CPF/RG, transação externa, CPF legado e lock retido durante transação insegura\n";
